<?php
/**
 * File containing the bccieTransferTask class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * One background export of CIE as a transfer task (bccieTransfer): what the job queue (job type bccie.export) and the
 * kernel's content jobs call for a job bccieJob::start() wrote. Parameters: array( 'job' => <job id> ).
 *
 * It runs the export command of the job (extension/bccie/bin/php/export.php --job=<id>, the command the engine
 * "process" starts in the background) as a child process and waits for it: the export keeps its own script
 * environment and memory out of the long-lived worker, and the job file, its log and its export file are written
 * exactly as before, so the pages of CIE show the job as they always did. Every second it passes the progress of the
 * job file on to the engine and asks the engine whether the run was cancelled; a cancel stops the command and marks
 * the job cancelled. A job that is done already (a worker that died after it) is not exported again.
 *
 * Declared only when the kernel has the transfer runs (6.0.15 and later): without them this file defines nothing.
 */
if ( interface_exists( 'expKernelTransferTask' ) )
{
    class bccieTransferTask implements expKernelTransferTask
    {
        /** @var float seconds between two looks at the job (a test makes it shorter) */
        protected $pollSeconds = 1.0;

        /** @var int seconds a stopped command gets to end before it is killed */
        protected $stopSeconds = 5;

        public function run( array $params, expKernelTransferContext $context )
        {
            $jobs = $this->jobClass();
            $jobID = isset( $params['job'] ) ? (string)$params['job'] : '';
            $state = $jobs::isID( $jobID ) ? $jobs::read( $jobID ) : false;
            if ( !$state )
            {
                throw new RuntimeException( 'The export job ' . $jobID . ' does not exist.' );
            }
            if ( $state['status'] === 'done' && $jobs::exportFile( $jobID, $state ) )
            {
                return $this->result( $jobID, $state );
            }

            // queued (or a run a killed worker left): the command starts it over
            // the engine goes into the job file before the command reads it: the command then leaves the run's
            // history to the engine
            $state['status'] = 'starting';
            $state['result'] = null;
            $state['engine'] = $context->engine();
            $state['job_ref'] = $context->jobRef();
            $state['run_id'] = $context->runID();
            $jobs::write( $jobID, $state );
            $context->log( 'Export job ' . $jobID );

            $log = $jobs::directory() . '/' . $jobID . '.log';
            $pipes = array();
            $process = @proc_open( $this->command( $jobID, $state ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $log, 'a' ), 2 => array( 'file', $log, 'a' ) ),
                                   $pipes, $this->workingDirectory(), $this->environment() );
            if ( !is_resource( $process ) )
            {
                $this->end( $jobID, 'failed', 'The export command could not be started.' );
                throw new RuntimeException( 'The export command could not be started.' );
            }
            fclose( $pipes[0] );

            $last = null;
            $exitCode = null;
            while ( true )
            {
                $status = proc_get_status( $process );
                $progress = bccieTransfer::progressOf( $jobs::read( $jobID ) );
                if ( $progress !== null && $progress !== $last )
                {
                    $context->progress( $progress[0], $progress[1] );
                    $last = $progress;
                }
                else
                {
                    $context->heartbeat();
                }
                if ( !$status['running'] )
                {
                    $exitCode = (int)$status['exitcode'];
                    break;
                }
                if ( $context->isCancelled() )
                {
                    $this->stop( $process );
                    $this->end( $jobID, 'cancelled', 'The export was cancelled.' );
                    $this->removePartialFile( $jobID );
                    throw new expKernelTransferCancelledException( 'The export was cancelled.' );
                }
                usleep( (int)( $this->pollSeconds * 1000000 ) );
            }
            proc_close( $process );

            $state = $jobs::read( $jobID );
            $failure = bccieTransfer::failureOf( $state, $exitCode );
            if ( $failure !== '' )
            {
                if ( !is_array( $state ) || in_array( $state['status'], array( 'starting', 'running' ), true ) )
                {
                    $this->end( $jobID, 'failed', $failure );
                }
                throw new RuntimeException( $failure );
            }

            return $this->result( $jobID, $state );
        }

        /**
         * @return string the class of the job files (a test answers its own)
         */
        protected function jobClass()
        {
            return 'bccieJob';
        }

        /**
         * @return string[] the command that runs the job
         */
        protected function command( $jobID, array $state )
        {
            $php = class_exists( 'expProcessTools' ) ? expProcessTools::phpCli() : false;
            if ( !$php )
            {
                $php = PHP_SAPI === 'cli' && PHP_BINARY !== '' ? PHP_BINARY : 'php';
            }

            return bccieTransfer::commandLine( $php, $jobID, isset( $state['siteaccess'] ) ? (string)$state['siteaccess'] : '',
                                               function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 );
        }

        /**
         * @return string
         */
        protected function workingDirectory()
        {
            return class_exists( 'eZSys' ) ? eZSys::rootDir() : getcwd();
        }

        /**
         * @return array
         */
        protected function environment()
        {
            $env = getenv();
            $env = is_array( $env ) ? $env : array();
            if ( empty( $env['PATH'] ) )
            {
                $env['PATH'] = '/usr/local/bin:/usr/bin:/bin';
            }

            return $env;
        }

        /**
         * The result of a finished job.
         */
        protected function result( $jobID, array $state )
        {
            $jobs = $this->jobClass();
            $path = $jobs::exportFile( $jobID, $state );

            return bccieTransfer::resultOf( $state, $path ? filesize( $path ) : null );
        }

        /**
         * Ends the job file in a state of its own (the command did not, or was stopped).
         */
        protected function end( $jobID, $status, $error )
        {
            $jobs = $this->jobClass();
            $state = $jobs::read( $jobID );
            if ( !is_array( $state ) )
            {
                return;
            }
            $state['status'] = $status;
            $state['finished'] = time();
            $state['result'] = array( 'error' => $error ) + ( is_array( $state['result'] ) ? $state['result'] : array() );
            $jobs::write( $jobID, $state );
            $jobs::log( $jobID, ( $status === 'cancelled' ? '' : 'ERROR: ' ) . $error );
        }

        /**
         * Stops the export command: TERM, then KILL when it does not end in time.
         *
         * @param resource $process
         */
        protected function stop( $process )
        {
            @proc_terminate( $process, 15 );
            $until = microtime( true ) + $this->stopSeconds;
            while ( microtime( true ) < $until )
            {
                $status = proc_get_status( $process );
                if ( !$status['running'] )
                {
                    break;
                }
                usleep( 100000 );
            }
            $status = proc_get_status( $process );
            if ( $status['running'] )
            {
                @proc_terminate( $process, 9 );
            }
            proc_close( $process );
        }

        /**
         * A cancelled export leaves no half written file behind.
         */
        protected function removePartialFile( $jobID )
        {
            $jobs = $this->jobClass();
            foreach ( array( '.csv', '.slk' ) as $extension )
            {
                $path = $jobs::directory() . '/' . $jobID . $extension;
                if ( is_file( $path ) )
                {
                    @unlink( $path );
                }
            }
        }
    }
}

?>
