<?php
/**
 * Plain helper functions used across the plugin, plus backward-compat
 * aliases that keep the v0.x global function names working in case any
 * theme template referenced them directly.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

// -------------------------------------------------------------------------
// Namespaced helpers.
// -------------------------------------------------------------------------

namespace Stgl\SwinogEvents\Helpers {

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * A safe email validator that replaces the old `ereg`-based one.
	 * Internally uses WordPress' `is_email()`.
	 */
	function is_valid_email( string $email ): bool {
		return false !== is_email( $email );
	}
}

// -------------------------------------------------------------------------
// Backward-compat shims for v0.x function names.
// These existed in the global namespace; some themes may still call them.
// -------------------------------------------------------------------------

namespace {

	if ( ! function_exists( 'stgl_swinog_validate_email' ) ) {
		/**
		 * @deprecated 1.0.0 Use is_email() or Stgl\SwinogEvents\Helpers\is_valid_email().
		 */
		function stgl_swinog_validate_email( $email ) {
			return \Stgl\SwinogEvents\Helpers\is_valid_email( (string) $email );
		}
	}

	if ( ! function_exists( 'stgl_swinog_plugin_trigger_error' ) ) {
		function stgl_swinog_plugin_trigger_error( $message, $errno = E_USER_NOTICE ) {
			if ( isset( $_GET['action'] ) && 'error_scrape' === $_GET['action'] ) { // phpcs:ignore
				echo '<p>' . esc_html( (string) $message ) . '</p>';
				exit;
			}
			trigger_error( esc_html( (string) $message ), (int) $errno ); // phpcs:ignore
		}
	}

	if ( ! function_exists( 'stgl_options_default' ) ) {
		function stgl_options_default() {
			return [ 'stgl_swinog_event_version' => STGL_SWINOG_VERSION ];
		}
	}
}
