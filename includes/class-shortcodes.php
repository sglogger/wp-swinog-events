<?php
/**
 * Shortcodes – preserves the v0.x shortcode names and attributes.
 *
 *   [swinog_list_presentations event="swinog-NN"]   – agenda / talks
 *   [swinog_sponsor             event="swinog-NN"]  – sponsor grid
 *   [swinog_event               event="swinog-NN"]  – event listing (was buggy in v0.x)
 *
 * Plus three new shortcodes:
 *
 *   [swinog_event_card slug="swinog-NN"]           – a hero card for an event
 *   [swinog_upcoming_events posts="3"]             – the next N upcoming events
 *   [swinog_cfp]                                   – CFP banner if a CFP is currently open
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	public function register(): void {
		add_shortcode( 'swinog_list_presentations', [ $this, 'presentations' ] );
		add_shortcode( 'swinog_sponsor',             [ $this, 'sponsors' ] );
		add_shortcode( 'swinog_event',               [ $this, 'events' ] );
		add_shortcode( 'swinog_event_card',          [ $this, 'event_card' ] );
		add_shortcode( 'swinog_upcoming_events',     [ $this, 'upcoming_events' ] );
		add_shortcode( 'swinog_cfp',                 [ $this, 'cfp_banner' ] );
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_list_presentations]                                       */
	/* ------------------------------------------------------------------ */

	public function presentations( $atts ): string {
		$atts = shortcode_atts( [
			'event'    => '',
			'orderby'  => 'meta_value',
			'order'    => 'ASC',
			'meta_key' => 'stgl_presenter_time',
			'posts'    => -1,
		], (array) $atts, 'swinog_list_presentations' );

		$query = new \WP_Query( [
			'post_type'              => Post_Types::CPT_PRESENTATION,
			'posts_per_page'         => (int) $atts['posts'],
			'orderby'                => $atts['orderby'],
			'meta_key'               => sanitize_key( (string) $atts['meta_key'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'                  => 'DESC' === strtoupper( (string) $atts['order'] ) ? 'DESC' : 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'tax_query'              => $this->event_tax_query( (string) $atts['event'] ),
		] );

		$tax_name = $this->term_name( (string) $atts['event'] );

		ob_start();

		if ( ! $query->have_posts() ) {
			printf(
				'<p class="stgl-empty">%s</p>',
				esc_html( sprintf( __( 'No presentations for event %s have been published yet.', 'stgl' ), $tax_name ) )
			);
			return (string) ob_get_clean();
		}

		?>
		<section class="stgl-block stgl-presentations">
			<h2 class="stgl-block-title"><?php echo esc_html( sprintf( __( 'Presentations @ %s', 'stgl' ), $tax_name ) ); ?></h2>

			<table class="stgl-table stgl-table-presentations">
				<thead>
					<tr>
						<th class="col-time"><?php esc_html_e( 'Time', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Topic', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Presenter', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Company', 'stgl' ); ?></th>
						<th class="col-links"><?php esc_html_e( 'Links', 'stgl' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				while ( $query->have_posts() ) {
					$query->the_post();
					$id = get_the_ID();

					$presenter   = (string) get_post_meta( $id, 'stgl_presenter_name', true );
					$company     = (string) get_post_meta( $id, 'stgl_presenter_company', true );
					$time        = (string) get_post_meta( $id, 'stgl_presenter_time', true );
					$publish     = (bool) get_post_meta( $id, 'stgl_presenter_publish', true );
					$video_pub   = (bool) get_post_meta( $id, 'stgl_presenter_publish_video', true );
					$video_url   = (string) get_post_meta( $id, 'stgl_presenter_videourl', true );
					$attachment  = self::resolve_attachment( $id );

					$file_url = ( $publish && $attachment ) ? $attachment : '';
					?>
					<tr class="stgl-row">
						<td class="col-time"><?php echo esc_html( $time ); ?></td>
						<td class="col-title">
							<strong>
								<?php if ( $file_url ) : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php the_title(); ?></a>
								<?php else : ?>
									<?php the_title(); ?>
								<?php endif; ?>
							</strong>
							<?php
							$abstract = wp_trim_words( wp_strip_all_tags( (string) get_the_content() ), 40 );
							if ( $abstract ) {
								echo '<br><small class="stgl-abstract">' . esc_html( $abstract ) . '</small>';
							}
							?>
						</td>
						<td><?php echo esc_html( $presenter ); ?></td>
						<td><?php echo esc_html( $company ); ?></td>
						<td class="col-links">
							<?php if ( $file_url ) : ?>
								<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener" class="stgl-link stgl-link-slides">
									<?php esc_html_e( 'Slides', 'stgl' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $video_pub && $video_url ) : ?>
								<a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener" class="stgl-link stgl-link-video">
									<?php esc_html_e( 'Video', 'stgl' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				}
				wp_reset_postdata();
				?>
				</tbody>
			</table>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_sponsor]                                                  */
	/* ------------------------------------------------------------------ */

	public function sponsors( $atts ): string {
		$atts = shortcode_atts( [
			'event'    => '',
			'orderby'  => 'meta_value_num',
			'order'    => 'DESC',
			'meta_key' => 'stgl_sponsor_level',
			'posts'    => -1,
			'layout'   => 'tiers', // 'tiers' (grouped) or 'list' (flat)
		], (array) $atts, 'swinog_sponsor' );

		$query = new \WP_Query( [
			'post_type'              => Post_Types::CPT_SPONSOR,
			'posts_per_page'         => (int) $atts['posts'],
			'orderby'                => $atts['orderby'],
			'meta_key'               => sanitize_key( (string) $atts['meta_key'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'                  => 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'tax_query'              => $this->event_tax_query( (string) $atts['event'] ),
		] );

		$tax_name = $this->term_name( (string) $atts['event'] );

		ob_start();

		if ( ! $query->have_posts() ) {
			printf(
				'<p class="stgl-empty">%s</p>',
				esc_html( sprintf( __( 'No sponsors for event %s have been published yet.', 'stgl' ), $tax_name ) )
			);
			return (string) ob_get_clean();
		}

		$levels = get_option( Installer::OPTION_SPONSOR_LEVELS, Installer::default_sponsor_levels() );

		?>
		<section class="stgl-block stgl-sponsors stgl-layout-<?php echo esc_attr( $atts['layout'] ); ?>">
			<h2 class="stgl-block-title"><?php echo esc_html( sprintf( __( 'Sponsors of %s', 'stgl' ), $tax_name ) ); ?></h2>

			<?php
			$current_level = null;
			$open_group    = false;

			while ( $query->have_posts() ) {
				$query->the_post();
				$id     = get_the_ID();
				$weight = (int) get_post_meta( $id, 'stgl_sponsor_level', true );
				$label  = (string) ( $levels[ $weight ] ?? '' );
				$url    = (string) get_post_meta( $id, 'stgl_sponsor_url', true );

				if ( 'tiers' === $atts['layout'] && $label !== $current_level ) {
					if ( $open_group ) {
						echo '</div>';
					}
					$current_level = $label;
					$pretty = ( 'Supporter' === $label || '' === $label ) ? $label : $label . ' ' . __( 'Sponsor', 'stgl' );
					echo '<h3 class="stgl-sponsor-tier">' . esc_html( $pretty ) . '</h3>';
					echo '<div class="stgl-sponsor-grid">';
					$open_group = true;
				} elseif ( 'list' === $atts['layout'] && ! $open_group ) {
					echo '<div class="stgl-sponsor-grid">';
					$open_group = true;
				}

				$thumb = get_the_post_thumbnail( $id, 'large', [ 'class' => 'stgl-sponsor-logo', 'loading' => 'lazy' ] );

				echo '<article class="stgl-sponsor-card">';
				if ( $thumb ) {
					if ( $url ) {
						echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="stgl-sponsor-link">' . $thumb . '</a>'; // phpcs:ignore
					} else {
						echo $thumb; // phpcs:ignore
					}
				}
				echo '<div class="stgl-sponsor-meta">';
				echo '<strong class="stgl-sponsor-name">';
				if ( $url ) {
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title() ) . '</a>';
				} else {
					echo esc_html( get_the_title() );
				}
				echo '</strong>';
				$desc = wp_strip_all_tags( (string) get_the_content() );
				if ( $desc ) {
					echo '<p class="stgl-sponsor-desc">' . esc_html( $desc ) . '</p>';
				}
				echo '</div>';
				echo '</article>';
			}
			wp_reset_postdata();

			if ( $open_group ) {
				echo '</div>';
			}
			?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_event] – fixed: was rendering "Sponsors of …" in v0.x     */
	/* ------------------------------------------------------------------ */

	public function events( $atts ): string {
		$atts = shortcode_atts( [
			'event'   => '',
			'orderby' => 'meta_value',
			'order'   => 'DESC',
			'posts'   => -1,
			'show'    => 'all', // all|upcoming|past
		], (array) $atts, 'swinog_event' );

		$args = [
			'post_type'              => Post_Types::CPT_EVENT,
			'posts_per_page'         => (int) $atts['posts'],
			'orderby'                => $atts['orderby'],
			'meta_key'               => 'stgl_event_date', // phpcs:ignore
			'order'                  => 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'tax_query'              => $this->event_tax_query( (string) $atts['event'] ),
		];

		$today = current_time( 'Y-m-d' );
		if ( 'upcoming' === $atts['show'] ) {
			$args['meta_query'] = [ // phpcs:ignore
				[ 'key' => 'stgl_event_date', 'value' => $today, 'compare' => '>=', 'type' => 'CHAR' ],
			];
		} elseif ( 'past' === $atts['show'] ) {
			$args['meta_query'] = [ // phpcs:ignore
				[ 'key' => 'stgl_event_date', 'value' => $today, 'compare' => '<', 'type' => 'CHAR' ],
			];
		}

		$query = new \WP_Query( $args );

		ob_start();

		if ( ! $query->have_posts() ) {
			printf( '<p class="stgl-empty">%s</p>', esc_html__( 'No events found.', 'stgl' ) );
			return (string) ob_get_clean();
		}

		?>
		<section class="stgl-block stgl-events">
			<ul class="stgl-event-list">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				$id       = get_the_ID();
				$date     = (string) get_post_meta( $id, 'stgl_event_date', true );
				$location = (string) get_post_meta( $id, 'stgl_event_location', true );
				$reg      = (string) get_post_meta( $id, 'stgl_event_reg_url', true );
				$cfp_open = (bool) get_post_meta( $id, 'stgl_event_cfp_open', true );

				echo '<li class="stgl-event-item">';
				echo '<time datetime="' . esc_attr( Helpers\to_iso_date( $date ) ) . '">' . esc_html( Helpers\format_event_date( $date ) ) . '</time> ';
				echo '<a class="stgl-event-name" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title() ) . '</a>';
				if ( $location ) {
					echo ' <span class="stgl-event-loc">— ' . esc_html( $location ) . '</span>';
				}
				if ( $cfp_open ) {
					echo ' <span class="stgl-pill stgl-pill-cfp">' . esc_html__( 'CFP open', 'stgl' ) . '</span>';
				}
				if ( $reg ) {
					echo ' <a class="stgl-pill stgl-pill-reg" href="' . esc_url( $reg ) . '" target="_blank" rel="noopener">' . esc_html__( 'Register', 'stgl' ) . '</a>';
				}
				echo '</li>';
			}
			wp_reset_postdata();
			?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_event_card]                                               */
	/* ------------------------------------------------------------------ */

	public function event_card( $atts ): string {
		$atts = shortcode_atts( [
			'slug' => '', // event term slug, e.g. "swinog-37"
		], (array) $atts, 'swinog_event_card' );

		$slug = (string) $atts['slug'];
		if ( '' === $slug ) {
			return '';
		}

		// Find the event post that has this term.
		$query = new \WP_Query( [
			'post_type'      => Post_Types::CPT_EVENT,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'tax_query'      => [
				[ 'taxonomy' => Post_Types::TAX_EVENT, 'field' => 'slug', 'terms' => $slug ],
			],
		] );

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			$id       = get_the_ID();
			$date     = (string) get_post_meta( $id, 'stgl_event_date', true );
			$location = (string) get_post_meta( $id, 'stgl_event_location', true );
			$reg      = (string) get_post_meta( $id, 'stgl_event_reg_url', true );
			$cfp_open = (bool) get_post_meta( $id, 'stgl_event_cfp_open', true );
			$cfp_url  = (string) get_post_meta( $id, 'stgl_event_cfp_url', true );
			$ical_url = home_url( '/?stgl_ical=' . rawurlencode( $slug ) );
			?>
			<aside class="stgl-card stgl-event-card">
				<header>
					<h3><?php echo esc_html( get_the_title() ); ?></h3>
					<time datetime="<?php echo esc_attr( Helpers\to_iso_date( $date ) ); ?>">
						<?php echo esc_html( Helpers\format_event_date( $date ) ); ?>
					</time>
				</header>
				<?php if ( $location ) : ?>
					<p class="stgl-card-location">📍 <?php echo esc_html( $location ); ?></p>
				<?php endif; ?>

				<div class="stgl-card-actions">
					<?php if ( $reg ) : ?>
						<a class="stgl-btn stgl-btn-primary" href="<?php echo esc_url( $reg ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Register', 'stgl' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $cfp_open && $cfp_url ) : ?>
						<a class="stgl-btn" href="<?php echo esc_url( $cfp_url ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Submit a talk', 'stgl' ); ?>
						</a>
					<?php endif; ?>
					<a class="stgl-btn stgl-btn-ical" href="<?php echo esc_url( $ical_url ); ?>">
						<?php esc_html_e( 'Add to calendar', 'stgl' ); ?>
					</a>
				</div>
			</aside>
			<?php
		}
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_upcoming_events]                                          */
	/* ------------------------------------------------------------------ */

	public function upcoming_events( $atts ): string {
		$atts = shortcode_atts( [
			'posts' => 3,
		], (array) $atts, 'swinog_upcoming_events' );

		return $this->events( [
			'show'    => 'upcoming',
			'posts'   => (int) $atts['posts'],
			'orderby' => 'meta_value',
			'order'   => 'ASC',
		] );
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_cfp]                                                      */
	/* ------------------------------------------------------------------ */

	public function cfp_banner( $atts ): string {
		$query = new \WP_Query( [
			'post_type'      => Post_Types::CPT_EVENT,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_query'     => [ // phpcs:ignore
				[ 'key' => 'stgl_event_cfp_open', 'value' => '1', 'compare' => '=' ],
			],
			'orderby'        => 'meta_value',
			'meta_key'       => 'stgl_event_date', // phpcs:ignore
			'order'          => 'ASC',
		] );

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			$id      = get_the_ID();
			$cfp_url = (string) get_post_meta( $id, 'stgl_event_cfp_url', true );
			?>
			<div class="stgl-cfp-banner">
				<strong><?php esc_html_e( 'Call for Papers is open!', 'stgl' ); ?></strong>
				<?php
				printf(
					/* translators: %s: event name */
					esc_html__( 'Submit your talk for %s.', 'stgl' ),
					'<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title() ) . '</a>'
				);
				?>
				<?php if ( $cfp_url ) : ?>
					<a class="stgl-btn stgl-btn-primary" href="<?php echo esc_url( $cfp_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Submit a talk', 'stgl' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php
		}
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Build a tax_query clause for the event taxonomy. Empty event = no clause.
	 *
	 * @return array<int, mixed>|array<string, string>
	 */
	private function event_tax_query( string $event_slug ): array {
		if ( '' === $event_slug ) {
			return [];
		}
		return [
			[
				'taxonomy' => Post_Types::TAX_EVENT,
				'field'    => 'slug',
				'terms'    => $event_slug,
			],
		];
	}

	private function term_name( string $event_slug ): string {
		if ( '' === $event_slug ) {
			return __( 'all events', 'stgl' );
		}
		$term = get_term_by( 'slug', $event_slug, Post_Types::TAX_EVENT );
		return $term instanceof \WP_Term ? $term->name : $event_slug;
	}

	/**
	 * Return the public URL of a presentation's slide deck. Looks at the new
	 * `_stgl_presentation_attachment_id` first, then falls back to the legacy
	 * `wp_custom_attachment` array. Returns '' if nothing is attached.
	 */
	private static function resolve_attachment( int $post_id ): string {
		$id = (int) get_post_meta( $post_id, '_stgl_presentation_attachment_id', true );
		if ( $id ) {
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				return (string) $url;
			}
		}
		$legacy = get_post_meta( $post_id, 'wp_custom_attachment', true );
		if ( is_array( $legacy ) && ! empty( $legacy['url'] ) ) {
			return (string) $legacy['url'];
		}
		return '';
	}
}
