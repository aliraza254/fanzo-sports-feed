<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options and transients from the database.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only run if WordPress triggered an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
$options = array(
	'fanzo_api_url',
	'fanzo_api_url_json',
	'fanzo_feed_enabled',
	'fanzo_cache_duration',
	'fanzo_disabled_message',
	'fanzo_last_fetch',
	'fanzo_last_fetch_status',
	'fanzo_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete all plugin transients (support multiple venue IDs).
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_fanzo_fixtures_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_fanzo_fixtures_' ) . '%'
	)
);
