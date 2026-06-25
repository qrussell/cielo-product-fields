<?php
/**
 * Bridge 4: License Management, API Verification, and Gatekeeping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cielo_License_Manager {

	// The endpoint on your custom CieloCloud server that validates licenses
	private $api_url = 'https://cielocloud.org/wp-json/cielo-licenses/v1/verify';

	public function __construct() {
		// 1. Add the settings page under our Custom Post Type menu
		add_action( 'admin_menu', array( $this, 'add_license_menu' ) );
		
		// 2. Register the setting in the WordPress database
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// 3. Intercept the saving process to ping our API server
		add_action( 'update_option_cielo_pro_license_key', array( $this, 'verify_license_key' ), 10, 3 );
		add_action( 'add_option_cielo_pro_license_key', array( $this, 'verify_license_key_on_add' ), 10, 2 );

		// 4. Background check if the transient expires
		add_action( 'admin_init', array( $this, 'check_transient_status' ) );
	}

	/**
	 * Step 1: Create the Submenu Page
	 */
	public function add_license_menu() {
		add_submenu_page(
			'edit.php?post_type=cielo_product_fields', // Parent slug (Our CPT)
			'Cielo Pro License',                       // Page title
			'License Settings',                        // Menu title
			'manage_options',                          // Capability required
			'cielo-license-settings',                  // Menu slug
			array( $this, 'render_settings_page' )     // Callback function
		);
	}

	/**
	 * Step 2: Register the License Key Option
	 */
	public function register_settings() {
		register_setting( 'cielo_license_group', 'cielo_pro_license_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => ''
		) );
	}

	/**
	 * UI: Render the Settings Page HTML
	 */
	public function render_settings_page() {
		$license_key = get_option( 'cielo_pro_license_key' );
		$status      = get_transient( 'cielo_plugin_license_status' );
		
		// Determine visual status badge
		if ( $status === 'valid' ) {
			$badge = '<span style="color: #15803d; background: #dcfce7; padding: 4px 8px; border-radius: 4px; font-weight: bold;">Active & Valid</span>';
		} elseif ( ! empty( $license_key ) ) {
			$badge = '<span style="color: #b91c1c; background: #fee2e2; padding: 4px 8px; border-radius: 4px; font-weight: bold;">Invalid or Expired</span>';
		} else {
			$badge = '<span style="color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: bold;">Inactive</span>';
		}

		?>
		<div class="wrap">
			<h1>Cielo Product Fields - Pro License</h1>
			<p>Unlock advanced features like Repeater Fields, File Uploads, and conditional logic by entering your Pro license key below.</p>
			
			<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 600px; margin-top: 20px;">
				<h2 style="margin-top: 0;">License Status: <?php echo $badge; ?></h2>
				<hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
				
				<form method="post" action="options.php">
					<?php settings_fields( 'cielo_license_group' ); ?>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row" style="padding-left: 0;">License Key</th>
							<td>
								<input type="password" name="cielo_pro_license_key" value="<?php echo esc_attr( $license_key ); ?>" style="width: 100%; max-width: 350px;" placeholder="Enter your license key..." />
								<p class="description">Your license key validates securely with cielocloud.org.</p>
							</td>
						</tr>
					</table>
					
					<?php submit_button( 'Save & Activate License' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Step 3: Trigger Verification on Option Add
	 */
	public function verify_license_key_on_add( $option, $value ) {
		$this->verify_license_key( '', $value, $option );
	}

	/**
	 * Step 3: API Ping to CieloCloud.org
	 * Fires automatically when the admin saves the settings page.
	 */
	public function verify_license_key( $old_value, $new_value, $option ) {
		if ( empty( $new_value ) ) {
			delete_transient( 'cielo_plugin_license_status' );
			return;
		}

		// Prepare payload for cielocloud.org
		$payload = array(
			'license_key' => $new_value,
			'domain'      => site_url(), // Send the store URL so you can track activations
		);

		// Ping your remote server
		$response = wp_remote_post( $this->api_url, array(
			'timeout' => 15,
			'body'    => wp_json_encode( $payload ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json'
			)
		) );

		// Handle connection errors
		if ( is_wp_error( $response ) ) {
			delete_transient( 'cielo_plugin_license_status' );
			return; // Optionally log error: $response->get_error_message()
		}

		// Decode the JSON response from your custom API
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// If your server says it's valid, cache that answer locally for 12 hours!
		if ( isset( $data['status'] ) && $data['status'] === 'valid' ) {
			set_transient( 'cielo_plugin_license_status', 'valid', 12 * HOUR_IN_SECONDS );
		} else {
			delete_transient( 'cielo_plugin_license_status' );
		}
	}

	/**
	 * Step 4: Background Cache Check
	 * If the 12-hour transient expires, we silently re-verify the saved key in the background
	 * so the store owner doesn't suddenly lose Pro features if they haven't visited the settings page.
	 */
	public function check_transient_status() {
		if ( false === get_transient( 'cielo_plugin_license_status' ) ) {
			$saved_key = get_option( 'cielo_pro_license_key' );
			if ( ! empty( $saved_key ) ) {
				// We pass dummy data for $old_value to trigger the API logic
				$this->verify_license_key( '', $saved_key, 'cielo_pro_license_key' );
			}
		}
	}
}

new Cielo_License_Manager();