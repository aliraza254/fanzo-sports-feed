=== Fanzo Sports Feed ===
Contributors: fanzo
Tags: sports, fixtures, feed, schedule, football
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays live sports fixture data from the Fanzo XML API via a shortcode, Gutenberg block, or classic widget with full admin settings.

== Description ==

**Fanzo Sports Feed** connects your WordPress site to the Fanzo sports data platform, pulling live fixture schedules from the Fanzo XML API and presenting them in a polished, filterable, date-navigated feed that works across any pub or venue theme.

= Key Features =

* **[fanzo_sports_feed] Shortcode** — Drop the fixture feed into any page or post.
* **Gutenberg Block** — Insert the feed from the block editor without touching shortcode syntax.
* **Classic Widget** — Show upcoming fixtures in sidebars for the next N days.
* **Sport Filter Dropdown** — Auto-populated from the feed; filters fixtures by sport in real time.
* **Date Navigator Strip** — Scrolls through all available fixture dates, 7 at a time, with arrow pagination. Auto-selects the nearest upcoming fixture date.
* **Transient Caching** — All API responses are cached using the WordPress Transients API. Cache duration is configurable (1, 6, 12, or 24 hours).
* **Manual Cache Clear** — One-click cache bust from the admin settings page, protected with a nonce.
* **Feed Enable/Disable Toggle** — Globally disable the feed and show a custom message instead.
* **Feed Status Dashboard** — See whether the last API call succeeded and when it was made.
* **REST API Endpoint** — `/wp-json/fanzo/v1/fixtures` returns fixture JSON for headless or decoupled setups.
* **WP-CLI Command** — `wp fanzo clear-cache` busts the cache from the command line.
* **Debug Logging** — When `WP_DEBUG` is enabled, all API errors are written to the WordPress debug log.
* **Per-venue Cache Keys** — Multiple shortcodes pointing to different venues never share a cache entry.
* **Fully Responsive** — Works down to 320px mobile width.
* **Print Styles** — Hides filters and displays all fixtures in a clean list when printed.
* **Translation Ready** — Ships with a `.pot` file and uses the `fanzo-sports-feed` text domain.

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* A valid Fanzo XML feed URL

= Usage =

1. Go to **Settings → Fanzo Sports Feed** and enter your Fanzo API URL.
2. Place `[fanzo_sports_feed]` in any page, or use the **Fanzo Sports Feed** Gutenberg block.
3. Optionally place the **Fanzo Sports Feed** widget in a sidebar.

= Shortcode Parameters =

`[fanzo_sports_feed]`
Uses the globally configured API URL from the settings page.

`[fanzo_sports_feed venue="https://www-service.fanzo.com/venues/1234/fixture/xml?newFields=1"]`
Overrides the global URL for this specific shortcode instance. Useful for multi-venue sites.

= REST API =

Authenticate with a WordPress application password, then call:

`GET /wp-json/fanzo/v1/fixtures`

Optional query parameter: `?venue=<url>` to specify a different feed URL.

= WP-CLI =

`wp fanzo clear-cache`
Clears all Fanzo transient caches across all venues.

`wp fanzo status`
Displays the current plugin settings and last fetch status.

= Privacy =

This plugin makes outbound HTTP requests to the Fanzo API server configured in the settings. No visitor data is transmitted to Fanzo.

== Installation ==

**Via the WordPress Admin:**

1. Go to **Plugins → Add New → Upload Plugin**.
2. Upload the `fanzo-sports-feed.zip` file.
3. Click **Activate Plugin**.
4. Navigate to **Settings → Fanzo Sports Feed** and enter your API endpoint URL.

**Via FTP:**

1. Upload the `fanzo-sports-feed` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → Fanzo Sports Feed** and enter your API endpoint URL.

== Frequently Asked Questions ==

= Where do I find my Fanzo API URL? =

Your Fanzo account manager will provide you with a URL in the format:
`https://www-service.fanzo.com/venues/XXXX/fixture/xml?newFields=1`
where `XXXX` is your unique venue ID.

= The fixture feed is not displaying. What should I check? =

1. Confirm the API URL is saved correctly in **Settings → Fanzo Sports Feed**.
2. Check that the **Feed Status** checkbox is ticked (feed enabled).
3. Click **Clear Cache Now** to force a fresh API request.
4. Enable `WP_DEBUG` and check the debug log for any `[Fanzo Sports Feed]` error messages.

= Can I show fixtures for multiple venues on the same site? =

Yes. Use the `venue` attribute on the shortcode:
`[fanzo_sports_feed venue="https://www-service.fanzo.com/venues/5678/fixture/xml?newFields=1"]`
Each venue URL gets its own independent cache entry.

= How do I disable the feed temporarily without deleting the settings? =

Uncheck the **Feed Status** checkbox in **Settings → Fanzo Sports Feed** and save. The shortcode will display the configured disabled message instead.

= Does this plugin add any database tables? =

No. The plugin uses the standard WordPress Options API for settings and the Transients API for caching. No custom database tables are created.

= Is the REST API endpoint public? =

No. The `/wp-json/fanzo/v1/fixtures` endpoint requires authentication. You can use a logged-in session (cookie + nonce) or a WordPress Application Password (Basic Auth).

= How do I clear the cache from the command line? =

`wp fanzo clear-cache`

= Why are team logos not showing? =

Team logo URLs are provided by the Fanzo API in the `team1` and `team2` XML fields. If logos are missing, the API may not have images on file for those teams. For F1 fixtures, a static logo is used automatically.

= Which PHP version is required? =

PHP 7.4 or higher. PHP 8.0+ is recommended.

== Screenshots ==

1. Admin settings page showing API URL, feed status, cache duration, and cache clear button.
2. Frontend fixture display with date navigator strip, sport filter, and fixture cards.
3. Date navigator with active day selected and arrow pagination.
4. Gutenberg block sidebar inspector showing the optional venue URL field.
5. Classic widget admin form showing title and days-ahead settings.

== Changelog ==

= 1.0.0 =
* Initial release.
* Shortcode [fanzo_sports_feed] with optional venue attribute.
* Gutenberg server-side block with venue inspector control.
* Classic WordPress widget with configurable days-ahead setting.
* Admin settings page (Settings API): API URL, feed enabled toggle, cache duration, disabled message.
* Manual cache clear with nonce protection.
* Feed status card showing last fetch time and success/error state.
* Transient caching per venue with configurable duration.
* REST API endpoint at /wp-json/fanzo/v1/fixtures.
* WP-CLI commands: clear-cache, status.
* Date navigator strip (7 dates, arrow pagination, auto-selects nearest upcoming date).
* Sport filter dropdown auto-populated from live feed data.
* Fully responsive CSS from 1200px to 320px.
* Print stylesheet.
* Translation-ready with fanzo-sports-feed.pot.
* WP_DEBUG logging with [Fanzo Sports Feed] prefix.
* Activation defaults, deactivation cleanup, uninstall cleanup.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
