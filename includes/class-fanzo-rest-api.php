<?php
/**
 * REST API endpoint for Fanzo Sports Feed.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_RestAPI
 *
 * Registers a read-only REST API endpoint at:
 *   /wp-json/fanzo/v1/fixtures
 *
 * The endpoint is protected: the caller must either be a logged-in
 * user (cookie + nonce) or use an application password (Basic Auth).
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_RestAPI {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAMESPACE = 'fanzo/v1';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FanzoSportsFeed_RestAPI
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @since  1.0.0
	 * @return FanzoSportsFeed_RestAPI
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers REST API hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/fixtures',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fixtures' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'venue' => array(
						'description'       => __( 'Override Fanzo venue API URL for this request.', 'fanzo-sports-feed' ),
						'type'              => 'string',
						'format'            => 'uri',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Permission callback — requires authentication.
	 *
	 * Accepts:
	 *   - Logged-in users via cookie + nonce (standard WP REST behaviour).
	 *   - Application passwords (Basic Auth header).
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error            True on success; WP_Error on failure.
	 */
	public function permissions_check( $request ) {
		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You must be authenticated to access Fanzo fixture data. Use an application password or log in.', 'fanzo-sports-feed' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Endpoint callback. Returns the fixture data as JSON.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Incoming request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_fixtures( $request ) {
		$feed_enabled = get_option( 'fanzo_feed_enabled', '1' );
		if ( '0' === $feed_enabled ) {
			return new WP_Error(
				'fanzo_feed_disabled',
				__( 'The Fanzo fixture feed is currently disabled.', 'fanzo-sports-feed' ),
				array( 'status' => 503 )
			);
		}

		$venue   = $request->get_param( 'venue' );
		$api_url = ! empty( $venue ) ? $venue : get_option( 'fanzo_api_url', '' );

		if ( empty( $api_url ) ) {
			return new WP_Error(
				'fanzo_no_url',
				__( 'No Fanzo API URL configured.', 'fanzo-sports-feed' ),
				array( 'status' => 503 )
			);
		}

		$cache = new FanzoSportsFeed_Cache();
		$data  = $cache->get_fixtures( $api_url );

		if ( is_wp_error( $data ) ) {
			return new WP_Error(
				$data->get_error_code(),
				$data->get_error_message(),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response(
			array(
				'status'   => 'ok',
				'count'    => count( $data['fixtures'] ),
				'fixtures' => $data['fixtures'],
				'sports'   => $data['sports'],
				'dates'    => $data['dates'],
			)
		);
	}
}
