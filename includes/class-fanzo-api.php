<?php
/**
 * Handles fetching and parsing the Fanzo API (XML and JSON formats).
 *
 * Auto-detects the endpoint format from the URL:
 *   - URLs ending in /json  →  parsed as JSON
 *   - All other URLs         →  parsed as XML (legacy)
 *
 * Both formats output the same normalised fixture array so the rest of
 * the plugin (cache, shortcode, widget, REST API) works unchanged.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FanzoSportsFeed_API
 *
 * Fetches sports fixture data from the Fanzo API and normalises it
 * into a consistent PHP array regardless of the source format.
 *
 * Normalised fixture array shape:
 * [
 *   'date_key'        => 'Y-m-d',
 *   'date_label'      => 'Monday 9th April',
 *   'sport'           => 'Football',
 *   'sport_logo'      => 'https://...',   // JSON only; '' for XML
 *   'description'     => 'Premier League',
 *   'competition_logo'=> 'https://...',   // JSON only; '' for XML
 *   'title'           => 'Arsenal vs Chelsea',
 *   'team1'           => 'https://...logo.png',
 *   'team2'           => 'https://...logo.png',
 *   'time'            => '8.00pm',
 *   'channel'         => 'Sky Sports Main Event',
 *   'channel_logo'    => 'https://...',   // JSON only; '' for XML
 *   'competition'     => 'Premier League',
 *   'big_screen'      => false,           // JSON only
 *   'sound'           => false,           // JSON only
 * ]
 *
 * @since 1.0.0
 */
class FanzoSportsFeed_API {

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const REQUEST_TIMEOUT = 15;

	/**
	 * Fetch and parse fixture data from the given URL.
	 *
	 * Automatically selects the XML or JSON parser based on the URL.
	 *
	 * @since  1.0.0
	 * @param  string $api_url The full Fanzo endpoint URL.
	 * @return array|WP_Error  Normalised fixture data array, or WP_Error on failure.
	 */
	public function fetch( $api_url ) {
		if ( empty( $api_url ) ) {
			return new WP_Error(
				'fanzo_empty_url',
				__( 'The Fanzo API URL is not configured. Please set it in Settings → Fanzo Sports Feed.', 'fanzo-sports-feed' )
			);
		}

		$response = wp_remote_get(
			esc_url_raw( $api_url ),
			array(
				'timeout'    => self::REQUEST_TIMEOUT,
				'user-agent' => 'FanzoSportsFeed/' . FANZO_SPORTS_FEED_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( WP_DEBUG ) {
				error_log( '[Fanzo Sports Feed] HTTP request failed: ' . $response->get_error_message() );
			}
			return new WP_Error(
				'fanzo_http_error',
				/* translators: %s: error message from WordPress HTTP API */
				sprintf( __( 'Failed to connect to the Fanzo API: %s', 'fanzo-sports-feed' ), $response->get_error_message() )
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $http_code ) {
			if ( WP_DEBUG ) {
				error_log( '[Fanzo Sports Feed] Unexpected HTTP status: ' . $http_code );
			}
			return new WP_Error(
				'fanzo_http_status',
				/* translators: %d: HTTP status code */
				sprintf( __( 'The Fanzo API returned an unexpected HTTP status: %d', 'fanzo-sports-feed' ), $http_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return new WP_Error(
				'fanzo_empty_body',
				__( 'The Fanzo API returned an empty response.', 'fanzo-sports-feed' )
			);
		}

		// Auto-detect format from URL path.
		if ( self::is_json_endpoint( $api_url ) ) {
			return $this->parse_json( $body );
		}

		return $this->parse_xml( $body );
	}

	// ─────────────────────────────────────────────────────────────
	// JSON Parser
	// ─────────────────────────────────────────────────────────────

	/**
	 * Parse the Fanzo JSON response body.
	 *
	 * Expected structure:
	 * {
	 *   "result": [
	 *     {
	 *       "id": 751419,
	 *       "eventName": "Arsenal vs Chelsea",
	 *       "startTimeLocal": "2026-04-09T14:00:00+01:00",
	 *       "sport": { "id": 21, "name": "Football", "logo": "https://..." },
	 *       "competition": { "id": 60, "name": "Premier League", "logo": "https://..." },
	 *       "teams": [
	 *         { "id": 5552, "name": "Arsenal", "side": "home", "logo": "https://..." },
	 *         { "id": 5986, "name": "Chelsea", "side": "away", "logo": "https://..." }
	 *       ],
	 *       "channel": { "id": 1000, "name": "Sky Sports Main Event", "logo": "https://..." },
	 *       "venueEvent": { "bigScreen": false, "sound": false }
	 *     }
	 *   ]
	 * }
	 *
	 * @since  1.0.0
	 * @param  string $body Raw JSON string.
	 * @return array|WP_Error Normalised fixture data, or WP_Error on failure.
	 */
	private function parse_json( $body ) {
		$data = json_decode( $body, true );

		if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
			$error_msg = json_last_error_msg();
			if ( WP_DEBUG ) {
				error_log( '[Fanzo Sports Feed] JSON parse failed: ' . $error_msg );
			}
			return new WP_Error(
				'fanzo_json_parse',
				/* translators: %s: JSON error message */
				sprintf( __( 'Failed to parse the Fanzo JSON feed: %s', 'fanzo-sports-feed' ), $error_msg )
			);
		}

		if ( ! isset( $data['result'] ) || ! is_array( $data['result'] ) ) {
			return new WP_Error(
				'fanzo_json_structure',
				__( 'The Fanzo JSON feed has an unexpected structure. No "result" array found.', 'fanzo-sports-feed' )
			);
		}

		$fixtures = array();
		$sports   = array();
		$dates    = array();

		foreach ( $data['result'] as $item ) {
			if ( empty( $item['startTimeLocal'] ) ) {
				continue;
			}

			$start_timestamp = strtotime( $item['startTimeLocal'] );
			if ( false === $start_timestamp ) {
				continue;
			}

			$date_key   = gmdate( 'Y-m-d', $start_timestamp );
			$date_label = gmdate( 'l jS F', $start_timestamp );

			// -- Sport --
			$sport_name = '';
			$sport_logo = '';
			if ( isset( $item['sport'] ) && is_array( $item['sport'] ) ) {
				$sport_name = sanitize_text_field( $item['sport']['name'] ?? '' );
				$sport_logo = esc_url_raw( $item['sport']['logo'] ?? '' );
			}

			// -- Competition --
			$competition_name = '';
			$competition_logo = '';
			if ( isset( $item['competition'] ) && is_array( $item['competition'] ) ) {
				$competition_name = sanitize_text_field( $item['competition']['name'] ?? '' );
				$competition_logo = esc_url_raw( $item['competition']['logo'] ?? '' );
			}

			// -- Teams --
			$team1_logo = $sport_logo; // Fallback: use sport logo when no teams.
			$team2_logo = $sport_logo;
			if ( ! empty( $item['teams'] ) && is_array( $item['teams'] ) ) {
				foreach ( $item['teams'] as $team ) {
					$side = $team['side'] ?? '';
					$logo = esc_url_raw( $team['logo'] ?? '' );
					if ( 'home' === $side ) {
						$team1_logo = $logo;
					} elseif ( 'away' === $side ) {
						$team2_logo = $logo;
					}
				}
			}

			// -- Channel --
			$channel_name = '';
			$channel_logo = '';
			if ( isset( $item['channel'] ) && is_array( $item['channel'] ) ) {
				$channel_name = sanitize_text_field( $item['channel']['name'] ?? '' );
				// Skip base64 data URIs (some channels use them) — use only real URLs.
				$raw_logo = $item['channel']['logo'] ?? '';
				if ( 0 !== strpos( $raw_logo, 'data:' ) ) {
					$channel_logo = esc_url_raw( $raw_logo );
				}
			}

			// -- Venue event flags --
			$big_screen = ! empty( $item['venueEvent']['bigScreen'] );
			$sound      = ! empty( $item['venueEvent']['sound'] );

			// -- Collect unique sports / dates --
			if ( $sport_name && ! in_array( $sport_name, $sports, true ) ) {
				$sports[] = $sport_name;
			}
			if ( ! in_array( $date_key, $dates, true ) ) {
				$dates[] = $date_key;
			}

			$fixtures[] = array(
				'date_key'         => $date_key,
				'date_label'       => $date_label,
				'sport'            => $sport_name,
				'sport_logo'       => $sport_logo,
				'description'      => $competition_name,
				'competition'      => $competition_name,
				'competition_logo' => $competition_logo,
				'title'            => sanitize_text_field( $item['eventName'] ?? '' ),
				'team1'            => $team1_logo,
				'team2'            => $team2_logo,
				'time'             => gmdate( 'g.ia', $start_timestamp ),
				'channel'          => $channel_name,
				'channel_logo'     => $channel_logo,
				'big_screen'       => $big_screen,
				'sound'            => $sound,
			);
		}

		sort( $dates );

		return array(
			'fixtures' => $fixtures,
			'sports'   => $sports,
			'dates'    => $dates,
			'format'   => 'json',
		);
	}

	// ─────────────────────────────────────────────────────────────
	// XML Parser (unchanged from original)
	// ─────────────────────────────────────────────────────────────

	/**
	 * Parse the raw XML body into a normalised fixture array.
	 *
	 * @since  1.0.0
	 * @param  string $body Raw XML string from the API.
	 * @return array|WP_Error Normalised fixture data, or WP_Error on failure.
	 */
	private function parse_xml( $body ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );

		if ( false === $xml ) {
			$errors    = libxml_get_errors();
			libxml_clear_errors();
			$error_msg = ! empty( $errors ) ? $errors[0]->message : __( 'Unknown XML parse error.', 'fanzo-sports-feed' );

			if ( WP_DEBUG ) {
				error_log( '[Fanzo Sports Feed] XML parse failed: ' . $error_msg );
			}

			return new WP_Error(
				'fanzo_xml_parse',
				/* translators: %s: XML error message */
				sprintf( __( 'Failed to parse the fixture XML feed: %s', 'fanzo-sports-feed' ), $error_msg )
			);
		}

		if ( ! isset( $xml->channel->item ) ) {
			return new WP_Error(
				'fanzo_xml_structure',
				__( 'The Fanzo XML feed has an unexpected structure. No fixture items found.', 'fanzo-sports-feed' )
			);
		}

		$fixtures = array();
		$sports   = array();
		$dates    = array();

		foreach ( $xml->channel->item as $item ) {
			$start_timestamp = strtotime( (string) $item->startTimeLocal );

			if ( false === $start_timestamp ) {
				continue;
			}

			$date_key    = gmdate( 'Y-m-d', $start_timestamp );
			$date_label  = gmdate( 'l jS F', $start_timestamp );
			$sport       = sanitize_text_field( (string) $item->sport );
			$description = sanitize_text_field( (string) $item->description );

			if ( ! in_array( $sport, $sports, true ) ) {
				$sports[] = $sport;
			}
			if ( ! in_array( $date_key, $dates, true ) ) {
				$dates[] = $date_key;
			}

			$f1_logo   = 'https://dunningsbar.com/wp-content/uploads/logo-F1.png';
			$team1_raw = sanitize_url( (string) $item->team1 );
			$team2_raw = sanitize_url( (string) $item->team2 );
			$team1_src = ( 'F1' === $description ) ? $f1_logo : $team1_raw;
			$team2_src = ( 'F1' === $description ) ? $f1_logo : $team2_raw;

			// -- Venue event flags --
			$big_screen = ( trim( (string) $item->bigScreen ) === '1' );
			$sound      = ( trim( (string) $item->sound ) === '1' );

			$fixtures[] = array(
				'date_key'         => $date_key,
				'date_label'       => $date_label,
				'sport'            => $sport,
				'sport_logo'       => esc_url_raw( (string) $item->sportLogo ),
				'description'      => $description,
				'competition'      => $description,
				'competition_logo' => '', // Not provided in XML
				'title'            => sanitize_text_field( (string) $item->title ),
				'team1'            => esc_url_raw( $team1_src ),
				'team2'            => esc_url_raw( $team2_src ),
				'time'             => gmdate( 'g.ia', $start_timestamp ),
				'channel'          => sanitize_text_field( (string) $item->channelName ),
				'channel_logo'     => '', // Not provided in XML
				'big_screen'       => $big_screen,
				'sound'            => $sound,
			);
		}

		sort( $dates );

		return array(
			'fixtures' => $fixtures,
			'sports'   => $sports,
			'dates'    => $dates,
			'format'   => 'xml',
		);
	}

	// ─────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────

	/**
	 * Determine whether a URL points to a JSON endpoint.
	 *
	 * Matches any URL whose path ends with /json (with or without query string).
	 *
	 * @since  1.0.0
	 * @param  string $url API URL to inspect.
	 * @return bool        True if this is a JSON endpoint.
	 */
	public static function is_json_endpoint( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return $path && ( '/json' === substr( $path, -5 ) );
	}

	/**
	 * Extract a numeric venue ID from a Fanzo API URL.
	 *
	 * Works for both XML and JSON endpoint URLs:
	 *   /venues/3219/fixture/xml
	 *   /venues/1037/fixture/json
	 *
	 * @since  1.0.0
	 * @param  string $api_url The Fanzo API URL.
	 * @return string          Venue ID string, or a short hash fallback.
	 */
	public static function extract_venue_id( $api_url ) {
		if ( preg_match( '/venues\/(\d+)/', $api_url, $matches ) ) {
			return $matches[1];
		}
		return substr( md5( $api_url ), 0, 8 );
	}
}
