<?php
/**
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

namespace Exponential\Command\Extension\Bccie
{

/**
 * ext:bccie:export - export the collected information of a form to a CSV or SYLK file, or run the scheduled export
 * (the cronjob parts exportcsv and exportsylk, by hand). A large export should not run inside a page request; the
 * admin starts this command in the background (--job).
 */
class Export extends \Exponential\Runnable\Command
{
    public function run()
    {
        $this->script( array( 'description' => 'Export the collected information of a form to a CSV or SYLK file',
                              'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
        $options = $this->startup( '[object:][format:][separator:][charset:][fields:][from:][to:][days:][output:][creation-date][modification-date][cron][remove-exported][dry-run][job:]', '',
            array( 'object' => 'The ID of the content object (the form) whose collected information is exported',
                   'format' => 'csv (default) or sylk',
                   'separator' => 'semicolon (default), comma, colon, pipe or hash (CSV only)',
                   'charset' => 'utf8, utf8bom, utf16le or cp1252 (the cie.ini default when not given)',
                   'fields' => 'Comma separated: contentobjectid, class attribute ids, -1 (empty column), -2 (ignored); default: the ID and every field',
                   'from' => 'Only collections created on or after this date, YYYY-MM-DD',
                   'to' => 'Only collections created on or before this date, YYYY-MM-DD',
                   'days' => 'Only the collections of the last N days',
                   'output' => 'The folder of the file (default: [CieSettings] Directory)',
                   'creation-date' => 'Add a column with the creation date',
                   'modification-date' => 'Add a column with the modification date',
                   'cron' => 'Run the scheduled export of the settings (Collection[], Directory, ...) instead of one object',
                   'remove-exported' => 'Remove the collected information after it was written to the file',
                   'dry-run' => 'Count what would be written and removed, write and remove nothing',
                   'job' => 'The ID of a background job (set by the admin)' ) );

        $jobID = ( $options['job'] && \bccieJob::isID( $options['job'] ) ) ? $options['job'] : false;
        $out = new \bccieJobOutput( $jobID ? false : $this->cli(), $jobID );
        \bccieRunner::loginCronUser();

        if ( $jobID )
        {
            $state = \bccieJob::status( $jobID );
            $saved = \bccieJob::options( $jobID );
            if ( !$state || !$saved )
            {
                $out->error( 'Unknown job.' );
                $this->shutdown( 1 );
                return 1;
            }
            $this->writeJob( $jobID, $state, 'running', null, $saved );
            return $this->runJob( $jobID, $state, $saved, $out );
        }

        if ( $options['cron'] )
        {
            $totals = \bccieRunner::runCron( $options['format'] ? $options['format'] : 'csv', $out, (bool)$options['dry-run'], 'console' );
            if ( $totals['locked'] )
            {
                $out->error( 'Another export is running.' );
                $this->shutdown( 1 );
                return 1;
            }
            $out->output( ( $options['dry-run'] ? 'Would write ' : 'Written ' ) . $totals['rows'] . ' collections of ' . $totals['objects'] . ' objects to ' . $totals['files'] . ' files.' );
            $code = $totals['ok'] && !$totals['errors'] ? 0 : 1;
            $this->shutdown( $code );
            return $code;
        }

        if ( !$options['object'] || !is_numeric( $options['object'] ) )
        {
            $out->error( 'Give the object with --object=ID, or --cron for the scheduled export.' );
            $this->shutdown( 1 );
            return 1;
        }
        $object = \eZContentObject::fetch( (int)$options['object'] );
        $input = $object ? \bccieRunner::defaultOptions( $object ) : array();
        foreach ( array( 'format', 'separator', 'charset' ) as $key )
        {
            if ( $options[$key] )
            {
                $input[$key] = $options[$key];
            }
        }
        if ( $options['fields'] )
        {
            $input['fields'] = array_map( 'trim', explode( ',', $options['fields'] ) );
        }
        $input['creation_date'] = (bool)$options['creation-date'];
        $input['modification_date'] = (bool)$options['modification-date'];
        $dates = array( 'start_date' => (string)$options['from'], 'end_date' => (string)$options['to'] );
        $input += $dates;
        if ( $options['days'] )
        {
            $input['from'] = mktime( 0, 0, 0, date( 'm' ), date( 'd' ) - (int)$options['days'], date( 'Y' ) );
            $input['to'] = time();
        }
        $normalized = \bccieRunner::normalizeOptions( $input, $object, $errors );
        if ( !$normalized )
        {
            foreach ( $errors as $message )
            {
                $out->error( $message );
            }
            $this->shutdown( 1 );
            return 1;
        }

        $count = \bccieRunner::countCollections( $normalized );
        if ( $options['dry-run'] )
        {
            $out->output( 'Would write ' . $count . ' collections of "' . $object->attribute( 'name' ) . '"' . ( $options['remove-exported'] ? ' and remove them' : '' ) . '.' );
            $this->shutdown( 0 );
            return 0;
        }
        $lock = \bccieRunner::lock( 'export' );
        if ( !$lock )
        {
            $out->error( 'Another export is running.' );
            $this->shutdown( 1 );
            return 1;
        }
        $dir = rtrim( $options['output'] ? $options['output'] : \eZINI::instance( 'cie.ini' )->variable( 'CieSettings', 'Directory' ), '/' );
        if ( ( !is_dir( $dir ) && !\eZDir::mkdir( $dir, false, true ) ) || !is_writable( $dir ) )
        {
            \bccieRunner::unlock( $lock );
            $out->error( 'The folder ' . $dir . ' is not writable.' );
            $this->shutdown( 1 );
            return 1;
        }
        $path = $dir . '/' . \bccieExportUtils::getFileName( $normalized['format'], $object );
        $rows = \bccieRunner::exportToFile( $normalized, $path );
        if ( $rows === false )
        {
            \bccieRunner::unlock( $lock );
            $out->error( 'The file ' . $path . ' cannot be written.' );
            $this->shutdown( 1 );
            return 1;
        }
        $out->output( 'Wrote ' . $rows . ' collections to ' . $path );
        \bccieRunner::recordExport( array( 'object_id' => $normalized['object_id'], 'name' => $object->attribute( 'name' ), 'format' => $normalized['format'],
                                           'rows' => $rows, 'source' => 'console', 'by' => 'console', 'file' => $path ) );
        if ( $options['remove-exported'] && $rows > 0 )
        {
            $out->output( 'Removed ' . \bccieRunner::removeCollections( $normalized, $rows ) . ' exported collections.' );
        }
        \bccieRunner::unlock( $lock );
        $this->shutdown( 0 );
        return 0;
    }

    protected function runJob( $jobID, $state, array $saved, $out )
    {
        $object = \eZContentObject::fetch( (int)$saved['object_id'] );
        $lock = \bccieRunner::lock( 'export' );
        if ( !$lock )
        {
            $out->error( 'Another export is running.' );
            $this->writeJob( $jobID, $state, 'failed', array( 'error' => 'Another export is running.' ), $saved );
            $this->shutdown( 1 );
            return 1;
        }
        $extension = $saved['format'] === 'sylk' ? 'slk' : 'csv';
        $fileName = $jobID . '.' . $extension;
        $path = \bccieJob::directory() . '/' . $fileName;
        $out->output( 'Exporting "' . ( $object ? $object->attribute( 'name' ) : $saved['object_id'] ) . '" as ' . strtoupper( $extension ) . '.' );
        $self = $this;
        $rows = \bccieRunner::exportToFile( $saved, $path, function ( $done, $total ) use ( $out, $jobID, $state, $saved, $self )
        {
            $out->output( 'Rows: ' . $done . ' of ' . $total );
            $self->writeJob( $jobID, $state, 'running', array( 'done' => $done, 'total' => $total ), $saved );
        } );
        \bccieRunner::unlock( $lock );
        if ( $rows === false )
        {
            $out->error( 'The file cannot be written.' );
            $this->writeJob( $jobID, $state, 'failed', array( 'error' => 'The file cannot be written.' ), $saved );
            $this->shutdown( 1 );
            return 1;
        }
        $out->output( 'Done: ' . $rows . ' rows.' );
        \bccieRunner::recordExport( array( 'object_id' => (int)$saved['object_id'], 'name' => $object ? $object->attribute( 'name' ) : '', 'format' => $saved['format'],
                                           'rows' => $rows, 'source' => 'job', 'job' => $jobID, 'by' => isset( $state['by'] ) ? $state['by'] : 'job' ) );
        $this->writeJob( $jobID, $state, 'done', array( 'rows' => $rows, 'done' => $rows, 'total' => $rows, 'file' => $fileName ), $saved );
        \bccieJob::prune();
        $this->shutdown( 0 );
        return 0;
    }

    public function writeJob( $jobID, $state, $status, $result, array $saved )
    {
        \bccieJob::write( $jobID, array( 'id' => $jobID, 'command' => 'export', 'status' => $status,
                                          'started' => $state && isset( $state['started'] ) ? $state['started'] : time(),
                                          'finished' => in_array( $status, array( 'done', 'failed' ), true ) ? time() : null,
                                          'by' => $state && isset( $state['by'] ) ? $state['by'] : 'job', 'options' => $saved, 'result' => $result ) );
    }
}

}
