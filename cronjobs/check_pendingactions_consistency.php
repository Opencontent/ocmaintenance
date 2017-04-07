<?php

/** @var eZCLI $cli */
/** @var eZScript $script */

// $doUpdate, true or false. Set to false for at dry test-run
$doUpdate = true;

set_time_limit(0);

if (!class_exists('OcCheckConsistency')){
    $script->shutdown(1, 'OcCheckConsistency class not found. Regenerate autoloads!');
}

$tool = OcCheckConsistency::instance();

if (!$isQuiet) $tool->verbose();

$cli->output();
$cli->output("Check pending action");
$cli->output();

$count = $tool->checkPendingActions();
$cli->output("Affected objects: $count");
if ($doUpdate && $count > 0) {
    $cli->warning("Fixing... please wait");
    $tool->checkPendingActions(true);
}

$script->shutdown();
