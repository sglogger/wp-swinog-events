<?php
/**
 * Admin-area integrations: list columns, sortable columns, settings page.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public function register(): void {
		// Presentation columns.
		add_filter( 'manage_' . Post_Types::CPT_PRESENTATION . '_posts_columns', [ $this, 'presentation_columns' ] );
		add_action( 'manage_' . Post_Types::CPT_PRESENTATION . '_posts_custom_column', [ $this, 'render_presentation_column' ], 10, 2 );
		add_filter( 'manage_edit-' . Post_Types::CPT_PRESENTATION . '_sortable_columns', [ $this, 'sortable_presentation_columns' ] );

		// Sponsor columns.
		add_filter( 'manage_' . Post_Types::CPT_SPONSOR . '_posts_columns', [ $this, 'sponsor_columns' ] );
		add_action( 'manage_' . Post_Types::CPT_SPONSOR . '_posts_custom_column', [ $this, 'render_sponsor_column' ], 10, 2 );

		// Settings page.
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Plugin row meta.
		add_filter( 'plugin_action_links_' . STGL_SWINOG_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	/* ------------------------------------------------------------------ */
	/*  Columns – Presentations                                           */
	/* ------------------------------------------------------------------ */

	public function presentation_columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['stgl_presenter_type']      = __( 'Type', 'stgl' );
		$columns['stgl_presenter']           = __( 'Presenter', 'stgl' );
		$columns['stgl_presenter_company']   = __( 'Company', 'stgl' );
		$columns['stgl_presenter_published'] = __( 'Published?', 'stgl' );
		$columns['stgl_presenter_time']      = __( 'Slot', 'stgl' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public function sortable_presentation_columns( array $columns ): array {
		$columns['stgl_presenter_type']    = 'stgl_presenter_type';
		$columns['stgl_presenter']         = 'stgl_presenter_name';
		$columns['stgl_presenter_company'] = 'stgl_presenter_company';
		$columns['stgl_presenter_time']    = 'stgl_presenter_time';
		return $columns;
	}

	public function render_presentation_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'stgl_presenter_type':
				$type = Installer::resolve_presentation_type( $post_id );
				echo esc_html( $type['label'] );
				break;
			case 'stgl_presenter':
				echo esc_html( (string) get_post_meta( $post_id, 'stgl_presenter_name', true ) );
				break;
			case 'stgl_presenter_company':
				echo esc_html( (string) get_post_meta( $post_id, 'stgl_presenter_company', true ) );
				break;
			case 'stgl_presenter_published':
				$pub = get_post_meta( $post_id, 'stgl_presenter_publish', true );
				if ( $pub ) {
					echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450" title="' . esc_attr__( 'Published', 'stgl' ) . '"></span>';
				} else {
					echo '<span class="dashicons dashicons-no-alt" style="color:#999" title="' . esc_attr__( 'Not published', 'stgl' ) . '"></span>';
				}
				break;
			case 'stgl_presenter_time':
				echo esc_html( (string) get_post_meta( $post_id, 'stgl_presenter_time', true ) );
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Columns – Sponsors                                                */
	/* ------------------------------------------------------------------ */

	public function sponsor_columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['stgl_sponsor_level'] = __( 'Level', 'stgl' );
		$columns['stgl_sponsor_url']   = __( 'URL', 'stgl' );
		$columns['stgl_sponsor_notes'] = __( 'Internal notes', 'stgl' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public function render_sponsor_column( string $column, int $post_id ): void {
		$levels = get_option( Installer::OPTION_SPONSOR_LEVELS, Installer::default_sponsor_levels() );

		switch ( $column ) {
			case 'stgl_sponsor_level':
				$weight = (int) get_post_meta( $post_id, 'stgl_sponsor_level', true );
				echo esc_html( (string) ( $levels[ $weight ] ?? '' ) );
				break;
			case 'stgl_sponsor_url':
				$url = (string) get_post_meta( $post_id, 'stgl_sponsor_url', true );
				if ( $url ) {
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
				}
				break;
			case 'stgl_sponsor_notes':
				echo esc_html( wp_trim_words( (string) get_post_meta( $post_id, 'stgl_sponsor_notes', true ), 8 ) );
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Settings page                                                     */
	/* ------------------------------------------------------------------ */

	public function register_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::CPT_PRESENTATION,
			__( 'SwiNOG Settings', 'stgl' ),
			__( 'Settings', 'stgl' ),
			'manage_options',
			'stgl-swinog-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'stgl_swinog_settings', Installer::OPTION_SPONSOR_LEVELS, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_sponsor_levels' ],
			'default'           => Installer::default_sponsor_levels(),
		] );

		register_setting( 'stgl_swinog_settings', Installer::OPTION_PRESENTATION_TYPES, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_presentation_types' ],
			'default'           => Installer::default_presentation_types(),
		] );
	}

	/**
	 * @param mixed $input
	 * @return array<int, string>
	 */
	public function sanitize_sponsor_levels( $input ): array {
		if ( ! is_array( $input ) ) {
			return Installer::default_sponsor_levels();
		}
		$out = [];
		foreach ( $input as $weight => $label ) {
			$out[ (int) $weight ] = sanitize_text_field( (string) $label );
		}
		krsort( $out, SORT_NUMERIC );
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, string>
	 */
	public function sanitize_presentation_types( $input ): array {
		if ( ! is_array( $input ) ) {
			return Installer::default_presentation_types();
		}
		$out = [];
		foreach ( $input as $slug => $label ) {
			$slug  = sanitize_key( (string) $slug );
			$label = sanitize_text_field( (string) $label );
			if ( '' !== $slug && '' !== $label ) {
				$out[ $slug ] = $label;
			}
		}
		return [] === $out ? Installer::default_presentation_types() : $out;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'api' === $tab ) {
			$this->render_api_settings_tab();
			return;
		}

		// Save handler.
		if ( isset( $_POST['stgl_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stgl_settings_nonce'] ) ), 'stgl_settings_save' ) ) {
			$weights = isset( $_POST['levels_weight'] ) ? (array) $_POST['levels_weight'] : [];
			$labels  = isset( $_POST['levels_label'] ) ? (array) $_POST['levels_label'] : [];

			$rebuilt = [];
			foreach ( $weights as $i => $weight ) {
				$weight = (int) $weight;
				$label  = sanitize_text_field( wp_unslash( $labels[ $i ] ?? '' ) );
				if ( $label !== '' || $weight === 0 ) {
					$rebuilt[ $weight ] = $label;
				}
			}
			krsort( $rebuilt, SORT_NUMERIC );
			update_option( Installer::OPTION_SPONSOR_LEVELS, $rebuilt );

			// Presentation types. A row needs both a label and a slug; the slug
			// is derived from the label when left blank, so adding "Icebreaker"
			// is a one-field job.
			$type_slugs  = isset( $_POST['types_slug'] ) ? (array) $_POST['types_slug'] : [];
			$type_labels = isset( $_POST['types_label'] ) ? (array) $_POST['types_label'] : [];

			$types = [];
			foreach ( $type_labels as $i => $label ) {
				$label = sanitize_text_field( wp_unslash( $label ) );
				if ( '' === $label ) {
					continue;
				}
				$slug = sanitize_key( wp_unslash( (string) ( $type_slugs[ $i ] ?? '' ) ) );
				if ( '' === $slug ) {
					$slug = sanitize_key( sanitize_title( $label ) );
				}
				if ( '' !== $slug ) {
					$types[ $slug ] = $label;
				}
			}
			update_option( Installer::OPTION_PRESENTATION_TYPES, $types ?: Installer::default_presentation_types() );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'stgl' ) . '</p></div>';
		}

		$levels = get_option( Installer::OPTION_SPONSOR_LEVELS, Installer::default_sponsor_levels() );
		$types  = Installer::presentation_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SwiNOG Events – Settings', 'stgl' ); ?></h1>
			<?php self::render_settings_tabs( 'general' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'stgl_settings_save', 'stgl_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Sponsor levels', 'stgl' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Higher weight = higher importance. Used to sort sponsors on the front-end.', 'stgl' ); ?></p>

				<table class="widefat striped" style="max-width:600px">
					<thead>
						<tr>
							<th style="width:120px"><?php esc_html_e( 'Weight', 'stgl' ); ?></th>
							<th><?php esc_html_e( 'Label', 'stgl' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $levels as $weight => $label ) : ?>
						<tr>
							<td><input type="number" name="levels_weight[]" value="<?php echo esc_attr( (string) $weight ); ?>" class="small-text" /></td>
							<td><input type="text" name="levels_label[]" value="<?php echo esc_attr( (string) $label ); ?>" class="regular-text" /></td>
						</tr>
					<?php endforeach; ?>
					<?php for ( $i = 0; $i < 2; $i++ ) : ?>
						<tr>
							<td><input type="number" name="levels_weight[]" value="" class="small-text" placeholder="0" /></td>
							<td><input type="text" name="levels_label[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'New level…', 'stgl' ); ?>" /></td>
						</tr>
					<?php endfor; ?>
					</tbody>
				</table>

				<h2 style="margin-top:2em"><?php esc_html_e( 'Presentation types', 'stgl' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: slug of the default type */
						esc_html__( 'Classification shown in the agenda (talk, break, keynote, …). Each presentation can overwrite its type on the edit screen; entries without an explicit type fall back to "%s". Clear a label to delete a type; leave the slug empty to derive it from the label.', 'stgl' ),
						esc_html( Installer::DEFAULT_PRESENTATION_TYPE )
					);
					?>
				</p>

				<table class="widefat striped" style="max-width:600px">
					<thead>
						<tr>
							<th style="width:180px"><?php esc_html_e( 'Slug', 'stgl' ); ?></th>
							<th><?php esc_html_e( 'Label', 'stgl' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $types as $slug => $label ) : ?>
						<tr>
							<td><input type="text" name="types_slug[]" value="<?php echo esc_attr( (string) $slug ); ?>" class="regular-text" /></td>
							<td><input type="text" name="types_label[]" value="<?php echo esc_attr( (string) $label ); ?>" class="regular-text" /></td>
						</tr>
					<?php endforeach; ?>
					<?php for ( $i = 0; $i < 2; $i++ ) : ?>
						<tr>
							<td><input type="text" name="types_slug[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'auto', 'stgl' ); ?>" /></td>
							<td><input type="text" name="types_label[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'New type…', 'stgl' ); ?>" /></td>
						</tr>
					<?php endfor; ?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr style="margin:2.5em 0 1.5em" />

			<h2><?php esc_html_e( 'Shortcodes', 'stgl' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Use the following shortcodes to embed plugin content in pages or posts. Replace swinog-NN with an event slug (e.g. swinog-89).', 'stgl' ); ?>
			</p>

			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Shortcode', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Description', 'stgl' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>[swinog_list_presentations event="swinog-NN"]</code></td>
						<td><?php esc_html_e( 'Table of presentations with links to slides and video (no time column).', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>[swinog_list_agenda event="swinog-NN"]</code></td>
						<td><?php esc_html_e( 'Agenda view with time slot and talk abstract (no slide/video links).', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>[swinog_sponsor event="swinog-NN" layout="tiers"]</code></td>
						<td><?php esc_html_e( 'Sponsor grid grouped by level. Use layout="list" for a flat grid.', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>[swinog_list_all_events]</code></td>
						<td><?php esc_html_e( 'List all event pages (child pages of the current page). Place on your "Events" parent page to enumerate every SwiNOG.', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>[stgl_list_presentations event="swinog-NN"]</code></td>
						<td><?php esc_html_e( 'Legacy alias of swinog_list_presentations (kept for backwards compatibility).', 'stgl' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3 style="margin-top:1.5em"><?php esc_html_e( 'Optional attributes', 'stgl' ); ?></h3>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th style="width:25%"><?php esc_html_e( 'Attribute', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Description', 'stgl' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>event</code></td>
						<td><?php esc_html_e( 'Event taxonomy slug (e.g. swinog-89). Omit to list items across all events.', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>orderby</code></td>
						<td>
							<?php esc_html_e( 'WP_Query orderby value. Defaults: presentations/agenda use "meta_value"; sponsors use "meta_value_num". Other accepted values include "title", "date", "menu_order".', 'stgl' ); ?>
						</td>
					</tr>
					<tr>
						<td><code>order</code></td>
						<td><?php esc_html_e( 'Sort direction: "ASC" or "DESC".', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>meta_key</code></td>
						<td><?php esc_html_e( 'Meta key used when orderby="meta_value" or "meta_value_num". Defaults: stgl_presenter_time (presentations/agenda), stgl_sponsor_level (sponsors).', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>posts</code></td>
						<td><?php esc_html_e( 'Limit results. -1 (default) returns all.', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>show_type</code></td>
						<td><?php esc_html_e( 'Presentation/agenda shortcodes only: show the Type column (Talk, Break, Keynote, …). Defaults to 1 for the agenda and 0 for the presentation list; set show_type="0" or "1" to overrule.', 'stgl' ); ?></td>
					</tr>
					<tr>
						<td><code>layout</code></td>
						<td><?php esc_html_e( 'Sponsor shortcode only: "tiers" (grouped by level, default) or "list" (flat grid).', 'stgl' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3 style="margin-top:1.5em"><?php esc_html_e( 'Examples', 'stgl' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Agenda sorted by time slot (ascending):', 'stgl' ); ?></p>
			<p><code>[swinog_list_agenda event="swinog-41" orderby="meta_value" meta_key="stgl_presenter_time" order="ASC"]</code></p>
			<p class="description"><?php esc_html_e( 'Sponsors sorted by level (highest first):', 'stgl' ); ?></p>
			<p><code>[swinog_sponsor event="swinog-41" orderby="meta_value_num" meta_key="stgl_sponsor_level" order="DESC"]</code></p>
		</div>
		<?php
	}

	private static function render_settings_tabs( string $active ): void {
		$base = admin_url( 'edit.php?post_type=' . Post_Types::CPT_PRESENTATION . '&page=stgl-swinog-settings' );
		$tabs = [
			'general' => [ __( 'General', 'stgl' ), $base ],
			'api'     => [ __( 'API Settings', 'stgl' ), add_query_arg( 'tab', 'api', $base ) ],
		];
		echo '<nav class="nav-tab-wrapper" style="margin-bottom:1em">';
		foreach ( $tabs as $key => [ $label, $url ] ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( $url ),
				$key === $active ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/* ------------------------------------------------------------------ */
	/*  Settings page – API tab                                           */
	/* ------------------------------------------------------------------ */

	private function render_api_settings_tab(): void {
		$notices = [];

		if ( isset( $_POST['stgl_cfp_api_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stgl_cfp_api_nonce'] ) ), 'stgl_cfp_api_save' ) ) {
			$current = Cfp_Client::settings();

			$server = Cfp_Client::normalize_server( (string) wp_unslash( $_POST['cfp_server'] ?? '' ) );

			// The key is never echoed back; an empty field keeps the stored one.
			$api_key = sanitize_text_field( wp_unslash( $_POST['cfp_api_key'] ?? '' ) );
			if ( '' === $api_key && empty( $_POST['cfp_api_key_clear'] ) ) {
				$api_key = $current['api_key'];
			}

			$types    = Installer::presentation_types();
			$posted   = isset( $_POST['cfp_type_map'] ) ? (array) wp_unslash( $_POST['cfp_type_map'] ) : [];
			$type_map = [];
			foreach ( Cfp_Client::SLOT_TYPES as $slot_type ) {
				$slug                   = sanitize_key( (string) ( $posted[ $slot_type ] ?? '' ) );
				$type_map[ $slot_type ] = isset( $types[ $slug ] ) ? $slug : '';
			}

			update_option( Installer::OPTION_CFP_API, [
				'server'   => $server,
				'api_key'  => $api_key,
				'type_map' => $type_map,
			], false );
			$notices[] = [ 'success', __( 'API settings saved.', 'stgl' ) ];

			if ( ! empty( $_POST['stgl_cfp_test'] ) ) {
				$notices = array_merge( $notices, self::test_cfp_connection() );
			}
		}

		$settings = Cfp_Client::settings();
		$types    = Installer::presentation_types();
		$default  = Installer::default_presentation_type();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SwiNOG Events – Settings', 'stgl' ); ?></h1>
			<?php self::render_settings_tabs( 'api' ); ?>

			<?php foreach ( $notices as [ $level, $message ] ) : ?>
				<div class="notice notice-<?php echo esc_attr( $level ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endforeach; ?>

			<form method="post">
				<?php wp_nonce_field( 'stgl_cfp_api_save', 'stgl_cfp_api_nonce' ); ?>

				<h2><?php esc_html_e( 'CFP server', 'stgl' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Used by the CFP Sync Tool to import the agenda from the SwiNOG CFP tool.', 'stgl' ); ?></p>

				<table class="form-table">
					<tbody>
					<tr>
						<th><label for="cfp_server"><?php esc_html_e( 'CFP server URL', 'stgl' ); ?></label></th>
						<td>
							<input type="url" id="cfp_server" name="cfp_server" value="<?php echo esc_attr( $settings['server'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( Cfp_Client::DEFAULT_SERVER ); ?>" />
							<p class="description"><?php esc_html_e( 'Base URL of the CFP tool, e.g. https://cfp.swinog.ch (a trailing /api or /api/v1 is stripped).', 'stgl' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cfp_api_key"><?php esc_html_e( 'API key', 'stgl' ); ?></label></th>
						<td>
							<input type="password" id="cfp_api_key" name="cfp_api_key" value="" class="regular-text" autocomplete="new-password"
								placeholder="<?php echo esc_attr( '' !== $settings['api_key'] ? __( '•••••••• (stored – leave empty to keep)', 'stgl' ) : 'swcfp_…' ); ?>" />
							<?php if ( '' !== $settings['api_key'] ) : ?>
								<label style="margin-left:.5em"><input type="checkbox" name="cfp_api_key_clear" value="1" /> <?php esc_html_e( 'Remove stored key', 'stgl' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Sent as "Authorization: Bearer …". Needed for the admin slot list, which carries presenter e-mail, consents and video URL.', 'stgl' ); ?></p>
						</td>
					</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Slot type mapping', 'stgl' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Which presentation type an imported CFP slot gets, by its slot_type.', 'stgl' ); ?></p>

				<table class="widefat striped" style="max-width:600px">
					<thead>
						<tr>
							<th style="width:200px"><?php esc_html_e( 'CFP slot_type', 'stgl' ); ?></th>
							<th><?php esc_html_e( 'Presentation type', 'stgl' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( Cfp_Client::SLOT_TYPES as $slot_type ) : ?>
						<tr>
							<td><code><?php echo esc_html( $slot_type ); ?></code></td>
							<td>
								<select name="cfp_type_map[<?php echo esc_attr( $slot_type ); ?>]">
									<option value=""<?php selected( ! isset( $types[ $settings['type_map'][ $slot_type ] ] ) ); ?>>
										<?php
										/* translators: %s: label of the default agenda entry type */
										printf( esc_html__( '— default (%s) —', 'stgl' ), esc_html( (string) ( $types[ $default ] ?? $default ) ) );
										?>
									</option>
									<?php foreach ( $types as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( (string) $slug ); ?>" <?php selected( $settings['type_map'][ $slot_type ], (string) $slug ); ?>><?php echo esc_html( (string) $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Changes', 'stgl' ), 'primary', 'submit', false ); ?>
					<?php submit_button( __( 'Save & test connection', 'stgl' ), 'secondary', 'stgl_cfp_test', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<int, array{0: string, 1: string}> Notices as [level, message].
	 */
	private static function test_cfp_connection(): array {
		$client  = new Cfp_Client();
		$version = $client->version();
		if ( is_wp_error( $version ) ) {
			return [ [ 'error', $version->get_error_message() ] ];
		}

		$notices = [ [
			'success',
			/* translators: %s: CFP tool version */
			sprintf( __( 'CFP server reachable (version %s).', 'stgl' ), (string) ( $version['version'] ?? '?' ) ),
		] ];

		$events = $client->events();
		if ( is_wp_error( $events ) || [] === $events ) {
			$notices[] = [ 'warning', is_wp_error( $events ) ? $events->get_error_message() : __( 'The CFP server lists no events.', 'stgl' ) ];
			return $notices;
		}

		$slots     = $client->slots( (string) $events[0]['id'] );
		$notices[] = is_wp_error( $slots )
			? [ 'error', $slots->get_error_message() ]
			: [ 'success', __( 'API key accepted – slot data can be read.', 'stgl' ) ];

		return $notices;
	}

	/**
	 * @param array<int, string> $links
	 * @return array<int, string>
	 */
	public function plugin_action_links( array $links ): array {
		$url = admin_url( 'edit.php?post_type=' . Post_Types::CPT_PRESENTATION . '&page=stgl-swinog-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'stgl' ) . '</a>' );
		return $links;
	}
}
