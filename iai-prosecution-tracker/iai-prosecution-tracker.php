<?php
/**
 * Plugin Name:       IAI Prosecution Fee Tracker
 * Plugin URI:        https://innovationaccess.org
 * Description:       Search patent applicants, retrieve prosecution fee payment data from the USPTO Open Data Portal, and visualize payment events and entity status changes on an interactive timeline.
 * Version:           1.0.0
 * Author:            Innovation Access Initiative
 * Author URI:        https://innovationaccess.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iai-prosecution-tracker
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'IAI_PT_VERSION', '1.0.0' );
define( 'IAI_PT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IAI_PT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IAI_PT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes
spl_autoload_register( function ( $class ) {
    $prefix   = 'IAI\\ProsecutionTracker\\';
    $base_dir = IAI_PT_PLUGIN_DIR . 'includes/';

    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    // Strip the prefix
    $relative_class = substr( $class, strlen( $prefix ) );
    
    // Convert namespace separators to directory separators and underscores to hyphens
    $relative_path = str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class );
    
    // Split into directory path and class name
    $parts = explode( '/', $relative_path );
    
    // Apply "class-" prefix only to the filename (last part)
    $parts[ count( $parts ) - 1 ] = 'class-' . $parts[ count( $parts ) - 1 ];
    
    // Lowercase everything and build the final path
    $file = $base_dir . strtolower( implode( '/', $parts ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

// Activation / Deactivation hooks
register_activation_hook( __FILE__, [ 'IAI\\ProsecutionTracker\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'IAI\\ProsecutionTracker\\Deactivator', 'deactivate' ] );

// Initialize plugin
add_action( 'plugins_loaded', function () {
    require_once IAI_PT_PLUGIN_DIR . 'includes/class-plugin.php';
    IAI\ProsecutionTracker\Plugin::get_instance();
});
