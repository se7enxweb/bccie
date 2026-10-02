<?php
/**
 * The code of extension/bccie/modules/bccie/export.php, moved into a class (#207 stage 1). The file extension/bccie/modules/bccie/export.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/bccie/modules/bccie/export.php:
 *
 *
 * File containing the export module view.
 *
 * @copyright Copyright (C) 1999 - 2017 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bccie
 *
 */

namespace Exponential\View\Extension\Bccie\Bccie
{

class Export extends \Exponential\Runnable\ModuleView
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
        $objectID = $Params['ObjectID'];

        $object = false;

        if ( !isset( $offset ) )
        {
            $offset = false;
        }

        if ( is_numeric( $objectID ) )
        {
            $object = \eZContentObject::fetch( $objectID );
        }

        if ( !$object )
        {
            return $this->viewResult( isset( $Result ) ? $Result : null,  $module->handleError( \eZError::KERNEL_NOT_AVAILABLE, 'kernel' ) );
        }

        $collections = \eZInformationCollection::fetchCollectionsList(
                                              $objectID, /* object id */
                                                  false, /* creator id */
                                                  false, /* user identifier */
                                                  array() /* limit array */
        );

        $numberOfCollections = \eZInformationCollection::fetchCollectionsCount( $objectID );

        $objects = \bccieExportUtils::getObjectsWithCollectedInformation();
        $numberOfInfoCollectorObjects = \bccieExportUtils::getCollectorObjectsCount();

        $viewParameters = array( 'offset' => $offset );
        $objectName = $object->attribute( 'name' );

        $tpl = \eZTemplate::factory();
        $tpl->setVariable( 'module', $module );
        $tpl->setVariable( 'object', $object );
        $tpl->setVariable( 'object_array', $objects );
        $tpl->setVariable( 'object_count', $numberOfInfoCollectorObjects );
        $tpl->setVariable( 'collection_array', $collections );
        $tpl->setVariable( 'collection_count', $numberOfCollections );

        if( $numberOfCollections >= 1 )
        {
            $createdTimestamp = $collections[0]->attribute( 'created' );
            $startDay = date( 'd', $createdTimestamp );
            $startMonth = date( 'm', $createdTimestamp );
            $startYear = date( 'Y', $createdTimestamp );

            $tpl->setVariable( 'start_day', $startDay );
            $tpl->setVariable( 'start_month', $startMonth );
            $tpl->setVariable( 'start_year', $startYear );
        }

        $tpl->setVariable( 'end_day', date( 'd' ) );
        $tpl->setVariable( 'end_month', date( 'm' ) );
        $tpl->setVariable( 'end_year', date( 'Y' ) );

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:bccie/export.tpl' );
        $Result['navigation_part'] = 'ezbccienavigationpart';
        $Result['left_menu'] = 'design:bccie/export_menu.tpl';
        $Result['path'] = array(
            array(
                'url' => '/bccie/overview',
                'text' => \ezpI18n::tr( 'extension/bccie', 'Collected information export' )
            ),
            array(
                'url' => false,
                'text' => $objectName
            )
        );

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
