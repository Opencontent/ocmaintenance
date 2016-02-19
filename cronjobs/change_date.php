<?php

eZExtension::activateExtensions();

$cli = eZCLI::instance();
$cli->setUseStyles( true );
$cli->setIsQuiet( $isQuiet );

$user_ini = eZINI::instance( 'ocmaintenance.ini' );
$CronjobUser = $user_ini->variable( 'UserSettings', 'CronjobUser' );
$user = eZUser::fetchByName( $CronjobUser );
if ( $user )
{
    eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );
    $cli->warning( "Eseguo lo script da utente {$user->attribute( 'contentobject' )->attribute( 'name' )}" );
}
else
{    
    throw new InvalidArgumentException( "Non esiste un utente con nome utente $CronjobUser" ); 
}

// check the login user
$logged_user= eZUser::currentUser();
$cli->warning( "Si sta eseguendo l'agente con l'utente " . $logged_user->attribute( 'contentobject' )->attribute( 'name' )  );


$db = eZDB::instance();

$ini = eZINI::instance( 'openpa.ini' );
$Classes = $ini->variable( 'ChangeDate','ClassList' );

$rootNodeIDList = $ini->variable( 'ChangeDate','RootNodeList' );

$PublishedDataTime_array =  $ini->variable( 'ChangeDate','PublishedDataTime' );
$PublishedSinceHours_array =  $ini->variable( 'ChangeDate','PublishedSinceHours' );
$ModifiedDataTimePubblicazione =  $ini->variable( 'ChangeDate','ModifiedDataTime' );
$today = time();

foreach( $Classes as $class )
{    
    $rootNode = eZContentObjectTreeNode::fetch( $rootNodeIDList[$class] );
    $PublishedSinceHours = ( $PublishedSinceHours_array[$class] ) * 3600;
    $NodeArray = $rootNode->subTree(  array( 'ClassFilterType' => 'include',
                                             'ClassFilterArray' => array($class),
                                             'AttributeFilter' => array( 'and',
                                                                         array( 'published', '<=', $today ),
                                                                         array( 'published', '>', $today - $PublishedSinceHours ) )
                                            ));
    
    $PublishedDataTime = $PublishedDataTime_array[$class];
    
    $cli->warning( $class . ' ' . $PublishedDataTime . ' -> ' . count( $NodeArray )  );    
    
    $total = count( $NodeArray );
    $i = 1;
    foreach ( $NodeArray as $Node )
    {
        $contentObject = eZContentObject::fetch( (int) $Node->ContentObjectID );
        $current = $contentObject->attribute('current');
        if ( $current instanceof eZContentObjectVersion )
        {
            $creator_id = $current->attribute( 'creator_id' );
        }
        
        $dataMap = $contentObject->attribute( 'data_map' );
        if ( $contentObject->attribute( 'can_edit' ) )
        {			
			if ( array_key_exists( $PublishedDataTime, $dataMap )
                 && $dataMap[$PublishedDataTime]->attribute( 'has_content' )
                 && $dataMap[$PublishedDataTime]->attribute( 'data_int' ) != $contentObject->attribute( 'published' ) )
			{
				$db->begin();
                $attribute = $dataMap[$PublishedDataTime];
				$classAttributeID = $attribute->attribute( 'contentclassattribute_id' );
				$dataType = $attribute->attribute( 'data_type_string' );
				switch ( $dataType ) {
					case 'ezdate':
					case 'eztime':
                    {
						$contentObject->setAttribute( 'published', (int) $attribute->attribute( 'data_int' ) );
					} break;
				}
				$attribute->store();
                
                if ( $creator_id )
                {
                    $contentObject->setAttribute( 'owner_id', $creator_id );
                }
                $contentObject->store();
                $db->commit();
            
                $cli->output( $i . '/' . $total . " Cambio data $class #".$contentObject->attribute( 'id' ) . " nodo #" . $contentObject->attribute('main_node_id') );
            
                $searchEngine = eZSearch::getEngine();
                $searchEngine->addObject( $contentObject, true ); 
                eZContentCacheManager::clearContentCacheIfNeeded( $contentObject->attribute( 'id' ) );
                eZContentObject::clearCache( $contentObject->attribute( 'id' ) );
                $contentObject->resetDataMap();
			}            
        }
        $i++;
    }
}


?>
