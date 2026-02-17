<?php
// File: includes/api/class-query-builder.php
namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query_Builder {

	/**
	 * Build query string using Wildcard Grouping Strategy
	 * PROVEN SUCCESS via Python Script: applicationMetaData.firstApplicantName:(*ZTE* AND *CORPORATION*)
	 */
	public function build_multi_name_query( $names ) {
		if ( empty( $names ) || ! is_array( $names ) ) {
			return '';
		}

		$queries = array();
		foreach ( $names as $name ) {
			$name_str = is_array( $name ) ? ( $name['name'] ?? '' ) : $name;
			if ( empty( $name_str ) ) continue;

			// 1. Clean the name: Remove punctuation that confuses Solr
			// Replace non-alphanumeric chars (like parens, &) with spaces
			$clean_name = preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $name_str );
			
			// 2. Tokenize
			$words = explode( ' ', $clean_name );
			$valid_words = array();
			
			foreach ( $words as $word ) {
				$word = trim( $word );
				if ( ! empty( $word ) ) {
					// 3. Wrap every word in wildcards
					$valid_words[] = '*' . $word . '*';
				}
			}
			
			if ( ! empty( $valid_words ) ) {
				// 4. Join with AND
				$query_part = implode( ' AND ', $valid_words );
				$queries[] = 'applicationMetaData.firstApplicantName:(' . $query_part . ')';
			}
		}

		return implode( ' OR ', $queries );
	}
}
