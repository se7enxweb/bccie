<?php
/**
 * bccieTransferTask, the background export of CIE as a transfer task of the kernel's job queue or content jobs, on
 * real job files in a temporary folder: the export command is a stand-in (fixtures/bccie_fake_export_command.php)
 * that writes the job file as the command does. Without the kernel the test declares the three types of the kernel's
 * transfer runs it needs (the interface, the context, the cancel exception), so it runs alike with the kernel's
 * bootstrap and without it:
 *
 *   php vendor/bin/phpunit --no-configuration extension/bccie/tests/bccieTransferTaskTest.php
 *
 *  BT-01 - a finished command: the progress reaches the engine, the result has the rows, the file and its size
 *  BT-02 - the job file gets the engine before the command runs (the command then leaves the history alone)
 *  BT-03 - a job that is done already is not exported again
 *  BT-04 - a command that fails: the run fails with the command's error
 *  BT-05 - a command that dies: the job file and the run say so (exit code)
 *  BT-06 - a cancel stops the command, marks the job cancelled and leaves no half written file
 *  BT-07 - an unknown job fails at once
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

use PHPUnit\Framework\TestCase;

if ( !interface_exists( 'expKernelTransferTask' ) )
{
    interface expKernelTransferTask
    {
        public function run( array $params, expKernelTransferContext $context );
    }
}
if ( !class_exists( 'expKernelTransferCancelledException' ) )
{
    class expKernelTransferCancelledException extends RuntimeException
    {
    }
}
if ( !class_exists( 'expKernelTransferContext' ) )
{
    class expKernelTransferContext
    {
        protected $engine;
        protected $runID;
        protected $jobRef;
        protected $hooks;

        public function __construct( $engine, $tool, $runID = 0, $jobRef = '', array $hooks = array() )
        {
            $this->engine = $engine;
            $this->runID = (int)$runID;
            $this->jobRef = (string)$jobRef;
            $this->hooks = $hooks;
        }

        public function engine() { return $this->engine; }
        public function runID() { return $this->runID; }
        public function jobRef() { return $this->jobRef; }

        public function progress( $done, $total = null, $message = '', $failed = null )
        {
            if ( isset( $this->hooks['progress'] ) )
                call_user_func( $this->hooks['progress'], (int)$done, $total, (string)$message );
        }

        public function heartbeat()
        {
            if ( isset( $this->hooks['heartbeat'] ) )
                call_user_func( $this->hooks['heartbeat'] );
        }

        public function isCancelled()
        {
            return isset( $this->hooks['cancelled'] ) && (bool)call_user_func( $this->hooks['cancelled'] );
        }

        public function log( $line )
        {
        }
    }
}

require_once dirname( __DIR__ ) . '/classes/bccieJob.php';
require_once dirname( __DIR__ ) . '/classes/bccieTransfer.php';
require_once dirname( __DIR__ ) . '/classes/bccieTransferTask.php';

/** bccieJob with its folder in the test's temporary folder */
class bccieTransferTaskTestJob extends bccieJob
{
    public static $dir = '';

    static function directory()
    {
        return self::$dir;
    }
}

/** bccieTransferTask with the stand-in command and the test's job class */
class bccieTransferTaskTestTask extends bccieTransferTask
{
    public static $mode = 'done';
    protected $pollSeconds = 0.05;
    protected $stopSeconds = 2;

    protected function jobClass()
    {
        return 'bccieTransferTaskTestJob';
    }

    protected function command( $jobID, array $state )
    {
        return array( PHP_BINARY, __DIR__ . '/fixtures/bccie_fake_export_command.php', bccieTransferTaskTestJob::$dir, $jobID, self::$mode );
    }

    protected function workingDirectory()
    {
        return bccieTransferTaskTestJob::$dir;
    }
}

class bccieTransferTaskTest extends TestCase
{
    const JOB = '20261022120000-0a1b2c3d';

    protected function setUp(): void
    {
        bccieTransferTaskTestJob::$dir = sys_get_temp_dir() . '/bccie-task-' . bin2hex( random_bytes( 4 ) );
        mkdir( bccieTransferTaskTestJob::$dir, 0700, true );
        bccieTransferTaskTestTask::$mode = 'done';
        bccieTransferTaskTestJob::write( self::JOB, array( 'id' => self::JOB, 'command' => 'export', 'status' => 'queued', 'started' => time(),
                                                           'by' => 'editor', 'user_id' => 14, 'siteaccess' => 'admin',
                                                           'options' => array( 'object_id' => 57, 'format' => 'csv', 'charset' => '' ), 'result' => null ) );
    }

    protected function tearDown(): void
    {
        foreach ( (array)glob( bccieTransferTaskTestJob::$dir . '/*' ) as $file )
        {
            unlink( $file );
        }
        rmdir( bccieTransferTaskTestJob::$dir );
    }

    /**
     * @return array the context and what reached it: progress (list of [done, total]), cancelled (set it to true)
     */
    protected function context( &$seen )
    {
        $seen = array( 'progress' => array(), 'cancel' => false, 'polls' => 0 );
        return new expKernelTransferContext( 'expqueue', 'transfer.bccie', 0, 'uuid-1', array(
            'progress' => function ( $done, $total ) use ( &$seen ) { $seen['progress'][] = array( $done, $total ); },
            'cancelled' => function () use ( &$seen ) { ++$seen['polls']; return $seen['cancel'] === true || ( $seen['cancel'] === 'soon' && $seen['polls'] > 2 ); },
        ) );
    }

    /** BT-01 */
    public function testAFinishedCommandGivesItsRowsAndFile()
    {
        $task = new bccieTransferTaskTestTask();
        $result = $task->run( array( 'job' => self::JOB ), $this->context( $seen ) );

        $this->assertSame( 400, $result['rows_done'] );
        $this->assertSame( 400, $result['rows_total'] );
        $this->assertSame( 'csv', $result['format'] );
        $this->assertSame( self::JOB . '.csv', $result['file_ref'] );
        $this->assertSame( strlen( "ID;Name\n1;Ada\n" ), $result['bytes'] );
        $this->assertContains( array( 200, 400 ), $seen['progress'] );
        $this->assertSame( 'done', bccieTransferTaskTestJob::read( self::JOB )['status'] );
    }

    /** BT-02 */
    public function testTheJobFileGetsTheEngineFirst()
    {
        $task = new bccieTransferTaskTestTask();
        $task->run( array( 'job' => self::JOB ), $this->context( $seen ) );
        $state = bccieTransferTaskTestJob::read( self::JOB );

        $this->assertSame( 'expqueue', $state['engine'] );
        $this->assertSame( 'uuid-1', $state['job_ref'] );
        $this->assertFalse( bccieTransfer::commandKeepsHistory( $state ) );
        $this->assertSame( 14, $state['user_id'], 'the owner stays with the job' );
    }

    /** BT-03 */
    public function testADoneJobIsNotExportedAgain()
    {
        file_put_contents( bccieTransferTaskTestJob::$dir . '/' . self::JOB . '.csv', "ID\n1\n2\n" );
        $state = bccieTransferTaskTestJob::read( self::JOB );
        $state['status'] = 'done';
        $state['result'] = array( 'rows' => 2, 'done' => 2, 'total' => 2, 'file' => self::JOB . '.csv' );
        bccieTransferTaskTestJob::write( self::JOB, $state );
        bccieTransferTaskTestTask::$mode = 'fail';

        $task = new bccieTransferTaskTestTask();
        $result = $task->run( array( 'job' => self::JOB ), $this->context( $seen ) );
        $this->assertSame( 2, $result['rows_done'] );
        $this->assertSame( array(), $seen['progress'], 'the command did not run' );
    }

    /** BT-04 */
    public function testAFailedCommandFailsTheRun()
    {
        bccieTransferTaskTestTask::$mode = 'fail';
        $task = new bccieTransferTaskTestTask();
        try
        {
            $task->run( array( 'job' => self::JOB ), $this->context( $seen ) );
            $this->fail( 'no exception' );
        }
        catch ( RuntimeException $e )
        {
            $this->assertNotInstanceOf( 'expKernelTransferCancelledException', $e );
            $this->assertSame( 'Another export is running.', $e->getMessage() );
        }
        $this->assertSame( 'failed', bccieTransferTaskTestJob::read( self::JOB )['status'] );
    }

    /** BT-05 */
    public function testACommandThatDiesIsMarkedFailed()
    {
        bccieTransferTaskTestTask::$mode = 'crash';
        $task = new bccieTransferTaskTestTask();
        try
        {
            $task->run( array( 'job' => self::JOB ), $this->context( $seen ) );
            $this->fail( 'no exception' );
        }
        catch ( RuntimeException $e )
        {
            $this->assertSame( 'The export stopped with exit code 3.', $e->getMessage() );
        }
        $state = bccieTransferTaskTestJob::read( self::JOB );
        $this->assertSame( 'failed', $state['status'] );
        $this->assertSame( 'The export stopped with exit code 3.', $state['result']['error'] );
    }

    /** BT-06 */
    public function testACancelStopsTheCommand()
    {
        bccieTransferTaskTestTask::$mode = 'slow';
        $task = new bccieTransferTaskTestTask();
        $context = $this->context( $seen );
        $seen['cancel'] = 'soon';
        $started = microtime( true );
        try
        {
            $task->run( array( 'job' => self::JOB ), $context );
            $this->fail( 'no exception' );
        }
        catch ( expKernelTransferCancelledException $e )
        {
            $this->assertSame( 'The export was cancelled.', $e->getMessage() );
        }
        $this->assertLessThan( 5, microtime( true ) - $started, 'the command was stopped, not waited for' );
        $this->assertSame( 'cancelled', bccieTransferTaskTestJob::read( self::JOB )['status'] );
        $this->assertFileDoesNotExist( bccieTransferTaskTestJob::$dir . '/' . self::JOB . '.csv' );
    }

    /** BT-07 */
    public function testAnUnknownJobFails()
    {
        $task = new bccieTransferTaskTestTask();
        $this->expectException( RuntimeException::class );
        $task->run( array( 'job' => '20261022120000-ffffffff' ), $this->context( $seen ) );
    }
}
