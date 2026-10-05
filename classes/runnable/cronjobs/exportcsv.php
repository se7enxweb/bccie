<?php
/**
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

namespace Exponential\Cronjob\Extension\Bccie
{

/**
 * Cronjob part exportcsv: writes the collected information of the objects in [CieSettings] Collection[] to CSV files
 * in [CieSettings] Directory. runcronjobs.php runs the part's file cronjobs/exportcsv.php, which is one call to this
 * class. The console command ext:bccie:export --cron --format=csv runs the same code.
 */
class Exportcsv extends \Exponential\Runnable\CronjobPart
{
    public function run( array $scope )
    {
        @ini_set( 'memory_limit', '512M' );
        $cli = isset( $scope['cli'] ) ? $scope['cli'] : \eZCLI::instance();
        \bccieRunner::loginCronUser();
        $totals = \bccieRunner::runCron( 'csv', $cli, false, 'cron' );
        if ( $totals['locked'] )
        {
            $cli->output( 'Another export is running; this run does nothing.' );
            return false;
        }
        $cli->output( 'Export done: ' . $totals['objects'] . ' objects, ' . $totals['rows'] . ' collections written to ' . $totals['files'] . ' files.' );

        return $totals['ok'] && !$totals['errors'];
    }
}

}
