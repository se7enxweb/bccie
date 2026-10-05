<?php
/**
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

namespace Exponential\Command\Extension\Bccie
{

/**
 * ext:bccie:purge - remove the collected information of a form, all of it or what is older than a date.
 */
class Purge extends \Exponential\Runnable\Command
{
    public function run()
    {
        $this->script( array( 'description' => 'Remove the collected information of a form (all of it or what is older than a date)',
                              'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
        $options = $this->startup( '[object:][before:][dry-run][yes]', '',
            array( 'object' => 'The ID of the content object (the form)',
                   'before' => 'Only the collections created before this date, YYYY-MM-DD',
                   'dry-run' => 'Count what would be removed, remove nothing',
                   'yes' => 'Confirm: without it nothing is removed' ) );
        $cli = $this->cli();

        if ( !$options['object'] || !is_numeric( $options['object'] ) || !\eZContentObject::fetch( (int)$options['object'] ) )
        {
            $cli->error( 'Give an existing object with --object=ID.' );
            $this->shutdown( 1 );
            return 1;
        }
        $before = false;
        if ( $options['before'] )
        {
            $dates = \bccieExportUtils::dateConditions( array( 'end_date' => (string)$options['before'] ) );
            if ( $dates['error'] !== '' || $dates['to'] === false )
            {
                $cli->error( $dates['error'] !== '' ? $dates['error'] : 'The date is not valid.' );
                $this->shutdown( 1 );
                return 1;
            }
            $before = $dates['to'] - 86399;
        }
        \bccieRunner::loginCronUser();
        $dry = (bool)$options['dry-run'] || !$options['yes'];
        $count = \bccieRunner::purge( (int)$options['object'], $before, $dry );
        if ( $dry )
        {
            $cli->output( 'Would remove ' . $count . ' collections.' . ( $options['dry-run'] ? '' : ' Add --yes to remove them.' ) );
        }
        else
        {
            $cli->output( 'Removed ' . $count . ' collections.' );
            \eZAudit::writeAudit( 'bccie-purge', array( 'Object ID' => (int)$options['object'], 'Collections' => $count, 'Before' => $before ? date( 'c', $before ) : 'all' ) );
        }
        $this->shutdown( 0 );
        return 0;
    }
}

}
