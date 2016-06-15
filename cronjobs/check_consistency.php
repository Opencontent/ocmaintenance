<?php

/** @var $cli */
/** @var $script */

// $doUpdate, true or false. Set to false for at dry test-run
$doUpdate = true;

set_time_limit(0);

if (!class_exists('OcCheckConsistency')){
    $script->shutdown(1, 'OcCheckConsistency class not found. Regenerate autoloads!');
}

$tool = OcCheckConsistency::instance();

if (!$isQuiet) $tool->verbose();

$cli->output();
$cli->output("First checking for content objects that has no contentobject_attributes at all...");
$cli->output();
$count = $tool->checkObjectsWithoutAttributes();
$cli->output("Affected objects: $count");
if ($doUpdate && $count > 0) {
    $cli->warning("Fixing... please wait");
    $tool->checkObjectsWithoutAttributes(true);
}
$cli->output();
$cli->output("Check nodes consistency");
$cli->output();
$count = $tool->checkNodesConsistency();
$cli->output("Affected objects: $count");
if ($doUpdate && $count > 0) {
    $cli->warning("Fixing... please wait");
    $tool->checkNodesConsistency(true);
}

$cli->output();
$cli->output("Then checking for content objects that has contentobject_attributes, but not of the current_version");
$cli->output();
$count = $tool->checkObjectsWithoutCurrentVersion();
$cli->output("Affected objects: $count");
if ($doUpdate && $count > 0) {
    $cli->warning("Fixing... please wait");
    $tool->checkObjectsWithoutCurrentVersion(true);
}

$cli->output();
$cli->output("Check users without login name");
$cli->output();
$count = $tool->checkUserWithoutLogin();
$cli->output("Affected objects: $count");
if ($doUpdate && $count > 0) {
    $cli->warning("Fixing... please wait");
    $tool->checkUserWithoutLogin(true);
}

$script->shutdown();
