<?php
//
//
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.3
// COPYRIGHT NOTICE: Copyright (C) 2010 Opencontent
// AUTHOR: gabriele@opencontent.it
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//

/*! \file unpublish.php
*/

//include_once( "kernel/classes/ezcontentobjecttreenode.php" );

// script di manutenzione
// svuota le sessioni

// Check for extension
//include_once( 'lib/ezutils/classes/ezextension.php' );
require_once( 'kernel/common/ezincludefunctions.php' );
eZExtension::activateExtensions();
// Extension check end

$cli = eZCLI::instance();
$cli->setUseStyles( true );
$cli->output( $cli->stylize( 'cyan', 'Leggo classi e attributi con le date di riferimento... ' ), false );
$user_ini = eZINI::instance( 'ocmaintenance.ini' );
$CronjobUser = $user_ini->variable( 'UserSettings', 'CronjobUser' );
$CronjobUserP = $user_ini->variable( 'UserSettings', 'CronjobUserP' );
$cli->output( $cli->stylize( 'cyan', "Lette\n" ), false );
// autentication as editor or administrator
$user = eZUser::loginUser( $CronjobUser, $CronjobUserP);
// check the login user

// cambia utente
//$logged_user= eZUser::currentUser();
//$cli->output( $cli->stylize( 'red', "Si sta eseguendo l'agente con l'utente ".$logged_user->Login."\n" ), false );

$db = eZDB::instance();

//include_once( "lib/ezutils/classes/ezini.php" );



	$db->begin();
	$db->query("TRUNCATE TABLE ezsession");
       	$db->commit();

$cli->output( $cli->stylize( 'red', "Svuotata la tabella delle sessioni" ), false );

?>
