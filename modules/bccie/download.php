<?php
/**
 * File containing the download module view.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package bccie
 */

// The file of an export that ran in the background.
$module = $Params['Module'];
$jobID = (string)$Params['JobID'];
$path = bccieJob::isID( $jobID ) ? bccieJob::exportFile( $jobID ) : false;

if ( !$path )
{
    bccieUI::notice( 'error', ezpI18n::tr( 'extension/bccie', 'The export file does not exist (any more). Run the export again.' ) );
    return $module->redirectToView( 'overview' );
}

$options = bccieJob::options( $jobID );
$object = isset( $options['object_id'] ) ? eZContentObject::fetch( (int)$options['object_id'] ) : false;
$fileName = ( $object ? bccieExportUtils::safeFileName( $object->attribute( 'name' ), 'export' ) : 'export' ) . '-' . substr( $jobID, 0, 8 ) . '-' . substr( $jobID, 8, 6 ) . substr( $path, -4 );
$handler = bccieExportFormatOutputHandler::instance( !empty( $options['charset'] ) ? $options['charset'] : null );
$handler->setOutputFileName( $fileName );
$handler->setContentType( substr( $path, -4 ) === '.slk' ? 'application/x-sylk' : 'text/csv' );
$handler->outputHeaders();
header( 'Content-Length: ' . filesize( $path ) );
readfile( $path );

eZExecution::cleanExit();

?>
