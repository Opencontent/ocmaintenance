#!/usr/bin/env php
<?php
//
// Created on: <19-Jul-2004 10:51:17 amos>
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

//include_once( 'lib/ezutils/classes/ezcli.php' );
//include_once( 'kernel/classes/ezscript.php' );

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish Content Cache Handler\n" .
                                                        "Allows for easy clearing of Content Caches\n" .
                                                        "\n" .
                                                        "Clearing node for content and users tree\n" .
                                                        "./bin/ezcontentcache.php --clear-node=/,5\n" .
                                                        "Clearing subtree for content tree\n" .
                                                        "./bin/ezcontentcache.php --clear-subtree=/" ),
                                     'use-session' => false,
                                     'use-modules' => false,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[clear-node:][clear-subtree:]",
                                "",
                                array( 'clear-node' => ( "Clears all content caches related to a given node,\n" .
                                                         "pass either node ID or nice url of node.\n" .
                                                         "Separate multiple nodes with a comma." ),
                                       'clear-subtree' => ( "Clears all content caches related to a given node subtree,\n" .
                                                            "subtree expects a nice url as input.\n" .
                                                            "Separate multiple subtrees with a comma" ) ) );
$sys = eZSys::instance();

$script->initialize();

//include_once( 'kernel/classes/ezcontentcachemanager.php' );
//include_once( 'kernel/classes/ezcontentobjecttreenode.php' );

// Max nodes to fetch at a time
$limit = 50000;

if ( $options['clear-node'] )
{
    $idList = explode( ',', $options['clear-node'] );
    foreach ( $idList as $nodeID )
    {
        if ( is_numeric( $nodeID ) )
        {
            $node = eZContentObjectTreeNode::fetch( $nodeID );
            if ( !$node )
            {
                $cli->output( "Node with ID $nodeID does not exist, skipping" );
                continue;
            }
        }
        else
        {
            $nodeSubtree = trim( $nodeID, '/' );
            $node = eZContentObjectTreeNode::fetchByURLPath( $nodeSubtree );
            if ( !$node )
            {
                $cli->output( "Node with subtree " . $cli->stylize( 'emphasize', $nodeSubtree ) . " does not exist, skipping" );
                continue;
            }
        }
        $nodeSubtree = $node->attribute( 'path_identification_string' );
        $nodeName = false;
        $object = $node->attribute( 'object' );
        if ( $object )
        {
            $nodeName = $object->attribute( 'name' );
        }
        $objectID = $node->attribute( 'contentobject_id' );
        $cli->output( "Clearing cache for $nodeName ($nodeSubtree) single node" );
        eZContentCacheManager::clearContentCache( $objectID );

	//aggiunte GAB
	//system("wget -q --proxy-user=skype_urp --proxy-password=conference http://cms.intra.comune.trento.it/".$node->urlAlias()." > /tmp/log_visite");
        //$cli->output( "Generated cache for $nodeName ($nodeSubtree) single node" );
    }

    foreach ( $idList as $nodeID )
    {
	//aggiunte GAB
        $node = eZContentObjectTreeNode::fetch( $nodeID );
	system("wget -q --proxy-user=skype_urp --proxy-password=conference http://cms.intra.comune.trento.it/".$node->urlAlias()." > /tmp/log_visite");
        $cli->output( "Generated cache for $nodeName ($nodeSubtree) single node" );
    }

    $script->shutdown( 0 );
}
else if ( $options['clear-subtree'] )
{
    $subtreeList = explode( ',', $options['clear-subtree'] );
    foreach ( $subtreeList as $nodeSubtree )
    {
        if ( is_numeric( $nodeSubtree ) )
        {
            $nodeID = (int)$nodeSubtree;
            $node = eZContentObjectTreeNode::fetch( $nodeID );
            if ( !$node )
            {
                $cli->output( "Node with ID " . $cli->stylize( 'emphasize', $nodeID ) . " does not exist, skipping" );
                continue;
            }
        }
        else
        {
            $nodeSubtree = trim( $nodeSubtree, '/' );
            $node = eZContentObjectTreeNode::fetchByURLPath( $nodeSubtree );
            if ( !$node )
            {
                $cli->output( "Node with subtree " . $cli->stylize( 'emphasize', $nodeSubtree ) . " does not exist, skipping" );
                continue;
            }
        }
        $nodeSubtree = $node->attribute( 'path_identification_string' );
        $nodeName = false;
        $object = $node->attribute( 'object' );
        if ( $object )
        {
            $nodeName = $object->attribute( 'name' );
        }
        $cli->output( $cli->stylize( 'emphasize', "Svuota le cache per il sottoalbero ". $nodeName ) );
        $objectID = $node->attribute( 'contentobject_id' );
	$objectID_root = $objectID;
        $offset = 0;
        $params = array( 'AsObject' => false,
                         'Depth' => false,
                         'Limitation' => array() ); // Empty array means no permission checking

        $subtreeCount = $node->subTreeCount( $params );
	$cli->output( $cli->stylize( 'black', '') . $cli->stylize( 'yellow-bg', 'Numero di sottonodi: ') . $cli->stylize( 'orange', $subtreeCount ) );

        $script->resetIteration( $subtreeCount );
        while ( $offset < $subtreeCount )
        {
            $params['Offset'] = $offset;
            $params['Limit'] = $limit;
	    print_r($params);
            $subtree =& $node->subTree( $params );
            $offset += count( $subtree );
            if ( count( $subtree ) == 0 )
            {
                break;
            }

            $objectIDList = array();
            foreach ( $subtree as $subtreeNode )
            {
                $objectIDList[] = $subtreeNode['contentobject_id'];
            }
	    
            $objectIDList = array_unique( $objectIDList );

            foreach ( $objectIDList as $objectID )
            {
		$object = eZContentObject::fetch( $objectID );
		$node_tmp = $object->mainNode();
		if (($node_tmp->ClassIdentifier=='frontpage')||($node_tmp->ClassIdentifier=='folder')) {
        	        eZContentCacheManager::clearContentCache( $objectID );
	                $script->iterate( $cli, $status, $cli->stylize( 'warning', "Cleared view cache for object $objectID : ".$node_tmp->Name) );
		}
            }
            foreach ( $objectIDList as $objectID )
            {
		$object = eZContentObject::fetch( $objectID );
		$node_tmp = $object->mainNode();
		if (($node_tmp->ClassIdentifier=='frontpage')||($node_tmp->ClassIdentifier=='folder')) {
			system("wget -q --proxy-user=skype_urp --proxy-password=conference http://cms.intra.comune.trento.it/".$node_tmp->urlAlias()." > /tmp/log_visite");
	                $script->iterate( $cli, $status, $cli->stylize( 'green-bg', "Generated cache for the node: ".$node_tmp->Name . " LINK: /".$node_tmp->urlAlias()) );
		}
            }
        }
    }
    $script->shutdown( 0 );
}
$cli->output( "You will need to specify what to clear, either with --clear-node or --clear-subtree" );

$script->shutdown( 1 );

?>
