<?php
//
//
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.3
// COPYRIGHT NOTICE: Copyright (C) 2010 Opencontent
// AUTHOR: gabriele@opencontent.it
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//

/*! \file unpublish.php
*/

//include_once( "kernel/classes/ezcontentobjecttreenode.php" );



// Check for extension
//include_once( 'lib/ezutils/classes/ezextension.php' );
require_once( 'kernel/common/ezincludefunctions.php' );
eZExtension::activateExtensions();
// Extension check end

$cli = eZCLI::instance();
$cli->setUseStyles( true );
$cli->output( $cli->stylize( 'cyan', 'Leggo classi e attributi con le date di riferimento... ' ), false );
$user_ini = eZINI::instance( 'ocmaintenance.ini' );
$CronjobUser = $user_ini->variable( 'UserSettings', 'CronjobUser' );
$CronjobUserP = $user_ini->variable( 'UserSettings', 'CronjobUserP' );
$cli->output( $cli->stylize( 'cyan', "Lette\n" ), false );

// autentication as editor or administrator
$user = eZUser::loginUser( $CronjobUser, $CronjobUserP);
$cli->output( $cli->stylize( 'red', "Si sta eseguendo l'agente con l'utente ".$user->attribute('login')."\n" ), false );

// lascia utente originale
$original_author = true;

$db = eZDB::instance();


//include_once( "lib/ezutils/classes/ezini.php" );
$ini = eZINI::instance( 'content.ini' );
$Classes = $ini->variable( 'ChangeDate','ClassList' );

$rootNodeIDList = $ini->variable( 'ChangeDate','RootNodeList' );

$PublishedDataTime_array =  $ini->variable( 'ChangeDate','PublishedDataTime' );
$PublishedSinceHours_array =  $ini->variable( 'ChangeDate','PublishedSinceHours' );
$ModifiedDataTimePubblicazione =  $ini->variable( 'ChangeDate','ModifiedDataTime' );
$today = time();


foreach( $Classes as $class )
{
    $rootNode = eZContentObjectTreeNode::fetch( $rootNodeIDList[$class] );
    $PublishedSinceHours = ($PublishedSinceHours_array[$class])*3600;
    $NodeArray = $rootNode->subTree( 
				      array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array($class),
					'AttributeFilter' => array('and',array('published','<=',$today), array('published','>',$today-$PublishedSinceHours))
				 ) );
    $PublishedDataTime = $PublishedDataTime_array[$class];
    foreach ( $NodeArray as $Node )
    {
	$contentObject = eZContentObject::fetch( (int) $Node->ContentObjectID );
	//$contentObjectVersion = $contentObject->createNewVersion();
	
    // @LUCA Modifica del 9 febbraio 2011
    //$creator_id = $contentObject->attribute( 'creator_id' );
    $current = $contentObject->attribute('current');
    if ( $current instanceof eZContentObjectVersion )
    {
        $creator_id = $current->attribute( 'creator_id' );
    }
    
        $dataMap = $contentObject->attribute( 'data_map' );
	if ( $contentObject->attribute( 'can_edit' ) ) {
		//	$contentObjectVersion = $contentObject->createNewVersion();
			$db->begin();
		//	$versionNumber  = $contentObjectVersion->attribute( 'version' );
			if ( array_key_exists( $PublishedDataTime, $dataMap ) )
			{
				$attribute = $dataMap[$PublishedDataTime];
				$classAttributeID = $attribute->attribute( 'contentclassattribute_id' );
				$dataType = $attribute->attribute( 'data_type_string' );
				switch ( $dataType ) {
					case 'ezdate':
					case 'eztime':
                    				{
							$contentObject->setAttribute( 'published', (int) $attribute->attribute( 'data_int' ) );
						} break;
				}
				$attribute->store();
			}
		//	$contentObjectVersion->setAttribute( 'owner_id', $author );
		//	$contentObjectVersion->store();
            // @LUCA Modifica del 9 febbraio 2011
            if ( $creator_id )
            {
                $contentObject->setAttribute( 'owner_id', $creator_id );
            }
		// TODO: controllare che funzioni la rimozione lato backend
		//	$contentObject->setAttribute( 'creator_id', $creator_id );
	            	$contentObject->store();
        	    	$db->commit();
            	//	$dataMap = $contentObjectVersion->dataMap();
	}
	//eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObject->attribute( 'id' ), 'version'   => $versionNumber ) );
	eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObject->attribute( 'id' )) );
	$cli->output( $cli->stylize( 'cyan', "Pubblicato l'oggetto ".$contentObject->attribute( 'id' ) . ": " . 
						$contentObject->attribute('name')."\n" ), false );
	//  eZContentCacheManager::clearContentCacheIfNeeded( $contentObject->attribute( 'id' ) );
	//  unset($contentObjectVersion);
   }
}


?>
