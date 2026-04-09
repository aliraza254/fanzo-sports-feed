<?php
/**
 * Gutenberg block registration for Fanzo Sports Feed.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_Block
 *
 * Registers a simple server-rendered Gutenberg block that lets
 * editors insert the Fanzo Sports Feed from the block editor
 * without needing to know the shortcode syntax.
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_Block {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FanzoSportsFeed_Block
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @since  1.0.0
	 * @return FanzoSportsFeed_Block
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the server-side Gutenberg block.
	 *
	 * @since 1.0.0
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'fanzo-sports-feed/fixture-feed',
			array(
				'api_version'     => 2,
				'title'           => __( 'Fanzo Sports Feed', 'fanzo-sports-feed' ),
				'category'        => 'widgets',
				'icon'            => 'megaphone',
				'description'     => __( 'Display live sports fixtures from the Fanzo feed.', 'fanzo-sports-feed' ),
				'supports'        => array(
					'html'  => false,
					'align' => array( 'wide', 'full' ),
				),
				'attributes'      => array(
					'venue' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'fanzo-block-editor',
				'editor_style'    => 'fanzo-block-editor-style',
			)
		);

		// Register the block editor script (inline — no build step required).
		wp_register_script(
			'fanzo-block-editor',
			FANZO_SPORTS_FEED_PLUGIN_URL . 'assets/js/fanzo-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			FANZO_SPORTS_FEED_VERSION,
			true
		);

		wp_set_script_translations( 'fanzo-block-editor', 'fanzo-sports-feed', FANZO_SPORTS_FEED_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Server-side render callback for the Gutenberg block.
	 *
	 * Delegates entirely to the shortcode renderer so the same
	 * output logic is shared between [fanzo_sports_feed] and the block.
	 *
	 * @since  1.0.0
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Inner content (unused).
	 * @return string               Rendered HTML.
	 */
	public function render_block( $attributes, $content ) {
		$venue   = ! empty( $attributes['venue'] ) ? sanitize_url( $attributes['venue'] ) : '';
		$atts    = ! empty( $venue ) ? array( 'venue' => $venue ) : array();

		$shortcode = FanzoSportsFeed_Shortcode::get_instance();
		return $shortcode->render_shortcode( $atts );
	}
}
