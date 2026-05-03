<?php
/**
 * Installation, deactivation and migration logic.
 *
 * Maintains backward compatibility with v0.x data: same option names, same
 * post-type slugs, same meta keys, same taxonomies. Old installs upgrade
 * cleanly without losing data.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	public const OPTION_VERSION        = 'stgl_swinog_event_version';
	public const OPTION_DATA_VERSION   = 'stgl_swinog_data_version';
	public const OPTION_SPONSOR_LEVELS = 'stgl_swinog_sponsor_levels';
	public const OPTION_EVENT_STATUS   = 'stgl_swinog_event_status';
	public const OPTION_AGENDA_TYPES   = 'stgl_swinog_agenda_type';

	/**
	 * Default sponsor levels (preserved from v0.x).
	 *
	 * Keyed by weight: higher = more important.
	 *
	 * @return array<int, string>
	 */
	public static function default_sponsor_levels(): array {
		return [
			500 => 'Exclusive',
			400 => 'Platinum',
			300 => 'Gold',
			200 => 'Silver',
			100 => 'Supporter',
			30  => 'W-LAN',
			20  => 'Other',
			15  => 'Social Event',
			10  => 'Event',
			0   => '',
		];
	}

	/**
	 * Fired on plugin activation.
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Seed options only if they don't already exist – never overwrite.
		add_option( self::OPTION_VERSION, STGL_SWINOG_VERSION );
		add_option( self::OPTION_SPONSOR_LEVELS, self::default_sponsor_levels() );
		add_option( self::OPTION_EVENT_STATUS, [ 'draft', 'published', 'hidden' ] );
		add_option( self::OPTION_AGENDA_TYPES, [
			__( 'Text entry',  'stgl' ),
			__( 'Presentation', 'stgl' ),
		] );

		// Make sure post types & rewrites exist before flushing.
		( new Post_Types() )->register();
		flush_rewrite_rules();

		self::maybe_migrate();
	}

	/**
	 * Fired on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Run any data migration when the stored version is older than the
	 * current data version.
	 */
	public static function maybe_migrate(): void {
		$installed = get_option( self::OPTION_DATA_VERSION, '0' );

		if ( version_compare( (string) $installed, STGL_SWINOG_DATA_VERSION, '>=' ) ) {
			return; // Already migrated.
		}

		// 0.x → 1.0 – idempotent, safe to re-run.
		self::migrate_to_1_0();

		update_option( self::OPTION_DATA_VERSION, STGL_SWINOG_DATA_VERSION );
		update_option( self::OPTION_VERSION, STGL_SWINOG_VERSION );
	}

	/**
	 * 0.x → 1.0 migration steps:
	 *  - Drop the unused `{prefix}swinog_events` table that was never read.
	 *  - Backfill the missing `stgl_swinog_event_levels` option (referenced
	 *    in old code but never created – caused notices in admin).
	 *  - Normalise checkbox meta to '1' / '' so REST returns clean booleans.
	 */
	private static function migrate_to_1_0(): void {
		global $wpdb;

		// 1) Old empty table – drop it if it's truly empty, otherwise leave it
		// alone so we never delete data on an unsuspecting site.
		$table = $wpdb->prefix . 'swinog_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore
			if ( 0 === $rows ) {
				$wpdb->query( "DROP TABLE `{$table}`" ); // phpcs:ignore
			}
		}

		// 2) Backfill the option that old event-list.php referenced.
		if ( false === get_option( 'stgl_swinog_event_levels', false ) ) {
			update_option( 'stgl_swinog_event_levels', [] );
		}

		// 3) Normalise legacy boolean-ish meta. Old code stored "true" strings
		// for checkboxes; REST and modern code prefer "1" / "".
		$bool_meta_keys = [
			'stgl_presenter_publish',
			'stgl_presenter_publish_video',
		];

		foreach ( $bool_meta_keys as $key ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = '1' WHERE meta_key = %s AND meta_value IN ('true','on','yes','TRUE')",
					$key
				)
			);
		}
	}

	/**
	 * Called from uninstall.php – fully removes plugin data.
	 * Kept here so the logic lives next to activation.
	 */
	public static function uninstall(): void {
		global $wpdb;

		// Delete options.
		$options = [
			self::OPTION_VERSION,
			self::OPTION_DATA_VERSION,
			self::OPTION_SPONSOR_LEVELS,
			self::OPTION_EVENT_STATUS,
			self::OPTION_AGENDA_TYPES,
			'stgl_swinog_event_levels', // legacy
		];
		foreach ( $options as $opt ) {
			delete_option( $opt );
		}

		// Delete custom posts and their meta + the legacy table.
		$post_types = [ 'stgl_presentation', 'stgl_event', 'stgl_sponsor' ];
		foreach ( $post_types as $pt ) {
			$ids = get_posts( [
				'post_type'      => $pt,
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		}

		// Drop the legacy table if it still exists.
		$table = $wpdb->prefix . 'swinog_events';
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore

		// Remove orphan terms in our taxonomy.
		$terms = get_terms( [
			'taxonomy'   => 'stgl_presentation_cat',
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( (int) $term_id, 'stgl_presentation_cat' );
			}
		}
	}
}
