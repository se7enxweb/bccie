<?php
/**
 * File containing the bccieExportPage class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * The export page of one form: the options form, the download, the start of an export in the background and the
 * removal of the collected information. The views export and doexport (the address of earlier versions) both
 * use it, so every old form keeps working.
 */
class bccieExportPage
{
    /**
     * @param eZModule $module
     * @param array $Params the view's parameters (ObjectID, UserParameters)
     * @return array|false|null the result of the view (a template result, or what a redirect returns)
     */
    static function handle( $module, $Params )
    {
        $http = eZHTTPTool::instance();
        $objectID = $Params['ObjectID'];
        $object = is_numeric( $objectID ) ? eZContentObject::fetch( (int)$objectID ) : false;
        if ( !$object )
        {
            return $module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
        }
        $objectID = (int)$object->attribute( 'id' );
        $user = eZUser::currentUser();
        $access = $user->hasAccessTo( 'bccie', 'remove' );
        $canRemove = $access['accessWord'] != 'no';

        $input = self::input( $http, $object );
        $errors = array();
        $confirmRemove = false;

        if ( $http->hasPostVariable( 'CancelRemoveButton' ) )
        {
            return $module->redirectTo( 'bccie/export/' . $objectID );
        }

        if ( $http->hasPostVariable( 'RemoveCollectedButton' ) || $http->hasPostVariable( 'ConfirmRemoveButton' ) )
        {
            if ( !$canRemove )
            {
                bccieUI::notice( 'error', ezpI18n::tr( 'extension/bccie', 'You do not have permission to remove collected information (policy bccie / remove).' ) );
                return $module->redirectTo( 'bccie/export/' . $objectID );
            }
            if ( $http->hasPostVariable( 'ConfirmRemoveButton' ) )
            {
                $count = bccieRunner::purge( $objectID );
                eZAudit::writeAudit( 'bccie-purge', array( 'Object ID' => $objectID, 'Collections' => $count, 'Before' => 'all' ) );
                bccieUI::notice( 'feedback', ezpI18n::tr( 'extension/bccie', '%count collections were removed.', null, array( '%count' => $count ) ) );
                return $module->redirectTo( 'bccie/overview' );
            }
            $confirmRemove = true;
        }
        else if ( $http->hasPostVariable( 'DoExport' ) || $http->hasPostVariable( 'RunBackgroundButton' ) )
        {
            $options = bccieRunner::normalizeOptions( $input, $object, $errors );
            if ( $options )
            {
                $count = bccieRunner::countCollections( $options );
                if ( $count < 1 )
                {
                    $errors['dates'] = ezpI18n::tr( 'extension/bccie', 'No collected information matches the options (the date range?); there is nothing to export.' );
                }
                else if ( $http->hasPostVariable( 'DoExport' ) && $count > bccieRunner::directLimit() )
                {
                    $errors['size'] = ezpI18n::tr( 'extension/bccie', 'This export has %count collections, more than the %limit that are written while you wait. Use "Run in the background".', null,
                                                   array( '%count' => $count, '%limit' => bccieRunner::directLimit() ) );
                }
            }
            if ( $options && !$errors )
            {
                if ( $http->hasPostVariable( 'RunBackgroundButton' ) )
                {
                    $error = '';
                    $jobID = bccieJob::start( $options, $error );
                    if ( !$jobID )
                    {
                        bccieUI::notice( 'error', ezpI18n::tr( 'extension/bccie', 'The export could not be started: %reason', null, array( '%reason' => $error ) ) );
                        return $module->redirectTo( 'bccie/export/' . $objectID );
                    }
                    return $module->redirectTo( 'bccie/export/' . $objectID . '/(job)/' . $jobID );
                }
                // the download: the file is written while the page waits
                @set_time_limit( (int)eZINI::instance( 'cie.ini' )->variable( 'CieSettings', 'ExportExecutionTimeLimit' ) );
                $text = bccieRunner::exportToString( $options );
                $handler = bccieExportFormatOutputHandler::instance( $options['charset'] !== '' ? $options['charset'] : null );
                $handler->setOutputFileName( bccieExportUtils::getFileName( $options['format'], $object ) );
                $handler->setContentType( $options['format'] === 'sylk' ? 'application/x-sylk' : 'text/csv' );
                $handler->outputHeaders();
                echo $text;
                bccieRunner::recordExport( array( 'object_id' => $objectID, 'name' => $object->attribute( 'name' ), 'format' => $options['format'],
                                                  'rows' => $count, 'source' => 'admin' ) );
                eZExecution::cleanExit();
            }
        }

        return self::show( $module, $object, $input, $errors, $Params, $confirmRemove, $canRemove );
    }

    /**
     * The options as the form sends them (the old form's field_N selects and the new checkboxes), or the defaults.
     */
    protected static function input( $http, $object )
    {
        $defaults = bccieRunner::defaultOptions( $object );
        if ( !$http->hasPostVariable( 'DoExport' ) && !$http->hasPostVariable( 'RunBackgroundButton' ) )
        {
            return $defaults + array( 'creation_date' => false, 'modification_date' => false );
        }

        $input = array();
        $input['format'] = $http->hasPostVariable( 'export_type' ) ? (string)$http->postVariable( 'export_type' ) : 'csv';
        $input['separator'] = $http->hasPostVariable( 'separation_char' ) ? (string)$http->postVariable( 'separation_char' ) : ';';
        $input['charset'] = $http->hasPostVariable( 'charset' ) ? (string)$http->postVariable( 'charset' ) : '';
        $input['creation_date'] = $http->hasPostVariable( 'creation_date' );
        $input['modification_date'] = $http->hasPostVariable( 'modification_date' );
        foreach ( array( 'start', 'end' ) as $edge )
        {
            foreach ( array( 'date', 'day', 'month', 'year' ) as $part )
            {
                $input[$edge . '_' . $part] = $http->hasPostVariable( $edge . '_' . $part ) ? trim( (string)$http->postVariable( $edge . '_' . $part ) ) : '';
            }
        }

        $fields = array();
        if ( $http->hasPostVariable( 'columns' ) && is_array( $http->postVariable( 'columns' ) ) )
        {
            if ( $http->hasPostVariable( 'include_id' ) )
            {
                $fields[] = 'contentobjectid';
            }
            foreach ( $http->postVariable( 'columns' ) as $column )
            {
                $fields[] = $column;
            }
        }
        else
        {
            // the select boxes field_0, field_1, ... of the form of earlier versions
            for ( $counter = 0; $http->hasPostVariable( 'field_' . $counter ); ++$counter )
            {
                $value = $http->postVariable( 'field_' . $counter );
                if ( $value === '' || $value === false )
                {
                    break;
                }
                $fields[] = $value;
            }
        }
        $input['fields'] = $fields;

        return $input;
    }

    protected static function show( $module, $object, $input, $errors, $Params, $confirmRemove, $canRemove )
    {
        $objectID = (int)$object->attribute( 'id' );
        $userParameters = isset( $Params['UserParameters'] ) && is_array( $Params['UserParameters'] ) ? $Params['UserParameters'] : array();

        $numberOfCollections = eZInformationCollection::fetchCollectionsCount( $objectID );
        $first = 0;
        $last = 0;
        $row = eZDB::instance()->arrayQuery( 'SELECT MIN( created ) AS f, MAX( created ) AS l FROM ezinfocollection WHERE contentobject_id = ' . $objectID );
        if ( $row )
        {
            $first = (int)$row[0]['f'];
            $last = (int)$row[0]['l'];
        }

        // which fields the form shows as checked: all of them at first, then what was sent
        $sent = array();
        foreach ( (array)$input['fields'] as $field )
        {
            $sent[] = (string)$field;
        }
        $includeID = !$sent || in_array( 'contentobjectid', $sent, true );
        $attributes = bccieExportUtils::collectorAttributes( $object );
        foreach ( $attributes as $index => $attribute )
        {
            $attributes[$index]['checked'] = !$sent || in_array( (string)$attribute['id'], $sent, true );
        }
        $errorList = array_values( $errors );
        $errors = $errors + array( 'fields' => '', 'dates' => '', 'format' => '', 'charset' => '', 'separator' => '', 'size' => '', 'object' => '' );
        $history = array();
        foreach ( bccieRunner::exportLog( bccieRunner::LOG_KEEP ) as $entry )
        {
            if ( (int)$entry['object_id'] === $objectID )
            {
                $entry['downloadable'] = !empty( $entry['job'] ) && bccieJob::exportFile( $entry['job'] );
                $history[] = $entry;
            }
        }

        $tpl = eZTemplate::factory();
        $tpl->setVariable( 'module', $module );
        $tpl->setVariable( 'object', $object );
        $tpl->setVariable( 'collection_count', $numberOfCollections );
        $tpl->setVariable( 'first_date', $first ? date( 'Y-m-d', $first ) : '' );
        $tpl->setVariable( 'last_date', $last ? date( 'Y-m-d', $last ) : '' );
        $tpl->setVariable( 'attributes', $attributes );
        $tpl->setVariable( 'include_id', $includeID );
        $tpl->setVariable( 'input', $input + array( 'format' => 'csv', 'separator' => ';', 'charset' => '' ) );
        $tpl->setVariable( 'separators', array_flip( bccieRunner::separators() ) );
        $tpl->setVariable( 'charsets', bccieExportFormatOutputHandler::available() );
        $tpl->setVariable( 'default_charset', eZINI::instance( 'cie.ini' )->variable( 'CieSettings', 'ExportOutputFormatHandlerDefault' ) );
        $tpl->setVariable( 'start_date', isset( $input['start_date'] ) && $input['start_date'] !== '' ? $input['start_date'] : '' );
        $tpl->setVariable( 'end_date', isset( $input['end_date'] ) && $input['end_date'] !== '' ? $input['end_date'] : '' );
        $tpl->setVariable( 'errors', $errors );
        $tpl->setVariable( 'error_list', $errorList );
        $tpl->setVariable( 'notices', bccieUI::takeNotices() );
        $tpl->setVariable( 'job_id', isset( $userParameters['job'] ) && bccieJob::isID( $userParameters['job'] ) ? $userParameters['job'] : '' );
        $tpl->setVariable( 'history', $history );
        $tpl->setVariable( 'direct_limit', bccieRunner::directLimit() );
        $tpl->setVariable( 'confirm_remove', $confirmRemove );
        $tpl->setVariable( 'can_remove', $canRemove );
        $tpl->setVariable( 'summary_background', class_exists( 'expProcessTools' ) && expProcessTools::phpCli() && expProcessTools::setsid() );

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:bccie/export.tpl' );
        $Result['navigation_part'] = 'ezbccienavigationpart';
        $Result['path'] = array(
            array( 'url' => 'bccie/overview', 'text' => ezpI18n::tr( 'extension/bccie', 'Collected information export' ) ),
            array( 'url' => false, 'text' => $object->attribute( 'name' ) )
        );

        return $Result;
    }
}

?>
