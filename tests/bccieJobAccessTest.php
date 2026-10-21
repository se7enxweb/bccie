<?php
/**
 * The state, the log and the file of an export in the background belong to the user who started it, and only while
 * that user can read the object. Reads the sources and calls bccieJob::belongsTo(), without a kernel or a database:
 *
 *   php vendor/bin/phpunit extension/bccie/tests/bccieJobAccessTest.php
 *
 *  JA-01 - belongsTo(): only the stored user id of the job; a job without one belongs to nobody, nor to anonymous 0
 *  JA-02 - bccie/download and bccie/job ask bccieJob::accessible() before they read the job
 *  JA-03 - accessible() checks the owner and canRead() of the job's object
 *  JA-04 - start() stores the user id, and the export command keeps it when it writes the state again
 *  JA-05 - the export page offers a download only for a job the user may have
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/classes/bccieJob.php';

class bccieJobAccessTest extends TestCase
{
    private static function source( $path )
    {
        $source = (string)file_get_contents( dirname( __DIR__ ) . '/' . $path );
        return (string)preg_replace( '#^\s*//.*$#m', '', $source );
    }

    /** JA-01 */
    public function testAJobBelongsToTheUserWhoStartedIt()
    {
        $this->assertTrue( bccieJob::belongsTo( array( 'user_id' => 14 ), 14 ) );
        $this->assertTrue( bccieJob::belongsTo( array( 'user_id' => '14' ), 14 ) );
        $this->assertFalse( bccieJob::belongsTo( array( 'user_id' => 14 ), 15 ) );
        $this->assertFalse( bccieJob::belongsTo( array( 'by' => 'admin' ), 14 ), 'a job without a user id belongs to nobody' );
        $this->assertFalse( bccieJob::belongsTo( array( 'user_id' => 0 ), 0 ) );
        $this->assertFalse( bccieJob::belongsTo( array(), 0 ) );
    }

    /** JA-02 */
    public function testTheViewsAskBeforeTheyRead()
    {
        $download = self::source( 'modules/bccie/download.php' );
        $this->assertMatchesRegularExpression( '/bccieJob::accessible\(\s*\$jobID\s*\)\s*\?\s*bccieJob::exportFile\(/', $download );
        $this->assertLessThan( strpos( $download, 'bccieJob::options(' ), strpos( $download, 'bccieJob::accessible(' ) );

        $job = self::source( 'modules/bccie/job.php' );
        $this->assertMatchesRegularExpression( '/bccieJob::accessible\(\s*\$jobID\s*\)\s*\?\s*bccieJob::status\(/', $job );
    }

    /** JA-03 */
    public function testAccessibleChecksTheOwnerAndTheObject()
    {
        $class = self::source( 'classes/bccieJob.php' );
        $this->assertSame( 1, preg_match( '/static function accessible\(.*?\n    \}/s', $class, $m ) );
        $this->assertStringContainsString( 'self::belongsTo( $state, static::currentUserID() )', $m[0] );
        $this->assertStringContainsString( "\$state['options']['object_id']", $m[0] );
        $this->assertStringContainsString( '$object->canRead()', $m[0] );
        $this->assertStringContainsString( 'return (int)eZUser::currentUserID();', $class );
        $this->assertStringContainsString( 'return eZContentObject::fetch( (int)$objectID );', $class );
    }

    /** JA-04 */
    public function testTheOwnerIsStoredAndKept()
    {
        $this->assertMatchesRegularExpression( "/'user_id' => \(int\)\\\$user->attribute\( 'contentobject_id' \)/", self::source( 'classes/bccieJob.php' ) );
        $command = self::source( 'classes/runnable/commands/php_export.php' );
        $this->assertSame( 1, preg_match( '/function writeJob\(.*?\n    \}/s', $command, $m ) );
        $this->assertStringContainsString( "'user_id' => \$state && isset( \$state['user_id'] )", $m[0] );
    }

    /** JA-05 */
    public function testTheExportPageOffersOnlyTheUsersDownloads()
    {
        $this->assertMatchesRegularExpression( "/\\\$entry\['downloadable'\] = [^;]*bccieJob::accessible\( \\\$entry\['job'\] \)/", self::source( 'classes/bccieExportPage.php' ) );
    }
}
