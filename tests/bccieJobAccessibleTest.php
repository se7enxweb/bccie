<?php
/**
 * bccieJob::accessible() on real job files in a temporary folder. A subclass answers the three seams of the class
 * (directory(), currentUserID(), fetchObject()), so the test needs no session and no database and declares no class
 * of the kernel: it runs alike with the kernel's bootstrap and without it:
 *
 *   php vendor/bin/phpunit --no-configuration extension/bccie/tests/bccieJobAccessibleTest.php
 *
 *  JB-01 - a job id without a job file, and an id of the wrong form, are refused
 *  JB-02 - a job of another user is refused, its owner may have it
 *  JB-03 - the user's own job is accessible while the user can read the object
 *  JB-04 - the user's own job is refused when the object cannot be read or does not exist (any more)
 *  JB-05 - a job written before the owner was stored (no user_id) is refused
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/classes/bccieJob.php';

/** bccieJob with its folder, its user and its objects in memory */
class bccieJobAccessibleTestJob extends bccieJob
{
    public static $dir = '';
    public static $userID = 0;
    /** @var array object id => readable */
    public static $objects = array();

    static function directory()
    {
        return self::$dir;
    }

    protected static function currentUserID()
    {
        return self::$userID;
    }

    protected static function fetchObject( $objectID )
    {
        return isset( self::$objects[$objectID] ) ? new bccieJobAccessibleTestObject( self::$objects[$objectID] ) : null;
    }
}

/** An object that can or cannot be read */
class bccieJobAccessibleTestObject
{
    private $readable;

    public function __construct( $readable )
    {
        $this->readable = $readable;
    }

    public function canRead()
    {
        return $this->readable;
    }
}

class bccieJobAccessibleTest extends TestCase
{
    const MINE = '20261020120000-0a1b2c3d';
    const OTHER = '20261020120001-0a1b2c3e';
    const LEGACY = '20261020120002-0a1b2c3f';
    const UNREADABLE = '20261020120003-0a1b2c40';
    const GONE = '20261020120004-0a1b2c41';

    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bccie-accessible-' . bin2hex( random_bytes( 4 ) );
        mkdir( $this->dir, 0700, true );
        bccieJobAccessibleTestJob::$dir = $this->dir;
        bccieJobAccessibleTestJob::$userID = 14;
        bccieJobAccessibleTestJob::$objects = array( 100 => true, 101 => false );
        $jobs = array(
            self::MINE => array( 'user_id' => 14, 'options' => array( 'object_id' => 100 ) ),
            self::OTHER => array( 'user_id' => 15, 'options' => array( 'object_id' => 100 ) ),
            self::LEGACY => array( 'by' => 'admin', 'options' => array( 'object_id' => 100 ) ),
            self::UNREADABLE => array( 'user_id' => 14, 'options' => array( 'object_id' => 101 ) ),
            self::GONE => array( 'user_id' => 14, 'options' => array( 'object_id' => 102 ) ),
        );
        foreach ( $jobs as $id => $state )
            file_put_contents( $this->dir . '/' . $id . '.json', json_encode( $state + array( 'id' => $id, 'status' => 'done' ) ) );
    }

    protected function tearDown(): void
    {
        if ( $this->dir && is_dir( $this->dir ) )
        {
            array_map( 'unlink', glob( $this->dir . '/*' ) ?: array() );
            rmdir( $this->dir );
        }
    }

    /** JB-01 */
    public function testAJobThatDoesNotExistIsRefused()
    {
        $this->assertFileDoesNotExist( $this->dir . '/20261020999999-ffffffff.json' );
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( '20261020999999-ffffffff' ) );
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( '../' . self::MINE ) );
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( '' ) );
    }

    /** JB-02 */
    public function testAJobOfAnotherUserIsRefused()
    {
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( self::OTHER ) );
        bccieJobAccessibleTestJob::$userID = 15;
        $this->assertTrue( bccieJobAccessibleTestJob::accessible( self::OTHER ), 'its owner may' );
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( self::MINE ) );
    }

    /** JB-03 */
    public function testTheOwnJobIsAccessible()
    {
        $this->assertTrue( bccieJobAccessibleTestJob::accessible( self::MINE ) );
    }

    /** JB-04 */
    public function testTheOwnJobIsRefusedWithoutReadAccessToTheObject()
    {
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( self::UNREADABLE ) );
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( self::GONE ) );
    }

    /** JB-05 */
    public function testAJobWithoutAnOwnerIsRefused()
    {
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( self::LEGACY ) );
        bccieJobAccessibleTestJob::$userID = 0;
        $this->assertFalse( bccieJobAccessibleTestJob::accessible( self::LEGACY ), 'not even for anonymous 0' );
    }
}
