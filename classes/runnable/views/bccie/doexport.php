<?php
/**
 * The code of extension/bccie/modules/bccie/doexport.php, moved into a class (#207 stage 1). The file extension/bccie/modules/bccie/doexport.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */

namespace Exponential\View\Extension\Bccie\Bccie
{

class Doexport extends \Exponential\Runnable\ModuleView
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

        $cieINI = \eZINI::instance( 'cie.ini' );
        $exportExecutionTimeLimit = $cieINI->variable( 'CieSettings', 'ExportExecutionTimeLimit' );

        set_time_limit( $exportExecutionTimeLimit );

        $object = false;
        $exportCreationDate = false;
        $exportModificationDate = false;

        if ( is_numeric( $objectID ) )
        {
            $object = \eZContentObject::fetch( $objectID );
        }

        if ( !$object )
        {
            return $this->viewResult( isset( $Result ) ? $Result : null,  $module->handleError( \eZError::KERNEL_NOT_AVAILABLE, 'kernel' ) );
        }

        $conditions = array( 'contentobject_id' => $objectID );

        $dateConditions = \bccieExportUtils::getDateConditions( $http );

        if ( $dateConditions['conditions'] != null )
        {
            $conditions['created'] = $dateConditions['conditions'];
        }

        $collections = \eZPersistentObject::fetchObjectList(
            \eZInformationCollection::definition(),
            null,
            $conditions,
            false,
            false
        );

        // TODO: change error handler
        if ( !$collections )
        {
            return $this->viewResult( isset( $Result ) ? $Result : null,  $module->handleError( \eZError::KERNEL_NOT_AVAILABLE, 'kernel' ) );
        }

        $counter = 0;
        $attributesToExport = array();

        while ( true )
        {
            $currentattribute = $http->postVariable( "field_$counter" );
            if ( !$currentattribute )
            {
                break;
            }
            $attributesToExport[] = $currentattribute;
            $counter++;
        }

        if ( $http->hasPostVariable( "creation_date" ) )
        {
           $exportCreationDate = true;
        }

        if ( $http->hasPostVariable( "modification_date" ) )
        {
           $exportModificationDate = true;
        }

        $separationCharacter = $http->postVariable( "separation_char" );
        $exportFormat = $http->postVariable( "export_type" );

        $filename = \bccieExportUtils::getFileName( $exportFormat, $object );

        $parser = new \Parser( $objectID );

        $export_string = $parser->exportInformationCollection(
            $collections,
            $attributesToExport,
            $separationCharacter,
            $exportFormat,
            $dateConditions['days'],
            $exportCreationDate,
            $exportModificationDate
        );

        $exportFormatOutputHandler = \bccieExportFormatOutputHandler::instance();
        $exportFormatOutputHandler->setOutputFileName( $filename );

        $exportFormatOutputHandler = $exportFormatOutputHandler->output( $export_string );

        flush();

        \eZExecution::cleanExit();

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
