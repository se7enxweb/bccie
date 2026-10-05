<?php
/**
 * File containing the ezobjectrelationhandler class.
 *
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bccie
 */

class eZObjectRelationHandler extends BaseHandler
{
    function exportAttribute( &$attribute, $seperationChar )
    {
        $content = $attribute->content();
        $ini = eZINI::instance( "export.ini" );

        if ( $ini->variable( "ezobjectrelation", "OutputRelatedObjectNames" ) !== 'false' )
        {
            return $this->escape( is_object( $content ) ? $content->name() : '', $seperationChar );
        }
        else
        {
            return $this->escape( is_object( $content ) ? $content->attribute( 'id' ) : '', $seperationChar );
        }
    }
}

?>