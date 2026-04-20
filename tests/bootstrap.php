<?php
/**
 * PHPUnit bootstrap (no full WordPress test install required).
 *
 * @package FeedToBlogroll
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require dirname( __DIR__ ) . '/includes/options-merge.php';
