<?php

$cli = eZCLI::instance();

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

# definizione delle variabili
$class = "user";
$sectionID = 29; // sezione intranet
$nodeID = 196450; // nodeid servizio a disposizione
$contentobject_id = 197584; // objectid servizio a disposizione
$contentClassAttributeId = 909; // classattributeid utente/servizio

$object = eZContentObject::fetch($contentobject_id); // node servizio a disposizione
if (!$object instanceof eZContentObject) {
    throw new InvalidArgumentException("Non trovo l'oggetto Servizio a disposizione");
}

# calcolo degli utenti inversamente relazionati al servizio
/** @var eZContentObject[] $objects */
$objects = $object->reverseRelatedObjectList(
    false,
    $contentClassAttributeId,
    false,
    array('AllRelations' => eZContentObject::RELATION_ATTRIBUTE)
);

# controllo della sezione corretta per ciascun utente: se non è in $sectionID viene aggiornato
foreach ($objects as $relatedObject) {
    if ($relatedObject->attribute('class_identifier') == $class) {
        $relatedNode = $relatedObject->attribute('main_node');
        if ($relatedNode instanceof eZContentObjectTreeNode) {
            $userObject = eZContentObject::fetch($relatedObject->attribute('id'));

            if ($userObject->attribute('section_id') != $sectionID) {
                //eZContentObjectTreeNode::assignSectionToSubTree( $related_node->attribute('node_id'), $sectionID );
                if (eZOperationHandler::operationIsAvailable('content_updatesection')) {
                    $operationResult = eZOperationHandler::execute(
                        'content',
                        'updatesection',
                        array(
                            'node_id' => $relatedNode->attribute('node_id'),
                            'selected_section_id' => $sectionID
                        ),
                        null,
                        true
                    );

                } else {
                    eZContentOperationCollection::updateSection(
                        $relatedNode->attribute('node_id'),
                        $sectionID
                    );
                }
                $cli->output("Il nodo " . $relatedNode->attribute('node_id') . " - " . $relatedNode->attribute('name') . " è stato associato alla sezione " . $sectionID);
            } else {
                $cli->output("Il nodo " . $relatedNode->attribute('node_id') . " - " . $relatedNode->attribute('name') . " è già associato alla sezione " . $sectionID);
            }
        } else {
            $cli->error("Main node dell'oggetto " . $relatedObject->attribute('id') . " (" . $relatedObject->attribute('name') . ") non trovato");
        }
    }
}
