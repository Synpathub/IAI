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
	private $base_url = 'https://api.uspto.gov/patent/v1';

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

		$url = $this->base_url . '/patent/applications/search';

		$body = array(
			'q'       => $query,
			'facets'  => array( 'firstApplicantName' ),
			'rows'    => 0,
			'limit'   => $limit,
			'offset'  => $offset,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'x-api-key'    => $this->api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => $this->timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', 'Failed to connect to USPTO API: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

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

		// Extract facet results
		$applicant_names = array();
		if ( isset( $data['facets']['firstApplicantName'] ) && is_array( $data['facets']['firstApplicantName'] ) ) {
			foreach ( $data['facets']['firstApplicantName'] as $facet ) {
				if ( isset( $facet['value'] ) && isset( $facet['count'] ) ) {
					$applicant_names[] = array(
						'name'  => $facet['value'],
						'count' => (int) $facet['count'],
					);
				}
			}
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

		$url = $this->base_url . '/patent/applications/search';

		$body = array(
			'q'      => $query,
			'fields' => array(
				'applicationNumberText',
				'filingDate',
				'patentNumber',
				'inventionTitle',
				'applicationStatusDescriptionText',
				'firstApplicantName',
				'businessEntityStatusCategory',
			),
			'rows'   => $limit,
			'start'  => $offset,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'x-api-key'    => $this->api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => $this->timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', 'Failed to connect to USPTO API: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

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

		$url = $this->base_url . '/patent/' . $application_number . '/transactions';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'x-api-key' => $this->api_key,
				),
				'timeout' => $this->timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', 'Failed to connect to USPTO API: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

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
