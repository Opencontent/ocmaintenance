<?php
//
// Created on: <16-Сен-2003 16:09:52 sp>
//
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.0.1
// BUILD VERSION: 22260
// COPYRIGHT NOTICE: Copyright (C) 1999-2008 eZ Systems AS
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
// check the login user
$logged_user= eZUser::currentUser();
$cli->output( $cli->stylize( 'red', "Si sta eseguendo l'agente con l'utente ".$logged_user->Login."\n" ), false );



//include_once( "lib/ezutils/classes/ezini.php" );
$ini = eZINI::instance( 'content.ini' );
$Classes = $ini->variable( 'ChangeSection_da_published','ClassList' );

$rootNodeIDList = $ini->variable( 'ChangeSection_da_published','RootNodeList' );

$DataTime =  $ini->variable( 'ChangeSection_da_published','DataTime' );
$SectionIDs =  $ini->variable( 'ChangeSection_da_published','ToSection' );

$PublishedSinceHours = $ini->variable( 'ChangeSection_da_published','PublishedSinceHours' );
$today = time();
$currrentDate = time();

foreach( $rootNodeIDList as $class => $nodeID )
{
    $rootNode = eZContentObjectTreeNode::fetch( $nodeID );

    $articleNodeArray = $rootNode->subTree( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array($class),
						'AttributeFilter' => array(array('section','!=',$SectionIDs[$class])) ) );

    foreach ( $articleNodeArray as $articleNode )
    {
        $article = $articleNode->attribute( 'object' );
        $dataMap = $article->attribute( 'data_map' );
	
	$unpublish_date = $DataTime[$article->ClassIdentifier];
        $dateAttribute = $dataMap[$unpublish_date];
        if ( is_null( $dateAttribute ) )
            continue;
        $date = $dateAttribute->content();
        $articleRetractDate = $date->attribute( 'timestamp' );
	$cli->output( $cli->stylize( 'cyan', "\nLeggo oggetto ".$articleNode->attribute( 'id' ) . ": " . 
						$articleNode->attribute('name'). "\n" ), false );
	$cli->output( $cli->stylize( 'red', ">> DATA ARCHIVIAZIONE ".$articleRetractDate. "\n" ), false );
        if ( $articleRetractDate > 0 && $articleRetractDate < $currrentDate )
        { 
            // Clean up content cache
            //include_once( 'kernel/classes/ezcontentcachemanager.php' );
            eZContentCacheManager::clearContentCacheIfNeeded( $article->attribute( 'id' ) );

            //$article->removeThis( $articleNode->attribute( 'node_id' ) );
	    //cambia sezione
        
        if ( eZOperationHandler::operationIsAvailable( 'content_updatesection' ) )
        {
            $operationResult = eZOperationHandler::execute( 'content',
                                                            'updatesection',
                                                            array( 'node_id'             => $articleNode->NodeID,
                                                                   'selected_section_id' => $SectionIDs[$class] ),
                                                            null,
                                                            true );
        
        }
        else
        {
            eZContentOperationCollection::updateSection( $articleNode->NodeID, $SectionIDs[$class] );
        }
        
	    $cli->output( $cli->stylize( 'cyan', "...GESTIONE ORDINARIA - Modifico sezione oggetto ".$articleNode->attribute( 'id' ) . ": " . 
						$articleNode->attribute('name'). "in " . $SectionIDs[$class] . "\n" ), false );
        }
	
/*	
	if ( 'published','<=',$currrentDate-$PublishedSinceHours )
	{ 
            // Clean up content cache
            //include_once( 'kernel/classes/ezcontentcachemanager.php' );
            eZContentCacheManager::clearContentCacheIfNeeded( $article->attribute( 'id' ) );

            //$article->removeThis( $articleNode->attribute( 'node_id' ) );
	    //cambia sezione
        if ( eZOperationHandler::operationIsAvailable( 'content_updatesection' ) )
        {
            $operationResult = eZOperationHandler::execute( 'content',
                                                            'updatesection',
                                                            array( 'node_id'             => $articleNode->NodeID,
                                                                   'selected_section_id' => $SectionIDs[$class] ),
                                                            null,
                                                            true );
        
        }
        else
        {
            eZContentOperationCollection::updateSection( $articleNode->NodeID, $SectionIDs[$class] );
        }
	    $cli->output( $cli->stylize( 'green', "...GESTIONE EXTRA - Modifico sezione oggetto ".$articleNode->attribute( 'id' ) . ": " . $articleNode->attribute('name'). "in " . $SectionIDs[$class] . "\n" ), false );
    }
*/
  	
    }
}


?>
