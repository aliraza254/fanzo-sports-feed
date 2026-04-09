<?php
/**
 * Admin settings page and notices for Fanzo Sports Feed.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_Admin
 *
 * Registers the Settings menu page, all settings fields via the
 * WordPress Settings API, handles the clear-cache action, and
 * outputs admin notices.
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_Admin {

	/**
	 * Settings option group name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_GROUP = 'fanzo_settings_group';

	/**
	 * Settings page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'fanzo-sports-feed';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FanzoSportsFeed_Admin
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @since  1.0.0
	 * @return FanzoSportsFeed_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers all admin hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Register the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Fanzo Sports Feed Settings', 'fanzo-sports-feed' ),
			__( 'Fanzo Sports Feed', 'fanzo-sports-feed' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register all settings, sections, and fields via the Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Register options with the Settings API.
		register_setting(
			self::OPTION_GROUP,
			'fanzo_api_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'fanzo_api_url_json',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'fanzo_feed_enabled',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'fanzo_cache_duration',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_cache_duration' ),
				'default'           => 12 * HOUR_IN_SECONDS,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'fanzo_disabled_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => __( 'The fixtures feed is currently unavailable. Please check back soon.', 'fanzo-sports-feed' ),
			)
		);

		// Settings section: API.
		add_settings_section(
			'fanzo_section_api',
			__( 'API Configuration', 'fanzo-sports-feed' ),
			array( $this, 'render_section_api' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'fanzo_api_url',
			__( 'XML Endpoint URL', 'fanzo-sports-feed' ),
			array( $this, 'render_field_api_url' ),
			self::PAGE_SLUG,
			'fanzo_section_api'
		);

		add_settings_field(
			'fanzo_api_url_json',
			__( 'JSON Endpoint URL', 'fanzo-sports-feed' ),
			array( $this, 'render_field_api_url_json' ),
			self::PAGE_SLUG,
			'fanzo_section_api'
		);

		add_settings_field(
			'fanzo_feed_enabled',
			__( 'Feed Status', 'fanzo-sports-feed' ),
			array( $this, 'render_field_feed_enabled' ),
			self::PAGE_SLUG,
			'fanzo_section_api'
		);

		// Settings section: Cache.
		add_settings_section(
			'fanzo_section_cache',
			__( 'Cache Settings', 'fanzo-sports-feed' ),
			array( $this, 'render_section_cache' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'fanzo_cache_duration',
			__( 'Cache Duration', 'fanzo-sports-feed' ),
			array( $this, 'render_field_cache_duration' ),
			self::PAGE_SLUG,
			'fanzo_section_cache'
		);

		add_settings_field(
			'fanzo_disabled_message',
			__( 'Disabled Feed Message', 'fanzo-sports-feed' ),
			array( $this, 'render_field_disabled_message' ),
			self::PAGE_SLUG,
			'fanzo_section_cache'
		);
	}

	/**
	 * Sanitize a checkbox value to '1' or '0'.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Raw form value.
	 * @return string       '1' or '0'.
	 */
	public function sanitize_checkbox( $value ) {
		return ( '1' === $value || true === $value ) ? '1' : '0';
	}

	/**
	 * Sanitize the cache duration dropdown to one of the allowed values.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Raw form value.
	 * @return int          Sanitized duration in seconds.
	 */
	public function sanitize_cache_duration( $value ) {
		$allowed = array(
			HOUR_IN_SECONDS,
			6 * HOUR_IN_SECONDS,
			12 * HOUR_IN_SECONDS,
			DAY_IN_SECONDS,
		);
		$value = (int) $value;
		if ( ! in_array( $value, $allowed, true ) ) {
			return 12 * HOUR_IN_SECONDS;
		}
		return $value;
	}

	/**
	 * Handle the clear-cache admin action.
	 *
	 * @since 1.0.0
	 */
	public function handle_clear_cache() {
		if ( ! isset( $_GET['fanzo_action'] ) || 'clear_cache' !== $_GET['fanzo_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fanzo-sports-feed' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanzo_clear_cache' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'fanzo-sports-feed' ) );
		}

		$cache = new FanzoSportsFeed_Cache();
		$cache->bust_all_caches();

		// Reset fetch metadata.
		delete_option( 'fanzo_last_fetch' );
		delete_option( 'fanzo_last_fetch_status' );

		// Redirect back with a success flag.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'fanzo_msg'   => 'cache_cleared',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue admin CSS only on the plugin settings page.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'fanzo-admin',
			FANZO_SPORTS_FEED_PLUGIN_URL . 'assets/css/fanzo-admin.css',
			array(),
			FANZO_SPORTS_FEED_VERSION
		);
	}

	/**
	 * Display transient-based admin notices on the settings page.
	 *
	 * @since 1.0.0
	 */
	public function display_admin_notices() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['fanzo_msg'] ) && 'cache_cleared' === $_GET['fanzo_msg'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Fanzo cache cleared successfully. The next page load will fetch fresh data from the API.', 'fanzo-sports-feed' )
				. '</p></div>';
		}

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Fanzo Sports Feed settings saved.', 'fanzo-sports-feed' )
				. '</p></div>';
		}
	}

	// ─────────────────────────────────────────────────────────
	// Section & Field Rendering Callbacks
	// ─────────────────────────────────────────────────────────

	/**
	 * Render the API settings section description.
	 *
	 * @since 1.0.0
	 */
	public function render_section_api() {
		echo '<p>' . esc_html__( 'Configure your Fanzo feed endpoint(s). You may enter an XML URL, a JSON URL, or both. The shortcode uses the JSON URL when both are set.', 'fanzo-sports-feed' ) . '</p>';
	}

	/**
	 * Render the Cache settings section description.
	 *
	 * @since 1.0.0
	 */
	public function render_section_cache() {
		echo '<p>' . esc_html__( 'Control how long fixture data is cached and what message appears when the feed is disabled.', 'fanzo-sports-feed' ) . '</p>';
	}

	/**
	 * Render the XML API URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_field_api_url() {
		$value = get_option( 'fanzo_api_url', '' );
		?>
		<input
			type="url"
			id="fanzo_api_url"
			name="fanzo_api_url"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://www-service.fanzo.com/venues/XXXX/fixture/xml?newFields=1"
		>
		<p class="description">
			<?php esc_html_e( 'Legacy XML endpoint. Used only if the JSON URL above is left empty.', 'fanzo-sports-feed' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the JSON API URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_field_api_url_json() {
		$value = get_option( 'fanzo_api_url_json', '' );
		?>
		<input
			type="url"
			id="fanzo_api_url_json"
			name="fanzo_api_url_json"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://www-service.fanzo.com/venues/XXXX/fixture/json"
		>
		<p class="description">
			<?php esc_html_e( 'Recommended. The JSON endpoint provides richer data (team logos, channel logos, big-screen and sound indicators). Takes priority over the XML URL when set.', 'fanzo-sports-feed' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the feed enabled/disabled checkbox.
	 *
	 * @since 1.0.0
	 */
	public function render_field_feed_enabled() {
		$value = get_option( 'fanzo_feed_enabled', '1' );
		?>
		<label for="fanzo_feed_enabled">
			<input
				type="checkbox"
				id="fanzo_feed_enabled"
				name="fanzo_feed_enabled"
				value="1"
				<?php checked( '1', $value ); ?>
			>
			<?php esc_html_e( 'Enable the fixture feed on the frontend', 'fanzo-sports-feed' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Uncheck to globally hide the feed. The shortcode will display the disabled message below instead.', 'fanzo-sports-feed' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the cache duration dropdown.
	 *
	 * @since 1.0.0
	 */
	public function render_field_cache_duration() {
		$current = (int) get_option( 'fanzo_cache_duration', 12 * HOUR_IN_SECONDS );
		$options = array(
			HOUR_IN_SECONDS        => __( '1 hour', 'fanzo-sports-feed' ),
			6 * HOUR_IN_SECONDS    => __( '6 hours', 'fanzo-sports-feed' ),
			12 * HOUR_IN_SECONDS   => __( '12 hours (recommended)', 'fanzo-sports-feed' ),
			DAY_IN_SECONDS         => __( '24 hours', 'fanzo-sports-feed' ),
		);
		?>
		<select id="fanzo_cache_duration" name="fanzo_cache_duration">
			<?php foreach ( $options as $seconds => $label ) : ?>
				<option value="<?php echo esc_attr( $seconds ); ?>" <?php selected( $current, $seconds ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'How long fixture data is cached before a fresh API request is made.', 'fanzo-sports-feed' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the disabled feed message textarea.
	 *
	 * @since 1.0.0
	 */
	public function render_field_disabled_message() {
		$value = get_option( 'fanzo_disabled_message', __( 'The fixtures feed is currently unavailable. Please check back soon.', 'fanzo-sports-feed' ) );
		?>
		<textarea
			id="fanzo_disabled_message"
			name="fanzo_disabled_message"
			rows="3"
			class="large-text"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'This message is shown instead of fixtures when the feed is disabled above.', 'fanzo-sports-feed' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the full settings page HTML.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanzo-sports-feed' ) );
		}

		$last_fetch        = get_option( 'fanzo_last_fetch', '' );
		$last_fetch_status = get_option( 'fanzo_last_fetch_status', '' );
		$clear_cache_url   = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'fanzo_action' => 'clear_cache',
				),
				admin_url( 'options-general.php' )
			),
			'fanzo_clear_cache'
		);
		?>
		<div class="wrap fanzo-admin-wrap">
			<h1>
				<span class="fanzo-admin-logo">⚽</span>
				<?php esc_html_e( 'Fanzo Sports Feed', 'fanzo-sports-feed' ); ?>
			</h1>

			<div class="fanzo-admin-layout">
				<div class="fanzo-admin-main">
					<form method="post" action="options.php">
						<?php
						settings_fields( self::OPTION_GROUP );
						do_settings_sections( self::PAGE_SLUG );
						submit_button( __( 'Save Settings', 'fanzo-sports-feed' ) );
						?>
					</form>
				</div>

				<div class="fanzo-admin-sidebar">

					<!-- Feed Status Card -->
					<div class="fanzo-admin-card fanzo-status-card">
						<h2><?php esc_html_e( 'Feed Status', 'fanzo-sports-feed' ); ?></h2>
						<?php if ( ! empty( $last_fetch ) ) : ?>
							<?php if ( 'success' === $last_fetch_status ) : ?>
								<span class="fanzo-status fanzo-status-ok">&#10003; <?php esc_html_e( 'Last fetch successful', 'fanzo-sports-feed' ); ?></span>
							<?php else : ?>
								<span class="fanzo-status fanzo-status-error">&#10007; <?php esc_html_e( 'Last fetch failed', 'fanzo-sports-feed' ); ?></span>
								<?php if ( ! empty( $last_fetch_status ) && 'success' !== $last_fetch_status ) : ?>
									<p class="fanzo-error-msg"><?php echo esc_html( $last_fetch_status ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
							<p class="fanzo-last-fetch">
								<?php
								/* translators: %s: date and time of last successful fetch */
								printf( esc_html__( 'Last fetched: %s', 'fanzo-sports-feed' ), esc_html( $last_fetch ) );
								?>
							</p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'No fetch recorded yet. The API will be called on first page load.', 'fanzo-sports-feed' ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Clear Cache Card -->
					<div class="fanzo-admin-card">
						<h2><?php esc_html_e( 'Cache', 'fanzo-sports-feed' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Force a fresh API pull on the next page load by clearing the current cache.', 'fanzo-sports-feed' ); ?></p>
						<a href="<?php echo esc_url( $clear_cache_url ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Clear Cache Now', 'fanzo-sports-feed' ); ?>
						</a>
					</div>

					<!-- Shortcode Reference Card -->
					<div class="fanzo-admin-card fanzo-shortcode-card">
						<h2><?php esc_html_e( 'Shortcode', 'fanzo-sports-feed' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Paste this shortcode into any page or post to display the fixture feed.', 'fanzo-sports-feed' ); ?></p>
						<input
							type="text"
							readonly
							value="[fanzo_sports_feed]"
							class="large-text fanzo-shortcode-input"
							id="fanzo_shortcode_ref"
							onclick="this.select();"
							aria-label="<?php esc_attr_e( 'Shortcode', 'fanzo-sports-feed' ); ?>"
						>
						<p class="description">
							<?php esc_html_e( 'With a custom venue URL: ', 'fanzo-sports-feed' ); ?>
						</p>
						<input
							type="text"
							readonly
							value='[fanzo_sports_feed venue="https://..."]'
							class="large-text fanzo-shortcode-input"
							onclick="this.select();"
							aria-label="<?php esc_attr_e( 'Shortcode with venue override', 'fanzo-sports-feed' ); ?>"
						>
					</div>

				</div><!-- .fanzo-admin-sidebar -->
			</div><!-- .fanzo-admin-layout -->
		</div><!-- .wrap -->
		<?php
	}
}
