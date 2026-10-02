<?php
/**
 * The code of extension/bccie/modules/bccie/overview.php, moved into a class (#207 stage 1). The file extension/bccie/modules/bccie/overview.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/bccie/modules/bccie/overview.php:
 *
 *
 * File containing the overview module view.
 *
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bccie
 *
 */

namespace Exponential\View\Extension\Bccie\Bccie
{

class Overview extends \Exponential\Runnable\ModuleView
{
    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        $http = \eZHTTPTool::instance();
        $module = $Params['Module'];
        $offset = $Params['Offset'];

        if ( !is_numeric( $offset ) )
        {
            $offset = 0;
        }


        if ( $module->isCurrentAction( 'RemoveObjectCollection' )
             && $http->hasPostVariable(
                     'ObjectIDArray'
            )
        )
        {
            $objectIDArray = $http->postVariable( 'ObjectIDArray' );
            $http->setSessionVariable( 'ObjectIDArray', $objectIDArray );

            $collections = 0;

            foreach ( $objectIDArray as $objectID )
            {
                $collections += \eZInformationCollection::fetchCollectionCountForObject( $objectID );
            }

            $tpl = \eZTemplate::factory();
            $tpl->setVariable( 'module', $module );
            $tpl->setVariable( 'collections', $collections );
            $tpl->setVariable( 'remove_type', 'objects' );

            $Result = array();
            $Result['content'] = $tpl->fetch( 'design:infocollector/confirmremoval.tpl' );
            $Result['path'] = array(
                array(
                    'url' => false,
                    'text' => \ezpI18n::tr( 'kernel/infocollector', 'Collected information' )
                )
            );

            return $this->viewResult( isset( $Result ) ? $Result : null, null );
        }


        if ( $module->isCurrentAction( 'ConfirmRemoval' ) )
        {

            $objectIDArray = $http->sessionVariable( 'ObjectIDArray' );

            if ( is_array( $objectIDArray ) )
            {
                foreach ( $objectIDArray as $objectID )
                {
                    \eZInformationCollection::removeContentObject( $objectID );
                }
            }
        }


        if ( \eZPreferences::value( 'admin_infocollector_list_limit' ) )
        {
            switch ( \eZPreferences::value( 'admin_infocollector_list_limit' ) )
            {
                case '2':
                {
                    $limit = 25;
                }
                    break;
                case '3':
                {
                    $limit = 50;
                }
                    break;
                default:
                    {
                    $limit = 10;
                    }
                    break;
            }
        }
        else
        {
            $limit = 10;
        }


        $objects = \bccieExportUtils::getObjectsWithCollectedInformation( $offset , $limit);
        $numberOfInfoCollectorObjects = \bccieExportUtils::getCollectorObjectsCount();

        $viewParameters = array( 'offset' => $offset );

        $tpl = \eZTemplate::factory();
        $tpl->setVariable( 'module', $module );
        $tpl->setVariable( 'limit', $limit );
        $tpl->setVariable( 'view_parameters', $viewParameters );
        $tpl->setVariable( 'object_array', $objects );
        $tpl->setVariable( 'object_count', $numberOfInfoCollectorObjects );

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:bccie/overview.tpl' );
        $Result['navigation_part'] = 'ezbccienavigationpart';
        $Result['left_menu'] = 'design:bccie/export_menu.tpl';
        $Result['path'] = array(
            array(
                'url' => false,
                'text' => \ezpI18n::tr( 'extension/bccie', 'Collected information export' )
            )
        );

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
