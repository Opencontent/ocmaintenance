<?php

require_once( 'kernel/common/ezincludefunctions.php' );
eZExtension::activateExtensions();

function checkStatus( $contentObject )
{
    $current = $contentObject->attribute('current');
    if ( $current->attribute( 'status' ) == eZContentObjectVersion::STATUS_ARCHIVED )
    {
        eZContentOperationCollection::setVersionStatus(
                                                       $contentObject->attribute( 'id' ),
                                                       $contentObject->attribute( 'current_version' ),
                                                       eZContentObjectVersion::STATUS_PUBLISHED );        
        unset( $current );
        return true;
    }    
    unset( $current );
    return false;
}


$endl = $cli->endlineString();

// Get top node
$topNodeArray = eZPersistentObject::fetchObjectList( eZContentObjectTreeNode::definition(),
                                                     null,
                                                     array( 'parent_node_id' => 1,
                                                            'depth' => 1 ) );
$subTreeCount = 0;
foreach ( array_keys ( $topNodeArray ) as $key  )
{
    $subTreeCount += $topNodeArray[$key]->subTreeCount( array( 'Limitation' => false ) );
}

print( "Number of objects to update: $subTreeCount $endl" );

$i = 0;
$dotMax = 70;
$dotCount = 0;
$limit = 50;

foreach ( array_keys ( $topNodeArray ) as $key  )
{
    $node =& $topNodeArray[$key];
    $offset = 0;
    $subTree =& $node->subTree( array( 'Offset' => $offset, 'Limit' => $limit,
                                       'Limitation' => array() ) );
    while ( $subTree != null )
    {
        foreach ( $subTree as $innerNode )
        {
            $object = $innerNode->attribute( 'object' );
            
            if ( checkStatus( $object ) )
            {            
                eZLog::write( 'node #' . $innerNode->attribute( 'node_id' ) . ' object #' . $object->attribute( 'id' ) , 'fixtstatus.log' );
            }
            eZContentObject::clearCache( $object->attribute( 'id' ) );
            $object->resetDataMap();
            
            unset( $object );

            // show progress bar
            ++$i;
            ++$dotCount;
            print( "." );
            if ( $dotCount >= $dotMax or $i >= $subTreeCount )
            {
                $dotCount = 0;
                $percent = (float)( ($i*100.0) / $subTreeCount );
                print( " " . $percent . "%" . $endl );
            }
        }
        $offset += $limit;
        unset( $subTree );
        $subTree =& $node->subTree( array( 'Offset' => $offset, 'Limit' => $limit,
                                           'Limitation' => array() ) );
    }
}


$memoryMax = memory_get_peak_usage(); // Result is in bytes
$memoryMax = round( $memoryMax / 1024 / 1024, 2 ); // Convert in Megabytes
$cli->notice( 'Peak memory usage : '.$memoryMax.'M' );

?>


