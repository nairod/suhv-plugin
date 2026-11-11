=== SUHV Web Components ===
Contributors: community
Tags: swiss unihockey, unihockey, floorball, shortcode
Requires at least: 6.4
Requires PHP: 8.1
Tested up to: 6.8
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed the current Swiss Unihockey web components with WordPress shortcodes.

== Description ==

This unofficial plugin bundles the Swiss Unihockey web-component JavaScript and CSS and renders the documented components from shortcodes. Live data and interaction are provided by the public Swiss Unihockey component/API service; JavaScript is required for the live display.

Configure optional defaults under Settings > SUHV Web Components. A shortcode attribute always overrides a configured default.

The settings page also includes a team shortcode generator: enter a club ID and season to retrieve the club's teams and copy codes for team games/results and the relevant ranking table. Team data is retrieved from the public Swiss Unihockey web-component service and cached temporarily.

Available shortcodes:

* `[uniho-league-games team-id="429494" season="2025"]`
* `[uniho-league-games game-class="21" league="3" season="2025" group="Gruppe 1"]`
* `[uniho-club-team-games club-id="372" season="2025" page-size="6"]`
* `[uniho-team-games team-id="429626" season="2025" page-size="5"]`
* `[uniho-club-games club-id="467" season="2025"]`
* `[uniho-ranking season="2025" league="3" game-class="21" group="Gruppe 1"]`
* `[uniho-mobiliar-topscorer season="2025" club-id="463845"]`

The attributes use the same names as the official custom elements. WordPress-friendly underscore forms such as `team_id`, `game_class`, and `page_size` are accepted too.

This plugin deliberately does not register the obsolete `suhv-api-*` shortcodes. Several of their old views, including direct encounters, date-range/weekend lists, game details, and filtered played/next-game views, have no equivalent in the documented component set.

== Installation ==

1. Upload the `suhv-webcomponents` folder to `/wp-content/plugins/` or upload a ZIP through Plugins > Add New.
2. Activate SUHV Web Components.
3. Optionally set your club, team, league, and season defaults under Settings > SUHV Web Components.
4. Place a shortcode in a page, post, or shortcode-capable widget.

== External services ==

The bundled JavaScript requests live match data from `https://wc.swissunihockey.ch/` in the visitor's browser. Requests contain the identifiers configured in the shortcode or plugin settings, such as club ID, team ID, league, group, and season.

== Local testing ==

Docker is required for the included local test commands.

* Run `tests/run-php-tests.sh` to execute the shortcode tests in PHP 8.3.
* Run `tests/local-wordpress.sh` to start a temporary WordPress 6.8 / PHP 8.3 site at `http://localhost:8080`. Complete the installer, activate the plugin, and create a page with one of the examples above.
* Run `tests/local-wordpress.sh --stop` to remove the temporary containers and Docker network.

== Changelog ==

= 1.1.0 =
* Add a settings-page generator for copyable team-game and ranking shortcodes.

= 1.0.0 =
* Initial release with six documented Swiss Unihockey component shortcodes.
