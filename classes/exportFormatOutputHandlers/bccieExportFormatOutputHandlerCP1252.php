<?php
/**
 * File containing the bccieExportFormatOutputHandlerCP1252 class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

class bccieExportFormatOutputHandlerCP1252 extends bccieExportFormatOutputHandler
{
    /*
     Originally introduced to provide for windows-1252 charset output
     Pull: https://github.com/brookinsconsulting/bccie/pull/16
     A character that the code page does not have is written as a question mark.
    */
    public function charset()
    {
        return 'windows-1252';
    }

    public function formatChunk( $chunk )
    {
        return mb_convert_encoding( $chunk, 'CP1252', 'UTF-8' );
    }
}

?>
