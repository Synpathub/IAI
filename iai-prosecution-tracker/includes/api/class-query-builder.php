<?php
// File: includes/api/class-query-builder.php
/**
 * Query Builder
 *
 * @package IAI\ProsecutionTracker\API
 */

namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query_Builder class - Constructs Solr queries for USPTO API
 */
class Query_Builder {

	/**
	 * Build query string for multiple applicant names
	 *
	 * @param array $names Array of applicant names (strings).
	 * @return string Solr query string.
	 */
	public function build_multi_name_query( $names ) {
		if ( empty( $names ) || ! is_array( $names ) ) {
			return '';
		}

		$queries = array();
		foreach ( $names as $name ) {
			// Handle case where $name might be passed as an array {name, count}
			$name_str = is_array( $name ) ? ( $name['name'] ?? '' ) : $name;
			
			if ( empty( $name_str ) ) {
				continue;
			}

			// Escape quotes for exact phrase match in Solr
			$escaped_name = str_replace( '"', '\"', $name_str );
			
			// Use the correct full path for the applicant name field
			$queries[] = 'applicationMetaData.firstApplicantName:"' . $escaped_name . '"';
		}

		// Join with OR to return applications belonging to any of the selected applicants
		return implode( ' OR ', $queries );
	}
}
