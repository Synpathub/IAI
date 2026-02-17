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
	 *
	 * @param array $names Array of names to search for.
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

			// 1. Escape all Solr special characters (including parentheses and ampersands)
			$escaped_name = $this->escape_solr_special_chars( $name_str );
			
			// 2. Wrap the escaped string in double quotes for phrase matching.
			// Note: In Solr, we escape inside the quotes to ensure the parser treats 
			// the characters as literals rather than operators.
			$queries[] = 'applicationMetaData.firstApplicantName:"' . $escaped_name . '"';
		}

		// Join with OR to return results matching any of the chosen names
		return implode( ' OR ', $queries );
	}

	/**
	 * Escape Solr special characters
	 * * @param string $string
	 * @return string
	 */
	private function escape_solr_special_chars( $string ) {
		// List of Solr special characters: + - && || ! ( ) { } [ ] ^ " ~ * ? : / \
		// We use double backslashes for PHP string escaping to produce a single backslash in the output
		$special_chars = array( '\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '/' );
		
		$escaped = $string;
		foreach ( $special_chars as $char ) {
			$escaped = str_replace( $char, '\\' . $char, $escaped );
		}

		return $escaped;
	}
}
