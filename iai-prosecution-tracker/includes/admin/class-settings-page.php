<?php
/**
 * Admin Settings Page
 *
 * @package IAI\ProsecutionTracker\Admin
 */

namespace IAI\ProsecutionTracker\Admin;

use IAI\ProsecutionTracker\Cache\Cache_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings_Page class - Handles admin settings page
 */
class Settings_Page {

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	private $page_slug = 'iai-prosecution-tracker-settings';

	/**
	 * Settings option group
	 *
	 * @var string
	 */
	private $option_group = 'iai_pt_settings';

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
		$this->cache_manager = new Cache_Manager();
	}

	/**
	 * Register settings page and hooks
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_iai_pt_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'IAI Prosecution Tracker', 'iai-prosecution-tracker' ),
			__( 'IAI Prosecution Tracker', 'iai-prosecution-tracker' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings and fields
	 */
	public function register_settings() {
		// Register options
		register_setting(
			$this->option_group,
			'iai_pt_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'iai_pt_search_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 24,
			)
		);

		register_setting(
			$this->option_group,
			'iai_pt_transaction_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 168,
			)
		);

		register_setting(
			$this->option_group,
			'iai_pt_require_login',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		// Add settings section
		add_settings_section(
			'iai_pt_main_settings',
			__( 'Main Settings', 'iai-prosecution-tracker' ),
			array( $this, 'render_section_description' ),
			$this->page_slug
		);

		// API Key field
		add_settings_field(
			'iai_pt_api_key',
			__( 'USPTO API Key', 'iai-prosecution-tracker' ),
			array( $this, 'render_api_key_field' ),
			$this->page_slug,
			'iai_pt_main_settings'
		);

		// Search Cache TTL field
		add_settings_field(
			'iai_pt_search_cache_ttl',
			__( 'Search Cache TTL', 'iai-prosecution-tracker' ),
			array( $this, 'render_search_cache_ttl_field' ),
			$this->page_slug,
			'iai_pt_main_settings'
		);

		// Transaction Cache TTL field
		add_settings_field(
			'iai_pt_transaction_cache_ttl',
			__( 'Transaction Cache TTL', 'iai-prosecution-tracker' ),
			array( $this, 'render_transaction_cache_ttl_field' ),
			$this->page_slug,
			'iai_pt_main_settings'
		);

		// Require Login field
		add_settings_field(
			'iai_pt_require_login',
			__( 'Require Login', 'iai-prosecution-tracker' ),
			array( $this, 'render_require_login_field' ),
			$this->page_slug,
			'iai_pt_main_settings'
		);
	}

	/**
	 * Render section description
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configure the IAI Prosecution Tracker plugin settings.', 'iai-prosecution-tracker' ) . '</p>';
	}

	/**
	 * Render API Key field
	 */
	public function render_api_key_field() {
		$value = get_option( 'iai_pt_api_key', '' );
		$is_constant_defined = defined( 'IAI_PT_USPTO_API_KEY' );

		if ( $is_constant_defined ) {
			echo '<input type="password" name="iai_pt_api_key" value="' . esc_attr( $value ) . '" class="regular-text" disabled />';
			echo '<p class="description">' . esc_html__( 'Defined in wp-config.php', 'iai-prosecution-tracker' ) . '</p>';
		} else {
			echo '<input type="password" name="iai_pt_api_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
			echo '<p class="description">' . esc_html__( 'Enter your USPTO Open Data Portal API key.', 'iai-prosecution-tracker' ) . '</p>';
		}

		echo '<p class="description" style="color: #d63638;">';
		echo esc_html__( 'If IAI_PT_USPTO_API_KEY is defined in wp-config.php, it takes precedence over this value.', 'iai-prosecution-tracker' );
		echo '</p>';
	}

	/**
	 * Render Search Cache TTL field
	 */
	public function render_search_cache_ttl_field() {
		$value = get_option( 'iai_pt_search_cache_ttl', 24 );
		echo '<input type="number" name="iai_pt_search_cache_ttl" value="' . esc_attr( $value ) . '" min="1" class="small-text" /> ';
		echo esc_html__( 'hours', 'iai-prosecution-tracker' );
		echo '<p class="description">' . esc_html__( 'How long to cache applicant search results.', 'iai-prosecution-tracker' ) . '</p>';
	}

	/**
	 * Render Transaction Cache TTL field
	 */
	public function render_transaction_cache_ttl_field() {
		$value = get_option( 'iai_pt_transaction_cache_ttl', 168 );
		$days  = intval( $value / 24 );
		echo '<input type="number" name="iai_pt_transaction_cache_ttl" value="' . esc_attr( $value ) . '" min="1" class="small-text" /> ';
		/* translators: %d: number of days */
		echo sprintf( esc_html__( 'hours (%d days)', 'iai-prosecution-tracker' ), $days );
		echo '<p class="description">' . esc_html__( 'How long to cache transaction histories.', 'iai-prosecution-tracker' ) . '</p>';
	}

	/**
	 * Render Require Login field
	 */
	public function render_require_login_field() {
		$value = get_option( 'iai_pt_require_login', false );
		echo '<label>';
		echo '<input type="checkbox" name="iai_pt_require_login" value="1" ' . checked( $value, true, false ) . ' />';
		echo ' ' . esc_html__( 'Require users to be logged in to access the prosecution tracker', 'iai-prosecution-tracker' );
		echo '</label>';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Include the view template
		include __DIR__ . '/views/settings-page.php';
	}

	/**
	 * Handle clear cache action
	 */
	public function handle_clear_cache() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'iai-prosecution-tracker' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'iai_pt_clear_cache' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'iai-prosecution-tracker' ) );
		}

		// Clear cache
		$this->cache_manager->clear_all();
		$this->cache_manager->purge_expired();

		// Redirect back with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => $this->page_slug,
					'cache_cleared' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
