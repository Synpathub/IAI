<?php
// File: includes/api/class-rest-controller.php
namespace IAI\ProsecutionTracker\API;

use IAI\ProsecutionTracker\Models\Fee_Classifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class REST_Controller extends \WP_REST_Controller {

	protected $namespace = 'iai/v1';
	protected $uspto_client;
	protected $query_builder;
	protected $fee_classifier;

	public function __construct() {
		$this->uspto_client   = new USPTO_Client();
		$this->query_builder  = new Query_Builder();
		$this->fee_classifier = new Fee_Classifier();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/search', array(
			'methods' => 'GET',
			'callback' => array( $this, 'search_applicants' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace, '/applications', array(
			'methods' => 'POST',
			'callback' => array( $this, 'get_applications' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace, '/transactions/(?P<app_number>[a-zA-Z0-9\/,]+)', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_transactions' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function search_applicants( $request ) {
		$query = $request->get_param( 'query' );
		if ( empty( $query ) ) return new \WP_REST_Response( array( 'message' => 'Query required' ), 400 );
		
		$results = $this->uspto_client->search_applicants( $query );
		if ( is_wp_error( $results ) ) return new \WP_REST_Response( array( 'message' => $results->get_error_message() ), 500 );
		
		return new \WP_REST_Response( array( 'applicant_names' => $results ), 200 );
	}

	public function get_applications( $request ) {
		$names = $request->get_param( 'applicant_names' );
		if ( empty( $names ) ) return new \WP_REST_Response( array( 'message' => 'Names required' ), 400 );

		$result = $this->uspto_client->get_applications( $names );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'message' => $result->get_error_message() ), 500 );
		}

		$applications = isset($result['results']) ? $result['results'] : $result;
		
		// Include Debug Info
		$debug_info = array(
			'url' => isset($result['debug_url']) ? $result['debug_url'] : 'N/A',
			'query' => isset($result['debug_q']) ? $result['debug_q'] : 'N/A'
		);

		return new \WP_REST_Response( array( 
			'applications' => $applications, 
			'total' => count( $applications ),
			'debug_info' => $debug_info
		), 200 );
	}

	public function get_transactions( $request ) {
		$id = $request->get_param( 'app_number' );
		$events = $this->uspto_client->get_transactions( $id );
		
		if ( is_wp_error( $events ) ) return new \WP_REST_Response( array( 'message' => $events->get_error_message() ), 500 );

		// Process classifications
		$processed = array();
		foreach ( $events as $event ) {
			$code = $event['recordTransactionCode'] ?? '';
			$cls = $this->fee_classifier->classify( $code );
			$item = array(
				'date' => $event['recordEventDate'] ?? '',
				'code' => $code,
				'description' => $event['recordEventDescription'] ?? '',
			);
			if($cls) { $item = array_merge($item, $cls); }
			$processed[] = $item;
		}
		
		return new \WP_REST_Response( array( 'events' => $processed ), 200 );
	}
}
