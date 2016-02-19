<?php

$cli = eZCLI::instance();
$cli->setUseStyles( true );
$cli->setIsQuiet( $isQuiet );
$cli->output( $cli->stylize( 'cyan', 'Leggo classi e attributi con le date di riferimento... ' ), false );

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


// lettura dei file INI
$ini = eZINI::instance( 'openpa.ini' );
$Classes = $ini->variable( 'ChangeSection','ClassList' );
$rootNodeIDList = $ini->variable( 'ChangeSection','RootNodeList' );
$DataTime =  $ini->variable( 'ChangeSection','DataTime' );
$SectionIDs =  $ini->variable( 'ChangeSection','ToSection' );
$ScadenzaSecondi = $ini->variable( 'ChangeSection','ScadeDopoTotSecondi' );
$currrentDate = time();


foreach( $rootNodeIDList as $class => $nodeID )
{
    $rootNode = eZContentObjectTreeNode::fetch( $nodeID );


	// TODO: sostituire la fetch SQL con fetch solr per problemi di performance
	// controllare automaticamente (dalla definizione della classe) se le date sono indicizzate in solr, altrimenti restituire errore

	$articleNodeArray = $rootNode->subTree( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array($class),
						'AttributeFilter' => array(array('section','!=',$SectionIDs[$class])) ) );

    foreach ( $articleNodeArray as $articleNode )
    {
        $article = $articleNode->attribute( 'object' );
        $dataMap = $article->attribute( 'data_map' );
	
	$unpublish_date = $DataTime[$article->ClassIdentifier];
	$ScadeDopoTotSecondi = $ScadenzaSecondi[$class];
	if (!$ScadeDopoTotSecondi>0) $ScadeDopoTotSecondi=9262300400;

        $dateAttribute = $dataMap[$unpublish_date];

	$date = $dateAttribute->content();
        $articleRetractDate = $date->attribute( 'timestamp' );

	//if ($articleRetractDate>0) {
        //	$articleRetractDate = $date->attribute( 'timestamp' );
	//} else {

		$articleRetractDate = $articleNode->ContentObject->Published + $ScadeDopoTotSecondi;

	//}

        if ( is_null( $dateAttribute ) ) 
		continue;

	$cli->output( $cli->stylize( 'blue', "Leggo l'oggetto ".$articleNode->attribute( 'node_id' ) . ": " . 
						$articleNode->attribute('name'). " con data = ".$articleRetractDate . " vs data = ".$currrentDate.  "\n" ), false );
        if ( $articleRetractDate > 0  && $articleRetractDate < $currrentDate )
        {
            // Clean up content cache
            //include_once( 'kernel/classes/ezcontentcachemanager.php' );
            eZContentCacheManager::clearContentCacheIfNeeded( $article->attribute( 'id' ) );

            //$article->removeThis( $articleNode->attribute( 'node_id' ) );
	    //cambia sezione
	    //eZContentObjectTreeNode::assignSectionToSubTree( $articleNode->NodeID, $SectionIDs[$class] );
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
	    $cli->output( $cli->stylize( 'cyan', "...Modificata sezione a ".$articleNode->attribute( 'node_id' ) . ": " . 
						$articleNode->attribute('name'). "\n" ), false );
        }
  	
    }
}

