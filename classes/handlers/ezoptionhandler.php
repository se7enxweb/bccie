<?php
/**
 * File containing the ezoptionhandler class.
 *
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bccie
 */

class eZOptionHandler extends BaseHandler
{

    function exportAttribute( &$attribute, $seperationChar )
    {
        $ret = false;
        $objectAttribute = $attribute->contentObjectAttribute();
        $objectAttributeContent = $objectAttribute->content();
        if ( is_object( $objectAttributeContent ) && is_array( $objectAttributeContent->Options ) )
        {
            // the stored number is the option's id, which is not always its position in the list
            foreach ( $objectAttributeContent->Options as $option )
            {
                if ( isset( $option['id'] ) && (int)$option['id'] === (int)$attribute->DataInt )
                {
                    $ret = $option['value'];
                    break;
                }
            }
            if ( $ret === false && isset( $objectAttributeContent->Options[$attribute->DataInt]['value'] ) )
            {
                $ret = $objectAttributeContent->Options[$attribute->DataInt]['value'];
            }
        }

        return $this->escape( $ret, $seperationChar );
    }
}

?>