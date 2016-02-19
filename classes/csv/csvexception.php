<?php

class CSVException extends ezcBaseException
{
    public function __construct( $message )
    {
        eZCLI::instance()->notice();
        eZCLI::instance()->error( $message );
        parent::__construct( $message );
    }
}
