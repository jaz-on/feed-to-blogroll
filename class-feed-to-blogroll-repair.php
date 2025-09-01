<?php
/**
 * Temporary Repair Script for Custom Post Type
 *
 * This script forces the registration of the blogroll Custom Post Type.
 * Run this once to fix the CPT registration issue.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repair class for CPT registration
 */
class Feed_To_Blogroll_Repair {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_repair_menu' ), 20 );
	}

	/**
	 * Add repair menu
	 */
	public function add_repair_menu() {
		// Add as a submenu under the main Feed to Blogroll menu instead of Tools
		add_submenu_page(
			'feed-to-blogroll',
			__( 'Repair Feed to Blogroll CPT', 'feed-to-blogroll' ),
			__( 'Repair CPT', 'feed-to-blogroll' ),
			'manage_options',
			'feed-to-blogroll-repair',
			array( $this, 'repair_page' )
		);
	}

	/**
	 * Repair page
	 */
	public function repair_page() {
		if ( isset( $_POST['repair_cpt'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'repair_cpt' ) ) {
			$this->force_cpt_registration();
			echo '<div class="notice notice-success"><p>Custom Post Type registration repaired successfully!</p></div>';
		}

		?>
		<div class="wrap">
			<h1>Repair Custom Post Type</h1>
			
			<div class="postbox">
				<h2 class="hndle"><span>Current Status</span></h2>
				<div class="inside">
					<?php $this->show_current_status(); ?>
				</div>
			</div>
			
			<div class="postbox">
				<h2 class="hndle"><span>Repair Actions</span></h2>
				<div class="inside">
					<form method="post">
						<?php wp_nonce_field( 'repair_cpt' ); ?>
						<p>Click the button below to force the registration of the blogroll Custom Post Type:</p>
						<input type="submit" name="repair_cpt" class="button button-primary" value="Repair Custom Post Type">
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show current status
	 */
	private function show_current_status() {
		echo '<table class="form-table">';
		echo '<tr><th>Blogroll CPT Registered:</th><td>' . ( post_type_exists( 'blogroll' ) ? '✅ Yes' : '❌ No' ) . '</td></tr>';
		echo '<tr><th>Blogroll Category Taxonomy:</th><td>' . ( taxonomy_exists( 'blogroll_category' ) ? '✅ Yes' : '❌ No' ) . '</td></tr>';
		echo '<tr><th>Plugin Active:</th><td>' . ( is_plugin_active( 'feed-to-blogroll/feed-to-blogroll.php' ) ? '✅ Yes' : '❌ No' ) . '</td></tr>';
		echo '</table>';
	}

	/**
	 * Force CPT registration
	 */
	private function force_cpt_registration() {
		// Load the CPT class (correct filename)
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-to-blogroll-cpt.php';

		// Create new instance and force registration
		$cpt = new Feed_To_Blogroll_CPT();
		$cpt->force_registration();

		// Also try to re-register ACF fields
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			$cpt->create_acf_fields();
		}

		// Log the repair
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Feed to Blogroll: CPT registration repaired manually' );
		}
	}
}

// Initialize repair
new Feed_To_Blogroll_Repair();
