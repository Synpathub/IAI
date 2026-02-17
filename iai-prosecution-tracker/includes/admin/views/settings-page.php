<?php
/**
 * Admin Settings Page Template
 *
 * @package IAI\ProsecutionTracker\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php
	// Show success message if cache was cleared
	if ( isset( $_GET['cache_cleared'] ) && '1' === $_GET['cache_cleared'] ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Cache cleared successfully.', 'iai-prosecution-tracker' ); ?></p>
		</div>
		<?php
	}

	// Show warning if no API key is configured
	$api_key_defined  = defined( 'IAI_PT_USPTO_API_KEY' ) && ! empty( IAI_PT_USPTO_API_KEY );
	$api_key_in_db    = ! empty( get_option( 'iai_pt_api_key', '' ) );

	if ( ! $api_key_defined && ! $api_key_in_db ) {
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Warning: No USPTO API key is configured. Please add it in wp-config.php or in the settings below.', 'iai-prosecution-tracker' ); ?></p>
		</div>
		<?php
	}
	?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'iai_pt_settings' );
		do_settings_sections( 'iai-prosecution-tracker-settings' );
		submit_button();
		?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'Cache Management', 'iai-prosecution-tracker' ); ?></h2>
	<p><?php esc_html_e( 'Clear all cached data including search results and transaction histories.', 'iai-prosecution-tracker' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="iai_pt_clear_cache" />
		<?php wp_nonce_field( 'iai_pt_clear_cache' ); ?>
		<?php submit_button( __( 'Clear All Cache', 'iai-prosecution-tracker' ), 'secondary', 'submit', false ); ?>
	</form>
</div>
