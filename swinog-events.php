<?php
/**
 * Plugin Name:       SwiNOG Events
 * Plugin URI:        https://www.glogger.ch
 * Description:       Manage SwiNOG events, call-for-papers, agendas, sponsors and presentations. Provides custom post types, shortcodes, REST API, iCal export and JSON-LD structured data.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Steven Glogger
 * Author URI:        https://www.glogger.ch
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stgl
 * Domain Path:       /languages
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// Plugin constants
// -----------------------------------------------------------------------------
define( 'STGL_SWINOG_VERSION', '1.0.0' );
define( 'STGL_SWINOG_FILE', __FILE__ );
define( 'STGL_SWINOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'STGL_SWINOG_URL', plugin_dir_url( __FILE__ ) );
define( 'STGL_SWINOG_BASENAME', plugin_basename( __FILE__ ) );

// Internal data-version constant – used by the migrator to know whether
// historic data needs to be patched up after an upgrade.
define( 'STGL_SWINOG_DATA_VERSION', '1.0' );

// -----------------------------------------------------------------------------
// PHP version guard
// -----------------------------------------------------------------------------
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p>';
		printf(
			/* translators: 1: required PHP version, 2: current PHP version */
			esc_html__( 'SwiNOG Events requires PHP %1$s or newer. You are running %2$s. The plugin has been disabled.', 'stgl' ),
			'7.4',
			esc_html( PHP_VERSION )
		);
		echo '</p></div>';
	} );
	return;
}

// -----------------------------------------------------------------------------
// Autoload our classes (simple PSR-4-ish loader scoped to this plugin)
// -----------------------------------------------------------------------------
require_once STGL_SWINOG_DIR . 'includes/class-plugin.php';
require_once STGL_SWINOG_DIR . 'includes/class-installer.php';
require_once STGL_SWINOG_DIR . 'includes/class-post-types.php';
require_once STGL_SWINOG_DIR . 'includes/class-meta-boxes.php';
require_once STGL_SWINOG_DIR . 'includes/class-admin.php';
require_once STGL_SWINOG_DIR . 'includes/class-shortcodes.php';
require_once STGL_SWINOG_DIR . 'includes/class-rest-api.php';
require_once STGL_SWINOG_DIR . 'includes/class-ical.php';
require_once STGL_SWINOG_DIR . 'includes/class-schema.php';
require_once STGL_SWINOG_DIR . 'includes/class-assets.php';
require_once STGL_SWINOG_DIR . 'includes/helpers.php';

// -----------------------------------------------------------------------------
// Lifecycle hooks
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, [ \Stgl\SwinogEvents\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Stgl\SwinogEvents\Installer::class, 'deactivate' ] );

// -----------------------------------------------------------------------------
// Boot
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', static function () {
	\Stgl\SwinogEvents\Plugin::instance()->boot();
} );
