<?php
/**
 * Utility class for CSV file handling.
 * First row of CSV file will be used for rows as labels.
 * All special characters will be removed in labels
 */
class CSVDoc
{
    /**
     * Pointer to CSV file
     * @var resource
     */
    protected $csvFile;
    
    /**
     * @var CSVOptions
     */
    protected $options;
    
    /**
     * Rows of CSV File
     * @var CSVRowSet
     */
    public $rows;
    
    /**
     * Constructor.
     * Will throw an exception if CSV file does not exist or is invalid
     * @param CSVOptions $options Options for CSV file (See {@link CSVOptions::__construct()})
     * @throws CSVException
     */
    public function __construct( CSVOptions $options )
    {
        $this->options = $options;
        $csvPath = $options['csv_path'];
        
        if( !file_exists( $csvPath ) )
            throw new CSVException( "CSV file $csvPath does not exist");
        
    }
    
    /**
     * Parses CSV File
     * @throws CSVException
     * @return void
     */
    public function parse()
    {
        eZDebug::accumulatorStart( 'csvdoc_loading', 'csvdoc', 'Loading CSV file in memory' );
        $this->csvFile = @fopen( $this->options['csv_path'], 'r' );
        if( !$this->csvFile )
            throw new CSVException( "Cannot open CSV file '{$this->options['csv_path']}' for reading" );
        
        $this->rows = CSVRowSet::fromCSVFile( $this->csvFile, $this->options );
        eZDebug::accumulatorStop( 'csvdoc_loading' );
    }
}
