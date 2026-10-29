<?php
/**
 * The overview's check-all box has an accessible name of its own, not only a title. Reads the template only,
 * without a kernel or a database:
 *
 *   php vendor/bin/phpunit extension/bccie/tests/bccieTemplateAccessibilityTest.php
 *
 *  CA-01 - Every check-all box (data-cie-check-all) has an aria-label
 *  CA-02 - Every row's box has an aria-label
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

use PHPUnit\Framework\TestCase;

class bccieTemplateAccessibilityTest extends TestCase
{
    private static function source(): string
    {
        $source = (string)file_get_contents( dirname( __DIR__ ) . '/design/standard/templates/bccie/overview.tpl' );
        return (string)preg_replace( '/\{\*.*?\*\}/s', '', $source );
    }

    /** CA-01 */
    public function testCheckAllBoxHasAnAccessibleName()
    {
        $this->assertSame( 1, preg_match_all( '/<input\b[^>]*data-cie-check-all[^>]*>/', self::source(), $m ) );
        $this->assertStringContainsString( "aria-label=\"{'Select all'|i18n( 'extension/bccie' )|wash}\"", $m[0][0] );
    }

    /** CA-02 */
    public function testRowBoxesHaveAnAccessibleName()
    {
        preg_match_all( '/<input type="checkbox" name="ObjectIDArray\[\]"[^\n]*/', self::source(), $m );
        $this->assertNotEmpty( $m[0] );
        foreach ( $m[0] as $tag )
            $this->assertStringContainsString( 'aria-label="', $tag );
    }
}
