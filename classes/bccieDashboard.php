<?php
/**
 * File containing the bccieDashboard class
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

/**
 * What the start page of the module shows: the forms with collected information and their counts, the last
 * exports, the settings of the scheduled exports and the problems found.
 */
class bccieDashboard
{
    /**
     * @return array 'totals' (bccieExportUtils::totals()), 'last' (the last export or false), 'log' (the last
     *         exports), 'cron' (the settings of the cronjob parts), 'background' (true when a run in the
     *         background is possible), 'problems' (a list of level, code, text, url), 'attention' (how many of them are warnings or errors)
     */
    static function summary()
    {
        $ini = eZINI::instance( 'cie.ini' );
        $totals = bccieExportUtils::totals();
        $summary = array( 'totals' => $totals, 'last' => bccieRunner::lastExport(), 'log' => bccieRunner::exportLog( 5 ),
                          'problems' => array(), 'background' => false );

        $directory = (string)$ini->variable( 'CieSettings', 'Directory' );
        $objects = array_filter( (array)$ini->variable( 'CieSettings', 'Collection' ), 'is_numeric' );
        $summary['cron'] = array( 'directory' => $directory, 'objects' => count( $objects ),
                                  'remove' => $ini->variable( 'CieSettings', 'RemoveExported' ) == 'enabled',
                                  'range' => $ini->variable( 'CieSettings', 'ExportLimitedRange' ) == 'enabled' ? (int)$ini->variable( 'CieSettings', 'DateRangeToExport' ) : 0,
                                  'direct_limit' => bccieRunner::directLimit() );
        $summary['background'] = class_exists( 'expProcessTools' ) && expProcessTools::phpCli() && expProcessTools::setsid() && function_exists( 'proc_open' );

        if ( !$summary['background'] )
        {
            $summary['problems'][] = array( 'level' => 'warning', 'code' => 'background', 'url' => false,
                                            'text' => ezpI18n::tr( 'extension/bccie', 'A large export cannot run in the background: proc_open, setsid or the PHP command line is missing. Exports from the admin are limited to %limit collections.', null, array( '%limit' => $summary['cron']['direct_limit'] ) ) );
        }
        if ( $totals['orphans'] )
        {
            $summary['problems'][] = array( 'level' => 'warning', 'code' => 'orphans', 'url' => false,
                                            'text' => ezpI18n::tr( 'extension/bccie', '%count objects collected information but have no location in the content tree any more; they are not listed below.', null, array( '%count' => $totals['orphans'] ) ) );
        }
        foreach ( $objects as $objectID )
        {
            if ( !eZContentObject::fetch( (int)$objectID ) )
            {
                $summary['problems'][] = array( 'level' => 'error', 'code' => 'collection', 'url' => false,
                                                'text' => ezpI18n::tr( 'extension/bccie', 'The scheduled export names the object %id in [CieSettings] Collection[], which does not exist.', null, array( '%id' => (int)$objectID ) ) );
            }
        }
        if ( $objects && $directory !== '' && is_dir( $directory ) && !is_writable( $directory ) )
        {
            $summary['problems'][] = array( 'level' => 'error', 'code' => 'directory', 'url' => false,
                                            'text' => ezpI18n::tr( 'extension/bccie', 'The folder %dir of the scheduled exports is not writable.', null, array( '%dir' => $directory ) ) );
        }
        if ( $summary['cron']['remove'] )
        {
            $summary['problems'][] = array( 'level' => 'info', 'code' => 'remove', 'url' => false,
                                            'text' => ezpI18n::tr( 'extension/bccie', 'RemoveExported is enabled: the scheduled exports remove the collected information they have written to a file.' ) );
        }
        if ( !$objects )
        {
            $summary['problems'][] = array( 'level' => 'info', 'code' => 'nocron', 'url' => false,
                                            'text' => ezpI18n::tr( 'extension/bccie', 'No scheduled export is set up: [CieSettings] Collection[] names no object. Exports from the admin work without it.' ) );
        }
        foreach ( bccieExportUtils::getObjectsWithCollectedInformation( 0, 50 ) as $row )
        {
            $object = eZContentObject::fetch( (int)$row['contentobject_id'] );
            if ( !$object )
            {
                continue;
            }
            foreach ( bccieExportUtils::collectorAttributes( $object ) as $attribute )
            {
                if ( !$attribute['exportable'] )
                {
                    $summary['problems'][] = array( 'level' => 'info', 'code' => 'datatype', 'url' => 'bccie/export/' . $row['contentobject_id'],
                                                    'text' => ezpI18n::tr( 'extension/bccie', '%object: the field %field (%type) has no export handler; its stored text is exported.', null,
                                                                           array( '%object' => $row['name'], '%field' => $attribute['name'], '%type' => $attribute['datatype'] ) ) );
                }
            }
        }

        $summary['attention'] = 0;
        foreach ( $summary['problems'] as $problem )
        {
            if ( $problem['level'] !== 'info' )
            {
                ++$summary['attention'];
            }
        }

        return $summary;
    }
}

?>
