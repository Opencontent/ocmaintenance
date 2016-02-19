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


//include_once( "lib/ezutils/classes/ezini.php" );
$ini = eZINI::instance('openpa.ini');
$Classes = $ini->variable('ChangeSection', 'ClassList');

$rootNodeIDList = $ini->variable('ChangeSection', 'RootNodeList');

$DataTime = $ini->variable('ChangeSection', 'DataTime');
$SectionIDs = $ini->variable('ChangeSection', 'ToSection');
$ScadenzaSecondi = $ini->variable('ChangeSection', 'ScadeDopoTotSecondi');
$currrentDate = time();

$class = "telefono";
$SectionID = 29; //intranet
$ClassAttribute = "tipo_telefono";
$nodeID = 306761;
$contentobject_id = 306804;
$content_attribute_id = 2082;
$object = eZContentObject::fetchByNodeID($nodeID);

$objects = $object->reverseRelatedObjectList(false, $content_attribute_id, false,
    array('AllRelations' => eZContentObject::RELATION_ATTRIBUTE));

foreach ($objects as $related_object) {
    $related_nodes = eZContentObjectTreeNode::fetchByContentObjectID($related_object->attribute('id'));
    $related_node = $related_nodes[0];
    $related_user = eZContentObject::fetchByNodeID($related_node->attribute('node_id'));

    if ($related_user->attribute('section_id') != $SectionID) {
        //eZContentObjectTreeNode::assignSectionToSubTree( $related_node->attribute('node_id'), $SectionID );
        if (eZOperationHandler::operationIsAvailable('content_updatesection')) {
            $operationResult = eZOperationHandler::execute('content',
                'updatesection',
                array(
                    'node_id' => $related_node->attribute('node_id'),
                    'selected_section_id' => $SectionID
                ),
                null,
                true);

        } else {
            eZContentOperationCollection::updateSection($related_node->attribute('node_id'), $SectionID);
        }
        $cli->output($cli->stylize('cyan', "Cambiata la sezione di " . $related_node->attribute('name')));
    }
}
