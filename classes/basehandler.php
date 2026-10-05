<?php
/**
 * File containing the BaseHandler class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

class BaseHandler
{
    /**
     * The text of one collected attribute. A datatype without a handler of its own ends up here.
     */
    function exportAttribute( &$attribute, $separationChar )
    {
        $content = $attribute->content();

        if ( is_object( $content ) && !method_exists( $content, 'toString' ) && !method_exists( $content, '__toString' ) )
        {
            // an object that cannot be written as text (a file, an image): the stored text of the attribute
            $content = $attribute->attribute( 'data_text' );
        }

        return $this->escape( $content, $separationChar );
    }

    /**
     * Makes a value one line of valid UTF-8 text. The text stays UTF-8: converting it to ISO-8859-1 (as older
     * versions did) replaced every character outside Latin-1, for example the euro sign, with a question mark.
     * The output format handlers convert the finished file when a legacy code page is wanted.
     */
    function escape( $stringtoescape, $separationChar = '' )
    {
        if ( is_array( $stringtoescape ) )
        {
            $parts = array();
            foreach ( $stringtoescape as $part )
            {
                if ( is_scalar( $part ) )
                {
                    $parts[] = (string)$part;
                }
            }
            $stringtoescape = implode( ', ', $parts );
        }
        elseif ( is_object( $stringtoescape ) )
        {
            if ( method_exists( $stringtoescape, 'toString' ) )
            {
                $stringtoescape = $stringtoescape->toString();
            }
            elseif ( method_exists( $stringtoescape, '__toString' ) )
            {
                $stringtoescape = (string)$stringtoescape;
            }
            else
            {
                $stringtoescape = '';
            }
        }
        elseif ( $stringtoescape === null || $stringtoescape === false )
        {
            $stringtoescape = '';
        }
        elseif ( $stringtoescape === true )
        {
            $stringtoescape = '1';
        }

        $stringtoescape = (string)$stringtoescape;

        if ( !mb_check_encoding( $stringtoescape, 'UTF-8' ) )
        {
            $stringtoescape = mb_convert_encoding( $stringtoescape, 'UTF-8', 'ISO-8859-1' );
        }

        return preg_replace( "(\r\n|\n|\r)", " ", $stringtoescape );
    }
}

?>
