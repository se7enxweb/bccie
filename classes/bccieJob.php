<?php
/**
 * File containing the bccieJob class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * An export in the background, started from the admin: the same console command the shell uses (ext:bccie:export),
 * started with expProcessTools like the preloader. The command writes its state, its output and the export file to
 * var/<site>/bccie/jobs/<id>.json, <id>.log and <id>.csv (or .slk).
 */
class bccieJob
{
    const KEEP = 20;

    /**
     * @return string the folder of the job files
     */
    static function directory()
    {
        return eZSys::varDirectory() . '/bccie/jobs';
    }

    /**
     * @return bool true when $id looks like a job id of this class
     */
    static function isID( $id )
    {
        return is_string( $id ) && preg_match( '/^[0-9]{14}-[a-f0-9]{8}$/D', $id ) === 1;
    }

    /**
     * Start an export in the background.
     *
     * On Exponential 6.0.15 and later the job goes to the kernel's transfer runner (bccieTransfer): the job queue or
     * the kernel's content jobs run it (bccieTransferTask) when the installation has them, and the job file says
     * which (engine, job_ref, run_id; status "queued" until the export command starts). Otherwise, and on older
     * kernels, the export command is started in the background here, as before.
     *
     * @param array $options the validated options of bccieRunner::normalizeOptions()
     * @param string $error receives the reason when it could not start
     * @param int|null $total the number of collections the export holds (for the run history)
     * @return string|false the job id
     */
    static function start( array $options, &$error, $total = null )
    {
        // every engine runs the export command: it needs the PHP command line and proc_open
        $php = class_exists( 'expProcessTools' ) ? expProcessTools::phpCli() : false;
        if ( !$php || !function_exists( 'proc_open' ) )
        {
            $error = class_exists( 'expProcessTools' ) && expProcessTools::error() !== ''
                ? expProcessTools::error() : 'A background run needs proc_open, setsid and the PHP command line.';
            return false;
        }
        $dir = static::directory();
        if ( !is_dir( $dir ) && !eZDir::mkdir( $dir, false, true ) )
        {
            $error = 'The folder ' . $dir . ' cannot be created.';
            return false;
        }
        $id = date( 'YmdHis' ) . '-' . bin2hex( random_bytes( 4 ) );
        $user = eZUser::currentUser();
        $siteaccess = eZSiteAccess::current();
        $siteaccessName = isset( $siteaccess['name'] ) && preg_match( '/^[A-Za-z0-9_.\-]+$/D', $siteaccess['name'] ) ? $siteaccess['name'] : '';
        $state = array( 'id' => $id, 'command' => 'export', 'status' => 'starting', 'started' => time(),
                        'by' => $user->attribute( 'login' ), 'user_id' => (int)$user->attribute( 'contentobject_id' ),
                        'siteaccess' => $siteaccessName, 'options' => $options, 'result' => null );
        self::write( $id, $state );
        touch( $dir . '/' . $id . '.log' );

        $run = bccieTransfer::submit( $id, $options, $total );
        if ( $run )
        {
            $state['engine'] = $run['engine'];
            $state['job_ref'] = $run['job_ref'];
            $state['run_id'] = (int)$run['run_id'];
            if ( $run['engine'] !== 'process' )
            {
                // the queue or a content job runs it; its task may have taken the job file already
                $now = self::read( $id );
                if ( !is_array( $now ) || empty( $now['engine'] ) )
                {
                    $state['status'] = 'queued';
                    self::write( $id, $state );
                }
                return $id;
            }
            self::write( $id, $state );
        }

        $setsid = class_exists( 'expProcessTools' ) ? expProcessTools::setsid() : false;
        if ( !$setsid )
        {
            $error = class_exists( 'expProcessTools' ) && expProcessTools::error() !== ''
                ? expProcessTools::error() : 'A background run needs proc_open, setsid and the PHP command line.';
            self::fail( $id, $state, $error );
            return false;
        }
        $line = array_merge( array( $setsid, '-f' ), bccieTransfer::commandLine( $php, $id, $siteaccessName, function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 ) );
        $pipes = array();
        $env = getenv();
        $env = is_array( $env ) ? $env : array();
        if ( empty( $env['PATH'] ) )
        {
            $env['PATH'] = '/usr/local/bin:/usr/bin:/bin';
        }
        $process = @proc_open( $line, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, eZSys::rootDir(), $env );
        if ( !is_resource( $process ) )
        {
            $error = 'The command could not be started.';
            self::fail( $id, $state, $error );
            return false;
        }
        foreach ( $pipes as $pipe )
        {
            fclose( $pipe );
        }
        proc_close( $process );

        return $id;
    }

    /**
     * Ends a job that could not be started: its file says failed, and so does its run in the run history.
     */
    protected static function fail( $id, array $state, $error )
    {
        $state['status'] = 'failed';
        $state['finished'] = time();
        $state['result'] = array( 'error' => $error );
        self::write( $id, $state );
        if ( !empty( $state['run_id'] ) && class_exists( 'expKernelTransferRun' ) )
        {
            bccieTransfer::history( 'finish', array( (int)$state['run_id'], expKernelTransferRun::STATE_FAILED, array( 'error' => $error ) ) );
        }
    }

    /**
     * @return array|false the job file of a job as it is stored (with its options), false for an unknown job
     */
    static function read( $id )
    {
        $file = static::directory() . '/' . $id . '.json';
        $state = self::isID( $id ) && is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : false;

        return is_array( $state ) && isset( $state['status'] ) ? $state : false;
    }

    /**
     * Store the state of a job.
     */
    static function write( $id, $state )
    {
        if ( !self::isID( $id ) )
        {
            return false;
        }
        $file = static::directory() . '/' . $id . '.json';
        return file_put_contents( $file . '.tmp', json_encode( $state ) ) !== false && rename( $file . '.tmp', $file );
    }

    /**
     * @return array|false the state of a job with the last log lines ('log') and, when the export file is ready,
     *         'file' (its size); false for an unknown job
     */
    static function status( $id )
    {
        if ( !self::isID( $id ) )
        {
            return false;
        }
        $file = static::directory() . '/' . $id . '.json';
        if ( !is_file( $file ) )
        {
            return false;
        }
        $state = json_decode( (string)file_get_contents( $file ), true );
        if ( !is_array( $state ) )
        {
            return false;
        }
        $log = static::directory() . '/' . $id . '.log';
        $lines = is_file( $log ) ? array_slice( file( $log, FILE_IGNORE_NEW_LINES ), -40 ) : array();
        $state['log'] = $lines;
        // a job that says "starting" or "running" for ten minutes without a sign of life is dead
        if ( in_array( $state['status'], array( 'starting', 'running' ), true ) && is_file( $log ) && filemtime( $log ) < time() - 600 && filemtime( $file ) < time() - 600 )
        {
            $state['status'] = 'failed';
            $state['result'] = array( 'error' => 'The job stopped without a result.' );
        }
        // a job waiting in the job queue or the content jobs: ended there before the export command ran?
        if ( $state['status'] === 'queued' && !empty( $state['engine'] ) && !empty( $state['job_ref'] ) && class_exists( 'expKernelTransferRunner' ) )
        {
            $state = bccieTransfer::queuedState( $state, expKernelTransferRunner::jobState( (string)$state['engine'], (string)$state['job_ref'] ) );
        }
        $export = self::exportFile( $id, $state );
        if ( $state['status'] === 'done' && $export )
        {
            $state['file'] = array( 'name' => basename( $export ), 'size' => filesize( $export ) );
        }
        // the options are the job's own business, the page needs no copy of them (only the format)
        $state['format'] = isset( $state['options'] ) && is_array( $state['options'] ) ? bccieTransfer::format( $state['options'] ) : '';
        unset( $state['options'] );

        return $state;
    }

    /**
     * @return string|false the path of the export file of a finished job
     */
    static function exportFile( $id, $state = null )
    {
        if ( !self::isID( $id ) )
        {
            return false;
        }
        if ( $state === null )
        {
            $file = static::directory() . '/' . $id . '.json';
            $state = is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : false;
        }
        if ( !is_array( $state ) || empty( $state['result']['file'] ) || !preg_match( '/^[0-9]{14}-[a-f0-9]{8}\.(csv|slk)$/D', $state['result']['file'] ) )
        {
            return false;
        }
        $path = static::directory() . '/' . $state['result']['file'];

        return is_file( $path ) ? $path : false;
    }

    /**
     * @return array the saved options of a job, empty for an unknown job
     */
    static function options( $id )
    {
        $file = static::directory() . '/' . $id . '.json';
        $state = self::isID( $id ) && is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : false;

        return is_array( $state ) && isset( $state['options'] ) && is_array( $state['options'] ) ? $state['options'] : array();
    }

    /**
     * Whether the current user may see the state, the log and the file of a job: only the user who started it, and
     * only while that user can still read the object whose collected information it exports. An unknown job, a job
     * without an owner or without an object is refused.
     *
     * @return bool
     */
    static function accessible( $id )
    {
        $file = static::directory() . '/' . $id . '.json';
        $state = self::isID( $id ) && is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : false;
        if ( !is_array( $state ) || !self::belongsTo( $state, static::currentUserID() ) )
        {
            return false;
        }
        $objectID = isset( $state['options']['object_id'] ) ? (int)$state['options']['object_id'] : 0;
        $object = $objectID > 0 ? static::fetchObject( $objectID ) : null;

        return is_object( $object ) && $object->canRead();
    }

    /**
     * @return int the id of the current user (a seam: a test subclass answers it without a session)
     */
    protected static function currentUserID()
    {
        return (int)eZUser::currentUserID();
    }

    /**
     * @return eZContentObject|null the object $objectID (a seam: a test subclass answers it without a database)
     */
    protected static function fetchObject( $objectID )
    {
        return eZContentObject::fetch( (int)$objectID );
    }

    /**
     * Whether the job state $state was started by the user $userID (the user id start() stores). A state without
     * one, written before it was stored, belongs to nobody.
     *
     * @return bool
     */
    static function belongsTo( array $state, $userID )
    {
        return $userID > 0 && isset( $state['user_id'] ) && (int)$state['user_id'] === (int)$userID;
    }

    /**
     * Append a line to the log of a job.
     */
    static function log( $id, $text )
    {
        if ( self::isID( $id ) )
        {
            file_put_contents( static::directory() . '/' . $id . '.log', $text . "\n", FILE_APPEND );
        }
    }

    /**
     * Keep the newest jobs (state, log and export file of each).
     */
    static function prune()
    {
        $files = glob( static::directory() . '/*.json' );
        if ( !$files || count( $files ) <= self::KEEP )
        {
            return;
        }
        sort( $files );
        foreach ( array_slice( $files, 0, count( $files ) - self::KEEP ) as $file )
        {
            $base = substr( $file, 0, -5 );
            foreach ( array( '.json', '.log', '.csv', '.slk' ) as $extension )
            {
                @unlink( $base . $extension );
            }
        }
    }
}

?>
