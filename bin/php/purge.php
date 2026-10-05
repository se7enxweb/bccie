#!/usr/bin/env php
<?php
/**
 * Remove the collected information of a form (all of it or what is older than a date)
 *
 * Usage:
 *   php extension/bccie/bin/php/purge.php [options]   (or ./console ext:bccie:purge)
 *   --help shows the options.
 *
 * @alias cie-purge
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

// The code is in extension/bccie/classes/runnable/commands/php_purge.php (#207); this file is the entry point.
\Exponential\Command\Extension\Bccie\Purge::main( __FILE__ );
