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

			// Remove characters that break the JSON payload or Solr syntax
			// We remove parens () because they confuse the Solr parser grouping
			$clean_name = str_replace( array( '"', '*', '(', ')' ), '', $name_str );
			
			// Use the wildcard grouping syntax which is robust for multi-word names
			// Example: applicationMetaData.firstApplicantName:(*ZTE* AND *CORPORATION*)
			// We replace spaces with " AND " to enforce all parts of the name match
			// This matches Experiment B which you confirmed works in Swagger
			$tokenized_name = str_replace( ' ', '* AND *', $clean_name );
			
			$queries[] = 'applicationMetaData.firstApplicantName:(*' . $tokenized_name . '*)';
		}

		return implode( ' OR ', $queries );
	}
}
