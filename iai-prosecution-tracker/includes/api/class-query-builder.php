<?php
// File: includes/api/class-query-builder.php
/**
 * Query Builder for USPTO Solr Engine
 *
 * @package IAI\ProsecutionTracker\API
 */

namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query_Builder class - Constructs Solr queries
 */
class Query_Builder {

	/**
	 * Build query string for multiple applicant names
	 * * @param array $names Array of names to search for.
	 * @return string Solr query string.
	 */
	public function build_multi_name_query( $names ) {
		if ( empty( $names ) || ! is_array( $names ) ) {
			return '';
		}

		$queries = array();
		foreach ( $names as $name ) {
			// Extract name string if passed as an object/array
			$name_str = is_array( $name ) ? ( $name['name'] ?? '' ) : $name;
			
			if ( empty( $name_str ) ) {
				continue;
			}

			// Escape quotes for exact phrase matching in Solr
			$escaped_name = str_replace( '"', '\"', $name_str );
			
			// Use the identified full field path for exact matching
			$queries[] = 'applicationMetaData.firstApplicantName:"' . $escaped_name . '"';
		}

		// Join with OR to return results matching any of the chosen names
		return implode( ' OR ', $queries );
	}
}
