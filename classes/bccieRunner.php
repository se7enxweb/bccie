<?php
/**
 * File containing the bccieRunner class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * The work of the exports as methods, so that the admin, the console commands and the cronjob parts do the same
 * thing: validate the options, write an export to a stream or a file row by row, take the lock of a run, remember
 * the last runs, remove collected information.
 */
class bccieRunner
{
    const LAST_EXPORT = 'bccie_last_export';
    const EXPORT_LOG = 'bccie_export_log';
    const LOG_KEEP = 20;
    const BATCH = 200;

    /**
     * @return array name => separator character of the separators a CSV file can have
     */
    static function separators()
    {
        return array( 'semicolon' => ';', 'comma' => ',', 'colon' => ':', 'pipe' => '|', 'hash' => '#' );
    }

    /**
     * @return array 'csv' and 'sylk'
     */
    static function formats()
    {
        return array( 'csv', 'sylk' );
    }

    /**
     * How many collections the admin writes in the request of the page; above it the export runs in the background.
     */
    static function directLimit()
    {
        $ini = eZINI::instance( 'cie.ini' );
        return $ini->hasVariable( 'CieSettings', 'DirectExportLimit' ) ? max( 1, (int)$ini->variable( 'CieSettings', 'DirectExportLimit' ) ) : 2000;
    }

    /**
     * Check the options of an export, from a form or from the command line, and complete them.
     *
     * @param array $input 'format', 'separator' (a character or a name of separators()), 'charset' (a key of the
     *        output format handlers), 'fields' (list of 'contentobjectid', -1, -2 or class attribute ids),
     *        'creation_date', 'modification_date', 'start_*' and 'end_*' (see bccieExportUtils::dateConditions()),
     *        or 'from', 'to' (timestamps), 'days'
     * @param eZContentObject $object
     * @param array $errors receives field => message
     * @return array|false the options: object_id, format, separator, charset, fields, creation_date,
     *         modification_date, from, to, days; false when something is not valid
     */
    static function normalizeOptions( array $input, $object, &$errors )
    {
        $errors = array();
        if ( !$object instanceof eZContentObject )
        {
            $errors['object'] = ezpI18n::tr( 'extension/bccie', 'The object does not exist.' );
            return false;
        }
        $objectID = (int)$object->attribute( 'id' );

        $format = isset( $input['format'] ) ? (string)$input['format'] : 'csv';
        if ( !in_array( $format, self::formats(), true ) )
        {
            $errors['format'] = ezpI18n::tr( 'extension/bccie', 'The export type must be CSV or SYLK.' );
            $format = 'csv';
        }

        $separator = isset( $input['separator'] ) ? (string)$input['separator'] : ';';
        $names = self::separators();
        if ( isset( $names[$separator] ) )
        {
            $separator = $names[$separator];
        }
        if ( !in_array( $separator, $names, true ) )
        {
            $errors['separator'] = ezpI18n::tr( 'extension/bccie', 'The separator must be one of ; , : | #.' );
            $separator = ';';
        }

        $charset = isset( $input['charset'] ) && $input['charset'] !== '' ? (string)$input['charset'] : '';
        if ( $charset !== '' && !in_array( $charset, bccieExportFormatOutputHandler::available(), true ) )
        {
            $errors['charset'] = ezpI18n::tr( 'extension/bccie', 'The character set "%charset" is not offered.', null, array( '%charset' => $charset ) );
            $charset = '';
        }

        $attributes = bccieExportUtils::collectorAttributes( $object );
        $allowed = array();
        foreach ( $attributes as $attribute )
        {
            $allowed[$attribute['id']] = true;
        }
        if ( !$attributes )
        {
            $errors['fields'] = ezpI18n::tr( 'extension/bccie', 'The class of this object has no attribute that collects information.' );
        }

        $fields = array();
        $given = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
        foreach ( $given as $field )
        {
            $field = is_string( $field ) ? trim( $field ) : $field;
            if ( $field === 'contentobjectid' )
            {
                $fields[] = 'contentobjectid';
            }
            else if ( is_numeric( $field ) && ( (int)$field === -1 || (int)$field === -2 ) )
            {
                $fields[] = (int)$field;
            }
            else if ( is_numeric( $field ) && isset( $allowed[(int)$field] ) )
            {
                $fields[] = (int)$field;
            }
            else
            {
                $errors['fields'] = ezpI18n::tr( 'extension/bccie', 'The field "%field" is not an attribute of this form.', null, array( '%field' => (string)$field ) );
            }
        }
        $real = array_filter( $fields, function ( $f ) { return $f !== -2; } );
        if ( !$real && !isset( $errors['fields'] ) )
        {
            $errors['fields'] = ezpI18n::tr( 'extension/bccie', 'Choose at least one field to export.' );
        }

        $from = isset( $input['from'] ) && $input['from'] ? (int)$input['from'] : false;
        $to = isset( $input['to'] ) && $input['to'] ? (int)$input['to'] : false;
        $days = isset( $input['days'] ) && $input['days'] ? (int)$input['days'] : false;
        if ( $from === false && $to === false )
        {
            $dates = bccieExportUtils::dateConditions( $input );
            if ( $dates['error'] !== '' )
            {
                $errors['dates'] = $dates['error'];
            }
            $from = $dates['from'];
            $to = $dates['to'];
            $days = $dates['days'] !== false ? $dates['days'] : $days;
        }

        if ( $errors )
        {
            return false;
        }

        return array( 'object_id' => $objectID, 'format' => $format, 'separator' => $separator, 'charset' => $charset,
                      'fields' => $fields, 'creation_date' => !empty( $input['creation_date'] ),
                      'modification_date' => !empty( $input['modification_date'] ),
                      'from' => $from, 'to' => $to, 'days' => $days );
    }

    /**
     * @return array the options for every attribute that collects information, the collection id first
     */
    static function defaultOptions( $object )
    {
        $fields = array( 'contentobjectid' );
        foreach ( bccieExportUtils::collectorAttributes( $object ) as $attribute )
        {
            $fields[] = $attribute['id'];
        }
        return array( 'format' => 'csv', 'separator' => ';', 'charset' => '', 'fields' => $fields );
    }

    /**
     * @return array the conditions of eZPersistentObject for the collections an export contains
     */
    static function conditions( array $options )
    {
        $conditions = array( 'contentobject_id' => (int)$options['object_id'] );
        $from = !empty( $options['from'] ) ? (int)$options['from'] : false;
        $to = !empty( $options['to'] ) ? (int)$options['to'] : false;
        if ( $from !== false && $to !== false )
        {
            $conditions['created'] = array( false, array( $from, $to ) );
        }
        else if ( $from !== false )
        {
            $conditions['created'] = array( '>=', $from );
        }
        else if ( $to !== false )
        {
            $conditions['created'] = array( '<=', $to );
        }

        return $conditions;
    }

    /**
     * @return int the number of collections an export with these options contains
     */
    static function countCollections( array $options )
    {
        return (int)eZPersistentObject::count( eZInformationCollection::definition(), self::conditions( $options ) );
    }

    /**
     * Write an export to a stream: the byte order mark, the header and every collection, converted by the output
     * format handler of the options. The collections are read 200 at a time.
     *
     * @param resource $handle
     * @param callable|null $progress called with (done, total) after every batch
     * @return int the number of rows written
     */
    static function writeExport( array $options, $handle, $progress = null )
    {
        $handler = bccieExportFormatOutputHandler::instance( $options['charset'] !== '' ? $options['charset'] : null );
        $exporter = new bccieExporter( array( 'format' => $options['format'], 'separator' => $options['separator'],
                                              'fields' => $options['fields'], 'creation_date' => $options['creation_date'],
                                              'modification_date' => $options['modification_date'] ), new Parser() );
        $conditions = self::conditions( $options );
        $total = (int)eZPersistentObject::count( eZInformationCollection::definition(), $conditions );

        fwrite( $handle, $handler->prefix() );
        fwrite( $handle, $handler->formatChunk( $exporter->begin() . $exporter->headerLine() ) );

        $done = 0;
        $offset = 0;
        while ( true )
        {
            $batch = eZPersistentObject::fetchObjectList( eZInformationCollection::definition(), null, $conditions,
                                                          array( 'id' => 'asc' ), array( 'offset' => $offset, 'limit' => self::BATCH ) );
            if ( !$batch )
            {
                break;
            }
            $text = '';
            foreach ( $batch as $collection )
            {
                $text .= $exporter->dataLine( $exporter->dataCells( $collection ) );
                ++$done;
            }
            fwrite( $handle, $handler->formatChunk( $text ) );
            $offset += self::BATCH;
            if ( $progress )
            {
                call_user_func( $progress, $done, $total );
            }
            if ( count( $batch ) < self::BATCH )
            {
                break;
            }
        }
        fwrite( $handle, $handler->formatChunk( $exporter->end() ) );

        return $done;
    }

    /**
     * The export as one string (what a download sends).
     */
    static function exportToString( array $options )
    {
        $handle = fopen( 'php://temp', 'w+' );
        self::writeExport( $options, $handle );
        rewind( $handle );
        $text = stream_get_contents( $handle );
        fclose( $handle );

        return $text;
    }

    /**
     * Write an export to a file.
     *
     * @return int|false the number of rows, false when the file cannot be written
     */
    static function exportToFile( array $options, $path, $progress = null )
    {
        $handle = @fopen( $path, 'wb' );
        if ( !$handle )
        {
            return false;
        }
        $rows = self::writeExport( $options, $handle, $progress );
        fclose( $handle );

        return $rows;
    }

    /**
     * Take the lock of a run: one export at a time, whoever starts it (cron, console, admin).
     *
     * @return resource|false the lock (keep it until the run is over), false when another run holds it
     */
    static function lock( $name = 'export' )
    {
        $dir = eZSys::cacheDirectory() . '/bccie';
        if ( !is_dir( $dir ) )
        {
            eZDir::mkdir( $dir, false, true );
        }
        $handle = @fopen( $dir . '/' . preg_replace( '/[^a-z_]/', '', $name ) . '.lock', 'c' );
        if ( !$handle || !flock( $handle, LOCK_EX | LOCK_NB ) )
        {
            return false;
        }

        return $handle;
    }

    static function unlock( $handle )
    {
        if ( is_resource( $handle ) )
        {
            flock( $handle, LOCK_UN );
            fclose( $handle );
        }
    }

    /**
     * The user the cronjob parts and the commands run as: [CieSettings] CronUser (default 14, the administrator).
     */
    static function loginCronUser()
    {
        $ini = eZINI::instance( 'cie.ini' );
        $userID = $ini->hasVariable( 'CieSettings', 'CronUser' ) ? (int)$ini->variable( 'CieSettings', 'CronUser' ) : 14;
        $user = eZUser::instance( $userID );
        eZUser::setCurrentlyLoggedInUser( $user, $userID );

        return $user;
    }

    protected static function siteData( $name, $default )
    {
        $data = eZSiteData::fetchByName( $name );
        if ( !$data )
        {
            return $default;
        }
        $value = json_decode( (string)$data->attribute( 'value' ), true );

        return is_array( $value ) ? $value : $default;
    }

    protected static function storeSiteData( $name, $value )
    {
        $json = json_encode( $value );
        $data = eZSiteData::fetchByName( $name );
        if ( !$data )
        {
            $data = eZSiteData::create( $name, $json );
        }
        else
        {
            $data->setAttribute( 'value', $json );
        }
        $data->store();
    }

    /**
     * Remember an export for the dashboard: the time, who started it, what was exported and how many rows.
     *
     * @param array $entry 'object_id', 'name', 'format', 'rows', 'source' ('admin', 'console', 'cron', 'job'), 'job', 'file', 'error'
     */
    static function recordExport( array $entry )
    {
        $entry['time'] = time();
        if ( !isset( $entry['by'] ) )
        {
            $entry['by'] = (string)eZUser::currentUser()->attribute( 'login' );
        }
        $log = self::siteData( self::EXPORT_LOG, array() );
        array_unshift( $log, $entry );
        self::storeSiteData( self::EXPORT_LOG, array_slice( $log, 0, self::LOG_KEEP ) );
        self::storeSiteData( self::LAST_EXPORT, $entry );
    }

    /**
     * @return array the last exports, newest first
     */
    static function exportLog( $limit = 5 )
    {
        return array_slice( self::siteData( self::EXPORT_LOG, array() ), 0, $limit );
    }

    /**
     * @return array|false the last export ('time', 'by', ...)
     */
    static function lastExport()
    {
        $last = self::siteData( self::LAST_EXPORT, array() );
        return isset( $last['time'] ) ? $last : false;
    }

    /**
     * The cronjob parts exportcsv and exportsylk: for every object of [CieSettings] Collection[] one file in
     * [CieSettings] Directory, as the settings say (ExportLimitedRange, ExcludeAttributeID, RemoveExported).
     *
     * @param string $format 'csv' or 'sylk'
     * @param object $out has output() and error()
     * @param bool $dryRun count what would be written, write and remove nothing
     * @param string $source what the log shows as the starter
     * @return array 'ok', 'locked', 'objects', 'files', 'rows', 'removed', 'errors'
     */
    static function runCron( $format, $out, $dryRun = false, $source = 'cron' )
    {
        $totals = array( 'ok' => true, 'locked' => false, 'objects' => 0, 'files' => 0, 'rows' => 0, 'removed' => 0, 'errors' => array() );
        $lock = $dryRun ? true : self::lock( 'export' );
        if ( !$lock )
        {
            $totals['ok'] = false;
            $totals['locked'] = true;
            return $totals;
        }

        try
        {
            $ini = eZINI::instance( 'cie.ini' );
            $format = $format === 'sylk' ? 'sylk' : 'csv';
            $ids = array_filter( (array)$ini->variable( 'CieSettings', 'Collection' ), 'is_numeric' );
            $dir = rtrim( (string)$ini->variable( 'CieSettings', 'Directory' ), '/' );
            $separator = (string)$ini->variable( 'CieSettings', $format === 'sylk' ? 'SylkSeparator' : 'CsvSeparator' );
            $exclude = array_map( 'intval', (array)$ini->variable( 'CieSettings', 'ExcludeAttributeID' ) );
            $limited = $ini->variable( 'CieSettings', 'ExportLimitedRange' ) == 'enabled';
            $days = $limited ? (int)$ini->variable( 'CieSettings', 'DateRangeToExport' ) : false;
            $remove = $ini->variable( 'CieSettings', 'RemoveExported' ) == 'enabled';

            if ( !$ids )
            {
                $out->output( 'No object is set in [CieSettings] Collection[]; nothing to export.' );
            }
            if ( $ids && !$dryRun && ( ( !is_dir( $dir ) && !eZDir::mkdir( $dir, false, true ) ) || !is_writable( $dir ) ) )
            {
                $totals['ok'] = false;
                $totals['errors'][] = 'The folder ' . $dir . ' is not writable.';
                $out->error( end( $totals['errors'] ) );
                $ids = array();
            }

            foreach ( $ids as $objectID )
            {
                $object = eZContentObject::fetch( (int)$objectID );
                if ( !$object )
                {
                    $totals['errors'][] = 'Object ' . (int)$objectID . ' does not exist.';
                    $out->error( end( $totals['errors'] ) );
                    continue;
                }
                $input = self::defaultOptions( $object );
                $input['format'] = $format;
                $input['separator'] = $separator;
                $input['fields'] = array_values( array_filter( $input['fields'], function ( $f ) use ( $exclude ) { return $f === 'contentobjectid' || !in_array( $f, $exclude, true ); } ) );
                if ( $days )
                {
                    $input['from'] = mktime( 0, 0, 0, date( 'm' ), date( 'd' ) - $days, date( 'Y' ) );
                    $input['to'] = time();
                }
                $options = self::normalizeOptions( $input, $object, $errors );
                if ( !$options )
                {
                    $totals['errors'][] = $object->attribute( 'name' ) . ': ' . implode( ' ', $errors );
                    $out->error( end( $totals['errors'] ) );
                    continue;
                }
                ++$totals['objects'];
                $count = self::countCollections( $options );
                $name = bccieExportUtils::safeFileName( $object->attribute( 'name' ), 'object' );
                $pattern = $days ? '_' . date( 'Y-m-d', $options['from'] ) . '_to_' . date( 'Y-m-d' ) : '_export_' . date( 'Y-m-d_H-i' );
                $file = $dir . '/' . $name . $pattern . ( $format === 'sylk' ? '.slk' : '.csv' );

                if ( $dryRun )
                {
                    $out->output( 'Would write ' . $count . ' collections of "' . $object->attribute( 'name' ) . '" to ' . $file . ( $remove ? ' and remove them' : '' ) . '.' );
                    $totals['rows'] += $count;
                    continue;
                }

                $rows = self::exportToFile( $options, $file );
                if ( $rows === false )
                {
                    $totals['ok'] = false;
                    $totals['errors'][] = 'The file ' . $file . ' cannot be written.';
                    $out->error( end( $totals['errors'] ) );
                    continue;
                }
                ++$totals['files'];
                $totals['rows'] += $rows;
                $out->output( 'Wrote ' . $rows . ' collections of "' . $object->attribute( 'name' ) . '" to ' . $file );
                self::recordExport( array( 'object_id' => (int)$objectID, 'name' => $object->attribute( 'name' ), 'format' => $format,
                                           'rows' => $rows, 'source' => $source, 'by' => $source, 'file' => $file ) );

                if ( $remove && $rows > 0 )
                {
                    // only what the file holds goes: the collections that came in during the export stay
                    $removed = self::removeCollections( $options, $rows );
                    $totals['removed'] += $removed;
                    $out->output( 'Removed ' . $removed . ' exported collections.' );
                }
            }
        }
        catch ( \Throwable $e )
        {
            $totals['ok'] = false;
            $totals['errors'][] = $e->getMessage();
            $out->error( $e->getMessage() );
        }
        self::unlock( $lock );

        return $totals;
    }

    /**
     * Remove the first $limit collections that match the options (the oldest ids, which are the first rows of an export).
     *
     * @return int the number removed
     */
    static function removeCollections( array $options, $limit )
    {
        $removed = 0;
        $rows = eZPersistentObject::fetchObjectList( eZInformationCollection::definition(), array( 'id' ), self::conditions( $options ),
                                                     array( 'id' => 'asc' ), array( 'offset' => 0, 'limit' => (int)$limit ), false );
        foreach ( (array)$rows as $row )
        {
            eZInformationCollection::removeCollection( (int)$row['id'] );
            ++$removed;
        }

        return $removed;
    }

    /**
     * Remove the collected information of an object.
     *
     * @param int|false $before only the collections created before this time, false for all
     * @param bool $dryRun count, remove nothing
     * @return int the number of collections removed (or that would be)
     */
    static function purge( $objectID, $before = false, $dryRun = false )
    {
        $objectID = (int)$objectID;
        $options = array( 'object_id' => $objectID, 'from' => false, 'to' => $before ? (int)$before - 1 : false );
        $count = self::countCollections( $options );
        if ( $dryRun || !$count )
        {
            return $count;
        }
        if ( !$before )
        {
            eZInformationCollection::removeContentObject( $objectID );
            return $count;
        }
        $db = eZDB::instance();
        $db->begin();
        $removed = 0;
        foreach ( (array)eZPersistentObject::fetchObjectList( eZInformationCollection::definition(), array( 'id' ), self::conditions( $options ), array( 'id' => 'asc' ), null, false ) as $row )
        {
            eZInformationCollection::removeCollection( (int)$row['id'] );
            ++$removed;
        }
        $db->commit();

        return $removed;
    }
}

?>
