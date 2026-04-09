<?php
/**
 * Plugin Name:       Fanzo Sports Feed
 * Plugin URI:        https://github.com/aliraza254/
 * Description:       Fetches sports fixture data from the Fanzo XML API and displays it on the frontend via a shortcode, Gutenberg block, and classic widget. Includes a full admin settings interface.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Muhammad Ali Raza
 * Author URI:        https://www.linkedin.com/in/muhammad-ali-raza-449587230/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fanzo-sports-feed
 * Domain Path:       /languages
 *
 * @package FanzoSportsFeed
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FANZO_SPORTS_FEED_VERSION', '1.0.0' );
define( 'FANZO_SPORTS_FEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FANZO_SPORTS_FEED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FANZO_SPORTS_FEED_PLUGIN_FILE', __FILE__ );
define( 'FANZO_SPORTS_FEED_TEXT_DOMAIN', 'fanzo-sports-feed' );

/**
 * Load all plugin class files immediately.
 *
 * Classes must be available before plugins_loaded because
 * register_activation_hook and register_deactivation_hook fire
 * during the activation request, which precedes plugins_loaded.
 *
 * @since 1.0.0
 */
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-activator.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-api.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-cache.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-shortcode.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-admin.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-widget.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-block.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-rest-api.php';
require_once FANZO_SPORTS_FEED_PLUGIN_DIR . 'includes/class-fanzo-cli.php';

/**
 * Load the plugin text domain for translations.
 *
 * @since 1.0.0
 */
function fanzo_load_textdomain() {
	load_plugin_textdomain(
		FANZO_SPORTS_FEED_TEXT_DOMAIN,
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'fanzo_load_textdomain' );

/**
 * Initialise all plugin components.
 *
 * Runs on plugins_loaded so all WordPress APIs are fully available.
 *
 * @since 1.0.0
 */
function fanzo_init_plugin() {
	FanzoSportsFeed_Shortcode::get_instance();
	FanzoSportsFeed_Admin::get_instance();
	FanzoSportsFeed_Block::get_instance();
	FanzoSportsFeed_RestAPI::get_instance();

	// Register the classic widget.
	add_action( 'widgets_init', 'fanzo_register_widget' );

	// Register WP-CLI command.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'fanzo', 'FanzoSportsFeed_CLI' );
	}
}
add_action( 'plugins_loaded', 'fanzo_init_plugin' );

/**
 * Register the classic widget.
 *
 * @since 1.0.0
 */
function fanzo_register_widget() {
	register_widget( 'FanzoSportsFeed_Widget' );
}

// Activation and deactivation hooks — classes are loaded above so these are safe.
register_activation_hook( __FILE__, array( 'FanzoSportsFeed_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FanzoSportsFeed_Activator', 'deactivate' ) );
