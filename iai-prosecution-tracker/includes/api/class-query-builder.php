<?php
// File: includes/api/class-query-builder.php
namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query_Builder {
	public function build_multi_name_query( $names ) {
		if ( empty( $names ) || ! is_array( $names ) ) return '';

		$queries = array();
		foreach ( $names as $name ) {
			$name_str = is_array( $name ) ? ( $name['name'] ?? '' ) : $name;
			if ( empty( $name_str ) ) continue;

			// CLEANUP: Replace any non-alphanumeric char with space
			// "ZTE (USA) INC." -> "ZTE  USA  INC "
			$clean_name = preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $name_str );
			
			$words = explode( ' ', $clean_name );
			$valid_words = array();
			
			foreach ( $words as $word ) {
				$word = trim( $word );
				if ( ! empty( $word ) ) {
					// Wrap every word in wildcards
					$valid_words[] = '*' . $word . '*';
				}
			}
			
			if ( ! empty( $valid_words ) ) {
				// Join with AND: applicationMetaData.firstApplicantName:(*ZTE* AND *USA* AND *INC*)
				$query_part = implode( ' AND ', $valid_words );
				$queries[] = 'applicationMetaData.firstApplicantName:(' . $query_part . ')';
			}
		}

		return implode( ' OR ', $queries );
	}
}
