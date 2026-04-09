<?php
/**
 * WP-CLI command for Fanzo Sports Feed.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_CLI
 *
 * Provides WP-CLI commands under the "fanzo" namespace.
 *
 * Usage:
 *   wp fanzo clear-cache          — Clears all Fanzo transient caches.
 *   wp fanzo status               — Shows current settings and last fetch info.
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_CLI {

	/**
	 * Clears all Fanzo Sports Feed transient caches.
	 *
	 * Forces a fresh API pull on the next page load across all venues.
	 *
	 * ## EXAMPLES
	 *
	 *   wp fanzo clear-cache
	 *
	 * @subcommand clear-cache
	 * @since 1.0.0
	 */
	public function clear_cache() {
		$cache = new FanzoSportsFeed_Cache();
		$cache->bust_all_caches();

		delete_option( 'fanzo_last_fetch' );
		delete_option( 'fanzo_last_fetch_status' );

		WP_CLI::success( 'Fanzo Sports Feed cache cleared. The next page load will fetch fresh data from the API.' );
	}

	/**
	 * Displays the current plugin settings and last fetch status.
	 *
	 * ## EXAMPLES
	 *
	 *   wp fanzo status
	 *
	 * @since 1.0.0
	 */
	public function status() {
		$api_url       = get_option( 'fanzo_api_url', '(not set)' );
		$feed_enabled  = get_option( 'fanzo_feed_enabled', '1' );
		$cache_secs    = (int) get_option( 'fanzo_cache_duration', 12 * HOUR_IN_SECONDS );
		$last_fetch    = get_option( 'fanzo_last_fetch', '(never)' );
		$fetch_status  = get_option( 'fanzo_last_fetch_status', '(unknown)' );

		$items = array(
			array( 'Setting', 'Value' ),
			array( 'API URL', $api_url ),
			array( 'Feed Enabled', ( '1' === $feed_enabled ) ? 'Yes' : 'No' ),
			array( 'Cache Duration', ( $cache_secs / HOUR_IN_SECONDS ) . ' hour(s)' ),
			array( 'Last Fetch', $last_fetch ),
			array( 'Fetch Status', $fetch_status ),
		);

		WP_CLI\Utils\format_items( 'table', array_slice( $items, 1 ), array( 'Setting', 'Value' ) );
	}
}
