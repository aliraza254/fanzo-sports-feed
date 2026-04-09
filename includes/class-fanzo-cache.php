<?php
/**
 * Transient-based caching layer for the Fanzo API.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_Cache
 *
 * Wraps the API class with WordPress Transients API caching.
 * Returns cached data if available, otherwise fetches fresh data
 * from the API, stores it, and records the fetch timestamp.
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_Cache {

	/**
	 * Transient key prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CACHE_KEY_PREFIX = 'fanzo_fixtures_';

	/**
	 * Get the fixture data for a given API URL, using the transient cache.
	 *
	 * @since  1.0.0
	 * @param  string $api_url  The Fanzo feed URL.
	 * @return array|WP_Error   Fixture data array or WP_Error.
	 */
	public function get_fixtures( $api_url ) {
		$cache_key = $this->build_cache_key( $api_url );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			if ( WP_DEBUG ) {
				error_log( '[Fanzo Sports Feed] Cache hit for key: ' . $cache_key );
			}
			return $cached;
		}

		// Cache miss — fetch fresh data.
		$api     = new FanzoSportsFeed_API();
		$data    = $api->fetch( $api_url );

		if ( is_wp_error( $data ) ) {
			// Record the failure status.
			update_option( 'fanzo_last_fetch_status', 'error: ' . $data->get_error_message(), false );
			return $data;
		}

		// Store in transient.
		$duration = (int) get_option( 'fanzo_cache_duration', 12 * HOUR_IN_SECONDS );
		set_transient( $cache_key, $data, $duration );

		// Record successful fetch metadata.
		update_option( 'fanzo_last_fetch', current_time( 'mysql' ), false );
		update_option( 'fanzo_last_fetch_status', 'success', false );

		if ( WP_DEBUG ) {
			error_log( '[Fanzo Sports Feed] Cache refreshed for key: ' . $cache_key );
		}

		return $data;
	}

	/**
	 * Delete the cached transient for a given API URL.
	 *
	 * @since  1.0.0
	 * @param  string $api_url  The Fanzo feed URL.
	 * @return bool             True on success, false if the transient did not exist.
	 */
	public function bust_cache( $api_url ) {
		$cache_key = $this->build_cache_key( $api_url );
		$deleted   = delete_transient( $cache_key );

		if ( WP_DEBUG ) {
			error_log( '[Fanzo Sports Feed] Cache busted for key: ' . $cache_key );
		}

		return $deleted;
	}

	/**
	 * Delete ALL plugin transients regardless of venue.
	 *
	 * @since 1.0.0
	 */
	public function bust_all_caches() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_KEY_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_KEY_PREFIX ) . '%'
			)
		);
	}

	/**
	 * Build the transient cache key for a given API URL.
	 *
	 * Incorporates the venue ID so each venue has an independent cache entry.
	 * WordPress transient keys are limited to 172 characters.
	 *
	 * @since  1.0.0
	 * @param  string $api_url The Fanzo feed URL.
	 * @return string          Cache key string.
	 */
	public function build_cache_key( $api_url ) {
		$venue_id = FanzoSportsFeed_API::extract_venue_id( $api_url );
		return self::CACHE_KEY_PREFIX . $venue_id;
	}
}
