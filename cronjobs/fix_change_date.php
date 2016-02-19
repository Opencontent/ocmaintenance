<?php

require_once( 'kernel/common/ezincludefunctions.php' );
eZExtension::activateExtensions();

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
$ini = eZINI::instance( 'openpa.ini' );
$Classes = $ini->variable( 'ChangeDate','ClassList' );

$rootNodeIDList = $ini->variable( 'ChangeDate','RootNodeList' );
$today = time();


foreach( $Classes as $class )
{
    
    $NodeArray = eZContentObjectTreeNode::subTreeByNodeID( array(
                                                                 'ClassFilterType' => 'include',
                                                                 'ClassFilterArray' => array($class)
                                                                 ),
                                                          $rootNodeIDList[$class] );
    $count = 0;
    
    $cli->notice( $class . ' ', false );

    foreach ( $NodeArray as $Node )
    {
        $contentObject = eZContentObject::fetch( (int) $Node->ContentObjectID );
        $current = $contentObject->attribute('current');
        
        if ( $current->attribute( 'status' ) == eZContentObjectVersion::STATUS_ARCHIVED )
        {
            //$cli->notice( $Node->ContentObjectID . ' ' . $current->attribute( 'version' ) . ' ' . $current->attribute( 'status' ) );
            $count++;
            
            eZContentOperationCollection::setVersionStatus( $contentObject->attribute( 'id' ), $contentObject->attribute( 'current_version' ), eZContentObjectVersion::STATUS_PUBLISHED );
            $cli->notice( '.', false );
/*
            $contentObjectVersion = $contentObject->createNewVersion();
            $versionNumber  = $contentObjectVersion->attribute( 'version' );
            $creator_id = $current->attribute( 'creator_id' );
            if ( $creator_id )
            {
                $contentObjectVersion->setAttribute( 'creator_id', $creator_id );
                $contentObjectVersion->store();
            }
            
            eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObject->attribute( 'id' ), 'version' => $versionNumber ) );
*/

        }
    }
    
    $cli->notice( $count );
}





?>


