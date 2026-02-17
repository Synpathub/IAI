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
 * Query_Builder class - Constructs Solr queries
 */
class Query_Builder {

	/**
	 * Build query string for multiple applicant names
	 *
	 * @param array $names Array of applicant names.
	 * @return string Solr query string.
	 */
	public function build_multi_name_query( $names ) {
		if ( empty( $names ) ) {
			return '';
		}

		$queries = array();
		foreach ( $names as $name ) {
			// Escape special characters in Solr syntax
			$escaped_name = $this->escape_solr_special_chars( $name );
			
			// FIX: Use the full field path 'applicationMetaData.firstApplicantName'
			// Using quotes "" creates an exact phrase match
			$queries[] = 'applicationMetaData.firstApplicantName:"' . $escaped_name . '"';
		}

		// Join with OR to find any of the selected applicants
		return implode( ' OR ', $queries );
	}

	/**
	 * Escape special characters for Solr query
	 *
	 * @param string $string Input string.
	 * @return string Escaped string.
	 */
	private function escape_solr_special_chars( $string ) {
		// Solr special characters that need escaping
		$special_chars = array( '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\', '/' );
		
		$escaped = $string;
		foreach ( $special_chars as $char ) {
			$escaped = str_replace( $char, '\\' . $char, $escaped );
		}

		return $escaped;
	}
}
