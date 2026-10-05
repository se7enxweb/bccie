#!/usr/bin/env php
<?php
/**
 * Export the collected information of a form to a CSV or SYLK file, or run the scheduled export
 *
 * Usage:
 *   php extension/bccie/bin/php/export.php [options]   (or ./console ext:bccie:export)
 *   --help shows the options.
 *
 * @alias cie-export
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

// The code is in extension/bccie/classes/runnable/commands/php_export.php (#207); this file is the entry point.
\Exponential\Command\Extension\Bccie\Export::main( __FILE__ );
