<?php
/**
 * Custom post types & taxonomy.
 *
 * Slugs and rewrite rules are kept identical to v0.x so existing permalinks
 * keep working.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Types {

	public const CPT_PRESENTATION = 'stgl_presentation';
	public const CPT_SPONSOR      = 'stgl_sponsor';
	public const TAX_EVENT        = 'stgl_presentation_cat';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_types' ], 10 );
		add_action( 'init', [ $this, 'register_taxonomy' ], 9 ); // Taxonomy first.
		add_action( 'init', [ $this, 'register_meta' ], 11 );
		add_filter( 'pre_get_posts', [ $this, 'extend_search' ] );
	}

	/* -------------------------------------------------------------------- */
	/*  Post types                                                          */
	/* -------------------------------------------------------------------- */

	public function register_post_types(): void {

		// --- PRESENTATIONS (top-level menu) -------------------------------
		register_post_type( self::CPT_PRESENTATION, [
			'labels'              => self::labels( __( 'Presentation', 'stgl' ), __( 'Presentations', 'stgl' ), __( 'SwiNOG', 'stgl' ) ),
			'public'              => true,
			'has_archive'         => true,
			'show_in_rest'        => true,
			'rest_base'           => 'swinog-presentations',
			'supports'            => [ 'title', 'editor', 'page-attributes', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
			'taxonomies'          => [ self::TAX_EVENT ],
			'rewrite'             => [ 'slug' => 'presentations', 'with_front' => false ],
			'menu_icon'           => 'dashicons-microphone',
			'capability_type'     => 'post',
			'exclude_from_search' => false,
			'menu_position'       => 25,
		] );

		// --- SPONSORS -----------------------------------------------------
		register_post_type( self::CPT_SPONSOR, [
			'labels'              => self::labels( __( 'Sponsor', 'stgl' ), __( 'Sponsors', 'stgl' ), __( 'Sponsors', 'stgl' ) ),
			'public'              => true,
			'has_archive'         => true,
			'show_in_rest'        => true,
			'rest_base'           => 'swinog-sponsors',
			'supports'            => [ 'title', 'editor', 'page-attributes', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
			'taxonomies'          => [ self::TAX_EVENT ],
			'rewrite'             => [ 'slug' => 'sponsors', 'with_front' => false ],
			'menu_icon'           => 'dashicons-awards',
			'capability_type'     => 'post',
			'exclude_from_search' => false,
			'show_in_menu'        => 'edit.php?post_type=' . self::CPT_PRESENTATION,
		] );
	}

	/**
	 * Build a labels array – keeps post-type registration tidy.
	 *
	 * @return array<string, string>
	 */
	private static function labels( string $singular, string $plural, string $menu ): array {
		return [
			'name'               => $plural,
			'singular_name'      => $singular,
			/* translators: %s: singular post type name */
			'add_new'            => sprintf( __( 'Add New %s', 'stgl' ), $singular ),
			/* translators: %s: singular post type name */
			'add_new_item'       => sprintf( __( 'Add New %s', 'stgl' ), $singular ),
			/* translators: %s: singular post type name */
			'edit_item'          => sprintf( __( 'Edit %s', 'stgl' ), $singular ),
			/* translators: %s: singular post type name */
			'new_item'           => sprintf( __( 'New %s', 'stgl' ), $singular ),
			/* translators: %s: plural post type name */
			'all_items'          => sprintf( __( 'All %s', 'stgl' ), $plural ),
			/* translators: %s: singular post type name */
			'view_item'          => sprintf( __( 'View %s', 'stgl' ), $singular ),
			/* translators: %s: plural post type name */
			'search_items'       => sprintf( __( 'Search %s', 'stgl' ), $plural ),
			/* translators: %s: singular post type name */
			'not_found'          => sprintf( __( 'No %s found.', 'stgl' ), strtolower( $singular ) ),
			/* translators: %s: singular post type name */
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'stgl' ), strtolower( $singular ) ),
			'menu_name'          => $menu,
		];
	}

	/* -------------------------------------------------------------------- */
	/*  Taxonomy                                                            */
	/* -------------------------------------------------------------------- */

	public function register_taxonomy(): void {
		register_taxonomy( self::TAX_EVENT, [
			self::CPT_PRESENTATION,
			self::CPT_SPONSOR,
		], [
			'labels'            => [
				'name'          => __( 'Event Categories', 'stgl' ),
				'singular_name' => __( 'Event Category', 'stgl' ),
				'menu_name'     => __( 'Event Categories', 'stgl' ),
				'search_items'  => __( 'Search Event Categories', 'stgl' ),
				'all_items'     => __( 'All Event Categories', 'stgl' ),
				'edit_item'     => __( 'Edit Event Category', 'stgl' ),
				'update_item'   => __( 'Update Event Category', 'stgl' ),
				'add_new_item'  => __( 'Add New Event Category', 'stgl' ),
				'new_item_name' => __( 'New Event Category', 'stgl' ),
			],
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'query_var'         => true,
		] );
	}

	/* -------------------------------------------------------------------- */
	/*  Meta registration – exposes legacy meta keys to REST API & blocks   */
	/* -------------------------------------------------------------------- */

	public function register_meta(): void {

		$presenter_meta = [
			'stgl_presenter_name'          => 'string',
			'stgl_presenter_company'       => 'string',
			'stgl_presenter_email'         => 'string',
			'stgl_presenter_videourl'      => 'string',
			'stgl_presenter_publish'       => 'boolean',
			'stgl_presenter_publish_video' => 'boolean',
			'stgl_presenter_time'          => 'string',
			// NB: typo retained for backward compat (old data lives under this key)
			'stgl_presenter_lenght'        => 'integer',
			'stgl_presenter_bio'           => 'string', // NEW
			'stgl_presenter_twitter'       => 'string', // NEW
			'stgl_presenter_linkedin'      => 'string', // NEW
		];
		foreach ( $presenter_meta as $key => $type ) {
			$this->register_post_meta_field( self::CPT_PRESENTATION, $key, $type );
		}

		$sponsor_meta = [
			'stgl_sponsor_url'   => 'string',
			'stgl_sponsor_notes' => 'string',
			'stgl_sponsor_level' => 'integer',
		];
		foreach ( $sponsor_meta as $key => $type ) {
			$this->register_post_meta_field( self::CPT_SPONSOR, $key, $type );
		}
	}

	private function register_post_meta_field( string $post_type, string $key, string $type ): void {
		register_post_meta( $post_type, $key, [
			'type'              => $type,
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => $this->sanitizer_for_type( $type, $key ),
			'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
		] );
	}

	private function sanitizer_for_type( string $type, string $key ): callable {
		switch ( $type ) {
			case 'integer':
				return static fn( $v ): int => (int) $v;
			case 'boolean':
				return static fn( $v ): bool => (bool) $v;
			case 'string':
			default:
				if ( in_array( $key, [ 'stgl_presenter_videourl', 'stgl_sponsor_url' ], true ) ) {
					return static fn( $v ): string => esc_url_raw( (string) $v );
				}
				if ( $key === 'stgl_presenter_bio' || $key === 'stgl_sponsor_notes' ) {
					return static fn( $v ): string => wp_kses_post( (string) $v );
				}
				return static fn( $v ): string => sanitize_text_field( (string) $v );
		}
	}

	/* -------------------------------------------------------------------- */
	/*  Search                                                              */
	/* -------------------------------------------------------------------- */

	/**
	 * Include our CPTs in the front-end search.
	 */
	public function extend_search( \WP_Query $query ) {
		if ( $query->is_search() && $query->is_main_query() && ! is_admin() ) {
			$existing = (array) $query->get( 'post_type' );
			if ( empty( $existing ) || $existing === [ 'post' ] ) {
				$query->set( 'post_type', [ 'post', 'page', self::CPT_PRESENTATION, self::CPT_SPONSOR ] );
			}
		}
		return $query;
	}
}
