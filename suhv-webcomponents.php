<?php
/**
 * Plugin Name:       SUHV Web Components
 * Description:       Bindet die offiziellen Swiss Unihockey Web Components über WordPress-Shortcodes ein.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Dorian Nyffeler, unofficial community plugin
 * License:           GPL-2.0-or-later
 * Text Domain:       suhv-webcomponents
 */

defined( 'ABSPATH' ) || exit;

define( 'SUHV_WEBCOMPONENTS_VERSION', '1.1.0' );
define( 'SUHV_WEBCOMPONENTS_FILE', __FILE__ );
define( 'SUHV_WEBCOMPONENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'SUHV_WEBCOMPONENTS_PATH', plugin_dir_path( __FILE__ ) );

require_once SUHV_WEBCOMPONENTS_PATH . 'includes/class-suhv-webcomponents-plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		new SUHV_Webcomponents_Plugin();
	}
);
