<?php
/**
 * Tests for feed_to_blogroll_merge_saved_options().
 *
 * @package FeedToBlogroll
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers ::feed_to_blogroll_merge_saved_options
 */
final class OptionsMergeTest extends TestCase {

	public function test_merge_preserves_runtime_keys(): void {
		$existing = array(
			'last_sync'   => '2020-01-01 12:00:00',
			'sync_status' => 'success',
		);
		$subset = array(
			'feedbin_username' => 'reader@example.com',
			'auto_sync'        => true,
		);

		$merged = feed_to_blogroll_merge_saved_options( $existing, $subset );

		$this->assertSame( '2020-01-01 12:00:00', $merged['last_sync'] );
		$this->assertSame( 'success', $merged['sync_status'] );
		$this->assertSame( 'reader@example.com', $merged['feedbin_username'] );
		$this->assertTrue( $merged['auto_sync'] );
	}
}
