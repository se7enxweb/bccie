<?php
/**
 * File containing the bccieExportUtils class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

class bccieExportUtils
{
    /**
     * Neutralises a cell against formula injection: a text that starts with = + - @, a tab or a carriage return is
     * run as a formula by a spreadsheet, so it gets a single quote in front, which the spreadsheet does not show.
     * A plain number (also with a sign, for example a phone number +4912345) is left as it is, it cannot be a formula.
     */
    public static function neutralise( $value )
    {
        $value = (string)$value;
        if ( $value === '' )
        {
            return $value;
        }

        if ( strpos( "=+-@\t\r", $value[0] ) !== false && !preg_match( '/^[+-]?[0-9]+([.,][0-9]+)?$/', $value ) )
        {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @return string a file name part with letters, digits, dot, dash and underscore only
     */
    public static function safeFileName( $name, $fallback = 'export' )
    {
        $name = strtolower( trim( (string)$name ) );
        $name = preg_replace( '/[^a-z0-9._-]+/', '_', $name );
        $name = trim( preg_replace( '/\.{2,}/', '.', $name ), '._-' );

        return $name === '' ? $fallback : substr( $name, 0, 80 );
    }

    /**
     * The objects that collected information, one row each: contentobject_id, name, class_identifier,
     * class_name, main_node_id, first_collection, last_collection, collections.
     *
     * @param string $q a part of the object's name
     * @param string $sort 'name', 'collections', 'first_collection', 'last_collection'
     * @param string $order 'asc' or 'desc'
     */
    public static function getObjectsWithCollectedInformation( $offset = 0, $limit = 10, $q = '', $sort = 'name', $order = 'asc' )
    {
        $db = eZDB::instance();
        $sorts = array( 'name' => 'o.name', 'collections' => 'collections',
                        'first_collection' => 'first_collection', 'last_collection' => 'last_collection' );
        $orderBy = ( isset( $sorts[$sort] ) ? $sorts[$sort] : 'o.name' ) . ( $order === 'desc' ? ' DESC' : ' ASC' );

        $rows = $db->arrayQuery(
            'SELECT i.contentobject_id,
                    o.name,
                    o.contentclass_id,
                    t.node_id AS main_node_id,
                    MIN( i.created ) AS first_collection,
                    MAX( i.created ) AS last_collection,
                    COUNT( i.id ) AS collections
            FROM ezinfocollection i,
                 ezcontentobject o,
                 ezcontentobject_tree t
            WHERE ' . self::objectConditions( $db, $q ) . '
            GROUP BY i.contentobject_id, o.name, o.contentclass_id, t.node_id
            ORDER BY ' . $orderBy . ', i.contentobject_id',
            array( 'limit' => (int)$limit, 'offset' => (int)$offset )
        );

        $objects = array();
        foreach ( (array)$rows as $row )
        {
            $class = eZContentClass::fetch( (int)$row['contentclass_id'] );
            $row['class_identifier'] = $class ? $class->attribute( 'identifier' ) : '';
            $row['class_name'] = $class ? $class->attribute( 'name' ) : '';
            $objects[] = $row;
        }

        return $objects;
    }

    protected static function objectConditions( $db, $q )
    {
        $where = 'i.contentobject_id = o.id AND t.contentobject_id = o.id AND t.node_id = t.main_node_id';
        $q = trim( (string)$q );
        if ( $q !== '' )
        {
            $where .= " AND LOWER( o.name ) LIKE '%" . $db->escapeString( mb_strtolower( $q ) ) . "%'";
        }

        return $where;
    }

    /**
     * @return int how many objects collected information (that match $q)
     */
    public static function getCollectorObjectsCount( $q = '' )
    {
        $db = eZDB::instance();
        $rows = $db->arrayQuery(
            'SELECT COUNT( DISTINCT i.contentobject_id ) AS count
            FROM ezinfocollection i,
                 ezcontentobject o,
                 ezcontentobject_tree t
            WHERE ' . self::objectConditions( $db, $q )
        );

        return $rows ? (int)$rows[0]['count'] : 0;
    }

    /**
     * @return array 'collections' (all of them), 'objects' (objects with collected information), 'orphans' (objects
     *               that collected information but have no location any more), 'oldest', 'newest' (timestamps or 0)
     */
    public static function totals()
    {
        $db = eZDB::instance();
        $all = $db->arrayQuery( 'SELECT COUNT( id ) AS c, COUNT( DISTINCT contentobject_id ) AS o, MIN( created ) AS f, MAX( created ) AS l FROM ezinfocollection' );
        $orphans = $db->arrayQuery( 'SELECT COUNT( DISTINCT contentobject_id ) AS c FROM ezinfocollection
                                     WHERE contentobject_id NOT IN ( SELECT contentobject_id FROM ezcontentobject_tree )' );

        return array( 'collections' => $all ? (int)$all[0]['c'] : 0,
                      'objects' => $all ? (int)$all[0]['o'] : 0,
                      'orphans' => $orphans ? (int)$orphans[0]['c'] : 0,
                      'oldest' => $all ? (int)$all[0]['f'] : 0,
                      'newest' => $all ? (int)$all[0]['l'] : 0 );
    }

    /**
     * @return array the class attributes of the object that collect information: array( id, identifier, name, datatype, exportable )
     */
    public static function collectorAttributes( $object )
    {
        $exportable = (array)eZINI::instance( 'export.ini' )->variable( 'General', 'ExportableDatatypes' );
        $attributes = array();
        foreach ( $object->contentClass()->fetchAttributes() as $attribute )
        {
            if ( $attribute->attribute( 'is_information_collector' ) )
            {
                $attributes[] = array( 'id' => (int)$attribute->attribute( 'id' ),
                                       'identifier' => $attribute->attribute( 'identifier' ),
                                       'name' => $attribute->attribute( 'name' ),
                                       'datatype' => $attribute->attribute( 'data_type_string' ),
                                       'exportable' => in_array( $attribute->attribute( 'data_type_string' ), $exportable, true ) );
            }
        }

        return $attributes;
    }

    /**
     * The date range of an export: 'conditions' for eZPersistentObject (null for none), 'days', 'from' and 'to'
     * (timestamps or false) and 'error' (an empty string when the dates are valid). A date is read from the
     * fields start_day, start_month, start_year (end_...), or from start_date, end_date as YYYY-MM-DD.
     */
    public static function getDateConditions( eZHTTPTool $http )
    {
        $request = array();
        foreach ( array( 'start', 'end' ) as $edge )
        {
            foreach ( array( 'day', 'month', 'year', 'date' ) as $part )
            {
                $name = $edge . '_' . $part;
                $request[$name] = $http->hasPostVariable( $name ) ? trim( (string)$http->postVariable( $name ) ) : '';
            }
        }

        return self::dateConditions( $request );
    }

    /**
     * @param array $request start_day, start_month, start_year, start_date, end_... as the form sends them
     */
    public static function dateConditions( array $request )
    {
        $start = false;
        $end = false;
        $days = false;
        $condition = null;
        $error = '';

        $cieINI = eZINI::instance( 'cie.ini' );
        $exportUsingDaysCalcualation = $cieINI->variable( 'CieSettings', 'ExportUsingDaysCalcualation' ) == 'enabled';

        foreach ( array( 'start', 'end' ) as $edge )
        {
            $y = $m = $d = null;
            $date = isset( $request[$edge . '_date'] ) ? $request[$edge . '_date'] : '';
            if ( $date !== '' )
            {
                if ( preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $date, $match ) )
                {
                    list( , $y, $m, $d ) = $match;
                }
                else
                {
                    $error = ezpI18n::tr( 'extension/bccie', 'The date "%date" is not valid. Use the form YYYY-MM-DD.', null, array( '%date' => $date ) );
                    continue;
                }
            }
            else if ( isset( $request[$edge . '_year'] ) && $request[$edge . '_year'] !== '' )
            {
                $y = $request[$edge . '_year'];
                $m = isset( $request[$edge . '_month'] ) && $request[$edge . '_month'] !== '' ? $request[$edge . '_month'] : 1;
                $d = isset( $request[$edge . '_day'] ) && $request[$edge . '_day'] !== '' ? $request[$edge . '_day'] : 1;
                if ( !ctype_digit( (string)$y ) || !ctype_digit( (string)$m ) || !ctype_digit( (string)$d ) )
                {
                    $error = ezpI18n::tr( 'extension/bccie', 'The date "%date" is not valid. Use the form YYYY-MM-DD.', null, array( '%date' => $d . '.' . $m . '.' . $y ) );
                    continue;
                }
            }
            else
            {
                continue;
            }

            if ( !checkdate( (int)$m, (int)$d, (int)$y ) )
            {
                $error = ezpI18n::tr( 'extension/bccie', 'The date "%date" is not valid. Use the form YYYY-MM-DD.', null, array( '%date' => sprintf( '%04d-%02d-%02d', $y, $m, $d ) ) );
                continue;
            }

            if ( $edge === 'start' )
            {
                $start = mktime( 0, 0, 0, (int)$m, (int)$d, (int)$y );
            }
            else
            {
                $end = mktime( 23, 59, 59, (int)$m, (int)$d, (int)$y );
            }
        }

        if ( $error === '' && $start !== false && $end !== false && $start > $end )
        {
            $error = ezpI18n::tr( 'extension/bccie', 'The start date is after the end date.' );
        }

        if ( $exportUsingDaysCalcualation && ( $start !== false and $end !== false ) )
        {
            $days = round( abs( $start - $end ) / 86400 );
        }

        if ( $start !== false and $end !== false )
        {
            $condition = array( false, array( $start, $end ) );
        }
        elseif ( $start !== false and $end === false )
        {
            $condition = array( '>=', $start );
        }
        elseif ( $start === false and $end !== false )
        {
            $condition = array( '<=', $end );
        }

        return array( 'conditions' => $condition,
                      'days'       => $days,
                      'from'       => $start,
                      'to'         => $end,
                      'error'      => $error );
    }

    /**
     * The file name of an export: the setting ExportFileName, the object's name, the time and the extension
     * (letters, digits, dot, dash and underscore only).
     */
    public static function getFileName( $exportFormat, $object )
    {
        $cieINI = eZINI::instance( 'cie.ini' );
        $exportFileName = $cieINI->variable( 'CieSettings', 'ExportFileName' ) === '' ? '' : preg_replace( '/[^A-Za-z0-9._-]/', '_', $cieINI->variable( 'CieSettings', 'ExportFileName' ) );
        $exportFileNameDateFormat = $cieINI->variable( 'CieSettings', 'ExportFileNameDateFormat' );

        $exportContentObjectName = self::safeFileName( $object->attribute( 'name' ), 'object' );
        $exportDateTimeString = preg_replace( '/[^0-9A-Za-z_-]/', '-', date( $exportFileNameDateFormat ) );

        return $exportFileName . $exportContentObjectName . '-on-' . $exportDateTimeString . ( $exportFormat === 'sylk' ? '.slk' : '.csv' );
    }
}

?>
