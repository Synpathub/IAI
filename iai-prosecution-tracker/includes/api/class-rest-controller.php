<?php
/**
 * REST API Controller
 *
 * @package IAI\ProsecutionTracker\API
 */

namespace IAI\ProsecutionTracker\API;

use IAI\ProsecutionTracker\Cache\Cache_Manager;
use IAI\ProsecutionTracker\Models\Fee_Classifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST_Controller class - Registers and handles WP REST API routes
 */
class REST_Controller {

	/**
	 * REST namespace
	 *
	 * @var string
	 */
	private $namespace = 'iai/v1';

	/**
	 * USPTO Client instance
	 *
	 * @var USPTO_Client
	 */
	private $uspto_client;

	/**
	 * Query Builder instance
	 *
	 * @var Query_Builder
	 */
	private $query_builder;

	/**
	 * Fee Classifier instance
	 *
	 * @var Fee_Classifier
	 */
	private $fee_classifier;

	/**
	 * Cache Manager instance
	 *
	 * @var Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->uspto_client   = new USPTO_Client();
		$this->query_builder  = new Query_Builder();
		$this->fee_classifier = new Fee_Classifier();
		$this->cache_manager  = new Cache_Manager();
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// POST /iai/v1/search
		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_applicants' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'query'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'  => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 50,
					),
					'offset' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
					),
				),
			)
		);

		// POST /iai/v1/applications
		register_rest_route(
			$this->namespace,
			'/applications',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_applications' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'applicant_names' => array(
						'required' => true,
						'type'     => 'array',
					),
					'limit'           => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 100,
					),
					'offset'          => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
					),
				),
			)
		);

		// GET /iai/v1/transactions/{app_number}
		register_rest_route(
			$this->namespace,
			'/transactions/(?P<app_number>[\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'app_number' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for all routes
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if permission granted.
	 */
	public function check_permission( $request ) {
		// Check if login is required
		$require_login = get_option( 'iai_pt_require_login', false );

		if ( $require_login ) {
			// User must be logged in
			return is_user_logged_in();
		}

		// Allow public access
		return true;
	}

	/**
	 * Search for applicants
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function search_applicants( $request ) {
		$query  = $request->get_param( 'query' );
		$limit  = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		// Compute query hash for caching
		$normalized_query = strtolower( trim( $query ) );
		$query_hash       = md5( $normalized_query );

		// Check cache
		$cached = $this->cache_manager->get_search( $query_hash );
		if ( null !== $cached ) {
			return $this->create_response( $cached );
		}

		// Build USPTO query
		$uspto_query = $this->query_builder->build_applicant_search( $query );

		// Call USPTO API
		$result = $this->uspto_client->search_applicants( $uspto_query, $limit, $offset );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		// Cache result
		$ttl_hours = get_option( 'iai_pt_search_cache_ttl', 24 );
		$this->cache_manager->set_search( $query_hash, $query, $result, $ttl_hours );

		// Return response
		return $this->create_response(
			array(
				'applicant_names' => $result,
			)
		);
	}

	/**
	 * Get applications for selected applicant names
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_applications( $request ) {
		$applicant_names = $request->get_param( 'applicant_names' );
		$limit           = $request->get_param( 'limit' );
		$offset          = $request->get_param( 'offset' );

		// Sanitize each name
		$applicant_names = array_map( 'sanitize_text_field', $applicant_names );

		// Build query and call USPTO API
		$result = $this->uspto_client->get_applications( $applicant_names, $limit, $offset );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		// Return response
		return $this->create_response(
			array(
				'applications' => $result,
				'total'        => count( $result ),
			)
		);
	}

	/**
	 * Get transactions for an application
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_transactions( $request ) {
		$app_number = $request->get_param( 'app_number' );

		// Sanitize
		$app_number = absint( $app_number );

		// Check cache
		$cached = $this->cache_manager->get_transactions( $app_number );
		if ( null !== $cached ) {
			return $this->create_response( $cached );
		}

		// Call USPTO API
		$events = $this->uspto_client->get_transactions( $app_number );

		if ( is_wp_error( $events ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => $events->get_error_code(),
					'message' => $events->get_error_message(),
				),
				500
			);
		}

		// Process events through Fee_Classifier
		$processed_events = array();
		foreach ( $events as $event ) {
			$code           = isset( $event['recordTransactionCode'] ) ? $event['recordTransactionCode'] : '';
			$classification = $this->fee_classifier->classify( $code );

			$processed_event = array(
				'date'        => isset( $event['recordEventDate'] ) ? $event['recordEventDate'] : '',
				'code'        => $code,
				'description' => isset( $event['recordEventDescription'] ) ? $event['recordEventDescription'] : '',
			);

			// Add classification if found
			if ( $classification ) {
				$processed_event['category'] = $classification['category'];
				$processed_event['label']    = $classification['label'];
				$processed_event['icon']     = $classification['icon'];
				$processed_event['color']    = $classification['color'];
			}

			// Mark flags
			$processed_event['is_fee_event']      = $this->fee_classifier->is_fee_event( $code );
			$processed_event['is_entity_change']  = $this->fee_classifier->is_entity_change( $code );

			$processed_events[] = $processed_event;
		}

		// Compute entity status timeline
		$entity_status_timeline = $this->fee_classifier->compute_entity_timeline( $processed_events );

		// Add entity_rate to each fee event
		foreach ( $processed_events as &$event ) {
			if ( $event['is_fee_event'] ) {
				$event['entity_rate'] = $this->fee_classifier->get_entity_rate_at_date(
					$entity_status_timeline,
					$event['date']
				);
			}
		}
		unset( $event );

		// Prepare result
		$result = array(
			'events'                 => $processed_events,
			'entity_status_timeline' => $entity_status_timeline,
		);

		// Cache result
		$ttl_hours = get_option( 'iai_pt_transaction_cache_ttl', 168 );
		$this->cache_manager->set_transactions( $app_number, $result, $ttl_hours );

		// Return response
		return $this->create_response( $result );
	}

	/**
	 * Create REST response with custom headers
	 *
	 * @param array $data Response data.
	 * @return \WP_REST_Response
	 */
	private function create_response( $data ) {
		$response = new \WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );
		$response->header( 'X-SG-Cache-Bypass', '1' );
		return $response;
	}
}
