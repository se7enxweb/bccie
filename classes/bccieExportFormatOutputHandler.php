<?php
/**
 * File containing the bccieExportFormatOutputHandler class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

class bccieExportFormatOutputHandler
{
    static protected $handlers = null;
    static protected $handlerKey = null;
    static protected $handlerClassName = null;

    static public $outputCharset = 'utf-8';
    static public $outputContentType = 'text/csv';
    static public $outputFileName = 'bccie_cie_export.csv';

    /**
     * @return bccieExportFormatOutputHandler the handler of the given key, the default handler for no key; an
     *         unknown key gives the default (UTF-8) handler
     */
    public static function instance( $argumentHandlerKey = null )
    {
        $ini = eZINI::instance( 'cie.ini' );
        self::$handlers = (array)$ini->variable( 'CieSettings', 'ExportOutputFormatHandlers' );

        if ( $argumentHandlerKey == null )
        {
            self::$handlerKey = $ini->variable( 'CieSettings', 'ExportOutputFormatHandlerDefault' );
        }
        else
        {
            self::$handlerKey = $argumentHandlerKey;
        }

        if ( !isset( self::$handlers[self::$handlerKey] ) || !class_exists( self::$handlers[self::$handlerKey] ) )
        {
            self::$handlerKey = 'utf8';
        }

        self::$handlerClassName = isset( self::$handlers[self::$handlerKey] ) ? self::$handlers[self::$handlerKey] : 'bccieExportFormatOutputHandlerUtf8';

        if ( class_exists( self::$handlerClassName ) )
        {
            $className = self::$handlerClassName;
            return new $className();
        }

        return new self();
    }

    /**
     * @return array of key => label of the handlers the settings offer
     */
    public static function available()
    {
        $keys = array();
        foreach ( (array)eZINI::instance( 'cie.ini' )->variable( 'CieSettings', 'ExportOutputFormatHandlers' ) as $key => $class )
        {
            if ( $key !== '' && class_exists( $class ) )
            {
                $keys[] = (string)$key;
            }
        }
        return $keys;
    }

    /**
     * The charset the file is written in, named as the Content-Type header names it.
     */
    public function charset()
    {
        return 'utf-8';
    }

    /**
     * The bytes that start a file (a byte order mark), written once.
     */
    public function prefix()
    {
        return '';
    }

    /**
     * Converts a part of the export (always whole rows of UTF-8 text); output() and formatOutput() are
     * prefix() followed by this for the whole text.
     */
    public function formatChunk( $chunk )
    {
        return $chunk;
    }

    public function output( $outputStringInput )
    {
        $this->outputHeaders();

        echo $this->formatOutput( $outputStringInput );
    }

    public function formatOutput( $outputStringInput )
    {
        return $this->prefix() . $this->formatChunk( (string)$outputStringInput );
    }

    public function setOutputFileName( $fileName )
    {
        self::$outputFileName = $fileName;
    }

    public function setContentType( $contentType )
    {
        self::$outputContentType = $contentType;
    }

    public function setOutputCharset( $charset )
    {
        self::$outputCharset = $charset;
    }

    public function outputHeaders()
    {
        $fileName = preg_replace( '/[^A-Za-z0-9._-]/', '_', (string)self::$outputFileName );
        header( "Content-type: " . self::$outputContentType . "; charset=" . $this->charset() );
        header( 'Content-Disposition: attachment; filename="' . $fileName . '"' );
        header( 'Cache-Control: private, no-store' );
        header( 'X-Content-Type-Options: nosniff' );
    }
}

?>
