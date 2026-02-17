<?php
/**
 * Cache Manager - Manages caching of USPTO API responses
 *
 * @package IAI\ProsecutionTracker\Cache
 */

namespace IAI\ProsecutionTracker\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache_Manager class - Handles caching of search and transaction data
 */
class Cache_Manager {

	/**
	 * WordPress database instance
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Search cache table name
	 *
	 * @var string
	 */
	private $search_cache_table;

	/**
	 * Transaction cache table name
	 *
	 * @var string
	 */
	private $transaction_cache_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb                      = $wpdb;
		$this->search_cache_table        = $wpdb->prefix . 'iai_pt_search_cache';
		$this->transaction_cache_table   = $wpdb->prefix . 'iai_pt_transaction_cache';
	}

	/**
	 * Get cached search results
	 *
	 * @param string $query_hash MD5 hash of the search query.
	 * @return array|null Cached data or null if expired/not found.
	 */
	public function get_search( $query_hash ) {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT result_data, expires_at FROM {$this->search_cache_table} WHERE query_hash = %s",
				$query_hash
			),
			ARRAY_A
		);

		if ( ! $result ) {
			return null;
		}

		// Check if expired
		if ( $result['expires_at'] <= current_time( 'mysql' ) ) {
			return null;
		}

		// Decode and return data
		return json_decode( $result['result_data'], true );
	}

	/**
	 * Set cached search results
	 *
	 * @param string $query_hash MD5 hash of the search query.
	 * @param string $query      Original search query string.
	 * @param array  $data       Data to cache.
	 * @param int    $ttl_hours  Time to live in hours (default: 24).
	 * @return void
	 */
	public function set_search( $query_hash, $query, $data, $ttl_hours = 24 ) {
		$fetched_at = current_time( 'mysql' );
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( $fetched_at ) + ( $ttl_hours * HOUR_IN_SECONDS ) );

		// Check if entry exists
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->search_cache_table} WHERE query_hash = %s",
				$query_hash
			)
		);

		if ( $exists ) {
			// Update existing entry
			$this->wpdb->update(
				$this->search_cache_table,
				array(
					'result_data' => wp_json_encode( $data ),
					'fetched_at'  => $fetched_at,
					'expires_at'  => $expires_at,
				),
				array( 'query_hash' => $query_hash ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);
		} else {
			// Insert new entry
			$this->wpdb->insert(
				$this->search_cache_table,
				array(
					'search_query' => $query,
					'query_hash'   => $query_hash,
					'result_data'  => wp_json_encode( $data ),
					'fetched_at'   => $fetched_at,
					'expires_at'   => $expires_at,
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Get cached transaction data
	 *
	 * @param string $app_number Application number.
	 * @return array|null Cached data or null if expired/not found.
	 */
	public function get_transactions( $app_number ) {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT transaction_data, expires_at FROM {$this->transaction_cache_table} WHERE application_number = %s",
				$app_number
			),
			ARRAY_A
		);

		if ( ! $result ) {
			return null;
		}

		// Check if expired
		if ( $result['expires_at'] <= current_time( 'mysql' ) ) {
			return null;
		}

		// Decode and return data
		return json_decode( $result['transaction_data'], true );
	}

	/**
	 * Set cached transaction data
	 *
	 * @param string $app_number Application number.
	 * @param array  $data       Transaction data to cache.
	 * @param int    $ttl_hours  Time to live in hours (default: 168 = 7 days).
	 * @return void
	 */
	public function set_transactions( $app_number, $data, $ttl_hours = 168 ) {
		$fetched_at = current_time( 'mysql' );
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( $fetched_at ) + ( $ttl_hours * HOUR_IN_SECONDS ) );

		// Use REPLACE to insert or update (since application_number has UNIQUE index)
		$this->wpdb->replace(
			$this->transaction_cache_table,
			array(
				'application_number' => $app_number,
				'transaction_data'   => wp_json_encode( $data ),
				'fetched_at'         => $fetched_at,
				'expires_at'         => $expires_at,
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Purge expired cache entries
	 *
	 * @return int Number of entries deleted.
	 */
	public function purge_expired() {
		$current_time = current_time( 'mysql' );

		// Delete expired search cache entries
		$search_deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->search_cache_table} WHERE expires_at < %s",
				$current_time
			)
		);

		// Delete expired transaction cache entries
		$transaction_deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->transaction_cache_table} WHERE expires_at < %s",
				$current_time
			)
		);

		return (int) $search_deleted + (int) $transaction_deleted;
	}

	/**
	 * Clear all cache entries
	 *
	 * @return void
	 */
	public function clear_all() {
		$this->wpdb->query( "TRUNCATE TABLE {$this->search_cache_table}" );
		$this->wpdb->query( "TRUNCATE TABLE {$this->transaction_cache_table}" );
	}
}
