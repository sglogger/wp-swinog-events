<?php
/**
 * "CFP Sync Tool" admin page: replaces the presentations of an event
 * category with the agenda of the matching CFP event day.
 *
 * Flow: pick a CFP event → map each event day to an event category →
 * dry-run table of what gets trashed and imported → confirm → import.
 * The dry-run plan is kept in a transient so the import writes exactly
 * what was previewed.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cfp_Sync {

	public const PAGE_SLUG = 'stgl-swinog-cfp-sync';

	private const CAPABILITY   = 'manage_options';
	private const PLAN_PREFIX  = 'stgl_cfp_plan_';
	private const PLAN_TTL     = 30 * MINUTE_IN_SECONDS;
	private const MAX_DAYS     = 14;
	private const META_SLOT_ID = '_stgl_cfp_slot_id';
	private const META_SUB_ID  = '_stgl_cfp_submission_id';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::CPT_PRESENTATION,
			__( 'CFP Sync Tool', 'stgl' ),
			__( 'CFP Sync Tool', 'stgl' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	private static function page_url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'edit.php?post_type=' . Post_Types::CPT_PRESENTATION . '&page=' . self::PAGE_SLUG ) );
	}

	/* ------------------------------------------------------------------ */
	/*  Dispatch                                                          */
	/* ------------------------------------------------------------------ */

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap stgl-cfp-sync">';
		echo '<h1>' . esc_html__( 'CFP Sync Tool', 'stgl' ) . '</h1>';

		$step = isset( $_POST['stgl_cfp_step'] ) ? sanitize_key( wp_unslash( $_POST['stgl_cfp_step'] ) ) : '';

		if ( 'import' === $step && self::verify_nonce( 'stgl_cfp_import' ) ) {
			$this->render_import();
		} elseif ( 'preview' === $step && self::verify_nonce( 'stgl_cfp_preview' ) ) {
			$this->render_preview();
		} elseif ( isset( $_GET['cfp_event'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_mapping( sanitize_text_field( wp_unslash( $_GET['cfp_event'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$this->render_event_picker();
		}

		echo '</div>';
	}

	private static function verify_nonce( string $action ): bool {
		$nonce = isset( $_POST['stgl_cfp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['stgl_cfp_nonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce, $action ) ) {
			return true;
		}
		self::notice( 'error', __( 'The form has expired. Please start again.', 'stgl' ) );
		return false;
	}

	private static function notice( string $level, string $message ): void {
		printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $level ), esc_html( $message ) );
	}

	/* ------------------------------------------------------------------ */
	/*  Step 1 – pick the CFP event                                       */
	/* ------------------------------------------------------------------ */

	private function render_event_picker(): void {
		$events = ( new Cfp_Client() )->events();
		if ( is_wp_error( $events ) ) {
			self::notice( 'error', $events->get_error_message() );
			self::settings_hint();
			return;
		}
		if ( [] === $events ) {
			self::notice( 'warning', __( 'The CFP server lists no events.', 'stgl' ) );
			return;
		}
		?>
		<p><?php esc_html_e( 'Import the agenda of a CFP event into the presentations of this site. Nothing is changed until you confirm the dry run.', 'stgl' ); ?></p>
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Post_Types::CPT_PRESENTATION ); ?>" />
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<label for="cfp_event"><strong><?php esc_html_e( 'CFP event', 'stgl' ); ?></strong></label><br />
			<select id="cfp_event" name="cfp_event">
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( (string) $event['id'] ); ?>"><?php echo esc_html( self::event_label( $event ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Next', 'stgl' ), 'primary', '', false ); ?>
		</form>
		<?php
		self::settings_hint();
	}

	private static function settings_hint(): void {
		$url = admin_url( 'edit.php?post_type=' . Post_Types::CPT_PRESENTATION . '&page=stgl-swinog-settings&tab=api' );
		printf(
			'<p class="description" style="margin-top:2em">%s <a href="%s">%s</a></p>',
			esc_html__( 'CFP server, API key and slot type mapping:', 'stgl' ),
			esc_url( $url ),
			esc_html__( 'Settings → API Settings', 'stgl' )
		);
	}

	/**
	 * @param array<string, mixed> $event
	 */
	private static function event_label( array $event ): string {
		$start = substr( (string) ( $event['starts_on'] ?? '' ), 0, 10 );
		$end   = substr( (string) ( $event['ends_on'] ?? '' ), 0, 10 );
		$dates = $start === $end || '' === $end ? $start : $start . ' – ' . $end;
		return sprintf( '%s (%s, %s)', (string) ( $event['name'] ?? '' ), $dates, (string) ( $event['status'] ?? '' ) );
	}

	/* ------------------------------------------------------------------ */
	/*  Step 2 – map event days to event categories                       */
	/* ------------------------------------------------------------------ */

	private function render_mapping( string $event_id ): void {
		$data = self::load_event( $event_id );
		if ( null === $data ) {
			return;
		}
		[ $event, $days ] = $data;

		$terms = self::terms();
		$saved = self::saved_mapping( $event_id );
		?>
		<h2><?php echo esc_html( self::event_label( $event ) ); ?></h2>
		<p>
			<?php esc_html_e( 'Choose the event category each day of the CFP event is imported into. Days set to "skip" are left untouched. The next page shows a dry run; nothing is changed yet.', 'stgl' ); ?>
		</p>

		<?php if ( [] === $terms ) : ?>
			<?php self::notice( 'warning', __( 'There are no event categories yet. Create one per event day first (e.g. "SwiNOG #42-1" and "SwiNOG #42-2").', 'stgl' ) ); ?>
			<?php return; ?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( self::page_url() ); ?>">
			<?php wp_nonce_field( 'stgl_cfp_preview', 'stgl_cfp_nonce' ); ?>
			<input type="hidden" name="stgl_cfp_step" value="preview" />
			<input type="hidden" name="cfp_event" value="<?php echo esc_attr( $event_id ); ?>" />

			<table class="widefat striped" style="max-width:800px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'CFP day', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Date', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Slots', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Event category', 'stgl' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $days as $date => $day ) : ?>
					<?php
					$selected = $saved[ $date ] ?? 0;
					if ( ! isset( $terms[ $selected ] ) ) {
						// Never preselect a category for an empty day – that would only delete.
						$selected = [] === $day['slots'] ? 0 : self::guess_term( $terms, $event, $day['index'], count( $days ) );
					}
					?>
					<tr>
						<td><?php echo esc_html( $day['label'] ); ?></td>
						<td><?php echo esc_html( $date ); ?></td>
						<td><?php echo esc_html( (string) count( $day['slots'] ) ); ?></td>
						<td>
							<select name="cfp_map[<?php echo esc_attr( $date ); ?>]">
								<option value="0"><?php esc_html_e( '— skip —', 'stgl' ); ?></option>
								<?php foreach ( $terms as $term_id => $term ) : ?>
									<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $selected, $term_id ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<a href="<?php echo esc_url( self::page_url() ); ?>" class="button"><?php esc_html_e( 'Back', 'stgl' ); ?></a>
				<?php submit_button( __( 'Show dry run', 'stgl' ), 'primary', '', false ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Fetch an event and its slots and split the slots into calendar days.
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, array{index: int, label: string, slots: array<int, array<string, mixed>>}>}|null
	 */
	private static function load_event( string $event_id ): ?array {
		$client = new Cfp_Client();

		$events = $client->events();
		if ( is_wp_error( $events ) ) {
			self::notice( 'error', $events->get_error_message() );
			return null;
		}
		$event = null;
		foreach ( $events as $candidate ) {
			if ( (string) ( $candidate['id'] ?? '' ) === $event_id ) {
				$event = $candidate;
				break;
			}
		}
		if ( null === $event ) {
			self::notice( 'error', __( 'The CFP event was not found.', 'stgl' ) );
			return null;
		}

		$slots = $client->slots( $event_id );
		if ( is_wp_error( $slots ) ) {
			self::notice( 'error', $slots->get_error_message() );
			self::settings_hint();
			return null;
		}

		return [ $event, self::split_days( $event, $slots ) ];
	}

	/**
	 * Every date from starts_on to ends_on is a numbered day. Slots on a date
	 * outside that range still get a row so nothing is silently dropped.
	 *
	 * @param array<string, mixed>             $event
	 * @param array<int, array<string, mixed>> $slots
	 * @return array<string, array{index: int, label: string, slots: array<int, array<string, mixed>>}>
	 */
	private static function split_days( array $event, array $slots ): array {
		$days  = [];
		$start = self::date_part( (string) ( $event['starts_on'] ?? '' ) );
		$end   = self::date_part( (string) ( $event['ends_on'] ?? '' ) );

		if ( '' !== $start ) {
			$utc    = new \DateTimeZone( 'UTC' );
			$cursor = new \DateTimeImmutable( $start, $utc );
			$last   = new \DateTimeImmutable( '' !== $end && $end >= $start ? $end : $start, $utc );
			for ( $i = 1; $cursor <= $last && $i <= self::MAX_DAYS; $i++ ) {
				$days[ $cursor->format( 'Y-m-d' ) ] = [
					'index' => $i,
					/* translators: %d: day number within the event */
					'label' => sprintf( __( 'Day %d', 'stgl' ), $i ),
					'slots' => [],
				];
				$cursor = $cursor->modify( '+1 day' );
			}
		}

		foreach ( $slots as $slot ) {
			$date = self::date_part( (string) ( $slot['starts_at'] ?? '' ) );
			if ( '' === $date ) {
				continue;
			}
			if ( ! isset( $days[ $date ] ) ) {
				$days[ $date ] = [
					'index' => 0,
					'label' => __( 'Outside event dates', 'stgl' ),
					'slots' => [],
				];
			}
			$days[ $date ]['slots'][] = $slot;
		}

		ksort( $days );
		foreach ( $days as &$day ) {
			usort( $day['slots'], static function ( $a, $b ) {
				return [ (string) $a['starts_at'], (int) ( $a['sort_order'] ?? 0 ) ] <=> [ (string) $b['starts_at'], (int) ( $b['sort_order'] ?? 0 ) ];
			} );
		}
		unset( $day );

		return $days;
	}

	private static function date_part( string $iso ): string {
		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', $iso, $m ) ? $m[1] : '';
	}

	/**
	 * @return array<int, \WP_Term>
	 */
	private static function terms(): array {
		$terms = get_terms( [
			'taxonomy'   => Post_Types::TAX_EVENT,
			'hide_empty' => false,
			'orderby'    => 'name',
		] );
		$out = [];
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[ (int) $term->term_id ] = $term;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, int> date => term id
	 */
	private static function saved_mapping( string $event_id ): array {
		$all = get_option( Installer::OPTION_CFP_EVENT_MAP, [] );
		$map = is_array( $all ) && is_array( $all[ $event_id ] ?? null ) ? $all[ $event_id ] : [];
		return array_map( 'intval', $map );
	}

	/**
	 * @param array<string, int> $map date => term id (0 = skip)
	 */
	private static function save_mapping( string $event_id, array $map ): void {
		$all = get_option( Installer::OPTION_CFP_EVENT_MAP, [] );
		$all = is_array( $all ) ? $all : [];

		$all[ $event_id ] = $map;
		update_option( Installer::OPTION_CFP_EVENT_MAP, $all, false );
	}

	/**
	 * Preselect a category by number: CFP "SwiNOG-42" day 2 of a two-day event
	 * matches "SwiNOG #42-2" / swinog-42-2; a one-day event matches
	 * "SwiNOG #41" / swinog-41 (or "…-1").
	 *
	 * @param array<int, \WP_Term>  $terms
	 * @param array<string, mixed>  $event
	 */
	private static function guess_term( array $terms, array $event, int $day_index, int $day_count ): int {
		if ( $day_index < 1 ) {
			return 0;
		}
		$number = self::trailing_number( (string) ( $event['name'] ?? '' ) ) ?: self::trailing_number( (string) ( $event['slug'] ?? '' ) );
		if ( '' === $number ) {
			return 0;
		}

		$wanted = $day_count > 1 ? [ [ $number, (string) $day_index ] ] : [ [ $number, '' ], [ $number, '1' ] ];
		foreach ( $wanted as $key ) {
			foreach ( $terms as $term_id => $term ) {
				if ( in_array( $key, [ self::term_key( $term->name ), self::term_key( $term->slug ) ], true ) ) {
					return $term_id;
				}
			}
		}
		return 0;
	}

	private static function trailing_number( string $text ): string {
		return preg_match( '/(\d+)\s*$/', $text, $m ) ? $m[1] : '';
	}

	/**
	 * "SwiNOG #42-2" → ['42', '2'], "swinog-41" → ['41', ''].
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function term_key( string $text ): array {
		if ( preg_match( '/(\d+)\s*-\s*(\d+)\s*$/', $text, $m ) ) {
			return [ $m[1], $m[2] ];
		}
		return [ self::trailing_number( $text ), '' ];
	}

	/* ------------------------------------------------------------------ */
	/*  Step 3 – dry run                                                  */
	/* ------------------------------------------------------------------ */

	private function render_preview(): void {
		$event_id = isset( $_POST['cfp_event'] ) ? sanitize_text_field( wp_unslash( $_POST['cfp_event'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in render_page().
		$posted   = isset( $_POST['cfp_map'] ) ? (array) wp_unslash( $_POST['cfp_map'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$back_url = self::page_url( [ 'cfp_event' => $event_id ] );

		$data = self::load_event( $event_id );
		if ( null === $data ) {
			return;
		}
		[ $event, $days ] = $data;
		$terms = self::terms();

		$map = [];
		foreach ( $days as $date => $day ) {
			$term_id      = (int) ( $posted[ $date ] ?? 0 );
			$map[ $date ] = isset( $terms[ $term_id ] ) ? $term_id : 0;
		}
		self::save_mapping( $event_id, $map );

		$chosen = array_filter( $map );
		if ( [] === $chosen ) {
			self::notice( 'warning', __( 'No day is mapped to an event category – there is nothing to do.', 'stgl' ) );
			printf( '<p><a href="%s" class="button">%s</a></p>', esc_url( $back_url ), esc_html__( 'Back', 'stgl' ) );
			return;
		}
		if ( count( $chosen ) !== count( array_unique( $chosen ) ) ) {
			self::notice( 'error', __( 'Each event category can only be used for one day.', 'stgl' ) );
			printf( '<p><a href="%s" class="button">%s</a></p>', esc_url( $back_url ), esc_html__( 'Back', 'stgl' ) );
			return;
		}

		$plan = [
			'user'  => get_current_user_id(),
			'event' => (string) ( $event['name'] ?? '' ),
			'days'  => [],
		];
		foreach ( $chosen as $date => $term_id ) {
			$plan['days'][] = [
				'date'   => $date,
				'label'  => $days[ $date ]['label'],
				'term'   => $term_id,
				'delete' => self::existing_posts( $term_id ),
				'import' => array_map( [ self::class, 'normalize_slot' ], $days[ $date ]['slots'] ),
			];
		}

		$token = wp_generate_password( 20, false );
		set_transient( self::PLAN_PREFIX . $token, $plan, self::PLAN_TTL );

		$delete_total = 0;
		$import_total = 0;
		$term_names   = [];
		?>
		<h2><?php echo esc_html( sprintf( /* translators: %s: CFP event name */ __( 'Dry run: %s', 'stgl' ), $plan['event'] ) ); ?></h2>

		<?php foreach ( $plan['days'] as $day ) : ?>
			<?php
			$term          = $terms[ $day['term'] ];
			$term_names[]  = $term->name;
			$delete_total += count( $day['delete'] );
			$import_total += count( $day['import'] );
			?>
			<h3 style="margin-top:2em">
				<?php echo esc_html( sprintf( '%s · %s → %s', $day['label'], $day['date'], $term->name ) ); ?>
			</h3>

			<h4><?php echo esc_html( sprintf( /* translators: %d: number of presentations */ __( 'Moved to trash (%d)', 'stgl' ), count( $day['delete'] ) ) ); ?></h4>
			<?php self::render_delete_table( $day['delete'] ); ?>

			<h4><?php echo esc_html( sprintf( /* translators: %d: number of slots */ __( 'Imported from CFP (%d)', 'stgl' ), count( $day['import'] ) ) ); ?></h4>
			<?php if ( [] === $day['import'] ) : ?>
				<?php self::notice( 'warning', __( 'The CFP tool has no slots for this day – the category will be emptied.', 'stgl' ) ); ?>
			<?php else : ?>
				<?php self::render_import_table( $day['import'] ); ?>
			<?php endif; ?>
		<?php endforeach; ?>

		<form method="post" action="<?php echo esc_url( self::page_url() ); ?>" style="margin-top:2em">
			<?php wp_nonce_field( 'stgl_cfp_import', 'stgl_cfp_nonce' ); ?>
			<input type="hidden" name="stgl_cfp_step" value="import" />
			<input type="hidden" name="plan" value="<?php echo esc_attr( $token ); ?>" />

			<p>
				<label>
					<input type="checkbox" name="confirm" value="1" required />
					<strong>
						<?php
						echo esc_html( sprintf(
							/* translators: 1: number of existing presentations, 2: comma-separated category names, 3: number of CFP slots */
							__( 'I understand that all %1$d existing presentations in %2$s will be moved to the trash and replaced by the %3$d entries imported from the CFP tool.', 'stgl' ),
							$delete_total,
							implode( ', ', $term_names ),
							$import_total
						) );
						?>
					</strong>
				</label>
			</p>

			<p class="submit">
				<a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( 'Back', 'stgl' ); ?></a>
				<?php submit_button( __( 'Delete & import', 'stgl' ), 'primary', '', false ); ?>
			</p>
			<p class="description"><?php esc_html_e( 'This dry run is valid for 30 minutes.', 'stgl' ); ?></p>
		</form>
		<?php
	}

	/**
	 * Presentations currently in a category, in any status but trash.
	 *
	 * @return array<int, array{id: int, title: string, time: string, presenter: string, status: string, file: bool}>
	 */
	private static function existing_posts( int $term_id ): array {
		$ids = get_posts( [
			'post_type'      => Post_Types::CPT_PRESENTATION,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy'         => Post_Types::TAX_EVENT,
					'field'            => 'term_id',
					'terms'            => $term_id,
					'include_children' => false,
				],
			],
		] );

		$out = [];
		foreach ( array_map( 'intval', $ids ) as $id ) {
			$legacy = get_post_meta( $id, 'wp_custom_attachment', true );
			$out[]  = [
				'id'        => $id,
				'title'     => get_the_title( $id ),
				'time'      => (string) get_post_meta( $id, 'stgl_presenter_time', true ),
				'presenter' => (string) get_post_meta( $id, 'stgl_presenter_name', true ),
				'status'    => (string) get_post_status( $id ),
				'file'      => (int) get_post_meta( $id, '_stgl_presentation_attachment_id', true ) > 0 || ( is_array( $legacy ) && ! empty( $legacy['url'] ) ),
			];
		}
		usort( $out, static fn( $a, $b ) => [ $a['time'], $a['title'] ] <=> [ $b['time'], $b['title'] ] );
		return $out;
	}

	/**
	 * Map a CFP slot onto the presentation fields.
	 *
	 * @param array<string, mixed> $slot
	 * @return array<string, mixed>
	 */
	private static function normalize_slot( array $slot ): array {
		$starts_at = (string) ( $slot['starts_at'] ?? '' );
		$slot_type = sanitize_key( (string) ( $slot['slot_type'] ?? '' ) );

		return [
			'slot_id'       => sanitize_text_field( (string) ( $slot['id'] ?? '' ) ),
			'submission_id' => sanitize_text_field( (string) ( $slot['submission_id'] ?? '' ) ),
			'slot_type'     => $slot_type,
			'type'          => Cfp_Client::map_slot_type( $slot_type ),
			'title'         => sanitize_text_field( (string) ( $slot['title'] ?? '' ) ),
			'abstract'      => wp_kses_post( (string) ( $slot['abstract'] ?? '' ) ),
			'name'          => sanitize_text_field( (string) ( $slot['presenter_name'] ?? '' ) ),
			'company'       => sanitize_text_field( (string) ( $slot['presenter_organization'] ?? '' ) ),
			'email'         => sanitize_email( (string) ( $slot['presenter_email'] ?? '' ) ),
			'publish'       => ! empty( $slot['consent_publish_presentation'] ),
			'video_url'     => esc_url_raw( (string) ( $slot['video_url'] ?? '' ) ),
			'publish_video' => ! empty( $slot['consent_publish_videos'] ),
			'time'          => preg_match( '/T(\d{2}:\d{2})/', $starts_at, $m ) ? $m[1] : '',
			'length'        => max( 0, (int) ( $slot['duration_minutes'] ?? 0 ) ),
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private static function render_delete_table( array $rows ): void {
		if ( [] === $rows ) {
			echo '<p class="description">' . esc_html__( 'The category has no presentations yet.', 'stgl' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:70px"><?php esc_html_e( 'Time', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Title', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Presenter', 'stgl' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Status', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Slides', 'stgl' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['time'] ); ?></td>
					<td><a href="<?php echo esc_url( (string) get_edit_post_link( $row['id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['title'] ); ?></a></td>
					<td><?php echo esc_html( $row['presenter'] ); ?></td>
					<td><?php echo esc_html( $row['status'] ); ?></td>
					<td>
						<?php if ( $row['file'] ) : ?>
							<span style="color:#b32d2e"><?php esc_html_e( 'File attached – will not be carried over', 'stgl' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private static function render_import_table( array $rows ): void {
		$types   = Installer::presentation_types();
		$default = Installer::default_presentation_type();
		$yes     = '<span class="dashicons dashicons-yes" style="color:#46b450"></span>';
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:70px"><?php esc_html_e( 'Schedule', 'stgl' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Min.', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Type', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Title / abstract', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Presenter', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Company', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'E-mail', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Publish slides', 'stgl' ); ?></th>
					<th><?php esc_html_e( 'Video', 'stgl' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php $type = '' !== $row['type'] ? $row['type'] : $default; ?>
				<tr>
					<td><?php echo esc_html( $row['time'] ); ?></td>
					<td><?php echo esc_html( (string) $row['length'] ); ?></td>
					<td>
						<?php echo esc_html( (string) ( $types[ $type ] ?? $type ) ); ?><br />
						<code style="font-size:11px"><?php echo esc_html( $row['slot_type'] ); ?></code>
					</td>
					<td>
						<strong><?php echo esc_html( $row['title'] ); ?></strong>
						<?php if ( '' !== $row['abstract'] ) : ?>
							<br /><span class="description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $row['abstract'] ), 20 ) ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row['name'] ); ?></td>
					<td><?php echo esc_html( $row['company'] ); ?></td>
					<td><?php echo esc_html( $row['email'] ); ?></td>
					<td><?php echo $row['publish'] ? $yes : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td>
						<?php if ( '' !== $row['video_url'] ) : ?>
							<a href="<?php echo esc_url( $row['video_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'link', 'stgl' ); ?></a>
						<?php endif; ?>
						<?php echo $row['publish_video'] ? $yes : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Step 4 – import                                                   */
	/* ------------------------------------------------------------------ */

	private function render_import(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in render_page().
		$token   = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : '';
		$confirm = ! empty( $_POST['confirm'] );
		// phpcs:enable

		$plan = '' !== $token ? get_transient( self::PLAN_PREFIX . $token ) : false;
		if ( ! is_array( $plan ) || (int) ( $plan['user'] ?? 0 ) !== get_current_user_id() ) {
			self::notice( 'error', __( 'The dry run has expired. Please run it again.', 'stgl' ) );
			printf( '<p><a href="%s" class="button">%s</a></p>', esc_url( self::page_url() ), esc_html__( 'Start again', 'stgl' ) );
			return;
		}
		if ( ! $confirm ) {
			self::notice( 'error', __( 'Please tick the confirmation checkbox.', 'stgl' ) );
			return;
		}

		// Refuse if a category changed since the dry run, so nothing is
		// deleted that was not on screen.
		foreach ( $plan['days'] as $day ) {
			$now    = array_column( self::existing_posts( (int) $day['term'] ), 'id' );
			$before = array_column( $day['delete'], 'id' );
			sort( $now );
			sort( $before );
			if ( $now !== $before ) {
				self::notice( 'error', __( 'Presentations in a selected category were changed after the dry run. Nothing was imported – please run the dry run again.', 'stgl' ) );
				printf( '<p><a href="%s" class="button">%s</a></p>', esc_url( self::page_url() ), esc_html__( 'Start again', 'stgl' ) );
				return;
			}
		}

		delete_transient( self::PLAN_PREFIX . $token );

		$errors = [];
		echo '<h2>' . esc_html( sprintf( /* translators: %s: CFP event name */ __( 'Import: %s', 'stgl' ), (string) $plan['event'] ) ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:800px"><thead><tr>';
		echo '<th>' . esc_html__( 'Event category', 'stgl' ) . '</th>';
		echo '<th>' . esc_html__( 'Moved to trash', 'stgl' ) . '</th>';
		echo '<th>' . esc_html__( 'Imported', 'stgl' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $plan['days'] as $day ) {
			$term_id = (int) $day['term'];
			$term    = get_term( $term_id, Post_Types::TAX_EVENT );

			$trashed = 0;
			foreach ( $day['delete'] as $row ) {
				if ( wp_trash_post( (int) $row['id'] ) ) {
					$trashed++;
				} else {
					/* translators: %s: presentation title */
					$errors[] = sprintf( __( 'Could not move "%s" to the trash.', 'stgl' ), $row['title'] );
				}
			}

			$created = 0;
			foreach ( $day['import'] as $row ) {
				$result = self::create_presentation( $row, $term_id );
				if ( is_wp_error( $result ) ) {
					/* translators: 1: slot title, 2: error message */
					$errors[] = sprintf( __( 'Could not import "%1$s": %2$s', 'stgl' ), $row['title'], $result->get_error_message() );
				} else {
					$created++;
				}
			}

			$name = $term instanceof \WP_Term ? $term->name : (string) $term_id;
			$list = $term instanceof \WP_Term
				? admin_url( 'edit.php?post_type=' . Post_Types::CPT_PRESENTATION . '&' . Post_Types::TAX_EVENT . '=' . rawurlencode( $term->slug ) )
				: '';
			printf(
				'<tr><td>%s</td><td>%d</td><td>%d</td></tr>',
				$list ? '<a href="' . esc_url( $list ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ),
				(int) $trashed,
				(int) $created
			);
		}
		echo '</tbody></table>';

		foreach ( $errors as $error ) {
			self::notice( 'error', $error );
		}
		if ( [] === $errors ) {
			self::notice( 'success', __( 'Import finished.', 'stgl' ) );
		}
		printf( '<p><a href="%s" class="button">%s</a></p>', esc_url( self::page_url() ), esc_html__( 'Sync another event', 'stgl' ) );
	}

	/**
	 * @param array<string, mixed> $row Normalized slot.
	 * @return int|\WP_Error
	 */
	private static function create_presentation( array $row, int $term_id ) {
		$post_id = wp_insert_post( [
			'post_type'    => Post_Types::CPT_PRESENTATION,
			'post_status'  => 'publish',
			'post_title'   => (string) $row['title'],
			'post_content' => (string) $row['abstract'],
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = [
			'stgl_presenter_type'          => (string) $row['type'],
			'stgl_presenter_name'          => (string) $row['name'],
			'stgl_presenter_company'       => (string) $row['company'],
			'stgl_presenter_email'         => (string) $row['email'],
			'stgl_presenter_publish'       => $row['publish'] ? '1' : '',
			'stgl_presenter_videourl'      => (string) $row['video_url'],
			'stgl_presenter_publish_video' => $row['publish_video'] ? '1' : '',
			'stgl_presenter_time'          => (string) $row['time'],
			'stgl_presenter_lenght'        => (int) $row['length'],
			'stgl_presenter_bio'           => '',
			self::META_SLOT_ID             => (string) $row['slot_id'],
			self::META_SUB_ID              => (string) $row['submission_id'],
		];
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		$terms = wp_set_object_terms( $post_id, [ $term_id ], Post_Types::TAX_EVENT );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		return $post_id;
	}
}
