<?php
/**
 * Plugin Activator
 *
 * @package IAI\ProsecutionTracker
 */

namespace IAI\ProsecutionTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activation handler
 */
class Activator {

	/**
	 * Activate the plugin
	 *
	 * Creates database tables and schedules cron events.
	 */
	public static function activate() {
		self::create_tables();
		self::schedule_cron();
	}

	/**
	 * Create database tables
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		// Search cache table
		$search_cache_table = "CREATE TABLE {$prefix}iai_pt_search_cache (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			search_query VARCHAR(500) NOT NULL,
			query_hash CHAR(32) NOT NULL,
			result_data LONGTEXT NOT NULL,
			fetched_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY query_hash (query_hash),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// Transaction cache table
		$transaction_cache_table = "CREATE TABLE {$prefix}iai_pt_transaction_cache (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			application_number VARCHAR(20) NOT NULL,
			transaction_data LONGTEXT NOT NULL,
			fetched_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY application_number (application_number),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// Saved searches table
		$saved_searches_table = "CREATE TABLE {$prefix}iai_pt_saved_searches (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			search_label VARCHAR(255) NOT NULL,
			name_variants TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id)
		) $charset_collate;";

		dbDelta( $search_cache_table );
		dbDelta( $transaction_cache_table );
		dbDelta( $saved_searches_table );
	}

	/**
	 * Schedule cron events
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'iai_pt_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'iai_pt_cache_cleanup' );
		}
	}
}
