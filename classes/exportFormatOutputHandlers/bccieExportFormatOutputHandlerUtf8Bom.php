<?php
/**
 * File containing the bccieExportFormatOutputHandlerUtf8Bom class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

class bccieExportFormatOutputHandlerUtf8Bom extends bccieExportFormatOutputHandler
{
    /*
     Originally introduced to provide for double click open support for ms-excel on windows
     Discussion: http://projects.ez.no/cie/forum/general/special_characters_problem
    */
    public function prefix()
    {
        return "\xEF\xBB\xBF";
    }
}

?>
