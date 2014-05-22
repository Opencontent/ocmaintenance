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

//include_once( "lib/ezutils/classes/ezini.php" );
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


$ini = eZINI::instance( 'content.ini' );
$unpublishClasses = $ini->variable( 'UnpublishSettings','ClassList' );

$rootNodeIDList = $ini->variable( 'UnpublishSettings','RootNodeList' );
$UnpublishDateAttributes =  $ini->variable( 'UnpublishSettings','Unpublish_field' );

$currrentDate = time();



if (!is_array($rootNodeIDList)) 
	$cli->output( $cli->stylize( 'cyan', "Mancano i nodi di riferimento: controlla content.ini\n" ), false );
else
foreach( $rootNodeIDList as $nodeID )
{
	$rootNode = eZContentObjectTreeNode::fetch( $nodeID );
	if (!is_object($rootNode))
	{
		$cli->output( $cli->stylize( 'red', "ATTENZIONE: il nodo ".$nodeID." non esiste; controlla content.ini!\n Processo gli altri nodi\n" ), false );
	} else {
	    $ContentNodeArray = $rootNode->subTree( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => $unpublishClasses ) );

	    foreach ( $ContentNodeArray as $ContentNode )
	    {
	        $Content = $ContentNode->attribute( 'object' );
		//$cli->output( $cli->stylize( 'cyan', "Esamino ".$Content->Name."\n" ), false );

        	$dataMap = $Content->attribute( 'data_map' );
	
		$unpublish_date = $UnpublishDateAttributes[$Content->ClassIdentifier];

	        $dateAttribute = $dataMap[$unpublish_date];

	        if ( is_null( $dateAttribute ) )
        	    continue;

	        $date = $dateAttribute->content();
        	$ContentRetractDate = $date->attribute( 'timestamp' );
	        if ( $ContentRetractDate > 0 && $ContentRetractDate < $currrentDate )
	        {
		    $cli->output( $cli->stylize( 'cyan', "Trovato ".$Content->Name."\n" ), false );
        	    // Clean up content cache
	            //include_once( 'kernel/classes/ezcontentcachemanager.php' );
        	    eZContentCacheManager::clearContentCacheIfNeeded( $Content->attribute( 'id' ) );


	            $Content->removeThis( $ContentNode->attribute( 'node_id' ) );
		    $cli->output( $cli->stylize( 'green', "   >> Collocato nel cestino ".$Content->Name."\n" ), false );
		    $Content->purge();
		    $cli->output( $cli->stylize( 'green', "   >> Rimosso definitivamente ".$Content->Name."\n" ), false );
	        }
	    } //forech
	} // is_object
}


?>
