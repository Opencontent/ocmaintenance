<?php
$cli = eZCLI::instance();

$limit = 1000;
$offset = 0;
$errors = array();

$count = eZPersistentObject::count( eZURLAliasML::definition(), array( 'action_type' => 'eznode' ) );
$cli->error( $count );

do
{
    $entries = eZPersistentObject::fetchObjectList( eZURLAliasML::definition(),
                                                null,
                                                array( 'action_type' => 'eznode' ),
                                                null,
                                                array( "limit" => $limit,
                                                       "offset" => $offset ) );
    
    $cli->output('');
    $cli->output( $limit + $offset . '/' . $count );
    
    foreach( $entries as $entry )
    {
        $action = $entry->attribute( 'action' );
        $parts = explode( ':', $action );
        $node = $parts[1];
        if ( !eZContentObjectTreeNode::fetch( intval( $node ), false ) )
        {            
            $errors[] = serialize( $entry );
            $db = eZDB::instance();            
            $query = "DELETE FROM ezurlalias_ml WHERE action = '{$action}'";
            $db->query( $query );
            $cli->output( '*', false );
        }
        else{ $cli->output( '.', false ); }
    }
    
    $offset += $limit;
}
while ( count( $entries ) == $limit );

$cli->error('');
$cli->error( count( $errors ) );

eZLog::write( var_export( $errors, 1 ), 'fix_url_alias_ml.log' );


?>