<?php
/**
 * File containing the bccieExportFormatOutputHandlerUtf16Le class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

class bccieExportFormatOutputHandlerUtf16Le extends bccieExportFormatOutputHandler
{
    /*
     Originally introduced to provide for double click open support for ms-excel on macosx
     Pull: https://github.com/brookinsconsulting/bccie/pull/10
    */
    public function charset()
    {
        return 'utf-16le';
    }

    public function prefix()
    {
        return "\xFF\xFE";
    }

    public function formatChunk( $chunk )
    {
        return mb_convert_encoding( $chunk, 'UTF-16LE', 'UTF-8' );
    }
}

?>
