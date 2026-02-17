<?php
/**
 * USPTO Open Data Portal API Client
 *
 * @package IAI\ProsecutionTracker\API
 */

namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * USPTO_Client class - Wrapper for USPTO Open Data Portal API
 */
class USPTO_Client {

	/**
	 * Base URL for USPTO API
	 *
	 * @var string
	 */
	private $base_url = 'https://api.uspto.gov/api/v1/patent';

	/**
	 * API key
	 *
	 * @var string|null
	 */
	private $api_key;

	/**
	 * Query builder instance
	 *
	 * @var Query_Builder
	 */
	private $query_builder;

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_key       = defined( 'IAI_PT_USPTO_API_KEY' ) ? IAI_PT_USPTO_API_KEY : get_option( 'iai_pt_api_key' );
		$this->query_builder = new Query_Builder();
	}

	/**
	 * Search for applicant names using faceted search
	 *
	 * @param string $query  Search query.
	 * @param int    $limit  Maximum number of results to return (default: 50).
	 * @param int    $offset Starting offset for pagination (default: 0).
	 * @return array|\WP_Error Array of ['name' => string, 'count' => int] or WP_Error on failure.
	 */
	public function search_applicants( $query, $limit = 50, $offset = 0 ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'missing_api_key', 'USPTO API key is not configured.' );
		}

		$base_url = $this->base_url . '/applications/search';

		// FIX: 'limit' must be 1 or greater according to USPTO API error.
		// We set it to 1 because we are only interested in the facets (names), not the documents.
		$query_params = array(
			'q'      => $query,
			'facets' => 'applicationMetaData.firstApplicantName',
			'limit'  => 1,
		);

		$url = add_query_arg( $query_params, $base_url );

		error_log( 'IAI PT Request: ' . $url );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'X-API-KEY' => $this->api_key,
					'Accept'    => 'application/json',
				),
				'timeout'   => $this->timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', 'Failed to connect to USPTO API: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log( 'IAI PT Response Code: ' . $response_code );
		error_log( 'IAI PT Response Body: ' . substr( $response_body, 0, 1000 ) );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'api_error',
				sprintf( 'USPTO API returned error code %d: %s', $response_code, $response_body )
			);
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_decode_error', 'Failed to parse USPTO API response: ' . json_last_error_msg() );
		}

		// Extract facet results - try multiple paths
		$applicant_names = array();
		$facet_array     = null;

		// Path a) response body -> facetCounts -> firstApplicantName (alternating array)
		if ( isset( $data['facetCounts']['firstApplicantName'] ) && is_array( $data['facetCounts']['firstApplicantName'] ) ) {
			$facet_array = $data['facetCounts']['firstApplicantName'];
		}
		// Path b) response body -> facets -> applicationMetaData.firstApplicantName
		elseif ( isset( $data['facets']['applicationMetaData.firstApplicantName'] ) && is_array( $data['facets']['applicationMetaData.firstApplicantName'] ) ) {
			$facet_array = $data['facets']['applicationMetaData.firstApplicantName'];
		}
		// Path c) response body -> facet_counts -> facet_fields -> applicationMetaData.firstApplicantName
		elseif ( isset( $data['facet_counts']['facet_fields']['applicationMetaData.firstApplicantName'] ) && is_array( $data['facet_counts']['facet_fields']['applicationMetaData.firstApplicantName'] ) ) {
			$facet_array = $data['facet_counts']['facet_fields']['applicationMetaData.firstApplicantName'];
		}

		if ( null !== $facet_array ) {
			// Parse alternating array into [{"name": name1, "count": count1}, ...] format
			for ( $i = 0; $i < count( $facet_array ); $i += 2 ) {
				if ( isset( $facet_array[ $i ] ) && isset( $facet_array[ $i + 1 ] ) ) {
					$applicant_names[] = array(
						'name'  => $facet_array[ $i ],
						'count' => (int) $facet_array[ $i + 1 ],
					);
				}
			}
		} else {
			// Log full response if no facet path found
			error_log( 'IAI PT: No facet data found in response. Full response: ' . wp_json_encode( $data ) );
		}

		return $applicant_names;
	}

	/**
	 * Get patent applications for specified applicant names
	 *
	 * @param array $applicant_names Array of exact applicant names.
	 * @param int   $limit           Maximum number of results to return (default: 100).
	 * @param int   $offset          Starting offset for pagination (default: 0).
	 * @return array|\WP_Error Array of application objects or WP_Error on failure.
	 */
	public function get_applications( $applicant_names, $limit = 100, $offset = 0 ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'missing_api_key', 'USPTO API key is not configured.' );
		}

		if ( empty( $applicant_names ) || ! is_array( $applicant_names ) ) {
			return new \WP_Error( 'invalid_applicant_names', 'Applicant names must be a non-empty array.' );
		}

		// Build query using Query_Builder instance
		$query = $this->query_builder->build_multi_name_query( $applicant_names );

		$base_url = $this->base_url . '/applications/search';

		$query_params = array(
			'q'      => $query,
			'fields' => 'applicationNumberText,filingDate,patentNumber,inventionTitle,applicationStatusDescriptionText,applicationMetaData.firstApplicantName,businessEntityStatusCategory',
			'limit'  => $limit,
			'offset' => $offset,
			'sort'   => 'filingDate desc',
		);

		$url = add_query_arg( $query_params, $base_url );

		error_log( 'IAI PT Request: ' . $url );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'X-API-KEY' => $this->api_key,
					'Accept'    => 'application/json',
				),
				'timeout'   => $this->timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', 'Failed to connect to USPTO API: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log( 'IAI PT Response Code: ' . $response_code );
		error_log( 'IAI PT Response Body: ' . substr( $response_body, 0, 1000 ) );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'api_error',
				sprintf( 'USPTO API returned error code %d: %s', $response_code, $response_body )
			);
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_decode_error', 'Failed to parse USPTO API response: ' . json_last_error_msg() );
		}

		// Extract applications from response
		$applications = array();
		if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
			$applications = $data['results'];
		}

		return $applications;
	}

	/**
	 * Get transaction history for a specific application
	 *
	 * @param string $application_number Application number (e.g., "16123456" or "16/123,456").
	 * @return array|\WP_Error Array of transaction events or WP_Error on failure.
	 */
	public function get_transactions( $application_number ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'missing_api_key', 'USPTO API key is not configured.' );
		}

		if ( empty( $application_number ) ) {
			return new \WP_Error( 'invalid_application_number', 'Application number is required.' );
		}

		// Sanitize and validate application number
		$application_number = sanitize_text_field( $application_number );
		
		// Validate format: should contain only digits, slashes, and commas
		// Examples: "16123456", "16/123456", "16/123,456"
		if ( ! preg_match( '/^[0-9\/,]+$/', $application_number ) ) {
			return new \WP_Error( 'invalid_application_number', 'Application number contains invalid characters.' );
		}
		
		// Enforce reasonable length limit (typical application numbers are 8-15 characters)
		if ( strlen( $application_number ) > 20 ) {
			return new \WP_Error( 'invalid_application_number', 'Application number is too long.' );
		}

		$url = $this->base_url . '/applications/' . $application_number . '/transactions';

		error_log( 'IAI PT Request: ' . $url );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'X-API-KEY' => $this->api_key,
					'Accept'    => 'application/json',
				),
				'timeout'   => $this->timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', 'Failed to connect to USPTO API: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log( 'IAI PT Response Code: ' . $response_code );
		error_log( 'IAI PT Response Body: ' . substr( $response_body, 0, 1000 ) );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'api_error',
				sprintf( 'USPTO API returned error code %d: %s', $response_code, $response_body )
			);
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_decode_error', 'Failed to parse USPTO API response: ' . json_last_error_msg() );
		}

		// Extract eventData array from response
		$events = array();
		if ( isset( $data['eventData'] ) && is_array( $data['eventData'] ) ) {
			$events = $data['eventData'];
		}

		return $events;
	}
}
