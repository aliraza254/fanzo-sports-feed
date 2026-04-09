<?php
/**
 * Shortcode registration and frontend output for Fanzo Sports Feed.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_Shortcode
 *
 * Registers the [fanzo_sports_feed] shortcode and renders the full
 * fixture display HTML, including the sport filter, date navigator,
 * and fixture cards.
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FanzoSportsFeed_Shortcode
	 */
	private static $instance = null;

	/**
	 * Flag to conditionally enqueue assets only on pages with the shortcode.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $shortcode_rendered = false;

	/**
	 * Get or create the singleton instance.
	 *
	 * @since  1.0.0
	 * @return FanzoSportsFeed_Shortcode
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
		add_shortcode( 'fanzo_sports_feed', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Render the [fanzo_sports_feed] shortcode output.
	 *
	 * Accepted attributes:
	 *   venue  — overrides the global API URL for this specific shortcode instance.
	 *
	 * @since  1.0.0
	 * @param  array  $atts   Shortcode attributes.
	 * @param  string $content Enclosed content (unused).
	 * @return string          HTML output.
	 */
	public function render_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'venue' => '',
			),
			$atts,
			'fanzo_sports_feed'
		);

		// Mark as rendered so assets can be enqueued.
		$this->shortcode_rendered = true;

		// Check if feed is globally enabled.
		$feed_enabled = get_option( 'fanzo_feed_enabled', '1' );
		if ( '0' === $feed_enabled ) {
			$message = get_option( 'fanzo_disabled_message', __( 'The fixtures feed is currently unavailable. Please check back soon.', 'fanzo-sports-feed' ) );
			return $this->render_alert( $message, 'info' );
		}

		// Determine which API URL to use.
		// Priority: [venue=""] shortcode attribute > JSON option > XML option.
		if ( ! empty( $atts['venue'] ) ) {
			$api_url = esc_url_raw( $atts['venue'] );
		} else {
			$json_url = get_option( 'fanzo_api_url_json', '' );
			$xml_url  = get_option( 'fanzo_api_url', '' );
			$api_url  = ! empty( $json_url ) ? $json_url : $xml_url;
		}

		if ( empty( $api_url ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return $this->render_alert(
					__( 'Fanzo Sports Feed: No API URL configured. Please visit Settings → Fanzo Sports Feed and add a JSON or XML endpoint.', 'fanzo-sports-feed' ),
					'warning'
				);
			}
			return '';
		}

		// Instantiate cache handler.
		$cache = new FanzoSportsFeed_Cache();

		// Handle manual refresh: bust the cache for this URL if requested.
		if ( isset( $_GET['fanzo_refresh'] ) && '1' === $_GET['fanzo_refresh'] ) {
			$cache->bust_cache( $api_url );
		}

		// Fetch data (cached).
		$data = $cache->get_fixtures( $api_url );

		if ( is_wp_error( $data ) ) {
			$error_msg = current_user_can( 'manage_options' )
				? $data->get_error_message()
				: __( 'Fixture data is temporarily unavailable. Please try again later.', 'fanzo-sports-feed' );

			return $this->render_alert( $error_msg, 'error', true );
		}

		$fixtures = $data['fixtures'];
		$sports   = $data['sports'];
		$dates    = $data['dates'];

		if ( empty( $fixtures ) ) {
			$cur_month = strtoupper( current_time( 'M' ) );
			$cur_day   = current_time( 'j' );

			return '<div class="fanzo-sports-feed">' .
				'<div class="fanzo-empty-state">' .
					'<div class="fanzo-dynamic-icon" aria-hidden="true">' .
						'<span class="fanzo-icon-month">' . esc_html( $cur_month ) . '</span>' .
						'<span class="fanzo-icon-day">' . esc_html( $cur_day ) . '</span>' .
					'</div>' .
					'<h2>' . esc_html__( 'No Fixtures Scheduled', 'fanzo-sports-feed' ) . '</h2>' .
					'<p>' . esc_html__( 'We couldn\'t find any upcoming matches for this venue at the moment. Please check back later or refresh the feed.', 'fanzo-sports-feed' ) . '</p>' .
					'<div class="fanzo-empty-actions">' .
						'<a href="' . esc_url( add_query_arg( 'fanzo_refresh', '1' ) ) . '" class="fanzo-refresh-btn">' . esc_html__( 'Refresh Feed', 'fanzo-sports-feed' ) . '</a>' .
					'</div>' .
				'</div>' .
			'</div>';
		}

		// Determine default date (nearest upcoming fixture).
		$today        = current_time( 'Y-m-d' );
		$default_date = $today;
		foreach ( $dates as $d ) {
			if ( $d >= $today ) {
				$default_date = $d;
				break;
			}
		}

		// Build output HTML using output buffering.
		ob_start();
		$this->render_feed_html( $fixtures, $sports, $dates, $default_date, $today );
		return ob_get_clean();
	}

	/**
	 * Render the full feed HTML.
	 *
	 * @since 1.0.0
	 * @param array  $fixtures     Array of fixture data.
	 * @param array  $sports       Array of unique sport names.
	 * @param array  $dates        Array of unique date keys (Y-m-d).
	 * @param string $default_date The default active date key.
	 * @param string $today        Today's date key.
	 */
	private function render_feed_html( $fixtures, $sports, $dates, $default_date, $today ) {
		?>
		<div class="fanzo-sports-feed">

			<div class="fanzo-filters">

				<!-- Sport Filter Dropdown -->
				<div class="fanzo-sport-filter">
					<label for="fanzo_sport_select" class="screen-reader-text">
						<?php esc_html_e( 'Filter by sport', 'fanzo-sports-feed' ); ?>
					</label>
					<select id="fanzo_sport_select" name="fanzo_sport_select">
						<option value="all"><?php esc_html_e( 'Filter by sport', 'fanzo-sports-feed' ); ?></option>
						<?php foreach ( $sports as $sport ) : ?>
							<option value="<?php echo esc_attr( $sport ); ?>">
								<?php echo esc_html( $sport ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Date Navigator -->
				<div class="fanzo-date-nav" role="navigation" aria-label="<?php esc_attr_e( 'Date navigation', 'fanzo-sports-feed' ); ?>">
					<button class="fanzo-date-arrow fanzo-prev-arrow" aria-label="<?php esc_attr_e( 'Previous dates', 'fanzo-sports-feed' ); ?>">&#8249;</button>
					<div class="fanzo-date-strip" id="fanzo_date_strip" role="list">
						<?php foreach ( $dates as $d ) :
							$ts       = strtotime( $d );
							$day_name = ( $d === $today ) ? esc_html__( 'TODAY', 'fanzo-sports-feed' ) : strtoupper( gmdate( 'D', $ts ) );
							$day_date = gmdate( 'j M', $ts );
							$active   = ( $d === $default_date ) ? ' fanzo-date-active' : '';
							?>
							<button
								class="fanzo-date-btn<?php echo esc_attr( $active ); ?>"
								data-date="<?php echo esc_attr( $d ); ?>"
								role="listitem"
								aria-pressed="<?php echo ( $d === $default_date ) ? 'true' : 'false'; ?>"
							>
								<span class="fanzo-date-day"><?php echo esc_html( $day_name ); ?></span>
								<span class="fanzo-date-num"><?php echo esc_html( $day_date ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
					<button class="fanzo-date-arrow fanzo-next-arrow" aria-label="<?php esc_attr_e( 'Next dates', 'fanzo-sports-feed' ); ?>">&#8250;</button>
				</div>

			</div><!-- .fanzo-filters -->

			<!-- Fixtures Output -->
			<div class="fanzo-fixtures-container">
				<?php
				$current_date = '';
				$group_open   = false;
				foreach ( $fixtures as $f ) :
					if ( $f['date_key'] !== $current_date ) :
						if ( $group_open ) :
							?>
							</div><!-- .fanzo-fixture-group -->
						</div><!-- .fanzo-day -->
							<?php
						endif;
						$hidden_attr = ( $f['date_key'] !== $default_date ) ? ' style="display:none"' : '';
						?>
						<div class="fanzo-day" data-date-group="<?php echo esc_attr( $f['date_key'] ); ?>"<?php echo $hidden_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute already escaped above ?>>
							<h3 class="api_heading"><?php echo esc_html( $f['date_label'] ); ?></h3>
							<div class="fanzo-fixture-group api_group">
						<?php
						$current_date = $f['date_key'];
						$group_open   = true;
					endif;
					?>
					<div class="api_item" data-date="<?php echo esc_attr( $f['date_key'] ); ?>" data-sport="<?php echo esc_attr( $f['sport'] ); ?>">

						<div class="api_details">
							<?php if ( ! empty( $f['sport_logo'] ) ) : ?>
								<img class="api_sport_logo" src="<?php echo esc_url( $f['sport_logo'] ); ?>" alt="<?php echo esc_attr( $f['sport'] ); ?>" loading="lazy">
							<?php endif; ?>
							<h3 class="api_sport"><?php echo esc_html( $f['sport'] ); ?></h3>
							<?php if ( ! empty( $f['competition_logo'] ) ) : ?>
								<img class="api_competition_logo" src="<?php echo esc_url( $f['competition_logo'] ); ?>" alt="<?php echo esc_attr( $f['description'] ); ?>" loading="lazy">
							<?php endif; ?>
							<?php if ( ! empty( $f['description'] ) ) : ?>
								<p class="api_description"><?php echo esc_html( $f['description'] ); ?></p>
							<?php endif; ?>
						</div>

						<img class="api_team api_team_1" src="<?php echo esc_url( $f['team1'] ); ?>" alt="<?php echo esc_attr( $f['title'] ); ?>" loading="lazy">

						<h3 class="api_title"><?php echo esc_html( $f['title'] ); ?></h3>

						<img class="api_team api_team_2" src="<?php echo esc_url( $f['team2'] ); ?>" alt="<?php echo esc_attr( $f['title'] ); ?>" loading="lazy">

						<div class="api_meta">
							<span class="api_time"><?php echo esc_html( $f['time'] ); ?></span>
							<?php if ( ! empty( $f['channel'] ) ) : ?>
								<span class="api_channel">
									<?php if ( ! empty( $f['channel_logo'] ) ) : ?>
										<img class="api_channel_logo" src="<?php echo esc_url( $f['channel_logo'] ); ?>" alt="<?php echo esc_attr( $f['channel'] ); ?>" loading="lazy">
									<?php else : ?>
										<?php echo esc_html( $f['channel'] ); ?>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<?php if ( ! empty( $f['big_screen'] ) ) : ?>
								<span class="api_badge api_badge_bigscreen" title="<?php esc_attr_e( 'Shown on big screen', 'fanzo-sports-feed' ); ?>"><?php esc_html_e( 'Big Screen', 'fanzo-sports-feed' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $f['sound'] ) ) : ?>
								<span class="api_badge api_badge_sound" title="<?php esc_attr_e( 'Shown with sound', 'fanzo-sports-feed' ); ?>"><?php esc_html_e( 'Sound', 'fanzo-sports-feed' ); ?></span>
							<?php endif; ?>
						</div>

					</div><!-- .api_item -->
					<?php
				endforeach;

				if ( $group_open ) :
					?>
					</div><!-- .fanzo-fixture-group -->
				</div><!-- .fanzo-day -->
				<?php endif; ?>

				<!-- No fixtures message (hidden by default; shown via JS) -->
				<div class="fanzo-no-fixtures" style="display:none">
					<p><?php esc_html_e( 'No fixtures available for the selected date and sport.', 'fanzo-sports-feed' ); ?></p>
				</div>

			</div><!-- .fanzo-fixtures-container -->

		</div><!-- .fanzo-sports-feed -->
		<?php
	}

	/**
	 * Conditionally enqueue frontend assets only if the shortcode has been rendered.
	 *
	 * This hook fires after the shortcode is processed because shortcodes run
	 * during the_content filter which is called before wp_footer.
	 *
	 * @since 1.0.0
	 */
	public function maybe_enqueue_assets() {
		// Always register — enqueue selectively.
		wp_register_style(
			'fanzo-frontend',
			FANZO_SPORTS_FEED_PLUGIN_URL . 'assets/css/fanzo-frontend.css',
			array(),
			FANZO_SPORTS_FEED_VERSION
		);

		wp_register_script(
			'fanzo-filters',
			FANZO_SPORTS_FEED_PLUGIN_URL . 'assets/js/fanzo-filters.js',
			array(),
			FANZO_SPORTS_FEED_VERSION,
			true // Load in footer.
		);

		// Enqueue only if we know the shortcode is active.
		// For correct detection on cached pages, we check has_shortcode on queried object.
		if ( $this->page_has_shortcode() ) {
			wp_enqueue_style( 'fanzo-frontend' );
			wp_enqueue_script( 'fanzo-filters' );
		}
	}

	/**
	 * Determine whether the current page contains the shortcode.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function page_has_shortcode() {
		global $post;

		if ( $this->shortcode_rendered ) {
			return true;
		}

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'fanzo_sports_feed' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Render a premium alert/notice box.
	 *
	 * @since  1.1.0
	 * @param  string $message     The message to display.
	 * @param  string $type        'error', 'warning', or 'info'.
	 * @param  bool   $add_refresh Whether to add a refresh button.
	 * @return string              HTML output.
	 */
	private function render_alert( $message, $type = 'info', $add_refresh = false ) {
		$icons = array(
			'error'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
			'warning' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
			'info'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M11 7h2v2h-2V7zm0 4h2v6h-2v-6zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>',
		);

		$icon_html = isset( $icons[ $type ] ) ? $icons[ $type ] : $icons['info'];
		$class     = "fanzo-alert fanzo-alert-{$type}";

		ob_start();
		?>
		<div class="fanzo-sports-feed">
			<div class="<?php echo esc_attr( $class ); ?>" role="alert">
				<div class="fanzo-alert-icon">
					<?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="fanzo-alert-content">
					<p><?php echo esc_html( $message ); ?></p>
					<?php if ( $add_refresh ) : ?>
						<div class="fanzo-alert-actions">
							<a href="<?php echo esc_url( add_query_arg( 'fanzo_refresh', '1' ) ); ?>" class="fanzo-alert-btn">
								<?php esc_html_e( 'Try Again', 'fanzo-sports-feed' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
