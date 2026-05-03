<?php
/**
 * Main plugin class – wires every component together.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var array<string, object> */
	private array $components = [];

	private bool $booted = false;

	private function __construct() {}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Spin everything up. Called once on `plugins_loaded`.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Translations.
		load_plugin_textdomain(
			'stgl',
			false,
			dirname( STGL_SWINOG_BASENAME ) . '/languages'
		);

		// Maybe run a database/option migration.
		Installer::maybe_migrate();

		// Register components in dependency order.
		$this->components['post_types'] = new Post_Types();
		$this->components['post_types']->register();

		$this->components['meta_boxes'] = new Meta_Boxes();
		$this->components['meta_boxes']->register();

		$this->components['admin'] = new Admin();
		$this->components['admin']->register();

		$this->components['shortcodes'] = new Shortcodes();
		$this->components['shortcodes']->register();

		$this->components['rest'] = new REST_API();
		$this->components['rest']->register();

		$this->components['ical'] = new ICal();
		$this->components['ical']->register();

		$this->components['schema'] = new Schema();
		$this->components['schema']->register();

		$this->components['assets'] = new Assets();
		$this->components['assets']->register();

		do_action( 'stgl_swinog_loaded', $this );
	}

	/**
	 * @return object|null
	 */
	public function component( string $key ) {
		return $this->components[ $key ] ?? null;
	}
}
