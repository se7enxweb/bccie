<?php
// @description Export the collected information of the objects in cie.ini [CieSettings] Collection[] to SYLK files
/**
 * File containing the eZCollectExport ExportSylk Cronjob.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

// Settings

// The code is in extension/bccie/classes/runnable/cronjobs/exportsylk.php (#207); this file is the entry point.
return \Exponential\Cronjob\Extension\Bccie\Exportsylk::main( __FILE__, get_defined_vars() );
