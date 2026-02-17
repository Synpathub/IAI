<?php
/**
 * Query Builder for USPTO ODP API
 *
 * @package IAI\ProsecutionTracker\API
 */

namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query_Builder class - Translates user search input into USPTO ODP query syntax
 */
class Query_Builder {

	/**
	 * Build applicant search query from user input
	 *
	 * Translates user-friendly search syntax into USPTO ODP query format:
	 * - + means AND
	 * - * stays as wildcard
	 * - Bare words without * are wrapped in wildcards for partial matching
	 *
	 * Examples:
	 * - "Electronics + Telecom*" → firstApplicantName:(*Electronics* AND Telecom*)
	 * - "Samsung*" → firstApplicantName:(Samsung*)
	 * - "ETRI" → firstApplicantName:(*ETRI*)
	 *
	 * @param string $user_input User search input.
	 * @return string USPTO ODP query string.
	 */
	public function build_applicant_search( $user_input ) {
		// Sanitize input
		$user_input = trim( $user_input );
		
		// Remove potentially dangerous characters while preserving search operators
		$user_input = preg_replace( '/[^\w\s\*\+\-\(\)\"\']/u', '', $user_input );
		
		if ( empty( $user_input ) ) {
			return '';
		}

		// Split by + for AND operations
		$terms = array_map( 'trim', explode( '+', $user_input ) );
		
		$processed_terms = array();
		foreach ( $terms as $term ) {
			if ( empty( $term ) ) {
				continue;
			}
			
			// If term already has a wildcard, keep it as-is
			if ( strpos( $term, '*' ) !== false ) {
				$processed_terms[] = $term;
			} else {
				// Wrap bare words in wildcards for partial matching
				$processed_terms[] = '*' . $term . '*';
			}
		}

		// Join terms with AND
		$query_string = implode( ' AND ', $processed_terms );
		
		// Wrap in field specifier
		return 'firstApplicantName:(' . $query_string . ')';
	}

	/**
	 * Build multi-name OR query from exact applicant names
	 *
	 * Takes array of exact applicant names selected by user and creates an OR query.
	 *
	 * Example:
	 * ["Name One", "Name Two", "Name Three"] → firstApplicantName:("Name One" OR "Name Two" OR "Name Three")
	 *
	 * @param array $exact_names Array of exact applicant names.
	 * @return string USPTO ODP query string.
	 */
	public function build_multi_name_query( $exact_names ) {
		if ( ! is_array( $exact_names ) || empty( $exact_names ) ) {
			return '';
		}

		// Sanitize and quote each name
		$quoted_names = array();
		foreach ( $exact_names as $name ) {
			$name = trim( $name );
			
			// Remove potentially dangerous characters
			$name = preg_replace( '/[\"\']/u', '', $name );
			
			if ( ! empty( $name ) ) {
				$quoted_names[] = '"' . $name . '"';
			}
		}

		if ( empty( $quoted_names ) ) {
			return '';
		}

		// Join with OR and wrap in field specifier
		return 'firstApplicantName:(' . implode( ' OR ', $quoted_names ) . ')';
	}
}
