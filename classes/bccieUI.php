<?php
/**
 * File containing the bccieUI class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * Helpers shared by the module views: flash notices and the parameters of a list view (paging, sorting, filter).
 */
class bccieUI
{
    const NOTICE_SESSION_KEY = 'BccieNotices';

    /**
     * Remember a message for the next page shown to this user.
     *
     * @param string $type 'feedback', 'warning' or 'error' (the admin message classes)
     */
    static function notice( $type, $text )
    {
        $http = eZHTTPTool::instance();
        $list = $http->hasSessionVariable( self::NOTICE_SESSION_KEY ) ? $http->sessionVariable( self::NOTICE_SESSION_KEY ) : array();
        if ( !is_array( $list ) )
        {
            $list = array();
        }
        $list[] = array( 'type' => in_array( $type, array( 'feedback', 'warning', 'error' ), true ) ? $type : 'feedback',
                         'text' => (string)$text );
        $http->setSessionVariable( self::NOTICE_SESSION_KEY, $list );
    }

    /**
     * @return array the remembered messages, which are forgotten at the same time
     */
    static function takeNotices()
    {
        $http = eZHTTPTool::instance();
        if ( !$http->hasSessionVariable( self::NOTICE_SESSION_KEY ) )
        {
            return array();
        }
        $list = $http->sessionVariable( self::NOTICE_SESSION_KEY );
        $http->removeSessionVariable( self::NOTICE_SESSION_KEY );

        return is_array( $list ) ? $list : array();
    }

    /**
     * The parameters of a list view: (offset), (q), (sort), (order). Unknown or malformed values fall back to the defaults.
     *
     * @param array $params the view's $Params array
     * @param array $sortFields the sort keys the view offers, the first one is the default
     * @return array 'offset', 'limit', 'q', 'sort', 'order'
     */
    static function listParameters( $params, $sortFields, $limit = 15 )
    {
        $user = isset( $params['UserParameters'] ) && is_array( $params['UserParameters'] ) ? $params['UserParameters'] : array();
        $offset = isset( $params['Offset'] ) && is_numeric( $params['Offset'] ) ? (int)$params['Offset'] : ( isset( $user['offset'] ) ? (int)$user['offset'] : 0 );
        $sort = isset( $user['sort'] ) && in_array( $user['sort'], $sortFields, true ) ? $user['sort'] : $sortFields[0];
        $order = isset( $user['order'] ) && $user['order'] === 'desc' ? 'desc' : 'asc';
        $q = isset( $user['q'] ) ? trim( rawurldecode( (string)$user['q'] ) ) : '';

        return array( 'offset' => max( 0, $offset ), 'limit' => (int)$limit, 'q' => mb_substr( $q, 0, 100 ),
                      'sort' => $sort, 'order' => $order );
    }

    /**
     * @return string the URL part of a list view with its parameters, for example 'bccie/overview/(q)/news/(sort)/name'
     */
    static function listURL( $view, $vp, $override = array() )
    {
        $vp = array_merge( $vp, $override );
        $url = 'bccie/' . $view;
        if ( $vp['offset'] )
        {
            $url .= '/(offset)/' . (int)$vp['offset'];
        }
        if ( $vp['q'] !== '' )
        {
            $url .= '/(q)/' . rawurlencode( $vp['q'] );
        }

        return $url . '/(sort)/' . $vp['sort'] . '/(order)/' . $vp['order'];
    }

    /**
     * @return string a size like "12.3 KB"
     */
    static function size( $bytes )
    {
        $bytes = (float)$bytes;
        foreach ( array( 'B', 'KB', 'MB', 'GB' ) as $unit )
        {
            if ( $bytes < 1024 || $unit === 'GB' )
            {
                return ( $unit === 'B' ? (int)$bytes : round( $bytes, 1 ) ) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
        return '';
    }
}

?>
