<?php
//
// Created on: <16-Сен-2003 16:09:52 sp>
//
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.3
// BUILD VERSION: 1
// AUTHOR: Gabriele Francescotto gabriele@opencontent.it
// COPYRIGHT NOTICE: Copyright (C) 2004-2010 OpenContent
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

/*! \file change_section_utente_servizio.php

Questo script permette di cambiare la sezione di un telefono
in base al servizio di appartenenza

Le impostazioni vanno cambiate nel file setting

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
$ini = eZINI::instance( 'openpa.ini' );
$Classes = $ini->variable( 'ChangeSection','ClassList' );

$rootNodeIDList = $ini->variable( 'ChangeSection','RootNodeList' );

$DataTime =  $ini->variable( 'ChangeSection','DataTime' );
$SectionIDs =  $ini->variable( 'ChangeSection','ToSection' );
$ScadenzaSecondi = $ini->variable( 'ChangeSection','ScadeDopoTotSecondi' );
$currrentDate = time();

$class="telefono";
$SectionID = 29; //intranet
$ClassAttribute = "tipo_telefono";
$nodeID = 306761;
$contentobject_id = 306804;
$content_attribute_id = 2082;
$object = eZContentObject::fetchByNodeID($nodeID);

$objects = $object->reverseRelatedObjectList( false, $content_attribute_id, false,
                                                              array( 'AllRelations' => eZContentObject::RELATION_ATTRIBUTE ) );

    foreach ( $objects as $related_object ) {
	$related_nodes = eZContentObjectTreeNode::fetchByContentObjectID( $related_object->attribute('id') );
	$related_node = $related_nodes[0];
	$related_user = eZContentObject::fetchByNodeID($related_node->attribute('node_id'));

	if ($related_user->attribute('section_id') != $SectionID ) {
		//eZContentObjectTreeNode::assignSectionToSubTree( $related_node->attribute('node_id'), $SectionID );
        if ( eZOperationHandler::operationIsAvailable( 'content_updatesection' ) )
        {
            $operationResult = eZOperationHandler::execute( 'content',
                                                            'updatesection',
                                                            array( 'node_id'             => $related_node->attribute('node_id'),
                                                                   'selected_section_id' => $SectionID ),
                                                            null,
                                                            true );
        
        }
        else
        {
            eZContentOperationCollection::updateSection( $related_node->attribute('node_id'), $SectionID );
        }
		$cli->output( $cli->stylize( 'cyan', "Cambiata la sezione di ". $related_node->attribute('name')));
	}
    }
	

?>
