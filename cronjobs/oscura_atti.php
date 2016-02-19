<?php

require_once( 'autoload.php' );
eZExtension::activateExtensions();

$cli = eZCLI::instance();
$cli->setUseStyles( true );
$cli->setIsQuiet( $isQuiet );

//$sitedata = eZSiteData::fetchByName( 'oscura_atti' );
//$data = unserialize( $sitedata->attribute( 'value' ) );
//print_r($data);die();

$ocmaintenanceINI = eZINI::instance( 'ocmaintenance.ini' );
$CronjobUser = $ocmaintenanceINI->variable( 'UserSettings', 'CronjobUser' );
$CronjobUserP = $ocmaintenanceINI->variable( 'UserSettings', 'CronjobUserP' );

// autentication as editor or administrator
$user = eZUser::loginUser( $CronjobUser, $CronjobUserP);
$cli->output( "Si sta eseguendo l'agente con l'utente ". $user->attribute( 'login' ) );

$handler = new OscuraAttiHandler( array(
                                        'cli' => $cli,
                                        'ini' => eZINI::instance( 'oscuraatti.ini' ),
                                        'csvOptions' => array( 'delimiter' => ';', 'enclosure' => '"' )
                                        ) );
$handler->run();

eZExecution::cleanExit();

?>


