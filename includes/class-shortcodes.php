<?php
/**
 * Shortcodes – preserves the v0.x shortcode names and attributes.
 *
 *   [swinog_list_presentations event="swinog-NN"]     – presentations with slides/video links (no time column)
 *   [swinog_list_agenda event="swinog-NN"]          – agenda view with time and talk abstract, no slides/video links
 *   [swinog_sponsor             event="swinog-NN"]  – sponsor grid
 *   [swinog_event               event="swinog-NN"]  – event listing (was buggy in v0.x)
 *   [stgl_list_presentations event="swinog-NN"]     – legacy alias for backwards compatibility
 *
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
		add_shortcode( 'stgl_list_presentations',   [ $this, 'presentations' ] );
		add_shortcode( 'swinog_list_agenda',        [ $this, 'agenda' ] );
		add_shortcode( 'swinog_sponsor',            [ $this, 'sponsors' ] );
	}

	/* ------------------------------------------------------------------ */
	/*  [swinog_list_presentations]                                       */
	/* ------------------------------------------------------------------ */

	public function presentations( $atts ): string {
		return $this->render_presentations_table( (array) $atts, __( 'Presentations', 'stgl' ), false, true );
	}

	public function agenda( $atts ): string {
		return $this->render_presentations_table( (array) $atts, __( 'Agenda', 'stgl' ), true, false );
	}

	private function render_presentations_table( array $atts, string $heading, bool $show_time, bool $show_links ): string {
		if ( '' === (string) ( $atts['event'] ?? '' ) && '' !== (string) ( $atts['cat'] ?? '' ) ) {
			$atts['event'] = $atts['cat'];
		}
		$atts = shortcode_atts( [
			'event'    => '',
			'orderby'  => 'meta_value',
			'order'    => 'ASC',
			'meta_key' => 'stgl_presenter_time',
			'posts'    => -1,
		], $atts, 'swinog_list_presentations' );

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
			<h2 class="stgl-block-title"><?php echo esc_html( sprintf( _x( '%s @ %s', 'presentation title heading', 'stgl' ), $heading, $tax_name ) ); ?></h2>

			<table class="stgl-table stgl-table-presentations">
				<thead>
					<tr>
						<?php if ( $show_time ) : ?>
							<th class="col-time"><?php esc_html_e( 'Time', 'stgl' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Topic', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Presenter', 'stgl' ); ?></th>
						<th><?php esc_html_e( 'Company', 'stgl' ); ?></th>
						<?php if ( $show_links ) : ?>
							<th class="col-links"><?php esc_html_e( 'Links', 'stgl' ); ?></th>
						<?php endif; ?>
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
					<?php if ( $show_time ) : ?>
						<td class="col-time"><?php echo esc_html( $time ); ?></td>
					<?php endif; ?>
					<td class="col-title">
						<strong>
							<?php if ( $show_links && $file_url ) : ?>
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
					<?php if ( $show_links ) : ?>
						<td class="col-links">
							<?php if ( $file_url ) : ?>
								<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener" class="stgl-link stgl-link-slides">
									<?php esc_html_e( 'Slides', 'stgl' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $video_pub && $video_url ) : ?>
								<?php if ( $file_url ) : ?><br><?php endif; ?>
								<a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener" class="stgl-link stgl-link-video"><?php esc_html_e( 'Video', 'stgl' ); ?></a>
							<?php endif; ?>
						</td>
					<?php endif; ?>
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

		$primary_dir = 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC';
		$orderby     = [
			(string) $atts['orderby'] => $primary_dir,
			'title'                   => 'ASC',
		];

		$query = new \WP_Query( [
			'post_type'              => Post_Types::CPT_SPONSOR,
			'posts_per_page'         => (int) $atts['posts'],
			'orderby'                => $orderby,
			'meta_key'               => sanitize_key( (string) $atts['meta_key'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
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
