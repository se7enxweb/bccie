<?php
/**
 * The code of extension/bccie/modules/bccie/export.php, moved into a class (#207 stage 1). The file extension/bccie/modules/bccie/export.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/bccie/modules/bccie/export.php:
 *
 *
 * File containing the export module view.
 *
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bccie
 *
 */

namespace Exponential\View\Extension\Bccie\Bccie
{

class Export extends \Exponential\Runnable\ModuleView
{
    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        return $this->viewResult( \bccieExportPage::handle( $Params['Module'], $Params ), null );
    }
}

}
