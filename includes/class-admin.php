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

		// Event columns.
		add_filter( 'manage_' . Post_Types::CPT_EVENT . '_posts_columns', [ $this, 'event_columns' ] );
		add_action( 'manage_' . Post_Types::CPT_EVENT . '_posts_custom_column', [ $this, 'render_event_column' ], 10, 2 );

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
		$columns['stgl_presenter']         = 'stgl_presenter_name';
		$columns['stgl_presenter_company'] = 'stgl_presenter_company';
		$columns['stgl_presenter_time']    = 'stgl_presenter_time';
		return $columns;
	}

	public function render_presentation_column( string $column, int $post_id ): void {
		switch ( $column ) {
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
	/*  Columns – Events                                                  */
	/* ------------------------------------------------------------------ */

	public function event_columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['stgl_event_date']     = __( 'Event date', 'stgl' );
		$columns['stgl_event_location'] = __( 'Location', 'stgl' );
		$columns['stgl_event_cfp']      = __( 'CFP', 'stgl' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public function render_event_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'stgl_event_date':
				$d = (string) get_post_meta( $post_id, 'stgl_event_date', true );
				echo esc_html( Helpers\format_event_date( $d ) );
				break;
			case 'stgl_event_location':
				echo esc_html( (string) get_post_meta( $post_id, 'stgl_event_location', true ) );
				break;
			case 'stgl_event_cfp':
				$open = (bool) get_post_meta( $post_id, 'stgl_event_cfp_open', true );
				echo $open ? '<strong style="color:#1ea000">' . esc_html__( 'Open', 'stgl' ) . '</strong>' : '<span style="color:#999">—</span>';
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

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
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
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'stgl' ) . '</p></div>';
		}

		$levels = get_option( Installer::OPTION_SPONSOR_LEVELS, Installer::default_sponsor_levels() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SwiNOG Events – Settings', 'stgl' ); ?></h1>

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

				<h2 style="margin-top:2em"><?php esc_html_e( 'Endpoints', 'stgl' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'REST events:', 'stgl' ); ?></strong>
					<code><?php echo esc_html( rest_url( 'swinog/v1/events' ) ); ?></code><br />
					<strong><?php esc_html_e( 'iCal feed for an event:', 'stgl' ); ?></strong>
					<code><?php echo esc_html( home_url( '/?stgl_ical=swinog-NN' ) ); ?></code>
				</p>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
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
