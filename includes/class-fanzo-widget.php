<?php
/**
 * Classic WordPress widget showing upcoming fixtures.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_Widget
 *
 * A classic WordPress widget that displays upcoming fixtures for
 * the next N days in a sidebar. The number of days is configurable
 * in the widget admin form.
 *
 * @since 1.0.0
 * @extends WP_Widget
 */
class FanzoSportsFeed_Widget extends WP_Widget {

	/**
	 * Constructor. Registers the widget.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			'fanzo_sports_feed_widget',
			__( 'Fanzo Sports Feed', 'fanzo-sports-feed' ),
			array(
				'description' => __( 'Displays upcoming sports fixtures from the Fanzo feed.', 'fanzo-sports-feed' ),
				'classname'   => 'fanzo-widget',
			)
		);
	}

	/**
	 * Output the widget HTML on the frontend.
	 *
	 * @since 1.0.0
	 * @param array $args     Widget display arguments (before_widget, after_widget, etc.).
	 * @param array $instance Saved widget settings.
	 */
	public function widget( $args, $instance ) {
		$feed_enabled = get_option( 'fanzo_feed_enabled', '1' );
		if ( '0' === $feed_enabled ) {
			return;
		}

		$title   = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Fixtures', 'fanzo-sports-feed' );
		$days    = isset( $instance['days'] ) ? (int) $instance['days'] : 7;
		$api_url = get_option( 'fanzo_api_url', '' );

		if ( empty( $api_url ) ) {
			return;
		}

		$cache   = new FanzoSportsFeed_Cache();
		$data    = $cache->get_fixtures( $api_url );

		if ( is_wp_error( $data ) || empty( $data['fixtures'] ) ) {
			return;
		}

		$today    = gmdate( 'Y-m-d' );
		$end_date = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

		// Filter fixtures within the date window.
		$upcoming = array_filter(
			$data['fixtures'],
			static function ( $f ) use ( $today, $end_date ) {
				return $f['date_key'] >= $today && $f['date_key'] <= $end_date;
			}
		);

		if ( empty( $upcoming ) ) {
			return;
		}

		echo wp_kses_post( $args['before_widget'] );
		echo wp_kses_post( $args['before_title'] ) . esc_html( apply_filters( 'widget_title', $title ) ) . wp_kses_post( $args['after_title'] );

		echo '<ul class="fanzo-widget-list">';
		foreach ( $upcoming as $f ) {
			echo '<li class="fanzo-widget-item">';
			echo '<span class="fanzo-widget-date">' . esc_html( $f['date_label'] ) . '</span> ';
			echo '<span class="fanzo-widget-time">' . esc_html( $f['time'] ) . '</span> ';
			echo '<span class="fanzo-widget-title">' . esc_html( $f['title'] ) . '</span>';
			if ( ! empty( $f['sport'] ) ) {
				echo ' <em class="fanzo-widget-sport">(' . esc_html( $f['sport'] ) . ')</em>';
			}
			echo '</li>';
		}
		echo '</ul>';

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Render the widget admin settings form.
	 *
	 * @since 1.0.0
	 * @param array $instance Current saved settings.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Fixtures', 'fanzo-sports-feed' );
		$days  = isset( $instance['days'] ) ? (int) $instance['days'] : 7;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'fanzo-sports-feed' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>"
			>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'days' ) ); ?>">
				<?php esc_html_e( 'Days ahead to show:', 'fanzo-sports-feed' ); ?>
			</label>
			<input
				class="tiny-text"
				id="<?php echo esc_attr( $this->get_field_id( 'days' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'days' ) ); ?>"
				type="number"
				min="1"
				max="30"
				value="<?php echo esc_attr( $days ); ?>"
			>
		</p>
		<?php
	}

	/**
	 * Sanitize and save widget settings.
	 *
	 * @since  1.0.0
	 * @param  array $new_instance Submitted widget settings.
	 * @param  array $old_instance Previous settings.
	 * @return array               Sanitized settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['days']  = ! empty( $new_instance['days'] ) ? min( 30, max( 1, (int) $new_instance['days'] ) ) : 7;
		return $instance;
	}
}
