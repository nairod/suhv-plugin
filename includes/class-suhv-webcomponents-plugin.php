<?php
/** Core plugin functionality. */

defined( 'ABSPATH' ) || exit;

final class SUHV_Webcomponents_Plugin {
	private const OPTION = 'suhv_webcomponents_defaults';

	/** @var array<string, array{element: string, required: array<int, string|array<int, string>>, allowed: array<int, string>}> */
	private const SHORTCODES = array(
		'uniho-league-games' => array(
			'element'  => 'uniho-league-games',
			'required' => array( 'season', array( 'team-id', 'game-class', 'league' ) ),
			'allowed'  => array( 'team-id', 'game-class', 'league', 'season', 'group' ),
		),
		'uniho-club-team-games' => array(
			'element'  => 'uniho-club-team-games',
			'required' => array( 'club-id', 'season' ),
			'allowed'  => array( 'club-id', 'season', 'page-size' ),
		),
		'uniho-team-games' => array(
			'element'  => 'uniho-team-games',
			'required' => array( 'team-id', 'season' ),
			'allowed'  => array( 'team-id', 'season', 'page-size' ),
		),
		'uniho-club-games' => array(
			'element'  => 'uniho-club-games',
			'required' => array( 'club-id', 'season' ),
			'allowed'  => array( 'club-id', 'season' ),
		),
		'uniho-ranking' => array(
			'element'  => 'uniho-ranking',
			'required' => array( 'season', 'league', 'game-class', 'group' ),
			'allowed'  => array( 'season', 'league', 'game-class', 'group', 'view' ),
		),
		'uniho-mobiliar-topscorer' => array(
			'element'  => 'uniho-mobiliar-topscorer',
			'required' => array( 'season', 'club-id' ),
			'allowed'  => array( 'season', 'club-id', 'view-type' ),
		),
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets_for_queried_content' ) );
		add_filter( 'script_loader_tag', array( $this, 'make_component_script_a_module' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_shortcodes(): void {
		foreach ( self::SHORTCODES as $shortcode => $definition ) {
			add_shortcode( $shortcode, array( $this, 'render_shortcode' ) );
		}
	}

	public function register_assets(): void {
		wp_register_style( 'suhv-webcomponents', SUHV_WEBCOMPONENTS_URL . 'assets/swissunihockey-webcomponents.css', array(), SUHV_WEBCOMPONENTS_VERSION );
		wp_register_style( 'suhv-webcomponents-fallback', SUHV_WEBCOMPONENTS_URL . 'assets/plugin.css', array( 'suhv-webcomponents' ), SUHV_WEBCOMPONENTS_VERSION );
		wp_register_script( 'suhv-webcomponents', SUHV_WEBCOMPONENTS_URL . 'assets/swissunihockey-webcomponents.js', array(), SUHV_WEBCOMPONENTS_VERSION, true );
		wp_script_add_data( 'suhv-webcomponents', 'type', 'module' );
	}

	/** Register assets early and load them in the document head when a queried post uses a shortcode. */
	public function enqueue_assets_for_queried_content(): void {
		$this->register_assets();
		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || empty( $post->post_content ) ) {
			return;
		}

		foreach ( array_keys( self::SHORTCODES ) as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$this->enqueue_assets();
				return;
			}
		}
	}

	/** Ensure the upstream ES module bundle is executed as a module on WordPress 6.4. */
	public function make_component_script_a_module( string $tag, string $handle, string $src ): string {
		if ( 'suhv-webcomponents' !== $handle ) {
			return $tag;
		}
		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/**
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @param string|null          $content Unused shortcode content.
	 * @param string               $shortcode Shortcode name.
	 */
	public function render_shortcode( $atts, $content = null, $shortcode = '' ): string {
		if ( ! isset( self::SHORTCODES[ $shortcode ] ) ) {
			return '';
		}

		$this->enqueue_assets();
		$definition = self::SHORTCODES[ $shortcode ];
		$values     = $this->resolved_attributes( is_array( $atts ) ? $atts : array(), $definition['allowed'] );
		$missing    = $this->missing_attributes( $values, $definition['required'] );

		if ( ! empty( $missing ) ) {
			return sprintf(
				'<div class="suhv-webcomponents-notice" role="status">%s</div>',
				esc_html( sprintf( 'Swiss Unihockey: Fehlende Einstellung oder Attribut: %s.', implode( ', ', $missing ) ) )
			);
		}

		$html_attributes = '';
		foreach ( $definition['allowed'] as $attribute ) {
			if ( '' !== $values[ $attribute ] ) {
				$html_attributes .= sprintf( ' %s="%s"', esc_attr( $attribute ), esc_attr( $values[ $attribute ] ) );
			}
		}

		return sprintf(
			'<div class="suhv-webcomponents-widget" aria-live="polite"><%1$s%2$s><p class="suhv-webcomponents-loading">%3$s</p></%1$s><noscript><p class="suhv-webcomponents-notice">%4$s</p></noscript></div>',
			esc_attr( $definition['element'] ),
			$html_attributes,
			esc_html__( 'Swiss Unihockey Daten werden geladen …', 'suhv-webcomponents' ),
			esc_html__( 'Für die Anzeige der Swiss Unihockey Daten muss JavaScript aktiviert sein.', 'suhv-webcomponents' )
		);
	}

	public function add_settings_page(): void {
		add_options_page( 'SUHV Web Components', 'SUHV Web Components', 'manage_options', 'suhv-webcomponents', array( $this, 'render_settings_page' ) );
	}

	public function register_settings(): void {
		register_setting( 'suhv_webcomponents', self::OPTION, array( $this, 'sanitize_defaults' ) );
		add_settings_section( 'suhv_webcomponents_defaults', 'Standardwerte', static function (): void {
			echo '<p>Diese Werte werden verwendet, wenn ein Shortcode das entsprechende Attribut nicht enthält. Leere Werte werden nicht automatisch erraten.</p>';
		}, 'suhv-webcomponents' );

		foreach ( $this->setting_labels() as $key => $label ) {
			add_settings_field( $key, $label, array( $this, 'render_setting_field' ), 'suhv-webcomponents', 'suhv_webcomponents_defaults', array( 'key' => $key ) );
		}
	}

	/** Load the small copy-to-clipboard enhancement only on this plugin's settings page. */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_suhv-webcomponents' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'suhv-webcomponents-admin', SUHV_WEBCOMPONENTS_URL . 'assets/admin.css', array(), SUHV_WEBCOMPONENTS_VERSION );
		wp_enqueue_script( 'suhv-webcomponents-admin', SUHV_WEBCOMPONENTS_URL . 'assets/admin.js', array(), SUHV_WEBCOMPONENTS_VERSION, true );
	}

	/** @param array<string, mixed> $input @return array<string, string> */
	public function sanitize_defaults( $input ): array {
		$clean = array();
		foreach ( array_keys( $this->setting_labels() ) as $key ) {
			$value = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $key ] ) ) : '';
			if ( 'page-size' === $key && '' !== $value ) {
				$value = (string) min( 100, max( 1, absint( $value ) ) );
			}
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	/** @param array{key: string} $args */
	public function render_setting_field( array $args ): void {
		$defaults = $this->defaults();
		$key      = $args['key'];
		printf( '<input class="regular-text" type="text" name="%1$s[%2$s]" value="%3$s" />', esc_attr( self::OPTION ), esc_attr( $key ), esc_attr( $defaults[ $key ] ?? '' ) );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>SUHV Web Components</h1><form action="options.php" method="post">';
		settings_fields( 'suhv_webcomponents' );
		do_settings_sections( 'suhv-webcomponents' );
		submit_button();
		echo '</form>';
		$this->render_shortcode_generator();
		echo '<h2>Shortcodes</h2><p>Verfügbar sind: <code>[uniho-league-games]</code>, <code>[uniho-club-team-games]</code>, <code>[uniho-team-games]</code>, <code>[uniho-club-games]</code>, <code>[uniho-ranking]</code> und <code>[uniho-mobiliar-topscorer]</code>.</p><p>Attribute entsprechen den offiziellen Komponenten, z. B. <code>[uniho-team-games team-id="429626" season="2025" page-size="5"]</code>.</p></div>';
	}

	/** Render the administrative helper which derives usable shortcodes from a club's teams. */
	private function render_shortcode_generator(): void {
		$defaults = $this->defaults();
		$club_id  = isset( $_GET['suhv_club_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['suhv_club_id'] ) ) : ( $defaults['club-id'] ?? '' );
		$season   = isset( $_GET['suhv_season'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['suhv_season'] ) ) : ( $defaults['season'] ?? '' );

		echo '<hr><h2>Team-Shortcodes generieren</h2><p>Ermittelt alle Teams eines Vereins über den offiziellen Swiss-Unihockey-Dienst. Die Suche ändert keine gespeicherten Standardwerte. <a href="https://www.swissunihockey.ch/de/administration/services/vereins-ids-finder-fuer-web-component/" target="_blank" rel="noopener noreferrer">Vereins-ID-Finder öffnen</a>.</p>';
		echo '<form method="get" class="suhv-webcomponents-generator"><input type="hidden" name="page" value="suhv-webcomponents">';
		wp_nonce_field( 'suhv_webcomponents_generate', 'suhv_webcomponents_generator_nonce' );
		printf( '<p><label>Club-ID<br><input class="regular-text" type="text" name="suhv_club_id" value="%s" inputmode="numeric"></label> <label>Saison (Startjahr)<br><input class="small-text" type="text" name="suhv_season" value="%s" inputmode="numeric"></label> ', esc_attr( $club_id ), esc_attr( $season ) );
		submit_button( 'Teams laden', 'secondary', 'suhv_generate', false );
		echo '</p></form>';

		if ( ! isset( $_GET['suhv_generate'] ) ) {
			return;
		}

		if ( ! isset( $_GET['suhv_webcomponents_generator_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['suhv_webcomponents_generator_nonce'] ) ), 'suhv_webcomponents_generate' ) ) {
			echo '<div class="notice notice-error"><p>Die Sicherheitsprüfung ist fehlgeschlagen. Bitte lade die Seite neu.</p></div>';
			return;
		}
		if ( ! preg_match( '/^\d+$/', $club_id ) || ! preg_match( '/^\d{4}$/', $season ) ) {
			echo '<div class="notice notice-error"><p>Bitte gib eine numerische Club-ID und ein vierstelliges Saison-Startjahr ein.</p></div>';
			return;
		}

		$result = $this->team_shortcodes( $club_id, $season );
		if ( isset( $result['error'] ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $result['error'] ) );
			return;
		}
		if ( empty( $result['teams'] ) ) {
			echo '<div class="notice notice-warning"><p>Für diese Club-ID und Saison wurden keine Teams gefunden.</p></div>';
			return;
		}

		echo '<h3>Teams</h3><div class="suhv-webcomponents-team-list">';
		foreach ( $result['teams'] as $team ) {
			printf( '<section class="suhv-webcomponents-team"><h4>%1$s <span>Team-ID: %2$s</span></h4>', esc_html( $team['name'] ), esc_html( $team['id'] ) );
			$this->render_copyable_shortcode( 'Spielplan & Resultate', $team['games'] );
			if ( isset( $team['ranking'] ) ) {
				$this->render_copyable_shortcode( 'Tabelle', $team['ranking'] );
			} else {
				printf( '<p class="description">Tabelle nicht verfügbar: %s</p>', esc_html( $team['ranking_error'] ?? 'Keine Ligazuweisung gefunden.' ) );
			}
			echo '</section>';
		}
		echo '</div>';
	}

	private function render_copyable_shortcode( string $label, string $shortcode ): void {
		printf( '<div class="suhv-webcomponents-code"><strong>%1$s</strong><textarea readonly rows="2" aria-label="%1$s Shortcode">%2$s</textarea><button type="button" class="button suhv-copy-shortcode">Kopieren</button><span class="suhv-copy-status" aria-live="polite"></span></div>', esc_html( $label ), esc_textarea( $shortcode ) );
	}

	/** @return array{teams?: array<int, array<string, string>>, error?: string} */
	private function team_shortcodes( string $club_id, string $season ): array {
		$cache_key = 'suhv_wc_shortcode_' . md5( $club_id . '|' . $season );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$teams_response = $this->api_get( '/teams?mode=by_club&club_id=' . rawurlencode( $club_id ) . '&season=' . rawurlencode( $season ) );
		if ( isset( $teams_response['error'] ) ) {
			return $teams_response;
		}

		$teams = array();
		foreach ( $teams_response['entries'] ?? array() as $entry ) {
			$team_id = isset( $entry['set_in_context']['team_id'] ) ? (string) $entry['set_in_context']['team_id'] : '';
			if ( '' === $team_id ) {
				continue;
			}
			$team = array(
				'name'  => isset( $entry['text'] ) ? (string) $entry['text'] : 'Unbenanntes Team',
				'id'    => $team_id,
				'games' => sprintf( '[uniho-team-games team-id="%s" season="%s"]', $team_id, $season ),
			);
			$assignment = $this->team_assignment( $team_id, $season );
			if ( isset( $assignment['error'] ) ) {
				$team['ranking_error'] = $assignment['error'];
			} else {
				$team['ranking'] = sprintf( '[uniho-ranking season="%s" league="%s" game-class="%s" group="%s"]', $season, $assignment['league'], $assignment['game_class'], $assignment['group'] );
			}
			$teams[] = $team;
		}

		$result = array( 'teams' => $teams );
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		return $result;
	}

	/** @return array{league?: string, game_class?: string, group?: string, error?: string} */
	private function team_assignment( string $team_id, string $season ): array {
		$response = $this->api_get( '/games?mode=team&team_id=' . rawurlencode( $team_id ) . '&season=' . rawurlencode( $season ) );
		if ( isset( $response['error'] ) ) {
			return $response;
		}
		foreach ( $response['tabs'] ?? array() as $tab ) {
			$ids = $tab['link']['ids'] ?? null;
			if ( 'league_group_detail' !== ( $tab['link']['page'] ?? '' ) || ! is_array( $ids ) || count( $ids ) < 4 || (string) $ids[0] !== $season ) {
				continue;
			}
			return array( 'league' => (string) $ids[1], 'game_class' => (string) $ids[2], 'group' => (string) $ids[3] );
		}
		return array( 'error' => 'Für dieses Team wurde keine Liga-, Klassen- und Gruppenzuordnung gefunden.' );
	}

	/** @return array<string, mixed> */
	private function api_get( string $path ): array {
		$response = wp_remote_get( 'https://wc.swissunihockey.ch' . $path, array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Swiss Unihockey konnte nicht erreicht werden: ' . $response->get_error_message() );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array( 'error' => 'Swiss Unihockey lieferte keine gültige Antwort.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? ( is_array( $data['data'] ?? null ) ? $data['data'] : $data ) : array( 'error' => 'Swiss Unihockey lieferte ungültige Daten.' );
	}

	private function enqueue_assets(): void {
		if ( ! wp_style_is( 'suhv-webcomponents', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'suhv-webcomponents' );
		wp_enqueue_style( 'suhv-webcomponents-fallback' );
		wp_enqueue_script( 'suhv-webcomponents' );
	}

	/** @param array<string, mixed> $atts @param array<int, string> $allowed @return array<string, string> */
	private function resolved_attributes( array $atts, array $allowed ): array {
		$normalized = array();
		foreach ( $atts as $key => $value ) {
			$normalized[ str_replace( '_', '-', strtolower( (string) $key ) ) ] = sanitize_text_field( (string) $value );
		}
		$defaults = $this->defaults();
		$values   = array();
		foreach ( $allowed as $key ) {
			$values[ $key ] = $normalized[ $key ] ?? $defaults[ $key ] ?? '';
		}
		return $values;
	}

	/** @param array<string, string> $values @param array<int, string|array<int, string>> $requirements @return array<int, string> */
	private function missing_attributes( array $values, array $requirements ): array {
		$missing = array();
		foreach ( $requirements as $requirement ) {
			if ( is_array( $requirement ) ) {
				if ( '' !== ( $values[ $requirement[0] ] ?? '' ) ) {
					continue;
				}
				foreach ( array_slice( $requirement, 1 ) as $key ) {
					if ( '' === ( $values[ $key ] ?? '' ) ) {
						$missing[] = $key;
					}
				}
			} elseif ( '' === ( $values[ $requirement ] ?? '' ) ) {
				$missing[] = $requirement;
			}
		}
		return $missing;
	}

	/** @return array<string, string> */
	private function defaults(): array {
		$defaults = get_option( self::OPTION, array() );
		return is_array( $defaults ) ? $defaults : array();
	}

	/** @return array<string, string> */
	private function setting_labels(): array {
		return array(
			'club-id'    => 'Standard Club-ID',
			'team-id'    => 'Standard Team-ID',
			'season'     => 'Standard Saison (Startjahr)',
			'league'     => 'Standard Liga-ID',
			'game-class' => 'Standard Klasse-ID',
			'group'      => 'Standard Gruppe',
			'page-size'  => 'Standard Anzahl Einträge pro Seite',
		);
	}
}
