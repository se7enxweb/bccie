#!/usr/bin/env php
<?php
/**
 * Show the forms with collected information, the last exports and the problems found
 *
 * Usage:
 *   php extension/bccie/bin/php/status.php [options]   (or ./console ext:bccie:status)
 *   --help shows the options.
 *
 * @alias cie-status
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

// The code is in extension/bccie/classes/runnable/commands/php_status.php (#207); this file is the entry point.
\Exponential\Command\Extension\Bccie\Status::main( __FILE__ );
