<?php
/**
 * Handles plugin activation and deactivation.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_Activator
 *
 * Manages the activation and deactivation lifecycle of the Fanzo Sports Feed plugin.
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * Sets default option values if they do not already exist.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Set default options only if they don't exist.
		if ( false === get_option( 'fanzo_api_url' ) ) {
			add_option( 'fanzo_api_url', '' );
		}

		if ( false === get_option( 'fanzo_feed_enabled' ) ) {
			add_option( 'fanzo_feed_enabled', '1' );
		}

		if ( false === get_option( 'fanzo_cache_duration' ) ) {
			// Default: 12 hours in seconds.
			add_option( 'fanzo_cache_duration', (string) ( 12 * HOUR_IN_SECONDS ) );
		}

		if ( false === get_option( 'fanzo_disabled_message' ) ) {
			add_option( 'fanzo_disabled_message', __( 'The fixtures feed is currently unavailable. Please check back soon.', 'fanzo-sports-feed' ) );
		}

		if ( false === get_option( 'fanzo_db_version' ) ) {
			add_option( 'fanzo_db_version', FANZO_SPORTS_FEED_VERSION );
		}

		// Flush rewrite rules after activation.
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Does NOT delete any settings or data. Only flushes rewrite rules and
	 * clears any scheduled cron events registered by this plugin.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear any cron events the plugin may have scheduled.
		$timestamp = wp_next_scheduled( 'fanzo_scheduled_cache_refresh' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'fanzo_scheduled_cache_refresh' );
		}

		flush_rewrite_rules();
	}
}
