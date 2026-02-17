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
	 * Maximum length for search query input
	 */
	const MAX_QUERY_LENGTH = 500;

	/**
	 * Maximum number of names in multi-name query
	 */
	const MAX_NAMES_COUNT = 50;

	/**
	 * Build applicant search query from user input
	 *
	 * Translates user-friendly search syntax into USPTO ODP query format:
	 * - + means AND
	 * - * stays as wildcard
	 * - Bare words without * are wrapped in wildcards for partial matching
	 *
	 * Examples:
	 * - "Electronics + Telecom*" → applicationMetaData.firstApplicantName:(*Electronics* AND Telecom*)
	 * - "Samsung*" → applicationMetaData.firstApplicantName:(Samsung*)
	 * - "ETRI" → applicationMetaData.firstApplicantName:(*ETRI*)
	 *
	 * @param string $user_input User search input.
	 * @return string USPTO ODP query string.
	 */
	public function build_applicant_search( $user_input ) {
		// Sanitize input
		$user_input = trim( $user_input );
		
		// Enforce length limit to prevent DoS
		if ( strlen( $user_input ) > self::MAX_QUERY_LENGTH ) {
			$user_input = substr( $user_input, 0, self::MAX_QUERY_LENGTH );
		}
		
		// Only allow alphanumeric characters, spaces, and safe search operators (* and +)
		// This prevents query injection through special characters
		$user_input = preg_replace( '/[^a-zA-Z0-9\s\*\+]/u', '', $user_input );
		
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

		if ( empty( $processed_terms ) ) {
			return '';
		}

		// Join terms with AND
		$query_string = implode( ' AND ', $processed_terms );
		
		// Use sprintf for safer string formatting
		return sprintf( 'applicationMetaData.firstApplicantName:(%s)', $query_string );
	}

	/**
	 * Build multi-name OR query from exact applicant names
	 *
	 * Takes array of exact applicant names selected by user and creates an OR query.
	 *
	 * Example:
	 * ["Name One", "Name Two", "Name Three"] → applicationMetaData.firstApplicantName:("Name One" OR "Name Two" OR "Name Three")
	 *
	 * @param array $exact_names Array of exact applicant names.
	 * @return string USPTO ODP query string.
	 */
	public function build_multi_name_query( $exact_names ) {
		if ( ! is_array( $exact_names ) || empty( $exact_names ) ) {
			return '';
		}

		// Enforce limit on number of names to prevent DoS
		if ( count( $exact_names ) > self::MAX_NAMES_COUNT ) {
			$exact_names = array_slice( $exact_names, 0, self::MAX_NAMES_COUNT );
		}

		// Sanitize and quote each name
		$quoted_names = array();
		foreach ( $exact_names as $name ) {
			$name = trim( $name );
			
			// Enforce length limit per name
			if ( strlen( $name ) > 255 ) {
				$name = substr( $name, 0, 255 );
			}
			
			// Remove dangerous characters that could break query structure
			// Only allow alphanumeric, spaces, and common punctuation for company names
			$name = preg_replace( '/[^\w\s\.\,\&\-]/u', '', $name );
			
			if ( ! empty( $name ) ) {
				// Escape any remaining quotes and wrap in quotes
				$name = str_replace( '"', '\\"', $name );
				$quoted_names[] = '"' . $name . '"';
			}
		}

		if ( empty( $quoted_names ) ) {
			return '';
		}

		// Join with OR and wrap in field specifier using sprintf for safety
		return sprintf( 'applicationMetaData.firstApplicantName:(%s)', implode( ' OR ', $quoted_names ) );
	}
}
