<?php

require_once( 'kernel/common/ezincludefunctions.php' );
eZExtension::activateExtensions();

$adminUser = 'admin';
$user = eZUser::fetchByName( $adminUser );
if ( $user )
{
    eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );
    $cli->notice( "Eseguo lo script da utente {$user->attribute( 'contentobject' )->attribute( 'name' )}" );
}
else
{    
    throw new InvalidArgumentException( "Non esiste un utente con nome utente $adminUser" ); 
}

$options = $script->getOptions();
$options = $options['arguments'];
foreach( $options as $option )
{
    if ( strpos( $option, 'parent' ) !== false )
    {
        $parentNodeID = str_replace( 'parent=', '', $option );
    }
    elseif ( strpos( $option, 'attribute' ) !== false )
    {
        $attributeID = str_replace( 'attribute=', '', $option );
    }
}

$endl = $cli->endlineString();

// Get top node
$topNode = eZContentObjectTreeNode::fetch( $parentNodeID );

if ( strpos( strtolower( $topNode->attribute( 'name' ) ), 'ws' ) === false )
{
    $cli->error( "Stai lavorando in " . $topNode->attribute( 'name' ) );
    die( 'Blocco tutto' );
}

$subTreeCount = $topNode->subTreeCount( array( 'Limitation' => false ) );

print( "Numero di oggetti da valutare: $subTreeCount $endl" );

$i = 0;
$dotMax = 70;
$dotCount = 0;
$limit = 100;

$defaults = array( 'ClassFilterType' => 'include',
                   'ClassFilterArray' => array( 'file' ),
                   'LoadDataMap' => false,
                   'SortBy' => array( 'name', 'asc' ));

$subTree = $topNode->subTree( array( 'Offset' => $offset,
                                      'Limit' => $limit,                                      
                                      'Limitation' => array() ) + $defaults );
$removeNodes = array();
$removeObjects = array();
while ( $subTree != null )
{
    foreach ( $subTree as $innerNode )
    {
        $object = $innerNode->attribute( 'object' );    
         
        $reverseRelateds &= $object->attribute( 'reverse_related_contentobject_count' );
        if ( $reverseRelateds == 0 )
        {            
            $removeNodes[] = $object->attribute( 'main_node_id' );
        }
        else
        {
            print $object->attribute( 'name' ) . ' ' . $reverseRelateds;
        }
        eZContentObject::clearCache( $object->attribute( 'id' ) );
        unset( $object );        
        unset( $reverseRelateds );        

        // show progress bar
        
        ++$i;
        ++$dotCount;
        print( "." );
        //$memoryMax = memory_get_peak_usage(); // Result is in bytes
        //$memoryMax = round( $memoryMax / 1024 / 1024, 2 ); // Convert in Megabytes        
        //print( $memoryMax );
        
        if ( $dotCount >= $dotMax or $i >= $subTreeCount )
        {
            $dotCount = 0;
            $percent = (float)( number_format( ($i*100.0) / $subTreeCount, 2 ) );
            
            $memoryMax = memory_get_peak_usage(); // Result is in bytes
            $memoryMax = round( $memoryMax / 1024 / 1024, 2 ); // Convert in Megabytes            
            print( " " . $percent . "% " .$memoryMax.'M' . $endl );
        }
    }
    if ( $memoryMax > 230 )
    {
        print( 'Interrompo per evitare oversize di memoria' );
        break;
    }
    $offset += $limit;
    unset( $subTree );
    $subTree = $topNode->subTree( array( 'Offset' => $offset, 'Limit' => $limit,
                                          'Limitation' => array() ) + $defaults );
}

$db = eZDB::instance();

print( 'Sposto nel cetino ' . count( $removeNodes ) . ' oggetti' . $endl );
if ( count( $removeNodes ) )
{
    $count = count( $removeNodes );
    foreach( $removeNodes as $i => $removeNodeID )
    {
       print( $i . '/' .$count . ' - Rimuovo nodo ' . $removeNodeID . $endl );
       $db->begin();
       eZContentObjectTreeNode::removeSubtrees( array( $removeNodeID ), true );
       $db->commit();
    }
}


$memoryMax = memory_get_peak_usage(); // Result is in bytes
$memoryMax = round( $memoryMax / 1024 / 1024, 2 ); // Convert in Megabytes
$cli->notice( 'Peak memory usage : '.$memoryMax.'M' );

?>


