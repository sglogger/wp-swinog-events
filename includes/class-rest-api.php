<?php
/**
 * Custom REST API endpoints under `/wp-json/swinog/v1/…`.
 *
 * Built on top of the standard `show_in_rest` exposure of our CPTs, but adds
 * convenience aggregations that are tedious to assemble client-side:
 *
 *   GET /swinog/v1/events                 List of events with summary fields
 *   GET /swinog/v1/events/{slug}          Single event + its sponsors + agenda
 *   GET /swinog/v1/events/{slug}/agenda   Just the agenda (presentations)
 *   GET /swinog/v1/events/{slug}/sponsors Sponsors only
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class REST_API {

	private const NS = 'swinog/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/events', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_events' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'show' => [
					'type'        => 'string',
					'enum'        => [ 'all', 'upcoming', 'past' ],
					'default'     => 'all',
					'description' => 'Filter by past / upcoming / all events.',
				],
			],
		] );

		register_rest_route( self::NS, '/events/(?P<slug>[a-z0-9_\-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_event' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/events/(?P<slug>[a-z0-9_\-]+)/agenda', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_agenda' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/events/(?P<slug>[a-z0-9_\-]+)/sponsors', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_sponsors' ],
			'permission_callback' => '__return_true',
		] );
	}

	/* ------------------------------------------------------------------ */

	public function list_events( \WP_REST_Request $req ): \WP_REST_Response {
		$show  = (string) $req->get_param( 'show' );
		$today = current_time( 'Y-m-d' );

		$args = [
			'post_type'      => Post_Types::CPT_EVENT,
			'posts_per_page' => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => 'stgl_event_date', // phpcs:ignore
			'order'          => 'ASC',
			'no_found_rows'  => true,
		];
		if ( 'upcoming' === $show ) {
			$args['meta_query'] = [ [ 'key' => 'stgl_event_date', 'value' => $today, 'compare' => '>=' ] ]; // phpcs:ignore
		} elseif ( 'past' === $show ) {
			$args['meta_query'] = [ [ 'key' => 'stgl_event_date', 'value' => $today, 'compare' => '<' ] ]; // phpcs:ignore
			$args['order']      = 'DESC';
		}

		$query = new \WP_Query( $args );

		$out = [];
		foreach ( $query->posts as $post ) {
			$out[] = $this->event_summary( $post );
		}
		return new \WP_REST_Response( $out, 200 );
	}

	public function get_event( \WP_REST_Request $req ) {
		$slug = sanitize_title( (string) $req->get_param( 'slug' ) );
		$post = $this->find_event_post( $slug );
		if ( ! $post ) {
			return new \WP_Error( 'stgl_event_not_found', __( 'Event not found', 'stgl' ), [ 'status' => 404 ] );
		}
		return new \WP_REST_Response(
			[
				'event'    => $this->event_summary( $post ),
				'agenda'   => $this->collect_agenda( $slug ),
				'sponsors' => $this->collect_sponsors( $slug ),
			],
			200
		);
	}

	public function get_agenda( \WP_REST_Request $req ) {
		$slug = sanitize_title( (string) $req->get_param( 'slug' ) );
		if ( ! $this->find_event_post( $slug ) ) {
			return new \WP_Error( 'stgl_event_not_found', __( 'Event not found', 'stgl' ), [ 'status' => 404 ] );
		}
		return new \WP_REST_Response( $this->collect_agenda( $slug ), 200 );
	}

	public function get_sponsors( \WP_REST_Request $req ) {
		$slug = sanitize_title( (string) $req->get_param( 'slug' ) );
		if ( ! $this->find_event_post( $slug ) ) {
			return new \WP_Error( 'stgl_event_not_found', __( 'Event not found', 'stgl' ), [ 'status' => 404 ] );
		}
		return new \WP_REST_Response( $this->collect_sponsors( $slug ), 200 );
	}

	/* ------------------------------------------------------------------ */

	private function find_event_post( string $slug ): ?\WP_Post {
		$term = get_term_by( 'slug', $slug, Post_Types::TAX_EVENT );
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}
		$query = new \WP_Query( [
			'post_type'      => Post_Types::CPT_EVENT,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'tax_query'      => [
				[ 'taxonomy' => Post_Types::TAX_EVENT, 'field' => 'slug', 'terms' => $slug ],
			],
		] );
		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function event_summary( \WP_Post $post ): array {
		$id      = $post->ID;
		$terms   = get_the_terms( $id, Post_Types::TAX_EVENT );
		$slug    = '';
		if ( is_array( $terms ) && $terms ) {
			$slug = $terms[0]->slug;
		}
		return [
			'id'           => $id,
			'name'         => get_the_title( $post ),
			'slug'         => $slug,
			'permalink'    => get_permalink( $post ),
			'description'  => apply_filters( 'the_content', $post->post_content ),
			'date'         => Helpers\to_iso_date( (string) get_post_meta( $id, 'stgl_event_date', true ) ),
			'end_date'     => Helpers\to_iso_date( (string) get_post_meta( $id, 'stgl_event_end_date', true ) ),
			'location'     => (string) get_post_meta( $id, 'stgl_event_location', true ),
			'lat'          => (string) get_post_meta( $id, 'stgl_event_venue_lat', true ),
			'lng'          => (string) get_post_meta( $id, 'stgl_event_venue_lng', true ),
			'register_url' => (string) get_post_meta( $id, 'stgl_event_reg_url', true ),
			'cfp_open'     => (bool) get_post_meta( $id, 'stgl_event_cfp_open', true ),
			'cfp_url'      => (string) get_post_meta( $id, 'stgl_event_cfp_url', true ),
			'max_seats'    => (int) get_post_meta( $id, 'stgl_event_max_seats', true ),
			'ical_url'     => $slug ? home_url( '/?stgl_ical=' . rawurlencode( $slug ) ) : '',
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_agenda( string $slug ): array {
		$query = new \WP_Query( [
			'post_type'      => Post_Types::CPT_PRESENTATION,
			'posts_per_page' => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => 'stgl_presenter_time', // phpcs:ignore
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'tax_query'      => [
				[ 'taxonomy' => Post_Types::TAX_EVENT, 'field' => 'slug', 'terms' => $slug ],
			],
		] );

		$out = [];
		foreach ( $query->posts as $post ) {
			$id          = $post->ID;
			$publish     = (bool) get_post_meta( $id, 'stgl_presenter_publish', true );
			$video_pub   = (bool) get_post_meta( $id, 'stgl_presenter_publish_video', true );
			$attachment  = '';
			if ( $publish ) {
				$att_id = (int) get_post_meta( $id, '_stgl_presentation_attachment_id', true );
				if ( $att_id ) {
					$attachment = (string) wp_get_attachment_url( $att_id );
				} else {
					$legacy = get_post_meta( $id, 'wp_custom_attachment', true );
					if ( is_array( $legacy ) && ! empty( $legacy['url'] ) ) {
						$attachment = (string) $legacy['url'];
					}
				}
			}

			$out[] = [
				'id'             => $id,
				'title'          => get_the_title( $post ),
				'abstract'       => wp_strip_all_tags( $post->post_content ),
				'time'           => (string) get_post_meta( $id, 'stgl_presenter_time', true ),
				'length_minutes' => (int) get_post_meta( $id, 'stgl_presenter_lenght', true ),
				'presenter'      => (string) get_post_meta( $id, 'stgl_presenter_name', true ),
				'company'        => (string) get_post_meta( $id, 'stgl_presenter_company', true ),
				'bio'            => (string) get_post_meta( $id, 'stgl_presenter_bio', true ),
				'twitter'        => (string) get_post_meta( $id, 'stgl_presenter_twitter', true ),
				'linkedin'       => (string) get_post_meta( $id, 'stgl_presenter_linkedin', true ),
				'photo'          => (string) get_the_post_thumbnail_url( $id, 'medium' ),
				'slides_url'     => $attachment,
				'video_url'      => $video_pub ? (string) get_post_meta( $id, 'stgl_presenter_videourl', true ) : '',
			];
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_sponsors( string $slug ): array {
		$query = new \WP_Query( [
			'post_type'      => Post_Types::CPT_SPONSOR,
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'stgl_sponsor_level', // phpcs:ignore
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'tax_query'      => [
				[ 'taxonomy' => Post_Types::TAX_EVENT, 'field' => 'slug', 'terms' => $slug ],
			],
		] );

		$levels = get_option( Installer::OPTION_SPONSOR_LEVELS, Installer::default_sponsor_levels() );
		$out    = [];
		foreach ( $query->posts as $post ) {
			$id     = $post->ID;
			$weight = (int) get_post_meta( $id, 'stgl_sponsor_level', true );
			$out[]  = [
				'id'           => $id,
				'name'         => get_the_title( $post ),
				'description'  => wp_strip_all_tags( $post->post_content ),
				'level'        => (string) ( $levels[ $weight ] ?? '' ),
				'level_weight' => $weight,
				'url'          => (string) get_post_meta( $id, 'stgl_sponsor_url', true ),
				'logo'         => (string) get_the_post_thumbnail_url( $id, 'large' ),
			];
		}
		return $out;
	}
}
