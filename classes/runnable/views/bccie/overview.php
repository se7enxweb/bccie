<?php
/**
 * The code of extension/bccie/modules/bccie/overview.php, moved into a class (#207 stage 1), rebuilt in 1.1.12 as a dashboard with a list. The file extension/bccie/modules/bccie/overview.php is one call to it.
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
        $user = \eZUser::currentUser();
        $access = $user->hasAccessTo( 'bccie', 'remove' );
        $canRemove = $access['accessWord'] != 'no';

        // the page size follows the preference the infocollector list of the admin has always used
        $limit = 10;
        switch ( \eZPreferences::value( 'admin_infocollector_list_limit' ) )
        {
            case '2': $limit = 25; break;
            case '3': $limit = 50; break;
        }
        $vp = \bccieUI::listParameters( $Params, array( 'name', 'collections', 'first_collection', 'last_collection' ), $limit );

        // The filter form: the page for the filter text.
        if ( $http->hasPostVariable( 'FilterButton' ) )
        {
            $q = trim( (string)$http->postVariable( 'Filter' ) );
            return $module->redirectTo( \bccieUI::listURL( 'overview', array_merge( $vp, array( 'offset' => 0, 'q' => $q ) ) ) );
        }

        // Remove: first the confirmation, then the removal. The ids travel with the confirmation, not in the session.
        $confirm = array();
        if ( $http->hasPostVariable( 'RemoveObjectCollectionButton' ) || $http->hasPostVariable( 'ConfirmRemoveButton' ) )
        {
            $ids = $http->hasPostVariable( 'ObjectIDArray' ) && is_array( $http->postVariable( 'ObjectIDArray' ) ) ? $http->postVariable( 'ObjectIDArray' ) : array();
            $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

            if ( !$canRemove )
            {
                \bccieUI::notice( 'error', \ezpI18n::tr( 'extension/bccie', 'You do not have permission to remove collected information (policy bccie / remove).' ) );
                return $module->redirectToView( 'overview' );
            }
            if ( !$ids )
            {
                \bccieUI::notice( 'warning', \ezpI18n::tr( 'extension/bccie', 'Select at least one form first.' ) );
                return $module->redirectToView( 'overview' );
            }

            if ( $http->hasPostVariable( 'ConfirmRemoveButton' ) )
            {
                $removed = 0;
                foreach ( $ids as $objectID )
                {
                    $count = \bccieRunner::purge( $objectID );
                    $removed += $count;
                    \eZAudit::writeAudit( 'bccie-purge', array( 'Object ID' => $objectID, 'Collections' => $count, 'Before' => 'all' ) );
                }
                \bccieUI::notice( 'feedback', \ezpI18n::tr( 'extension/bccie', '%count collections were removed.', null, array( '%count' => $removed ) ) );
                return $module->redirectToView( 'overview' );
            }

            foreach ( $ids as $objectID )
            {
                $object = \eZContentObject::fetch( $objectID );
                if ( $object )
                {
                    $confirm[] = array( 'id' => $objectID, 'name' => $object->attribute( 'name' ),
                                        'collections' => \eZInformationCollection::fetchCollectionCountForObject( $objectID ) );
                }
            }
        }

        $total = \bccieExportUtils::getCollectorObjectsCount( $vp['q'] );
        if ( $vp['offset'] > 0 && $vp['offset'] >= $total )
        {
            $vp['offset'] = 0;
        }
        $objects = \bccieExportUtils::getObjectsWithCollectedInformation( $vp['offset'], $vp['limit'], $vp['q'], $vp['sort'], $vp['order'] );

        $vp['q_url'] = rawurlencode( $vp['q'] );

        $tpl = \eZTemplate::factory();
        $tpl->setVariable( 'module', $module );
        $tpl->setVariable( 'limit', $vp['limit'] );
        $tpl->setVariable( 'vp', $vp );
        $tpl->setVariable( 'view_parameters', array( 'offset' => $vp['offset'], 'q' => rawurlencode( $vp['q'] ), 'sort' => $vp['sort'], 'order' => $vp['order'] ) );
        $tpl->setVariable( 'object_array', $objects );
        $tpl->setVariable( 'object_count', $total );
        $tpl->setVariable( 'summary', \bccieDashboard::summary() );
        $tpl->setVariable( 'notices', \bccieUI::takeNotices() );
        $tpl->setVariable( 'confirm_list', $confirm );
        $tpl->setVariable( 'can_remove', $canRemove );

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:bccie/overview.tpl' );
        $Result['navigation_part'] = 'ezbccienavigationpart';
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
