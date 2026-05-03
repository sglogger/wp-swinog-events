<?php
/**
 * Asset registration / enqueueing.
 *
 * Note: in v0.x the sponsors CSS was enqueued at file-load time, which fires
 * before the right hooks. Here we enqueue on the proper hooks.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'public_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
	}

	public function public_assets(): void {
		// Only load on pages that may use our shortcodes / CPT templates.
		// We can't cheaply detect shortcode usage without parsing every post,
		// so we load on singular CPT pages and on any page that contains one
		// of our shortcodes in its content.
		if ( ! $this->should_load_public_assets() ) {
			return;
		}

		wp_enqueue_style(
			'stgl-swinog-public',
			STGL_SWINOG_URL . 'assets/css/public.css',
			[],
			STGL_SWINOG_VERSION
		);
	}

	private function should_load_public_assets(): bool {
		if ( is_singular( [ Post_Types::CPT_EVENT, Post_Types::CPT_PRESENTATION, Post_Types::CPT_SPONSOR ] ) ) {
			return true;
		}
		if ( is_post_type_archive( [ Post_Types::CPT_EVENT, Post_Types::CPT_PRESENTATION, Post_Types::CPT_SPONSOR ] ) ) {
			return true;
		}
		if ( is_tax( Post_Types::TAX_EVENT ) ) {
			return true;
		}
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && $this->content_uses_shortcode( (string) $post->post_content ) ) {
				return true;
			}
		}
		return (bool) apply_filters( 'stgl_swinog_force_assets', false );
	}

	private function content_uses_shortcode( string $content ): bool {
		foreach ( [ 'swinog_list_presentations', 'swinog_sponsor', 'swinog_event', 'swinog_event_card', 'swinog_upcoming_events', 'swinog_cfp' ] as $sc ) {
			if ( has_shortcode( $content, $sc ) ) {
				return true;
			}
		}
		return false;
	}

	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$ours = [ Post_Types::CPT_PRESENTATION, Post_Types::CPT_EVENT, Post_Types::CPT_SPONSOR ];
		if ( ! in_array( $screen->post_type, $ours, true ) && false === strpos( (string) $screen->id, 'stgl-swinog' ) ) {
			return;
		}

		wp_enqueue_style(
			'stgl-swinog-admin',
			STGL_SWINOG_URL . 'assets/css/admin.css',
			[],
			STGL_SWINOG_VERSION
		);

		// Media library JS for the file-picker on presentation edit screens.
		if ( Post_Types::CPT_PRESENTATION === $screen->post_type ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'stgl-swinog-admin',
				STGL_SWINOG_URL . 'assets/js/admin.js',
				[ 'jquery' ],
				STGL_SWINOG_VERSION,
				true
			);
			wp_localize_script( 'stgl-swinog-admin', 'stglSwinog', [
				'pickTitle'  => __( 'Select presentation file', 'stgl' ),
				'pickButton' => __( 'Use this file', 'stgl' ),
			] );
		}
	}
}
