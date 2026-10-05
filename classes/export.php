<?php
/**
 * File containing the Export functions file.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/*
  The functions of earlier versions, kept for code that still calls them. They write through bccieRunner;
  the cronjob parts and the console command use the runner directly.
*/

/**
 * Export the collections of a list of objects to files.
 *
 * @param array $collections object ids
 */
function exportCollections( $collections, $dir = 'var/export', $format = 'csv', $separator = ',', $days = false, $remove = false, $debug = false )
{
    foreach ( (array)$collections as $collection_id )
    {
        if ( is_numeric( $collection_id ) )
        {
            exportCollection( $collection_id, $dir, $format, $separator, $days, $debug );
        }
    }
}

/**
 * Export the collections of one object to a file in $dir.
 *
 * @return string|false the path of the file
 */
function exportCollection( $objectID = false, $dir = 'var/export', $format = 'csv', $separator = ',', $days = false, $debug = false )
{
    $object = is_numeric( $objectID ) ? eZContentObject::fetch( (int)$objectID ) : false;
    if ( !$object )
    {
        print_r( "Encountered Non-Object, Unknown Error\n" );
        return false;
    }

    $input = bccieRunner::defaultOptions( $object );
    $input['format'] = $format;
    $input['separator'] = $separator;
    $excluded = array_map( 'intval', (array)eZINI::instance( 'cie.ini' )->variable( 'CieSettings', 'ExcludeAttributeID' ) );
    $input['fields'] = array_values( array_filter( $input['fields'], function ( $f ) use ( $excluded ) { return $f === 'contentobjectid' || !in_array( $f, $excluded, true ); } ) );
    if ( $days )
    {
        $input['from'] = mktime( 0, 0, 0, date( 'm' ), date( 'd' ) - $days, date( 'Y' ) );
        $input['to'] = time();
    }
    $options = bccieRunner::normalizeOptions( $input, $object, $errors );
    if ( !$options )
    {
        print_r( implode( ' ', $errors ) . "\n" );
        return false;
    }

    if ( !is_dir( $dir ) )
    {
        eZDir::mkdir( $dir, false, true );
    }
    $name = bccieExportUtils::safeFileName( $object->attribute( 'name' ), 'object' );
    $pattern = $days ? '_' . date( 'Y-m-d', $options['from'] ) . '_to_' . date( 'Y-m-d' ) : '_export_' . date( 'Y-m-d_H-i' );
    $path = rtrim( $dir, '/' ) . '/' . $name . $pattern . ( $format === 'sylk' ? '.slk' : '.csv' );
    if ( bccieRunner::exportToFile( $options, $path ) === false )
    {
        print_r( "The file $path cannot be written.\n" );
        return false;
    }
    print_r( "Object Collection Data Export File Path: $path\n" );

    return $path;
}

?>
