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

	public const OPTION_VERSION            = 'stgl_swinog_event_version';
	public const OPTION_DATA_VERSION       = 'stgl_swinog_data_version';
	public const OPTION_SPONSOR_LEVELS     = 'stgl_swinog_sponsor_levels';
	public const OPTION_PRESENTATION_TYPES = 'stgl_swinog_presentation_types';
	public const OPTION_CFP_API            = 'stgl_swinog_cfp_api';
	public const OPTION_CFP_EVENT_MAP      = 'stgl_swinog_cfp_event_map';

	/**
	 * Slug used when a presentation carries no explicit type. Presentations are
	 * talks unless the editor overwrites the type on the edit screen.
	 */
	public const DEFAULT_PRESENTATION_TYPE = 'talk';

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
	 * Default agenda entry types, keyed by slug.
	 *
	 * The list is editable on the Settings screen; these are only the seeds.
	 *
	 * @return array<string, string>
	 */
	public static function default_presentation_types(): array {
		return [
			'talk'           => 'Talk',
			'keynote'        => 'Keynote',
			'break'          => 'Break',
			'transportation' => 'Transportation',
			'social'         => 'Social',
			'other'          => 'Other',
		];
	}

	/**
	 * The configured (or default) agenda entry types, keyed by slug.
	 *
	 * @return array<string, string>
	 */
	public static function presentation_types(): array {
		$types = get_option( self::OPTION_PRESENTATION_TYPES, null );

		if ( ! is_array( $types ) || [] === $types ) {
			return self::default_presentation_types();
		}

		$out = [];
		foreach ( $types as $slug => $label ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$out[ $slug ] = (string) $label;
			}
		}

		return [] === $out ? self::default_presentation_types() : $out;
	}

	/**
	 * Slug used for presentations without an explicit type. Falls back to the
	 * first configured type if `talk` was renamed or removed.
	 */
	public static function default_presentation_type(): string {
		return self::default_type_from( self::presentation_types() );
	}

	/**
	 * @param array<string, string> $types
	 */
	private static function default_type_from( array $types ): string {
		if ( isset( $types[ self::DEFAULT_PRESENTATION_TYPE ] ) ) {
			return self::DEFAULT_PRESENTATION_TYPE;
		}

		return (string) array_key_first( $types );
	}

	/**
	 * Resolve a presentation's type into a slug + display label.
	 *
	 * An empty meta value means "not overwritten" and resolves to the default
	 * type. A slug that is no longer configured (renamed / deleted in the
	 * settings) is kept as-is and gets a prettified label, so stored data is
	 * never silently relabelled.
	 *
	 * @return array{slug: string, label: string}
	 */
	public static function resolve_presentation_type( int $post_id ): array {
		$types = self::presentation_types();
		$slug  = sanitize_key( (string) get_post_meta( $post_id, 'stgl_presenter_type', true ) );

		if ( '' === $slug ) {
			$slug = self::default_type_from( $types );
		}

		$label = $types[ $slug ] ?? ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );

		return [
			'slug'  => $slug,
			'label' => (string) $label,
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
		add_option( self::OPTION_PRESENTATION_TYPES, self::default_presentation_types() );

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

		// Delete options (current + legacy).
		$options = [
			self::OPTION_VERSION,
			self::OPTION_DATA_VERSION,
			self::OPTION_SPONSOR_LEVELS,
			self::OPTION_PRESENTATION_TYPES,
			self::OPTION_CFP_API,
			self::OPTION_CFP_EVENT_MAP,
			'stgl_swinog_event_status', // legacy
			'stgl_swinog_agenda_type',  // legacy
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
