<?php
/**
 * Plugin Uninstall Handler
 *
 * @package IAI\ProsecutionTracker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix;

// Drop database tables
$wpdb->query( "DROP TABLE IF EXISTS {$prefix}iai_pt_search_cache" );
$wpdb->query( "DROP TABLE IF EXISTS {$prefix}iai_pt_transaction_cache" );
$wpdb->query( "DROP TABLE IF EXISTS {$prefix}iai_pt_saved_searches" );

// Delete all plugin options
$wpdb->query( "DELETE FROM {$prefix}options WHERE option_name LIKE 'iai\_pt\_%'" );
