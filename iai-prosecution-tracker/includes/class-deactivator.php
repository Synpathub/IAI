<?php
/**
 * Plugin Deactivator
 *
 * @package IAI\ProsecutionTracker
 */

namespace IAI\ProsecutionTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin deactivation handler
 */
class Deactivator {

	/**
	 * Deactivate the plugin
	 *
	 * Clears scheduled cron events.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'iai_pt_cache_cleanup' );
	}
}
