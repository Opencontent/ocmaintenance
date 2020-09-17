<?php
require 'autoload.php';

$script = eZScript::instance(array('description' => ("Check ezuser"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true));

$script->startup();

$options = $script->getOptions('[fix_zombies][fix_interrupted]',
    '',
    array(
        'fix_zombies' => 'Fix zombies',
        'fix_interrupted' => 'Fix interrupted',
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();


try {

    $cli->output('Check zombies... ', false);
    $zombiesRows = eZDB::instance()->arrayQuery('SELECT * FROM ezuser WHERE contentobject_id NOT IN ( SELECT DISTINCT ezcontentobject.id FROM ezcontentobject ) ORDER BY contentobject_id DESC;');
    $cli->output(count($zombiesRows));

    if ($options['fix_zombies'] && count($zombiesRows) > 0) {
        $cli->warning('Fix zombies');

        foreach ($zombiesRows as $zombiesRow) {
            $userID = $zombiesRow['contentobject_id'];
            eZSubtreeNotificationRule::removeByUserID($userID);
            eZCollaborationNotificationRule::removeByUserID($userID);
            eZUserSetting::removeByUserID($userID);
            eZUserAccountKey::removeByUserID($userID);
            eZForgotPassword::removeByUserID($userID);
            eZWishList::removeByUserID($userID);
            eZGeneralDigestUserSettings::removeByUserId($userID);
        }
        eZDB::instance()->arrayQuery('DELETE FROM ezuser WHERE contentobject_id NOT IN ( SELECT DISTINCT ezcontentobject.id FROM ezcontentobject );');
    }

    $cli->output('Check interrupted... ', false);
    $interruptedRows = eZDB::instance()->arrayQuery('SELECT * FROM ezuser WHERE contentobject_id IN ( SELECT DISTINCT ezcontentobject.id FROM ezcontentobject WHERE status = ' . eZContentObject::STATUS_DRAFT . ') ORDER BY contentobject_id DESC;');
    $cli->output(count($interruptedRows));


    if ($options['fix_interrupted'] && count($interruptedRows) > 0) {

        $cli->warning('Clean all admin internal draft before last hour');
        eZContentObject::cleanupAllInternalDrafts(14, 3600);

        $cli->warning('Remaining interrupted users: check manually...');
        foreach ($interruptedRows as $row) {
            $object = eZContentObject::fetch($row['contentobject_id']);            
            $version = $object->attribute('current');
            if ($version->attribute('status') == eZContentObjectVersion::STATUS_INTERNAL_DRAFT){
                $cli->output($row['contentobject_id'] . ' ' . $row['login'] . ' ' . $object->attribute('name') . ' ' . date('c', $object->attribute('current')->attribute('created')));                
                if (ezcConsoleDialogViewer::displayDialog(ezcConsoleQuestionDialog::YesNoQuestion(new ezcConsoleOutput(),"Remove?","y")) == "y" ){
                    $version->removeThis();
                }
            }
        }
    }

    function fetchUnactivated($sort = false, $limit = 10, $offset = 0)
    {
        $accountDef = eZUserAccountKey::definition();
        $settingsDef = eZUserSetting::definition();

        return eZPersistentObject::fetchObjectList(
            eZUser::definition(), null, null, array('contentobject_id' => true), null,
            true, false, null,
            array($accountDef['name'], $settingsDef['name']),
            " WHERE contentobject_id = {$accountDef['name']}.user_id"
            . " AND {$settingsDef['name']}.user_id = contentobject_id"
            . " AND is_enabled = 0"
            . " AND contentobject_id IN ( SELECT DISTINCT ezcontentobject.id FROM ezcontentobject )"
        );
    }

    $cli->output('Check unactivate... ', false);
    $unactivated = fetchUnactivated();
    $cli->output(count($unactivated));

    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown($errCode, $e->getMessage());
}
