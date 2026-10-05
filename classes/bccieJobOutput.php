<?php
/**
 * File containing the bccieJobOutput class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * Output sink with the two methods of eZCLI the runner uses; a line goes to the terminal and to the log of a job.
 */
class bccieJobOutput
{
    protected $cli;
    protected $jobID;

    function __construct( $cli, $jobID = false )
    {
        $this->cli = $cli;
        $this->jobID = $jobID;
    }

    function output( $text = false, $addEOL = true )
    {
        if ( $this->cli )
        {
            $this->cli->output( $text, $addEOL );
        }
        if ( $this->jobID )
        {
            bccieJob::log( $this->jobID, (string)$text );
        }
    }

    function error( $text = false, $addEOL = true )
    {
        if ( $this->cli )
        {
            $this->cli->error( $text, $addEOL );
        }
        if ( $this->jobID )
        {
            bccieJob::log( $this->jobID, 'ERROR: ' . $text );
        }
    }
}

?>
