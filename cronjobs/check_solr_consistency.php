<?php

// $doUpdate, true or false. Set to false for at dry test-run
$doUpdate = true;

$cli = eZCLI::instance();

$user_ini = eZINI::instance( 'ocmaintenance.ini' );
$CronjobUser = $user_ini->variable( 'UserSettings', 'CronjobUser' );
/** @var eZUser $user */
$user = eZUser::fetchByName( $CronjobUser );
if ( $user )
{
    eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );
    $cli->output( "Eseguo lo script da utente {$user->attribute( 'contentobject' )->attribute( 'name' )}" );
}
else
{
    throw new InvalidArgumentException( "Non esiste un utente con nome utente $CronjobUser" );
}

$excludeClasses = eZINI::instance( 'ezfind.ini' )->variable( 'IndexExclude', 'ClassIdentifierList' );
$classes = eZContentClass::fetchAllClasses( false );

foreach( $classes as $class )
{
    $classID = $class['id'];
    $classIdentifier = eZContentClass::classIdentifierByID( $class['id'] );
    if ( !in_array( $classIdentifier, $excludeClasses ) )
    {
        $cli->warning( str_pad($classIdentifier, 50), false );

        if ($doUpdate) {
            $objects = eZContentObject::fetchSameClassList($classID);
            $dbCount = str_pad(count($objects), 5, ' ', STR_PAD_LEFT);
        }else{
            $dbCount = eZContentObject::fetchSameClassListCount($classID);
        }
        $countSql = str_pad($dbCount, 5, ' ', STR_PAD_LEFT);
        $cli->output( ' SQL: ' . $countSql, false );
        $sql = array();
        foreach( $objects as $object )
            $sql[] = $object->attribute( 'id' );
        
        $solrSearch = new eZSolr();
        $params = array(
            'SearchLimit' => $doUpdate ? 200000 : 1,
            'Filter' => array( 'contentclass_id:' . $classID ),
            'SearchSubTreeArray' => array( 1 ),
            'AsObjects' => false,
            'FieldsToReturn' => array( 'meta_id_si' )
        );        
        $result = $solrSearch->search( '', $params );
        $searchCount = $result['SearchCount'];

        $countSolr = str_pad($searchCount, 4, ' ');
        $cli->output( ' SOLR: ' . $countSolr, false );

        if ($doUpdate) {
            $searchResult = $result['SearchResult'];
            $solr = array();
            foreach ($searchResult as $object) {
                $solr[] = $object['id'];
            }
            $forceIndex = array_diff( $sql, $solr );
            $countReindex = count($forceIndex);
            foreach ($forceIndex as $id) {
                $contentObject = eZContentObject::fetch($id);
                $result = $solrSearch->addObject($contentObject, true);
                if ($result) {
                    //$cli->warning( 'Index object ' . $id );
                } else {
                    $cli->error('Error indexing object ' . $id);
                }
                eZContentObject::clearCache($contentObject->attribute('id'));
                $contentObject->resetDataMap();
            }
        }else{
            $countReindex = $dbCount - $countSql;
        }

        if ($countReindex > 0){
            $cli->error( " Reindex {$countReindex} objects" );
        }else{
            $cli->output();
        }
    }
}
