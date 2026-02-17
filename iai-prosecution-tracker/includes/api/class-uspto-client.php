<?php
// File: includes/api/class-uspto-client.php
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
	 */
	private $base_url = 'https://api.uspto.gov/api/v1/patent';

	/**
	 * API key
	 */
	private $api_key;

	/**
	 * Query builder instance
	 */
	private $query_builder;

	/**
	 * Request timeout in seconds
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
	 * * @param string $query User search term.
	 * @return array|\WP_Error
	 */
	public function search_applicants( $query ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'missing_api_key', 'USPTO API key is not configured.' );
		}

		$url = $this->base_url . '/applications/search';

		// Solr wildcard search on the specific applicant name field
		$clean_query = str_replace( '*', '', $query );
		$solr_query = 'applicationMetaData.firstApplicantName:(*' . $clean_query . '*)';

		$query_params = array(
			'q'      => $solr_query,
			'facets' => 'applicationMetaData.firstApplicantName',
			'limit'  => 1, // Documentation requires limit > 0
		);

		$url = add_query_arg( $query_params, $url );

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'X-API-KEY' => $this->api_key,
				'Accept'    => 'application/json',
			),
			'timeout'   => $this->timeout,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error( 'api_error', "USPTO API error $code: $body" );
		}

		$data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_error', 'Invalid JSON from USPTO' );
		}

		$applicant_names = array();
		$facet_array     = null;

		// The Swagger docs indicate facets are returned in a specific metadata block
		if ( isset( $data['facetCounts']['firstApplicantName'] ) ) {
			$facet_array = $data['facetCounts']['firstApplicantName'];
		} elseif ( isset( $data['facets']['applicationMetaData.firstApplicantName'] ) ) {
			$facet_array = $data['facets']['applicationMetaData.firstApplicantName'];
		}

		if ( is_array( $facet_array ) && ! empty( $facet_array ) ) {
			$first = $facet_array[0];

			// Handle both "list of objects" and "alternating array" formats
			if ( is_array( $first ) || is_object( $first ) ) {
				foreach ( $facet_array as $item ) {
					$item = (array) $item;
					if ( isset( $item['value'] ) ) {
						$applicant_names[] = array(
							'name'  => $item['value'],
							'count' => (int) $item['count'],
						);
					}
				}
			} else {
				for ( $i = 0; $i < count( $facet_array ); $i += 2 ) {
					if ( isset( $facet_array[ $i ] ) ) {
						$applicant_names[] = array(
							'name'  => $facet_array[ $i ],
							'count' => (int) ( $facet_array[ $i + 1 ] ?? 0 ),
						);
					}
				}
			}
		}

		return $applicant_names;
	}

	/**
	 * Get patent applications for specified applicant names
	 */
	public function get_applications( $applicant_names ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'api_key_missing' );
		}

		$query = $this->query_builder->build_multi_name_query( $applicant_names );
		$url   = $this->base_url . '/applications/search';

		// We omit sorting on filingDate as the USPTO API returned errors for that field mapping
		$query_params = array(
			'q'      => $query,
			'fields' => 'applicationNumberText,filingDate,patentNumber,inventionTitle,applicationStatusDescriptionText,applicationMetaData.firstApplicantName,businessEntityStatusCategory',
			'limit'  => 100,
		);

		$url = add_query_arg( $query_params, $url );

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'X-API-KEY' => $this->api_key,
				'Accept'    => 'application/json',
			),
			'timeout' => $this->timeout,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return ( isset( $data['results'] ) ) ? $data['results'] : array();
	}

	/**
	 * Get transaction history for an application
	 */
	public function get_transactions( $application_number ) {
		$application_number = sanitize_text_field( $application_number );
		// Ensure any slashes/commas are handled correctly in the URL path
		$url = $this->base_url . '/applications/' . rawurlencode($application_number) . '/transactions';

		$response = wp_remote_get( $url, array(
			'headers' => array( 'X-API-KEY' => $this->api_key, 'Accept' => 'application/json' ),
			'timeout' => $this->timeout,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return ( isset( $data['eventData'] ) ) ? $data['eventData'] : array();
	}
}
