<?php
// File: includes/api/class-rest-controller.php
/**
 * REST Controller
 *
 * @package IAI\ProsecutionTracker\API
 */

namespace IAI\ProsecutionTracker\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST_Controller class
 */
class REST_Controller extends \WP_REST_Controller {

	protected $namespace = 'iai/v1';
	protected $client;

	public function __construct() {
		$this->client = new USPTO_Client();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		// GET /iai/v1/search?query=mira
		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_applicants' ),
				'permission_callback' => '__return_true',
			)
		);

		// POST /iai/v1/applications { applicant_names: [] }
		register_rest_route(
			$this->namespace,
			'/applications',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_applications' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /iai/v1/transactions/{id}
		register_rest_route(
			$this->namespace,
			'/transactions/(?P<id>[a-zA-Z0-9\/,]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Search applicants - Handled via USPTO_Client
	 */
	public function search_applicants( $request ) {
		$query = $request->get_param( 'query' );
		
		if ( empty( $query ) ) {
			return new \WP_REST_Response( array( 'message' => 'Query is required' ), 400 );
		}

		$results = $this->client->search_applicants( $query );

		if ( is_wp_error( $results ) ) {
			return new \WP_REST_Response( array( 
				'message' => $results->get_error_message(),
				'data' => $results->get_error_data()
			), 500 );
		}

		return new \WP_REST_Response( array( 'applicant_names' => $results ), 200 );
	}

	/**
	 * Get applications - Handled via USPTO_Client
	 */
	public function get_applications( $request ) {
		$params = $request->get_json_params();
		$names  = isset( $params['applicant_names'] ) ? $params['applicant_names'] : array();

		if ( empty( $names ) ) {
			return new \WP_REST_Response( array( 'message' => 'Names are required' ), 400 );
		}

		$results = $this->client->get_applications( $names );

		if ( is_wp_error( $results ) ) {
			return new \WP_REST_Response( array( 'message' => $results->get_error_message() ), 500 );
		}

		return new \WP_REST_Response( array( 'applications' => $results, 'total' => count($results) ), 200 );
	}

	/**
	 * Get transactions
	 */
	public function get_transactions( $request ) {
		$id = $request->get_param( 'id' );
		$results = $this->client->get_transactions( $id );

		if ( is_wp_error( $results ) ) {
			return new \WP_REST_Response( array( 'message' => $results->get_error_message() ), 500 );
		}

		return new \WP_REST_Response( array( 'events' => $results ), 200 );
	}
}
