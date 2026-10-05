<?php
/**
 * File containing the Parser class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * Turns the collected information of one object into rows and writes them as CSV or SYLK. The work of writing is in
 * bccieExporter, which can also write a file row by row; this class keeps the older entry points.
 */
class Parser
{
    var $handlerMap = array();
    var $exportableDatatypes;
    var $contentClassCollectorAttributes;
    var $exportCreationDate = false;
    var $exportModificationDate = false;

    /**
     * Loads the handler of every exportable datatype. (Until 1.1.12 this was a constructor named after the class,
     * which PHP 8 no longer calls: no handler was loaded and every datatype was written by the base handler.)
     */
    function __construct( $objectID = false )
    {
        $ini = eZINI::instance( "export.ini" );
        $this->exportableDatatypes = (array)$ini->variable( "General", "ExportableDatatypes" );

        foreach ( $this->exportableDatatypes as $typename )
        {
            if ( !$ini->hasVariable( $typename, 'HandlerClass' ) )
            {
                continue;
            }
            $classname = $ini->variable( $typename, 'HandlerClass' );
            if ( !class_exists( $classname ) )
            {
                eZDebug::writeWarning( "The export handler class $classname of the datatype $typename does not exist", __METHOD__ );
                continue;
            }
            $this->handlerMap[$typename] = array( "handler" => new $classname, "exportable" => true );
        }
    }

    function getExportableDatatypes()
    {
        return $this->exportableDatatypes;
    }

    function exportAttributeHeader( &$attribute, $seperationChar )
    {
        return $attribute->attribute( 'name' );
    }

    function exportAttribute( &$attribute, $seperationChar )
    {
        $datatypeName = eZContentClassAttribute::dataTypeByID( $attribute->ContentClassAttributeID );

        if ( array_key_exists( $datatypeName, $this->handlerMap ) )
        {
            $handler = $this->handlerMap[$datatypeName]['handler'];
        }
        else
        {
            $handler = new BaseHandler();
        }

        $ret = $attribute ? $handler->exportAttribute( $attribute, $seperationChar ) : false;

        return is_null( $ret ) ? false : $ret;
    }

    /*
     * Returns all collection attributes from the ContentClass of contentObject $objectID
     */
    function getContentClassCollectorAttributes( $objectID )
    {
        $this->contentClassCollectorAttributes = array();
        $formObject = eZContentObject::fetch( $objectID );
        if ( !$formObject )
        {
            return;
        }

        foreach ( $formObject->contentClass()->fetchAttributes() as $contentClassAttribute )
        {
            if ( $contentClassAttribute->attribute( 'is_information_collector' ) )
            {
                $this->contentClassCollectorAttributes[] = $contentClassAttribute->attribute( 'id' );
            }
        }
    }

    /**
     * The header of every column the export has, in the order of $attributes_to_export: "ID" for the collection id,
     * the attribute's name for an attribute, an empty name for "leave empty"; an ignored field has no column.
     */
    function exportCollectionObjectHeaderNew( &$collection, &$attributes_to_export, $seperationChar )
    {
        $resultstring = array();

        foreach ( $attributes_to_export as $attributeid )
        {
            if ( $attributeid === "contentobjectid" )
            {
                $resultstring[] = "ID";
            }
            else if ( (int)$attributeid === -1 )
            {
                $resultstring[] = "";
            }
            else if ( (int)$attributeid !== -2 )
            {
                $contentClassAttribute = eZContentClassAttribute::fetch( (int)$attributeid );
                $resultstring[] = $contentClassAttribute instanceof eZContentClassAttribute
                    ? $this->exportAttributeHeader( $contentClassAttribute, $seperationChar ) : '';
            }
        }

        if ( $this->getCreationDate() === true )
        {
            $resultstring[] = ezpI18n::tr( "design/bccie/export", "Created" );
        }
        if ( $this->getModificationDate() === true )
        {
            $resultstring[] = ezpI18n::tr( "design/bccie/export", "Modified" );
        }

        return $resultstring;
    }

    function exportCollectionObject( &$collection, &$attributes_to_export, $seperationChar )
    {
        $resultstring = array();
        $emptyAttributeExport = eZINI::instance( "cie.ini" )->variable( "CieSettings", "ExportZeroToEmptyString" ) === 'enabled';
        $attributes = null;

        foreach ( $attributes_to_export as $attributeid )
        {
            if ( $attributeid === "contentobjectid" )
            {
                $resultstring[] = $collection->ID;
            }
            else if ( (int)$attributeid === -1 )
            {
                $resultstring[] = "";
            }
            else if ( (int)$attributeid !== -2 )
            {
                if ( $attributes === null )
                {
                    $attributes = $collection->informationCollectionAttributes();
                }
                $exportedAttribute = false;

                foreach ( $attributes as $currentattribute )
                {
                    if ( (int)$attributeid === (int)$currentattribute->ContentClassAttributeID )
                    {
                        $exportedAttribute = $this->exportAttribute( $currentattribute, $seperationChar );
                    }
                }

                if ( ( !$emptyAttributeExport && $exportedAttribute !== false ) || ( $emptyAttributeExport && $exportedAttribute ) )
                {
                    $resultstring[] = (string)$exportedAttribute;
                }
                else
                {
                    $resultstring[] = "";
                }
            }
        }

        if ( $this->getCreationDate() === true )
        {
            $resultstring[] = date( 'c', $collection->attribute( 'created' ) );
        }

        if ( $this->getModificationDate() === true )
        {
            $resultstring[] = date( 'c', $collection->attribute( 'modified' ) );
        }

        return $resultstring;
    }

    function exportCollectionObjectHeader( &$attributes_to_export )
    {
        $resultstring = array();
        foreach ( $attributes_to_export as $attributeid )
        {
            if ( $attributeid === "contentobjectid" )
            {
                $resultstring[] = "ID";
            }
            else if ( (int)$attributeid === -1 )
            {
                $resultstring[] = "";
            }
            else if ( (int)$attributeid !== -2 )
            {
                $attribute = eZContentClassAttribute::fetch( (int)$attributeid );
                $resultstring[] = $attribute ? preg_replace( "(\r\n|\n|\r)", " ", $attribute->name() ) : '';
            }
        }

        return $resultstring;
    }

    /**
     * The export as one string.
     *
     * @param array $collections eZInformationCollection objects
     * @param array $attributes_to_export 'contentobjectid', -1 (empty column), -2 (ignored) or class attribute ids
     * @param string $seperationChar the separator of a CSV file
     * @param string $export_type 'csv' or 'sylk'; anything else is CSV
     * @param int|false $days only collections of the last days
     * @return string the file's text (UTF-8)
     */
    function exportInformationCollection(
        $collections,
        $attributes_to_export,
        $seperationChar,
        $export_type = 'csv',
        $days = false,
        $creation_date = false,
        $modification_date = false
    )
    {
        $this->setCreationDate( $creation_date !== false );
        $this->setModificationDate( $modification_date !== false );

        $exporter = new bccieExporter( array(
            'format' => $export_type === 'sylk' ? 'sylk' : 'csv',
            'separator' => $seperationChar,
            'fields' => $attributes_to_export,
            'creation_date' => $creation_date !== false,
            'modification_date' => $modification_date !== false ), $this );

        $range = $days != false ? mktime( 0, 0, 0, date( "m" ), date( "d" ) - $days, date( "Y" ) ) : false;
        $now = time();
        $collections = is_array( $collections ) ? $collections : array();

        $text = $exporter->begin() . $exporter->headerLine();
        foreach ( $collections as $collection )
        {
            if ( $range !== false && !( $collection->Created < $now && $collection->Created >= $range ) )
            {
                continue;
            }
            $text .= $exporter->dataLine( $this->exportCollectionObject( $collection, $attributes_to_export, $seperationChar ) );
        }

        return $text . $exporter->end();
    }

    /*
     * SYLK EXPORT: $tableau is a list of rows, the first one the header
     */
    function sylk( $tableau )
    {
        if ( !$tableau )
        {
            return false;
        }

        $exporter = new bccieExporter( array( 'format' => 'sylk', 'fields' => array() ), $this );
        $first = array_shift( $tableau );
        $text = $exporter->begin( count( $first ) ) . $exporter->headerLine( $first );
        foreach ( $tableau as $row )
        {
            $text .= $exporter->dataLine( $row );
        }

        return $text . $exporter->end();
    }

    /*
     * CSV EXPORT: $tableau is a list of rows
     */
    function csv( $tableau, $seperator )
    {
        if ( !$tableau )
        {
            return '';
        }

        $exporter = new bccieExporter( array( 'format' => 'csv', 'separator' => $seperator, 'fields' => array() ), $this );
        $text = '';
        foreach ( $tableau as $row )
        {
            $text .= $exporter->csvLine( $row );
        }

        return $text;
    }

    public function setModificationDate( $date )
    {
        $this->exportModificationDate = $date;
    }

    public function setCreationDate( $date )
    {
        $this->exportCreationDate = $date;
    }

    public function getCreationDate()
    {
        return $this->exportCreationDate;
    }

    public function getModificationDate()
    {
        return $this->exportModificationDate;
    }
}

?>
