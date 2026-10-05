<?php
/**
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

namespace Exponential\Command\Extension\Bccie
{

/**
 * ext:bccie:status - the dashboard of the admin as text: the forms with collected information, the last
 * exports and the problems found.
 */
class Status extends \Exponential\Runnable\Command
{
    public function run()
    {
        $this->script( array( 'description' => 'Show the forms with collected information, the last exports and the problems found',
                              'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
        $this->startup( '', '', array() );
        $cli = $this->cli();

        $summary = \bccieDashboard::summary();
        $totals = $summary['totals'];
        $cli->output( 'Collected information: ' . $totals['collections'] . ' collections of ' . $totals['objects'] . ' forms' );
        foreach ( \bccieExportUtils::getObjectsWithCollectedInformation( 0, 100 ) as $row )
        {
            $cli->output( sprintf( '  %6d  %-40s %6d collections, last %s', $row['contentobject_id'], mb_substr( $row['name'], 0, 40 ), $row['collections'], date( 'Y-m-d H:i', $row['last_collection'] ) ) );
        }
        $cli->output( 'Last exports:' );
        foreach ( $summary['log'] as $entry )
        {
            $cli->output( '  ' . date( 'Y-m-d H:i', $entry['time'] ) . '  ' . ( isset( $entry['name'] ) ? $entry['name'] : '' ) . ' (' . ( isset( $entry['format'] ) ? $entry['format'] : '' ) . ', ' . ( isset( $entry['rows'] ) ? $entry['rows'] : 0 ) . ' rows, ' . ( isset( $entry['source'] ) ? $entry['source'] : '' ) . ')' );
        }
        if ( !$summary['log'] )
        {
            $cli->output( '  none yet' );
        }
        $cli->output( 'Scheduled export: ' . $summary['cron']['objects'] . ' objects to ' . $summary['cron']['directory'] . ( $summary['cron']['range'] ? ', the last ' . $summary['cron']['range'] . ' days' : '' ) );
        $cli->output( 'Background runs: ' . ( $summary['background'] ? 'available' : 'not available' ) );
        foreach ( $summary['problems'] as $problem )
        {
            $cli->output( '  [' . $problem['level'] . '] ' . $problem['text'] );
        }
        $this->shutdown( 0 );
        return 0;
    }
}

}
