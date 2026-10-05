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
     * @param array $options the validated options of bccieRunner::normalizeOptions()
     * @param string $error receives the reason when it could not start
     * @return string|false the job id
     */
    static function start( array $options, &$error )
    {
        $php = class_exists( 'expProcessTools' ) ? expProcessTools::phpCli() : false;
        $setsid = class_exists( 'expProcessTools' ) ? expProcessTools::setsid() : false;
        if ( !$php || !$setsid || !function_exists( 'proc_open' ) )
        {
            $error = class_exists( 'expProcessTools' ) && expProcessTools::error() !== ''
                ? expProcessTools::error() : 'A background run needs proc_open, setsid and the PHP command line.';
            return false;
        }
        $dir = self::directory();
        if ( !is_dir( $dir ) && !eZDir::mkdir( $dir, false, true ) )
        {
            $error = 'The folder ' . $dir . ' cannot be created.';
            return false;
        }
        $id = date( 'YmdHis' ) . '-' . bin2hex( random_bytes( 4 ) );
        $user = eZUser::currentUser();
        self::write( $id, array( 'id' => $id, 'command' => 'export', 'status' => 'starting', 'started' => time(),
                                 'by' => $user->attribute( 'login' ), 'options' => $options, 'result' => null ) );
        touch( $dir . '/' . $id . '.log' );

        $line = array( $setsid, '-f', $php, 'extension/bccie/bin/php/export.php', '--job=' . $id, '-q' );
        $siteaccess = eZSiteAccess::current();
        if ( isset( $siteaccess['name'] ) && preg_match( '/^[A-Za-z0-9_.\-]+$/', $siteaccess['name'] ) )
        {
            $line[] = '--siteaccess=' . $siteaccess['name'];
        }
        if ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 )
        {
            $line[] = '--allow-root-user';
        }
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
            self::write( $id, array( 'id' => $id, 'command' => 'export', 'status' => 'failed', 'started' => time(), 'options' => $options, 'result' => array( 'error' => $error ) ) );
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
     * Store the state of a job.
     */
    static function write( $id, $state )
    {
        if ( !self::isID( $id ) )
        {
            return false;
        }
        $file = self::directory() . '/' . $id . '.json';
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
        $file = self::directory() . '/' . $id . '.json';
        if ( !is_file( $file ) )
        {
            return false;
        }
        $state = json_decode( (string)file_get_contents( $file ), true );
        if ( !is_array( $state ) )
        {
            return false;
        }
        $log = self::directory() . '/' . $id . '.log';
        $lines = is_file( $log ) ? array_slice( file( $log, FILE_IGNORE_NEW_LINES ), -40 ) : array();
        $state['log'] = $lines;
        // a job that says "starting" or "running" for ten minutes without a sign of life is dead
        if ( in_array( $state['status'], array( 'starting', 'running' ), true ) && is_file( $log ) && filemtime( $log ) < time() - 600 && filemtime( $file ) < time() - 600 )
        {
            $state['status'] = 'failed';
            $state['result'] = array( 'error' => 'The job stopped without a result.' );
        }
        $export = self::exportFile( $id, $state );
        if ( $state['status'] === 'done' && $export )
        {
            $state['file'] = array( 'name' => basename( $export ), 'size' => filesize( $export ) );
        }
        // the options are the job's own business, the page needs no copy of them
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
            $file = self::directory() . '/' . $id . '.json';
            $state = is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : false;
        }
        if ( !is_array( $state ) || empty( $state['result']['file'] ) || !preg_match( '/^[0-9]{14}-[a-f0-9]{8}\.(csv|slk)$/D', $state['result']['file'] ) )
        {
            return false;
        }
        $path = self::directory() . '/' . $state['result']['file'];

        return is_file( $path ) ? $path : false;
    }

    /**
     * @return array the saved options of a job, empty for an unknown job
     */
    static function options( $id )
    {
        $file = self::directory() . '/' . $id . '.json';
        $state = self::isID( $id ) && is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : false;

        return is_array( $state ) && isset( $state['options'] ) && is_array( $state['options'] ) ? $state['options'] : array();
    }

    /**
     * Append a line to the log of a job.
     */
    static function log( $id, $text )
    {
        if ( self::isID( $id ) )
        {
            file_put_contents( self::directory() . '/' . $id . '.log', $text . "\n", FILE_APPEND );
        }
    }

    /**
     * Keep the newest jobs (state, log and export file of each).
     */
    static function prune()
    {
        $files = glob( self::directory() . '/*.json' );
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
