<?php
/**
 * The code of extension/bccie/cronjobs/exportcsv.php, moved into a class (#207 stage 1). The file extension/bccie/cronjobs/exportcsv.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/bccie/cronjobs/exportcsv.php:
 *
 *
 * File containing the eZCollectExport ExportCSV Cronjob.
 *
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bccie
 *
 */

namespace Exponential\Cronjob\Extension\Bccie
{

class Exportcsv extends \Exponential\Runnable\CronjobPart
{
    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        $ini = \eZINI::instance( "cie.ini" );

        $debug = $ini->hasVariable( 'CieSettings', 'Debug' ) ? $ini->variable(
                                                                   'CieSettings',
                                                                       'Debug'
                                                               ) == 'enabled' : false;
        $collection = $ini->variable( "CieSettings", "Collection" );
        $dir = $ini->variable( "CieSettings", "Directory" );
        $format = $ini->variable( "CieSettings", "CsvFormat" );
        $separator = $ini->variable( "CieSettings", "CsvSeparator" );
        $limitedRange = $ini->variable( "CieSettings", "ExportLimitedRange" ) == 'enabled' ? true : false;
        $removeExported = $ini->variable( "CieSettings", "RemoveExported" ) == 'enabled' ? true : false;

        // Test range
        if ( $limitedRange == true )
        {
            $days = $ini->variable( "CieSettings", "DateRangeToExport" );
        }
        else
        {
            $days = false;
        }

        // Export collections
        exportCollections( $collection, $dir, $format, $separator, $days, $removeExported, $debug );
    }
}

}
