<?php
/**
 * bccieTransfer, CIE in the kernel's transfer runs and run history (Exponential 6.0.15 and later): the pure parts and
 * the wiring, without a kernel or a database:
 *
 *   php vendor/bin/phpunit --no-configuration extension/bccie/tests/bccieTransferTest.php
 *
 *  TR-01 - the history row of a background export: export of data, the form, the format, the job, its page, the charset
 *  TR-02 - the progress and the result a job file gives (rows, total, the file by name, never a path)
 *  TR-03 - why a job file holds no finished export
 *  TR-04 - the export command keeps the history only when no engine of the kernel runs the job
 *  TR-05 - the command line of a job, and the keys every write of the job file keeps
 *  TR-06 - a queued job the engine cancelled or gave up shows so
 *  TR-07 - the runner is used only when the kernel has it (submit, record and history do nothing on an older kernel)
 *  TR-08 - the registry and the job type: QueueJobType, HistoryAllPermission, [JobType_bccie.export]
 *  TR-09 - start() hands the job to the runner before the old background process, the command writes the history
 *          on the engine process only, cron, console and downloads are recorded
 *  TR-10 - the templates: the links to Imports only behind the registry check, Open in Imports only for CSV
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/classes/bccieTransfer.php';

class bccieTransferTest extends TestCase
{
    const JOB = '20261022120000-0a1b2c3d';

    protected static function source( $path )
    {
        return (string)file_get_contents( dirname( __DIR__ ) . '/' . $path );
    }

    /** TR-01 */
    public function testTheHistoryRowOfABackgroundExport()
    {
        $info = bccieTransfer::historyInfo( array( 'object_id' => 57, 'format' => 'csv', 'separator' => ';', 'charset' => 'utf8bom' ), self::JOB, 'Contact (CSV)', 1234 );
        $this->assertSame( 'export', $info['direction'] );
        $this->assertSame( 'export', $info['kind'] );
        $this->assertSame( 'data', $info['scope'] );
        $this->assertSame( '57', $info['scope_ref'] );
        $this->assertSame( 'csv', $info['format'] );
        $this->assertSame( self::JOB, $info['tool_ref'] );
        $this->assertSame( 'bccie/export/57/(job)/' . self::JOB, $info['detail_url'] );
        $this->assertSame( array( 'charset' => 'utf8bom', 'separator' => ';' ), $info['detail'] );
        $this->assertSame( 1234, $info['rows_total'] );
        $this->assertSame( 'Contact (CSV)', $info['title'] );

        $sylk = bccieTransfer::historyInfo( array( 'object_id' => 57, 'format' => 'sylk', 'separator' => ';', 'charset' => '' ), self::JOB );
        $this->assertSame( 'sylk', $sylk['format'] );
        $this->assertSame( array( 'charset' => 'default' ), $sylk['detail'], 'SYLK has no separator' );
        $this->assertArrayNotHasKey( 'rows_total', $sylk );
        $this->assertSame( 'bccie/export/57', bccieTransfer::detailURL( 57 ) );
        $this->assertSame( 'bccie/overview', bccieTransfer::detailURL( 0, self::JOB ) );
        $this->assertSame( 'Contact (SYLK)', bccieTransfer::title( ' Contact ', array( 'format' => 'sylk' ) ) );
    }

    /** TR-02 */
    public function testProgressAndResultOfAJobFile()
    {
        $this->assertNull( bccieTransfer::progressOf( false ) );
        $this->assertNull( bccieTransfer::progressOf( array( 'status' => 'starting', 'result' => null ) ) );
        $this->assertSame( array( 200, 400 ), bccieTransfer::progressOf( array( 'status' => 'running', 'result' => array( 'done' => 200, 'total' => 400 ) ) ) );

        $result = bccieTransfer::resultOf( array( 'status' => 'done', 'options' => array( 'format' => 'sylk' ),
                                                  'result' => array( 'rows' => 7, 'done' => 7, 'total' => 7, 'file' => self::JOB . '.slk' ) ), 321 );
        $this->assertSame( array( 'rows_done' => 7, 'rows_total' => 7, 'format' => 'sylk', 'file_ref' => self::JOB . '.slk', 'bytes' => 321 ), $result );

        $odd = bccieTransfer::resultOf( array( 'result' => array( 'done' => 3, 'file' => '../../etc/passwd' ) ) );
        $this->assertSame( 3, $odd['rows_done'] );
        $this->assertArrayNotHasKey( 'file_ref', $odd, 'only a job file name becomes a file reference' );
    }

    /** TR-03 */
    public function testWhyAJobHoldsNoExport()
    {
        $this->assertSame( '', bccieTransfer::failureOf( array( 'status' => 'done' ), 0 ) );
        $this->assertSame( 'Another export is running.', bccieTransfer::failureOf( array( 'status' => 'failed', 'result' => array( 'error' => 'Another export is running.' ) ), 1 ) );
        $this->assertSame( 'The export stopped with exit code 255.', bccieTransfer::failureOf( array( 'status' => 'running', 'result' => array( 'done' => 1 ) ), 255 ) );
        $this->assertSame( 'The export stopped without a result.', bccieTransfer::failureOf( array( 'status' => 'running' ), 0 ) );
        $this->assertSame( 'The export stopped without a result.', bccieTransfer::failureOf( false ) );
    }

    /** TR-04 */
    public function testTheCommandKeepsTheHistoryOnlyOnItsOwnEngine()
    {
        $this->assertTrue( bccieTransfer::commandKeepsHistory( array( 'status' => 'starting' ) ), 'a job of an older version' );
        $this->assertTrue( bccieTransfer::commandKeepsHistory( array( 'engine' => 'process', 'run_id' => 5 ) ) );
        $this->assertFalse( bccieTransfer::commandKeepsHistory( array( 'engine' => 'expqueue', 'run_id' => 5 ) ) );
        $this->assertFalse( bccieTransfer::commandKeepsHistory( array( 'engine' => 'contentjob', 'run_id' => 5 ) ) );
        $this->assertTrue( bccieTransfer::commandKeepsHistory( false ) );
    }

    /** TR-05 */
    public function testCommandLineAndJobKeys()
    {
        $this->assertSame( array( '/usr/bin/php', 'extension/bccie/bin/php/export.php', '--job=' . self::JOB, '-q', '--siteaccess=admin', '--allow-root-user' ),
                           bccieTransfer::commandLine( '/usr/bin/php', self::JOB, 'admin', true ) );
        $this->assertSame( array( 'php', 'extension/bccie/bin/php/export.php', '--job=' . self::JOB, '-q' ),
                           bccieTransfer::commandLine( 'php', self::JOB, 'ad min;rm', false ), 'a strange siteaccess name is left out' );
        $this->assertSame( array( 'engine' => 'expqueue', 'job_ref' => 'u', 'run_id' => 4, 'siteaccess' => 'admin' ),
                           bccieTransfer::jobKeys( array( 'status' => 'running', 'engine' => 'expqueue', 'job_ref' => 'u', 'run_id' => 4, 'siteaccess' => 'admin', 'user_id' => 14 ) ) );
        $this->assertSame( array(), bccieTransfer::jobKeys( false ) );
    }

    /** TR-06 */
    public function testAQueuedJobTheEngineEnded()
    {
        $queued = array( 'status' => 'queued', 'engine' => 'expqueue', 'job_ref' => 'u', 'result' => null );
        $this->assertSame( 'cancelled', bccieTransfer::queuedState( $queued, 'cancelled' )['status'] );
        $this->assertSame( 'failed', bccieTransfer::queuedState( $queued, 'failed' )['status'] );
        $this->assertSame( 'queued', bccieTransfer::queuedState( $queued, 'queued' )['status'] );
        $this->assertSame( 'queued', bccieTransfer::queuedState( $queued, '' )['status'], 'an unknown engine state changes nothing' );
        $running = array( 'status' => 'running' ) + $queued;
        $this->assertSame( $running, bccieTransfer::queuedState( $running, 'cancelled' ), 'the command owns a running job' );
    }

    /** TR-07 */
    public function testNothingChangesOnAnOlderKernel()
    {
        if ( class_exists( 'expKernelTransferRunner' ) || class_exists( 'expKernelTransferHistory' ) )
        {
            $this->markTestSkipped( 'the kernel has the transfer runs' );
        }
        $this->assertFalse( bccieTransfer::useRunner() );
        $this->assertFalse( bccieTransfer::historyAvailable() );
        $this->assertNull( bccieTransfer::submit( self::JOB, array( 'object_id' => 57, 'format' => 'csv' ) ) );
        $this->assertSame( 0, bccieTransfer::record( array( 'object_id' => 57 ), array( 'rows_done' => 1 ) ) );
        $this->assertFalse( bccieTransfer::history( 'finish', array( 1, 'done', array() ) ) );

        $task = self::source( 'classes/bccieTransferTask.php' );
        $this->assertMatchesRegularExpression( "/^if \( interface_exists\( 'expKernelTransferTask' \) \)\n\{\n    class bccieTransferTask implements expKernelTransferTask/m", $task );
    }

    /** TR-08 */
    public function testTheRegistryAndTheJobType()
    {
        $registry = self::source( 'settings/kernelservices.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^QueueJobType=bccie\.export$/m', $registry );
        $this->assertMatchesRegularExpression( '#^HistoryAllPermission=bccie/remove$#m', $registry );

        $queue = self::source( 'settings/expqueue.ini.append.php' );
        $this->assertSame( 1, preg_match( '/^\[JobType_bccie\.export\]\n(.*?)\n\n/ms', $queue, $m ) );
        $this->assertStringContainsString( "Handler=expKernelTransferQueueHandler\n", $m[1] );
        $this->assertStringContainsString( "Class=bulk\n", $m[1] );
        $this->assertStringContainsString( "MaxAttempts=1\n", $m[1] );
        $this->assertMatchesRegularExpression( '/^Timeout=[0-9]{4,}$/m', $m[1] );
    }

    /** TR-09 */
    public function testTheWiring()
    {
        $job = self::source( 'classes/bccieJob.php' );
        $this->assertSame( 1, preg_match( '/static function start\(.*?\n    \}/s', $job, $m ) );
        $this->assertLessThan( strpos( $m[0], 'expProcessTools::setsid()' ), strpos( $m[0], 'bccieTransfer::submit(' ), 'the runner first, then the old way' );
        $this->assertStringContainsString( "if ( \$run['engine'] !== 'process' )", $m[0] );

        $command = self::source( 'classes/runnable/commands/php_export.php' );
        $this->assertStringContainsString( '\bccieTransfer::commandKeepsHistory( $state )', $command );
        $this->assertStringContainsString( '\bccieJob::read( $jobID )', $command, 'the command reads the job file with its engine' );
        $this->assertStringContainsString( '+ \bccieTransfer::jobKeys( $state )', $command, 'every write keeps the engine' );
        $this->assertStringContainsString( "\\bccieTransfer::record( \$normalized, array( 'state' => 'done'", $command, 'console exports are recorded' );

        $runner = self::source( 'classes/bccieRunner.php' );
        $this->assertStringContainsString( "bccieTransfer::record( \$options, array( 'state' => 'done'", $runner, 'cron exports are recorded' );
        $page = self::source( 'classes/bccieExportPage.php' );
        $this->assertStringContainsString( "'engine' => 'inline'", $page, 'downloads are recorded' );
        $this->assertStringContainsString( 'bccieJob::start( $options, $error, $count )', $page );
    }

    /** TR-10 */
    public function testTheTemplates()
    {
        $guard = "{if ezini_hasvariable( 'Service_transfer.registry', 'Class', 'kernelservices.ini' )}{include uri='design:parts/bccie/transfer_links.tpl'";
        foreach ( array( 'design/standard/templates/parts/bccie/job.tpl', 'design/standard/templates/bccie/export.tpl', 'design/standard/templates/bccie/overview.tpl' ) as $path )
        {
            $source = self::source( $path );
            $this->assertStringContainsString( $guard, $source, $path );
            $this->assertSame( substr_count( $source, "transfer_links.tpl" ), substr_count( $source, $guard ) + substr_count( $source, "kernelservices.ini' )} {include uri='design:parts/bccie/transfer_links.tpl'" ), $path . ': every include behind the check' );
        }
        $links = self::source( 'design/standard/templates/parts/bccie/transfer_links.tpl' );
        $this->assertStringContainsString( "transfer_tool( 'transfer.sqliimport' )", $links );
        $this->assertStringContainsString( '$cie_imports.active', $links );
        $this->assertStringContainsString( "eq( \$format, 'csv' )", $links );
        $this->assertStringContainsString( "'sqliimport/edit/new/(from)/bccie/(job)/'", $links );
        $this->assertStringContainsString( "'sqliimport/list/(tool)/transfer.bccie'", $links );
        $this->assertStringNotContainsString( 'style=', $links );

        $ts = self::source( 'translations/ger-DE/translation.ts' );
        foreach ( array( 'Open in Imports', 'All import and export runs', 'queued', 'cancelled' ) as $text )
        {
            $this->assertStringContainsString( '<source>' . $text . '</source>', $ts );
        }
    }
}
