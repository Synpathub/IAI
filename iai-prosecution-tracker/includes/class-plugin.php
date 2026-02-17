<?php
/**
 * Plugin Core Class
 *
 * @package IAI\ProsecutionTracker
 */

namespace IAI\ProsecutionTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin class - Singleton loader
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - register hooks
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'iai_pt_cache_cleanup', array( $this, 'handle_cache_cleanup' ) );
	}

	/**
	 * Register admin menu
	 */
	public function register_admin_menu() {
		$settings_page = new Admin\Settings_Page();
		$settings_page->register();
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		$rest_controller = new API\REST_Controller();
		$rest_controller->register_routes();
	}

	/**
	 * Register shortcode
	 */
	public function register_shortcode() {
		add_shortcode( 'iai_prosecution_tracker', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		return '<div class="iai-pt-app-root" id="iai-prosecution-tracker"></div>';
	}

	/**
	 * Execute cache cleanup
	 */
	public function handle_cache_cleanup() {
		$cache_manager = new Cache\Cache_Manager();
		$cache_manager->purge_expired();
	}
}
