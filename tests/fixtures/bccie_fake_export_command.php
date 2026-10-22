<?php
/**
 * A stand-in for the export command (extension/bccie/bin/php/export.php --job=<id>) in bccieTransferTaskTest: writes
 * the job file the way the command does, without a kernel or a database.
 *
 *   php bccie_fake_export_command.php <job folder> <job id> <mode>
 *
 * mode: done (two progress steps, then the file), fail (the command's error), crash (exit 3 with the job running),
 * slow (progress, then waits until it is stopped).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

list( , $dir, $jobID, $mode ) = $argv;
$file = $dir . '/' . $jobID . '.json';
$state = json_decode( (string)file_get_contents( $file ), true );

$write = function ( $status, $result ) use ( $file, &$state )
{
    $state['status'] = $status;
    $state['result'] = $result;
    file_put_contents( $file . '.tmp', json_encode( $state ) );
    rename( $file . '.tmp', $file );
};

$write( 'running', null );
$write( 'running', array( 'done' => 200, 'total' => 400 ) );
usleep( 150000 );

if ( $mode === 'fail' )
{
    $write( 'failed', array( 'error' => 'Another export is running.' ) );
    exit( 1 );
}
if ( $mode === 'crash' )
{
    exit( 3 );
}
if ( $mode === 'slow' )
{
    file_put_contents( $dir . '/' . $jobID . '.csv', "ID;Name\n" );
    while ( true )
    {
        usleep( 100000 );
    }
}

file_put_contents( $dir . '/' . $jobID . '.csv', "ID;Name\n1;Ada\n" );
$write( 'running', array( 'done' => 400, 'total' => 400 ) );
usleep( 150000 );
$write( 'done', array( 'rows' => 400, 'done' => 400, 'total' => 400, 'file' => $jobID . '.csv' ) );
exit( 0 );
