<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUHV_WEBCOMPONENTS_VERSION', '1.0.0' );
define( 'SUHV_WEBCOMPONENTS_URL', 'https://example.test/wp-content/plugins/suhv-webcomponents/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['suhv_test_options'] = array();
$GLOBALS['suhv_test_shortcodes'] = array();
$GLOBALS['suhv_test_enqueued'] = array();
$GLOBALS['suhv_test_transients'] = array();
$GLOBALS['suhv_test_http'] = array();

function add_action( ...$args ): void {}
function add_filter( ...$args ): void {}
function add_shortcode( string $tag, callable $callback ): void { $GLOBALS['suhv_test_shortcodes'][ $tag ] = $callback; }
function wp_register_style( ...$args ): void {}
function wp_register_script( ...$args ): void {}
function wp_script_add_data( ...$args ): void {}
function wp_style_is( ...$args ): bool { return true; }
function wp_enqueue_style( string $handle ): void { $GLOBALS['suhv_test_enqueued'][] = $handle; }
function wp_enqueue_script( string $handle ): void { $GLOBALS['suhv_test_enqueued'][] = $handle; }
function get_option( string $name, $default = false ) { return $GLOBALS['suhv_test_options'][ $name ] ?? $default; }
function get_transient( string $key ) { return $GLOBALS['suhv_test_transients'][ $key ] ?? false; }
function set_transient( string $key, $value, int $expiration ): bool { $GLOBALS['suhv_test_transients'][ $key ] = $value; return true; }
function wp_remote_get( string $url, array $args = array() ) { return $GLOBALS['suhv_test_http'][ $url ] ?? array( 'code' => 404, 'body' => '' ); }
function is_wp_error( $thing ): bool { return false; }
function wp_remote_retrieve_response_code( array $response ): int { return $response['code']; }
function wp_remote_retrieve_body( array $response ): string { return $response['body']; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function wp_unslash( string $value ): string { return $value; }
function absint( $value ): int { return abs( (int) $value ); }
function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( string $value, string $domain ): string { return $value; }
function add_options_page( ...$args ): void {}
function register_setting( ...$args ): void {}
function add_settings_section( ...$args ): void {}
function add_settings_field( ...$args ): void {}
function current_user_can( string $capability ): bool { return true; }
function settings_fields( string $group ): void {}
function do_settings_sections( string $page ): void {}
function submit_button(): void {}

require_once __DIR__ . '/../includes/class-suhv-webcomponents-plugin.php';

function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$plugin = new SUHV_Webcomponents_Plugin();
$plugin->register_shortcodes();
assert_true( 6 === count( $GLOBALS['suhv_test_shortcodes'] ), 'all six shortcodes registered' );

$GLOBALS['suhv_test_options']['suhv_webcomponents_defaults'] = array( 'season' => '2025', 'team-id' => '429494' );
$html = $plugin->render_shortcode( array( 'page_size' => '6' ), null, 'uniho-team-games' );
assert_true( str_contains( $html, '<uniho-team-games' ), 'team games element rendered' );
assert_true( str_contains( $html, 'team-id="429494"' ), 'default team id applied' );
assert_true( str_contains( $html, 'page-size="6"' ), 'underscore attributes normalized' );
assert_true( in_array( 'suhv-webcomponents', $GLOBALS['suhv_test_enqueued'], true ), 'assets enqueued' );
assert_true( str_contains( $plugin->make_component_script_a_module( '<script src="component.js"></script>', 'suhv-webcomponents', 'component.js' ), 'type="module"' ), 'component script is an ES module' );

$html = $plugin->render_shortcode( array( 'season' => '2025' ), null, 'uniho-ranking' );
assert_true( str_contains( $html, 'Fehlende Einstellung oder Attribut' ), 'missing attributes produce a notice' );

$html = $plugin->render_shortcode( array( 'team-id' => 'x" onmouseover="alert(1)', 'season' => '2025' ), null, 'uniho-team-games' );
assert_true( ! str_contains( $html, 'team-id="x" onmouseover=' ), 'attributes are escaped' );
assert_true( str_contains( $html, 'team-id="x&quot; onmouseover=&quot;alert(1)' ), 'quotes are entity-escaped' );

$GLOBALS['suhv_test_http'] = array(
	'https://wc.swissunihockey.ch/teams?mode=by_club&club_id=372&season=2025' => array(
		'code' => 200,
		'body' => json_encode( array( 'entries' => array( array( 'text' => 'Herren 1. Liga', 'set_in_context' => array( 'team_id' => 429532 ) ) ) ) ),
	),
	'https://wc.swissunihockey.ch/games?mode=team&team_id=429532&season=2025' => array(
		'code' => 200,
		'body' => json_encode( array( 'tabs' => array( array( 'link' => array( 'page' => 'league_group_detail', 'ids' => array( '2025', '3', '21', 'Gruppe 1' ) ) ) ) ) ),
	),
);
$method = new ReflectionMethod( $plugin, 'team_shortcodes' );
$method->setAccessible( true );
$teams = $method->invoke( $plugin, '372', '2025' );
assert_true( 1 === count( $teams['teams'] ), 'team generator returns all API teams' );
assert_true( '[uniho-team-games team-id="429532" season="2025"]' === $teams['teams'][0]['games'], 'team generator produces games shortcode' );
assert_true( str_contains( $teams['teams'][0]['ranking'], 'league="3" game-class="21" group="Gruppe 1"' ), 'team generator produces ranking shortcode from assignment' );

echo "Shortcode tests passed.\n";
