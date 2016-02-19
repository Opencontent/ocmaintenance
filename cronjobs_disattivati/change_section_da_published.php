<?php

$cli = eZCLI::instance();
$cli->setUseStyles(true);
$cli->setIsQuiet($isQuiet);
$cli->output($cli->stylize('cyan', 'Leggo classi e attributi con le date di riferimento... '), false);

$user_ini = eZINI::instance('ocmaintenance.ini');
$CronjobUser = $user_ini->variable('UserSettings', 'CronjobUser');
/** @var eZUser $user */
$user = eZUser::fetchByName($CronjobUser);
if ($user) {
    eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));
    $cli->output("Eseguo lo script da utente {$user->attribute( 'contentobject' )->attribute( 'name' )}");
} else {
    throw new InvalidArgumentException("Non esiste un utente con nome utente $CronjobUser");
}

//include_once( "lib/ezutils/classes/ezini.php" );
$ini = eZINI::instance('content.ini');
$Classes = $ini->variable('ChangeSection_da_published', 'ClassList');

$rootNodeIDList = $ini->variable('ChangeSection_da_published', 'RootNodeList');

$DataTime = $ini->variable('ChangeSection_da_published', 'DataTime');
$SectionIDs = $ini->variable('ChangeSection_da_published', 'ToSection');

$PublishedSinceHours = $ini->variable('ChangeSection_da_published', 'PublishedSinceHours');
$today = time();
$currrentDate = time();

foreach ($rootNodeIDList as $class => $nodeID) {
    $rootNode = eZContentObjectTreeNode::fetch($nodeID);

    $articleNodeArray = $rootNode->subTree(array(
        'ClassFilterType' => 'include',
        'ClassFilterArray' => array($class),
        'AttributeFilter' => array(array('section', '!=', $SectionIDs[$class]))
    ));

    foreach ($articleNodeArray as $articleNode) {
        $article = $articleNode->attribute('object');
        $dataMap = $article->attribute('data_map');

        $unpublish_date = $DataTime[$article->ClassIdentifier];
        $dateAttribute = $dataMap[$unpublish_date];
        if (is_null($dateAttribute)) {
            continue;
        }
        $date = $dateAttribute->content();
        $articleRetractDate = $date->attribute('timestamp');
        $cli->output($cli->stylize('cyan', "\nLeggo oggetto " . $articleNode->attribute('id') . ": " .
                                           $articleNode->attribute('name') . "\n"), false);
        $cli->output($cli->stylize('red', ">> DATA ARCHIVIAZIONE " . $articleRetractDate . "\n"), false);
        if ($articleRetractDate > 0 && $articleRetractDate < $currrentDate) {
            // Clean up content cache
            //include_once( 'kernel/classes/ezcontentcachemanager.php' );
            eZContentCacheManager::clearContentCacheIfNeeded($article->attribute('id'));

            //$article->removeThis( $articleNode->attribute( 'node_id' ) );
            //cambia sezione

            if (eZOperationHandler::operationIsAvailable('content_updatesection')) {
                $operationResult = eZOperationHandler::execute('content',
                    'updatesection',
                    array(
                        'node_id' => $articleNode->NodeID,
                        'selected_section_id' => $SectionIDs[$class]
                    ),
                    null,
                    true);

            } else {
                eZContentOperationCollection::updateSection($articleNode->NodeID, $SectionIDs[$class]);
            }

            $cli->output($cli->stylize('cyan',
                "...GESTIONE ORDINARIA - Modifico sezione oggetto " . $articleNode->attribute('id') . ": " .
                $articleNode->attribute('name') . "in " . $SectionIDs[$class] . "\n"), false);
        }

        /*
            if ( 'published','<=',$currrentDate-$PublishedSinceHours )
            {
                    // Clean up content cache
                    //include_once( 'kernel/classes/ezcontentcachemanager.php' );
                    eZContentCacheManager::clearContentCacheIfNeeded( $article->attribute( 'id' ) );

                    //$article->removeThis( $articleNode->attribute( 'node_id' ) );
                //cambia sezione
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
                $cli->output( $cli->stylize( 'green', "...GESTIONE EXTRA - Modifico sezione oggetto ".$articleNode->attribute( 'id' ) . ": " . $articleNode->attribute('name'). "in " . $SectionIDs[$class] . "\n" ), false );
            }
        */

    }
}
