<?php
require 'autoload.php';

$script = eZScript::instance( array( 'description' => ( "Search user by mail\n\n" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( '[mail:][remove]',
                                '',
                                array( 'mail'  => 'Mail address', 'remove' => 'Remove found user?'  )
);
$script->initialize();
$script->setUseDebugAccumulators( true );


try
{    
    $user = eZUser::fetchByEmail( $options['mail'] );
    if ( $user instanceof eZUser )
    {
        eZCLI::instance()->warning( var_export( $user, 1 ) );    
        if ( $options['remove'] == 1 )
        {
            $userID = $user->attribute( 'contentobject_id' );
            eZUser::removeUser( $userID );
        }
    }
    else
    {
        eZCLI::instance()->error( "Nada..." );
    }
    $script->shutdown();
}
catch( Exception $e )
{    
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown( $errCode, $e->getMessage() );
}
