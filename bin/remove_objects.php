#!/usr/bin/env php
<?php
//
// Created on: <30-Apr-2010 15:47:14 gabriele@opencontent.it>
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/products/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//


// configurazioni
// NOTA per l'amministratore:
// impostare in questo array PARENT_NODE_ID => CLASS_IDENTIFIER

print "SCRIPT DISABILITATO" ; exit();

$containers_to_clean = array(
				317652 => 'file',
				317653 => 'file',
				317654 => 'file',
				210707 => 'mozione',
				210705 => 'interrogazione',
				210706 => 'interpellanza'
			);
$log_class_identifier = 'comment';
$log_parent_node_id = 316617;
$log_class_attribute_message = 'message';
$log_class_attribute_subject = 'subject';
// inizio dello script

proc_nice(-100);

require 'autoload.php';

function updateClass( $classId )
{
    global $cli, $script, $db, $scheduledScript;

    // If the class is not stored yet, store it now
    $class = eZContentClass::fetch( $classId, true, eZContentClass::VERSION_STATUS_TEMPORARY );
    if ( $class )
    {
        $cli->output( "Storing class" );
        $class->storeDefined( $class->fetchAttributes() );
    }

    // Fetch the stored class
    $class = eZContentClass::fetch( $classId );
    if ( !$class )
    {
        $cli->error( 'Could not fetch class with ID: ' . $classId );
        return;
    }
    $classAttributes = $class->fetchAttributes();
    $classAttributeIDs = array();
    foreach ( $classAttributes as $classAttribute )
    {
        $classAttributeIDs[] = $classAttribute->attribute( 'id' );
    }

    $objectCount = eZContentObject::fetchSameClassListCount( $classId );
    $cli->output( 'Numero di oggetti per questa classe: ' . $objectCount );

    $counter = 0;
    $offset = 0;
    $limit = 100;
    $objects = eZContentObject::fetchSameClassList( $classId, true, $offset, $limit );

    // Add and/or remove attributes for all versions and translations of all objects of this class
    $test_all_objects=false;

    if ($test_all_objects)
    while ( count( $objects ) > 0 )
    {
        // Run a transaction per $limit objects
        $db->begin();

        foreach ( $objects as $object )
        {
            $contentObjectID = $object->attribute( 'id' );
            $objectVersions = $object->versions();
            foreach ( $objectVersions as $objectVersion )
            {
                $versionID = $objectVersion->attribute( 'version' );
                $translations = $objectVersion->translations();
                foreach ( $translations as $translation )
                {
                    $translationName = $translation->attribute( 'language_code' );

                    // Class attribute IDs of object attributes (not necessarily the same as those in the class, hence the manual sql)
                    $objectClassAttributeIDs = array();
                    $rows = $db->arrayQuery( "SELECT id,contentclassattribute_id, data_type_string
                                              FROM ezcontentobject_attribute
                                              WHERE contentobject_id = '$contentObjectID' AND
                                                    version = '$versionID' AND
                                                    language_code='$translationName'" );
                    foreach ( $rows as $row )
                    {
                        $objectClassAttributeIDs[ $row['id'] ] = $row['contentclassattribute_id'];
                    }

                    // Quick array diffs
                    $attributesToRemove = array_diff( $objectClassAttributeIDs, $classAttributeIDs ); // Present in the object, not in the class
                    $attributesToAdd = array_diff( $classAttributeIDs, $objectClassAttributeIDs ); // Present in the class, not in the object

                    // Remove old attributes
                    foreach ( $attributesToRemove as $objectAttributeID => $classAttributeID )
                    {
                        $objectAttribute = eZContentObjectAttribute::fetch( $objectAttributeID, $versionID );
                        if ( !is_object( $objectAttribute ) )
                            continue;
                        $objectAttribute->remove( $objectAttributeID );
                    }

                    // Add new attributes
                    foreach ( $attributesToAdd as $classAttributeID )
                    {
                        $objectAttribute = eZContentObjectAttribute::create( $classAttributeID, $contentObjectID, $versionID, $translationName );
                        if ( !is_object( $objectAttribute ) )
                            continue;
                        $objectAttribute->setAttribute( 'language_code', $translationName );
                        $objectAttribute->initialize();
                        $objectAttribute->store();
                        $objectAttribute->postInitialize();
                    }
                }
            }

            // Progress bar and Script Monitor progress
            $cli->output( '.', false );
            $counter++;
            if ( $counter % 70 == 0 or $counter >= $objectCount )
            {
                $progressPercentage = ( $counter / $objectCount ) * 100;
                $cli->output( sprintf( ' %01.1f %%', $progressPercentage ) );

                if ( $scheduledScript )
                {
                    $scheduledScript->updateProgress( $progressPercentage );
                }
            }
        }

        $db->commit();

        $offset += $limit;
        $objects = eZContentObject::fetchSameClassList( $classId, true, $offset, $limit );
    }

    // Set the object name to the first attribute, if not set
    $classAttributes = $class->fetchAttributes();

    // Fetch the first attribute
    if ($test_all_objects)
    if ( count( $classAttributes ) > 0 && trim( $class->attribute( 'contentobject_name' ) ) == '' )
    {
        $db->begin();
        $identifier = $classAttributes[0]->attribute( 'identifier' );
        $identifier = '<' . $identifier . '>';
        $class->setAttribute( 'contentobject_name', $identifier );
        $class->store();
        $db->commit();
    }
}


// Init script

$cli = eZCLI::instance();
$endl = $cli->endlineString();

$script = eZScript::instance( array( 'description' => ( "Add missing object attributes\n\n" .
                                                        "Will add missing content object attributes, and remove redundant ones, for a given class.\n" .
                                                        "If the class is not given, it will check all classes.\n" .
                                                        "\n" .
                                                        'addmissingobjectattributes.php -s admin --classid=42' ),
                                      'use-session' => false,
                                      'use-modules' => true,
                                      'use-extensions' => true ) );
$script->startup();

$options = $script->getOptions( '[db-host:][db-user:][db-password:][db-database:][db-driver:][sql][classid:][admin-user:][scriptid:]',
                                '[name]',
                                array( 'db-host' => 'Database host',
                                       'db-user' => 'Database user',
                                       'db-password' => 'Database password',
                                       'db-database' => 'Database name',
                                       'db-driver' => 'Database driver',
                                       'sql' => 'Display sql queries',
                                       'classid' => 'ID of class to update',
                                       'admin-user' => 'Alternative login for the user to perform operation as',
                                       'scriptid' => 'Used by the Script Monitor extension, do not use manually' ) );
$script->initialize();

$dbUser = $options['db-user'] ? $options['db-user'] : false;
$dbPassword = $options['db-password'] ? $options['db-password'] : false;
$dbHost = $options['db-host'] ? $options['db-host'] : false;
$dbName = $options['db-database'] ? $options['db-database'] : false;
$dbImpl = $options['db-driver'] ? $options['db-driver'] : false;
$showSQL = $options['sql'] ? true : false;
$siteAccess = $options['siteaccess'] ? $options['siteaccess'] : false;

if ( $siteAccess )
{
    changeSiteAccessSetting( $siteAccess );
}

function changeSiteAccessSetting( $siteAccess )
{
    global $isQuiet;
    $cli = eZCLI::instance();
    if ( file_exists( 'settings/siteaccess/' . $siteAccess ) )
    {
        if ( !$isQuiet )
            $cli->notice( "Usa il siteaccess $siteAccess" );
    }
    else
    {
        if ( !$isQuiet )
            $cli->notice( "Siteaccess $siteAccess non esiste, usa default siteaccess" );
    }
}

$db = eZDB::instance();

if ( $dbHost or $dbName or $dbUser or $dbImpl )
{
    $params = array();
    if ( $dbHost !== false )
        $params['server'] = $dbHost;
    if ( $dbUser !== false )
    {
        $params['user'] = $dbUser;
        $params['password'] = '';
    }
    if ( $dbPassword !== false )
        $params['password'] = $dbPassword;
    if ( $dbName !== false )
        $params['database'] = $dbName;
    $db = eZDB::instance( $dbImpl, $params, true );
    eZDB::setInstance( $db );
}

$db->setIsSQLOutputEnabled( $showSQL );


// Log in admin user
if ( isset( $options['admin-user'] ) )
{
    $adminUser = $options['admin-user'];
}
else
{
    $adminUser = 'admin';
}
$user = eZUser::fetchByName( $adminUser );
if ( $user )
    eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'id' ) );
else
{
    $cli->error( 'Could not fetch admin user object' );
    $script->shutdown( 1 );
    return;
}

foreach ($containers_to_clean as $key => $container_to_clean) {
	
	$log_start = "Rimuovo ".$container_to_clean." dal contenitore ".$key."\n";
	$tree_count = eZFunctionHandler::execute('content','tree_count', array('parent_node_id' => $key));
	$times = round($tree_count/1000+0.5);

	$log_start .= "\nTotale oggetti da cancellare: ".$tree_count."\n"."Totale operazioni previste per questo contenitore: ".$times."\n";

	for ($i = 1; $i <= $times; $i++) {
		$log = $log_start;
		$tree_count_rel = eZFunctionHandler::execute('content','tree_count', array('parent_node_id' => $key));
		$log .= "\nOggetti rimanenti: ".$tree_count_rel."\n"."Cancellazione n°".$i." per questo contenitore.\n\n\nRisultato:\n";
		if ($container_to_clean=='*')
			$log_exec = system('php runcronjobs.php batchtool --filter="fetchnodelist;parent='.$key.';limit=1000" --operation="nodedelete"');
		else
			$log_exec = system('php runcronjobs.php batchtool --filter="fetchnodelist;parent='.$key.';classname='.$container_to_clean.';limit=1000" --operation="nodedelete"');

		$log .= $log_exec."\n\n";
		$params = array();
		$params['parent_node_id'] = $log_parent_node_id; // node id of /Media/Files
		$params['class_identifier'] = $log_class_identifier;
		$params['creator_id'] = 14; // admin
		$params['storage_dir'] = '/tmp/data/'; // don't forget the ended /
		$params['section_id'] = $section_id; // section media
		$attributesData = array();
		$attributesData[$log_class_attribute_subject] = 'Cancella '.$container_to_clean.' da '.$key.': '.round(($i/$times)*100).'%';
		$attributesData[$log_class_attribute_message] = $log."\n\n\n\n\n".$log_filename;
		$params['attributes'] = $attributesData;
		$contentObject = eZContentFunctions::createAndPublishObject( $params );
	}
}

$script->shutdown();

?>
