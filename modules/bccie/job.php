<?php
/**
 * File containing the job module view.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

// The state of an export in the background as JSON: status (starting, running, done, failed), the result and the last log lines.
$jobID = (string)$Params['JobID'];
$state = bccieJob::isID( $jobID ) ? bccieJob::status( $jobID ) : false;

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
if ( !$state )
{
    header( 'HTTP/1.1 404 Not Found' );
    echo json_encode( array( 'status' => 'unknown' ) );
}
else
{
    echo json_encode( $state );
}
eZExecution::cleanExit();

?>
