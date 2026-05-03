<?php
/**
 * SwiNOG Events – uninstall handler.
 *
 * Fired by WordPress when the plugin is deleted via the Plugins screen.
 * Removes all options, post-types content, and meta written by the plugin.
 *
 * @package Stgl\SwinogEvents
 */

// Exit if accessed directly or not part of a real uninstall request.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-installer.php';

\Stgl\SwinogEvents\Installer::uninstall();
