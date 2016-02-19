<?php

$user = eZUser::fetchByName( 'admin' );
eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );

$excludeClasses = eZINI::instance( 'ezfind.ini' )->variable( 'IndexExclude', 'ClassIdentifierList' );
$classes = eZContentClass::fetchAllClasses( false );

foreach( $classes as $class )
{
    $classID = $class['id'];
    $classIdentifier = eZContentClass::classIdentifierByID( $class['id'] );
    if ( !in_array( $classIdentifier, $excludeClasses ) )
    {
        $cli->warning( $class['name'] );        
        
        $objects = eZContentObject::fetchSameClassList( $classID );
        $cli->output( 'Sql count: ' . count( $objects ) );
        $sql = array();
        foreach( $objects as $object )
            $sql[] = $object->attribute( 'id' );
        
        $solrSearch = new eZSolr();
        $params = array(
            'SearchLimit' => 200000,
            'Filter' => array( 'contentclass_id:' . $classID ),
            'SearchSubTreeArray' => array( 1 ),
            'AsObjects' => false,
            'FieldsToReturn' => array( 'meta_id_si' )
        );        
        $result = $solrSearch->search( '', $params );
        $searchCount = $result['SearchCount'];
        $searchResult = $result['SearchResult'];
        
        $cli->output( 'Solr count: ' . $searchCount );
        
        $solr = array();
        foreach( $searchResult as $object )
        {
            $solr[] = $object['id'];
        }
        
        $forceIndex = array_diff( $sql, $solr );

        foreach( $forceIndex as $id )
        {
            $contentObject = eZContentObject::fetch( $id );
            $result = $solrSearch->addObject( $contentObject, true );
            if ( $result )
            {
                $cli->warning( 'Index object ' . $id );
            }
            else
            {
                $cli->error( 'Error indexing object ' . $id );
            }
            eZContentObject::clearCache( $contentObject->attribute( 'id' ) );
            $contentObject->resetDataMap();
        }
    }
}
