<?php


$cli = eZCLI::instance();
$cli->setUseStyles(true);
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
// lascia utente originale
$original_author = true;

$db = eZDB::instance();


//include_once( "lib/ezutils/classes/ezini.php" );
$ini = eZINI::instance('openpa.ini');
$Classes = $ini->variable('ChangeDate', 'ClassList');

$rootNodeIDList = $ini->variable('ChangeDate', 'RootNodeList');
$today = time();


foreach ($Classes as $class) {

    $NodeArray = eZContentObjectTreeNode::subTreeByNodeID(array(
        'ClassFilterType' => 'include',
        'ClassFilterArray' => array($class)
    ),
        $rootNodeIDList[$class]);
    $count = 0;

    $cli->notice($class . ' ', false);

    foreach ($NodeArray as $Node) {
        $contentObject = eZContentObject::fetch((int)$Node->ContentObjectID);
        $current = $contentObject->attribute('current');

        if ($current->attribute('status') == eZContentObjectVersion::STATUS_ARCHIVED) {
            //$cli->notice( $Node->ContentObjectID . ' ' . $current->attribute( 'version' ) . ' ' . $current->attribute( 'status' ) );
            $count++;

            eZContentOperationCollection::setVersionStatus($contentObject->attribute('id'),
                $contentObject->attribute('current_version'), eZContentObjectVersion::STATUS_PUBLISHED);
            $cli->notice('.', false);
            /*
                        $contentObjectVersion = $contentObject->createNewVersion();
                        $versionNumber  = $contentObjectVersion->attribute( 'version' );
                        $creator_id = $current->attribute( 'creator_id' );
                        if ( $creator_id )
                        {
                            $contentObjectVersion->setAttribute( 'creator_id', $creator_id );
                            $contentObjectVersion->store();
                        }

                        eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObject->attribute( 'id' ), 'version' => $versionNumber ) );
            */

        }
    }

    $cli->notice($count);
}


?>


