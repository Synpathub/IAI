<?php
// File: includes/api/class-uspto-client.php
namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USPTO_Client {

	private $base_url = 'https://api.uspto.gov/api/v1/patent';
	private $api_key;
	private $query_builder;
	private $timeout = 30;

	public function __construct() {
		$this->api_key       = defined( 'IAI_PT_USPTO_API_KEY' ) ? IAI_PT_USPTO_API_KEY : get_option( 'iai_pt_api_key' );
		$this->query_builder = new Query_Builder();
	}

	public function search_applicants( $query, $limit = 50, $offset = 0 ) {
		if ( empty( $this->api_key ) ) return new \WP_Error( 'missing_api_key' );

		$url = $this->base_url . '/applications/search';
		
		$clean_query = str_replace( array( '(', ')', '*', '"' ), '', $query );
		$solr_query = 'applicationMetaData.firstApplicantName:(*' . $clean_query . '*)';

		$query_params = array(
			'q'      => $solr_query,
			'facets' => 'applicationMetaData.firstApplicantName',
			'limit'  => 1,
		);

		$url = add_query_arg( $query_params, $url );
		$response = wp_remote_get( $url, array(
			'headers' => array( 'X-API-KEY' => $this->api_key, 'Accept' => 'application/json' ),
			'timeout' => $this->timeout,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) return new \WP_Error( 'api_error', "USPTO API error $code: $body" );

		$data = json_decode( $body, true );
		$applicant_names = array();
		
		$facet_array = $data['facetCounts']['firstApplicantName'] 
			?? $data['facets']['applicationMetaData.firstApplicantName'] 
			?? $data['facet_counts']['facet_fields']['applicationMetaData.firstApplicantName'] 
			?? null;

		if ( is_array( $facet_array ) && ! empty( $facet_array ) ) {
			$first = $facet_array[0];
			if ( is_array( $first ) || is_object( $first ) ) {
				foreach ( $facet_array as $item ) {
					$item = (array) $item;
					if ( isset( $item['value'] ) ) $applicant_names[] = array( 'name' => $item['value'], 'count' => (int) $item['count'] );
				}
			} else {
				for ( $i = 0; $i < count( $facet_array ); $i += 2 ) {
					if ( isset( $facet_array[ $i ] ) ) $applicant_names[] = array( 'name' => $facet_array[ $i ], 'count' => (int) ($facet_array[ $i + 1 ] ?? 0) );
				}
			}
		}
		return $applicant_names;
	}

	public function get_applications( $applicant_names, $limit = 100, $offset = 0 ) {
		if ( empty( $this->api_key ) ) return new \WP_Error( 'api_key_missing' );

		$query = $this->query_builder->build_multi_name_query( $applicant_names );
		$url   = $this->base_url . '/applications/search';

		// FIX: Removed 'fields' parameter completely. 
		// Requesting specific fields was causing Solr to return 0 results if any field was unmapped.
		// By removing it, we get the default fields, which guarantees data if the query matches.
		$query_params = array(
			'q'      => $query,
			'limit'  => $limit,
			'offset' => $offset,
		);

		$full_url = add_query_arg( $query_params, $url );

		$response = wp_remote_get( $full_url, array(
			'headers' => array( 'X-API-KEY' => $this->api_key, 'Accept' => 'application/json' ),
			'timeout' => $this->timeout,
		) );

		if ( is_wp_error( $response ) ) return $response;
		
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		
		return array(
			'results' => isset( $data['results'] ) ? $data['results'] : array(),
			'debug_url' => $full_url,
			'debug_q' => $query
		);
	}

	public function get_transactions( $application_number ) {
		$application_number = sanitize_text_field( $application_number );
		$url = $this->base_url . '/applications/' . rawurlencode($application_number) . '/transactions';
		$response = wp_remote_get( $url, array(
			'headers' => array( 'X-API-KEY' => $this->api_key, 'Accept' => 'application/json' ),
			'timeout' => $this->timeout,
		) );
		if ( is_wp_error( $response ) ) return $response;
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return ( isset( $data['eventData'] ) ) ? $data['eventData'] : array();
	}
}
