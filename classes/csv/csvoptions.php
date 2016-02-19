<?php


class CSVOptions extends CSVBaseOptions
{
    /**
     * Constructor.
     * Available options are :
     *  - csv_path (Path to CSV file)
     *  - delimiter (Field delimiter, one character only)
     *  - enclosure (Field enclosure character, one character only)
     *  - csv_line_length (CSV line length. Must be higher than the longest line in CSV file)
     * @param $options
     */
    public function __construct( array $options = array() )
    {
        // Define some default values
        $this->properties = array(
            'csv_path'         => null, // Path to CSV file
            'delimiter'        => ';', // Field delimiter (one character only)
            'enclosure'        => '"', // Field enclosure character (one character only)
            'csv_line_length'  => 100000, // CSV line length. Must be higher than the longest line in CSV file
        );
        
        parent::__construct( $options );
    }
    
    public function __set( $optionName, $optionValue )
    {
        parent::__set( $optionName, $optionValue );
    }
}
