<?php
/**
 * File containing the bccieExporter class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * Writes the rows of an export as CSV or SYLK, line by line, so that a large export never needs the whole file in memory.
 *
 * Every text cell is neutralised against formula injection (bccieExportUtils::neutralise()): a cell that starts with
 * = + - @ (or a tab or carriage return) would be run as a formula by a spreadsheet.
 */
class bccieExporter
{
    const SEPARATORS = ';,:|#';

    public $options;
    protected $parser;
    protected $columns = 0;
    protected $row = 1;
    protected $integerColumns = array();

    /**
     * @param array $options 'format' ('csv', 'sylk'), 'separator', 'fields' (what the columns are),
     *                       'creation_date', 'modification_date'
     */
    function __construct( array $options, $parser = null )
    {
        $this->options = $options + array( 'format' => 'csv', 'separator' => ';', 'fields' => array(),
                                           'creation_date' => false, 'modification_date' => false );
        if ( $this->options['format'] !== 'sylk' )
        {
            $this->options['format'] = 'csv';
        }
        $this->parser = $parser ? $parser : new Parser();
        $this->parser->setCreationDate( (bool)$this->options['creation_date'] );
        $this->parser->setModificationDate( (bool)$this->options['modification_date'] );
    }

    /**
     * @return array the header cells, one for every column
     */
    function headerCells()
    {
        $fields = $this->options['fields'];
        $dummy = null;
        return $this->parser->exportCollectionObjectHeaderNew( $dummy, $fields, $this->options['separator'] );
    }

    /**
     * @return array the cells of one collection
     */
    function dataCells( $collection )
    {
        $fields = $this->options['fields'];
        return $this->parser->exportCollectionObject( $collection, $fields, $this->options['separator'] );
    }

    /**
     * The start of the file: nothing for CSV, the format records of SYLK.
     *
     * @param int|false $columns the number of columns, from the header when not given
     */
    function begin( $columns = false )
    {
        $this->row = 1;
        if ( $columns === false )
        {
            $columns = count( $this->headerCells() );
        }
        $this->columns = (int)$columns;

        // the columns of the collection id are numbers, all others are text
        $this->integerColumns = array();
        $column = 0;
        foreach ( $this->options['fields'] as $field )
        {
            if ( $field === 'contentobjectid' )
            {
                $this->integerColumns[$column] = true;
            }
            if ( (int)$field !== -2 || $field === 'contentobjectid' )
            {
                $column++;
            }
        }

        if ( $this->options['format'] !== 'sylk' )
        {
            return '';
        }

        $text = "ID;Pcie\n\n";
        $text .= "P;PGeneral\nP;P#,##0.00\nP;P#,##0\nP;P@\n\n";
        $text .= "P;EArial;M200\nP;EArial;M200\nP;EArial;M200\nP;FArial;M200;SB\n\n";
        for ( $column = 1; $column <= $this->columns; $column++ )
        {
            $text .= "F;W" . $column . " " . $column . " " . ( isset( $this->integerColumns[$column - 1] ) ? 8 : 24 ) . "\n";
        }
        $text .= "F;W" . ( $this->columns + 1 ) . " 256 8\n\n";

        return $text;
    }

    /**
     * @return string the header line (cells from headerCells() unless given)
     */
    function headerLine( $cells = false )
    {
        $cells = $cells === false ? $this->headerCells() : $cells;
        if ( $this->options['format'] !== 'sylk' )
        {
            return $this->csvLine( $cells );
        }

        $text = '';
        $this->columns = max( $this->columns, count( $cells ) );
        foreach ( array_values( $cells ) as $index => $cell )
        {
            $text .= "F;SDM4;FG0C;" . ( $index == 0 ? "Y1;" : "" ) . "X" . ( $index + 1 ) . "\n";
            $text .= 'C;N;K"' . $this->sylkText( $cell ) . "\"\n";
        }
        $this->row = 1;

        return $text . "\n";
    }

    /**
     * @return string the line of one collection's cells
     */
    function dataLine( array $cells )
    {
        if ( $this->options['format'] !== 'sylk' )
        {
            return $this->csvLine( $cells );
        }

        $this->row++;
        $text = '';
        for ( $column = 0; $column < $this->columns; $column++ )
        {
            $value = isset( $cells[$column] ) ? $cells[$column] : '';
            $integer = isset( $this->integerColumns[$column] ) && is_numeric( $value );
            $text .= "F;P" . ( $integer ? 2 : 3 ) . ";" . ( $integer ? "FF0R" : "FG0L" )
                   . ( $column == 0 ? ";Y" . $this->row : "" ) . ";X" . ( $column + 1 ) . "\n";
            $text .= $integer ? "C;N;K" . (int)$value . "\n" : 'C;N;K"' . $this->sylkText( $value ) . "\"\n";
        }

        return $text . "\n";
    }

    function end()
    {
        return $this->options['format'] === 'sylk' ? "E\n" : '';
    }

    /**
     * One CSV line: every cell in double quotes, a double quote in a cell doubled, no separator after the last cell.
     */
    function csvLine( array $cells )
    {
        $separator = strpos( self::SEPARATORS, (string)$this->options['separator'] ) !== false && $this->options['separator'] !== ''
            ? $this->options['separator'] : ';';
        $quoted = array();
        foreach ( $cells as $cell )
        {
            $quoted[] = '"' . str_replace( '"', '""', bccieExportUtils::neutralise( trim( (string)$cell ) ) ) . '"';
        }

        return implode( $separator, $quoted ) . "\n";
    }

    /**
     * The text of a SYLK string cell: neutralised, a double quote doubled, a semicolon doubled.
     */
    protected function sylkText( $value )
    {
        return str_replace( array( '"', ';' ), array( '""', ';;' ), bccieExportUtils::neutralise( trim( (string)$value ) ) );
    }
}

?>
