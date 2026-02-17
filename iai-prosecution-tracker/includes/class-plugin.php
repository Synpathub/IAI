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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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
		// Flag that shortcode is present for conditional script loading
		add_filter( 'iai_pt_load_scripts', '__return_true' );
		return '<div class="iai-pt-app-root" id="iai-prosecution-tracker"></div>';
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		// Only load on pages with the shortcode
		if ( ! apply_filters( 'iai_pt_load_scripts', false ) ) {
			return;
		}

		$asset_file = IAI_PT_PLUGIN_DIR . '/assets/build/index.asset.php';
		
		// If build doesn't exist, show admin notice
		if ( ! file_exists( $asset_file ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				add_action( 'admin_notices', array( $this, 'show_build_notice' ) );
			}
			return;
		}

		$asset = include $asset_file;

		// Enqueue the React app
		wp_enqueue_script(
			'iai-pt-app',
			IAI_PT_PLUGIN_URL . '/assets/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue styles
		wp_enqueue_style(
			'iai-pt-styles',
			IAI_PT_PLUGIN_URL . '/assets/build/index.css',
			array(),
			$asset['version']
		);

		// Localize script with API data
		wp_localize_script(
			'iai-pt-app',
			'iaiPT',
			array(
				'restUrl'   => rest_url( 'iai/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => IAI_PT_PLUGIN_URL,
			)
		);
	}

	/**
	 * Execute cache cleanup
	 */
	public function handle_cache_cleanup() {
		$cache_manager = new Cache\Cache_Manager();
		$cache_manager->purge_expired();
	}

	/**
	 * Show admin notice when assets are not built
	 */
	public function show_build_notice() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>IAI Prosecution Tracker:</strong> Assets not built. ';
		echo 'Run <code>npm install && npm run build</code> in the plugin directory.';
		echo '</p></div>';
	}
}
