<?php
/**
 * File containing the bccieTransfer class
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * CIE and the kernel's transfer runs (Exponential 6.0.15 and later, the kernel's doc/eng-us/features/6.0/transfer-runs.md):
 * one engine and one run history for every import and export tool.
 *
 *  - A background export (bccieJob::start()) is handed to expKernelTransferRunner::submit(): the job queue (expqueue,
 *    job type bccie.export) when it can take it, else the kernel's content jobs, else the export command started in
 *    the background as before ("process"). The queue and the content jobs call bccieTransferTask, which runs the
 *    same export command for the job and waits for it.
 *  - Every export writes one row of the run history: a background job (the engine's runner keeps it, or the export
 *    command itself on the engine process), the scheduled exports of the cronjob parts, the console exports and the
 *    downloads of the export page (recorded when they are over).
 *  - The job file stays what the pages of CIE read (bccie/export/<id>/(job)/<job>, bccie/job, bccie/download).
 *
 * On an older kernel (no expKernelTransferRunner) everything stays as it was: useRunner() and historyAvailable() are
 * false and record() does nothing. The methods without a kernel call are pure, so they are tested without one.
 */
class bccieTransfer
{
    /** The tool's id in the transfer registry */
    const TOOL = 'transfer.bccie';

    /** The task class the engines call */
    const TASK = 'bccieTransferTask';

    /** The keys of a job file that belong to the transfer run (kept by every write of the job) */
    const JOB_KEYS = array( 'engine', 'job_ref', 'run_id', 'siteaccess' );

    /**
     * @return bool whether the kernel can run CIE's background exports (expKernelTransferRunner and the task)
     */
    static function useRunner()
    {
        return class_exists( 'expKernelTransferRunner' ) && interface_exists( 'expKernelTransferTask' ) && class_exists( self::TASK );
    }

    /**
     * @return bool whether the kernel keeps a run history
     */
    static function historyAvailable()
    {
        return class_exists( 'expKernelTransferHistory' );
    }

    /**
     * The columns of the history row of a background export (pure).
     *
     * @param array $options the validated options (bccieRunner::normalizeOptions())
     * @param string $jobID
     * @param string $title
     * @param int|null $total the number of collections the export holds
     * @return array
     */
    static function historyInfo( array $options, $jobID, $title = '', $total = null )
    {
        $objectID = isset( $options['object_id'] ) ? (int)$options['object_id'] : 0;
        $info = array(
            'direction' => 'export',
            'kind' => 'export',
            'scope' => 'data',
            'scope_ref' => (string)$objectID,
            'format' => self::format( $options ),
            'tool_ref' => (string)$jobID,
            'detail_url' => self::detailURL( $objectID, $jobID ),
            'detail' => self::detail( $options ),
        );
        if ( $title !== '' )
        {
            $info['title'] = (string)$title;
        }
        if ( $total !== null )
        {
            $info['rows_total'] = (int)$total;
        }

        return $info;
    }

    /**
     * @return string csv or sylk
     */
    static function format( array $options )
    {
        return isset( $options['format'] ) && $options['format'] === 'sylk' ? 'sylk' : 'csv';
    }

    /**
     * What the history row keeps of the options besides the format: the character set and the separator.
     *
     * @return array
     */
    static function detail( array $options )
    {
        $detail = array( 'charset' => isset( $options['charset'] ) && $options['charset'] !== '' ? (string)$options['charset'] : 'default' );
        if ( self::format( $options ) === 'csv' && isset( $options['separator'] ) )
        {
            $detail['separator'] = (string)$options['separator'];
        }

        return $detail;
    }

    /**
     * The page of a run: the export page of the form with the job's progress and download (bccie/job is the JSON
     * that page polls), or the export page alone for a run without a job.
     *
     * @return string
     */
    static function detailURL( $objectID, $jobID = '' )
    {
        if ( (int)$objectID <= 0 )
        {
            return 'bccie/overview';
        }

        return 'bccie/export/' . (int)$objectID . ( (string)$jobID !== '' ? '/(job)/' . $jobID : '' );
    }

    /**
     * The title of a run (pure): "<form> as CSV".
     *
     * @return string
     */
    static function title( $objectName, array $options )
    {
        $format = strtoupper( self::format( $options ) );
        $objectName = trim( (string)$objectName );

        return $objectName !== '' ? $objectName . ' (' . $format . ')' : $format;
    }

    /**
     * The progress a job file reports (pure).
     *
     * @param array|false $state the job file
     * @return array|null array( done, total ) or null when it says nothing yet
     */
    static function progressOf( $state )
    {
        if ( !is_array( $state ) || !isset( $state['result'] ) || !is_array( $state['result'] ) || !isset( $state['result']['done'] ) )
        {
            return null;
        }

        return array( (int)$state['result']['done'], isset( $state['result']['total'] ) ? (int)$state['result']['total'] : null );
    }

    /**
     * The task result of a finished job file (pure): what the history row gets.
     *
     * @param array $state the job file of a job in state done
     * @param int|null $bytes the size of its file
     * @return array rows_done, rows_total, format, file_ref, bytes
     */
    static function resultOf( array $state, $bytes = null )
    {
        $result = isset( $state['result'] ) && is_array( $state['result'] ) ? $state['result'] : array();
        $rows = isset( $result['rows'] ) ? (int)$result['rows'] : ( isset( $result['done'] ) ? (int)$result['done'] : 0 );
        $out = array( 'rows_done' => $rows, 'rows_total' => isset( $result['total'] ) ? (int)$result['total'] : $rows );
        if ( isset( $state['options'] ) && is_array( $state['options'] ) )
        {
            $out['format'] = self::format( $state['options'] );
        }
        if ( !empty( $result['file'] ) && preg_match( '/^[0-9]{14}-[a-f0-9]{8}\.(csv|slk)$/D', (string)$result['file'] ) )
        {
            $out['file_ref'] = (string)$result['file'];
        }
        if ( $bytes !== null )
        {
            $out['bytes'] = (int)$bytes;
        }

        return $out;
    }

    /**
     * Why a job file does not hold a finished export (pure), '' when it does.
     *
     * @param array|false $state
     * @param int|null $exitCode the exit code of the export command, when it ran
     * @return string
     */
    static function failureOf( $state, $exitCode = null )
    {
        if ( is_array( $state ) && isset( $state['status'] ) && $state['status'] === 'done' )
        {
            return '';
        }
        if ( is_array( $state ) && !empty( $state['result']['error'] ) )
        {
            return (string)$state['result']['error'];
        }
        if ( $exitCode !== null && (int)$exitCode !== 0 )
        {
            return 'The export stopped with exit code ' . (int)$exitCode . '.';
        }

        return 'The export stopped without a result.';
    }

    /**
     * Whether the export command writes the run's history itself (pure): only when no engine of the kernel runs the
     * job (engine process, or a job written before the engines), since the kernel's runner keeps the row otherwise.
     *
     * @param array|false $state
     * @return bool
     */
    static function commandKeepsHistory( $state )
    {
        $engine = is_array( $state ) && isset( $state['engine'] ) ? (string)$state['engine'] : '';

        return $engine === '' || $engine === 'process';
    }

    /**
     * The command line of the export command for a job (pure), as bccieJob::start() starts it in the background
     * and bccieTransferTask runs it in the foreground.
     *
     * @param string $php the PHP command line binary
     * @param string $jobID
     * @param string $siteaccess
     * @param bool $root whether the process runs as root
     * @return string[]
     */
    static function commandLine( $php, $jobID, $siteaccess = '', $root = false )
    {
        $line = array( (string)$php, 'extension/bccie/bin/php/export.php', '--job=' . $jobID, '-q' );
        if ( $siteaccess !== '' && preg_match( '/^[A-Za-z0-9_.\-]+$/D', (string)$siteaccess ) )
        {
            $line[] = '--siteaccess=' . $siteaccess;
        }
        if ( $root )
        {
            $line[] = '--allow-root-user';
        }

        return $line;
    }

    /**
     * The job file's state from what a later write must keep of it (pure): the transfer keys.
     *
     * @param array|false $state
     * @return array
     */
    static function jobKeys( $state )
    {
        return is_array( $state ) ? array_intersect_key( $state, array_flip( self::JOB_KEYS ) ) : array();
    }

    /**
     * The job state the page shows for a job queued in an engine (pure): failed or cancelled when the engine's job
     * ended so before the export command ran, else the state itself.
     *
     * @param array $state the job file
     * @param string $engineState the engine's job state (expKernelTransferRunner::jobState())
     * @return array
     */
    static function queuedState( array $state, $engineState )
    {
        if ( !isset( $state['status'] ) || $state['status'] !== 'queued' )
        {
            return $state;
        }
        if ( $engineState === 'cancelled' )
        {
            $state['status'] = 'cancelled';
            $state['result'] = array( 'error' => 'The export was cancelled before it started.' );
        }
        else if ( $engineState === 'failed' )
        {
            $state['status'] = 'failed';
            $state['result'] = array( 'error' => 'The export could not be run.' );
        }

        return $state;
    }

    /**
     * Hands a background export to the kernel's runner.
     *
     * @param string $jobID
     * @param array $options
     * @param int|null $total
     * @return array|null engine, job_ref, run_id, error; null when the runner refused (the old way runs it)
     */
    static function submit( $jobID, array $options, $total = null )
    {
        if ( !self::useRunner() )
        {
            return null;
        }
        $object = !empty( $options['object_id'] ) ? eZContentObject::fetch( (int)$options['object_id'] ) : null;
        $title = self::title( $object ? $object->attribute( 'name' ) : '', $options );
        try
        {
            return expKernelTransferRunner::submit( self::TOOL, self::TASK, array( 'job' => (string)$jobID ), array(
                'title' => $title,
                'history' => self::historyInfo( $options, $jobID, $title, $total ),
                'queue' => array( 'idempotency_key' => 'bccie.export:' . $jobID ),
            ) );
        }
        catch ( \Throwable $e )
        {
            eZDebug::writeWarning( 'The transfer runner refused the export ' . $jobID . ': ' . $e->getMessage(), __METHOD__ );
            return null;
        }
    }

    /**
     * Records a run that is over (cron, console, a download of the export page) in the run history; nothing on an
     * older kernel.
     *
     * @param array $options the validated options
     * @param array $info rows_done, state, error, file_ref, engine, started, title, detail ...
     * @return int the row's id, 0 when none was written
     */
    static function record( array $options, array $info )
    {
        if ( !self::historyAvailable() )
        {
            return 0;
        }
        $objectID = isset( $options['object_id'] ) ? (int)$options['object_id'] : 0;
        $base = array( 'direction' => 'export', 'kind' => 'export', 'scope' => 'data', 'scope_ref' => (string)$objectID,
                       'format' => self::format( $options ), 'detail_url' => self::detailURL( $objectID ), 'engine' => 'process' );
        $detail = self::detail( $options ) + ( isset( $info['detail'] ) && is_array( $info['detail'] ) ? $info['detail'] : array() );
        $info['detail'] = $detail;
        try
        {
            return (int)expKernelTransferHistory::record( self::TOOL, $info + $base );
        }
        catch ( \Throwable $e )
        {
            eZDebug::writeWarning( 'The run history could not be written: ' . $e->getMessage(), __METHOD__ );
            return 0;
        }
    }

    /**
     * A call of expKernelTransferHistory that never breaks an export (the history is a summary).
     *
     * @param string $method start, update, progress or finish
     * @param array $arguments
     * @return mixed
     */
    static function history( $method, array $arguments )
    {
        if ( !self::historyAvailable() || !in_array( $method, array( 'start', 'update', 'progress', 'finish' ), true ) )
        {
            return false;
        }
        try
        {
            return call_user_func_array( array( 'expKernelTransferHistory', $method ), $arguments );
        }
        catch ( \Throwable $e )
        {
            eZDebug::writeWarning( 'The run history could not be written: ' . $e->getMessage(), __METHOD__ );
            return false;
        }
    }
}

?>
