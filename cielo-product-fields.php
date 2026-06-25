<?php
/**
 * Plugin Name: Cielo Custom Product Fields
 * Plugin URI: https://cielocloud.org
 * Description: Advanced custom fields for WooCommerce products with dynamic pricing, conditional logic, file uploads, and premium licensing.
 * Version: 2.0.0
 * Author: CieloCloud
 * Text Domain: cielo-product-fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Cielo_Product_Fields {

	public function __construct() {
		// Phase 1: Backend Setup
		add_action( 'init', array( $this, 'register_cpt' ) );
	}

	/**
	 * Phase 8: The Gatekeeper Function (Platform-Agnostic)
	 */
	public static function is_pro() {
		// Checks the transient cache established by the License Manager
		$status = get_transient( 'cielo_plugin_license_status' );
		return $status === 'valid';
	}

	/**
	 * Phase 1: Register the Custom Post Type & Taxonomy Link
	 */
	public function register_cpt() {
		$args = array(
			'label'                 => 'Product Field Group',
			'labels'                => array(
				'name'          => 'Product Fields',
				'singular_name' => 'Product Field Group',
				'add_new_item'  => 'Add New Field Group',
			),
			// FIX: Removed 'custom-fields' to hide the native WP text box
			'supports'              => array( 'title' ), 
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_icon'             => 'dashicons-forms',
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
		);

		register_post_type( 'cielo_product_fields', $args );

		// Hook up native WooCommerce Categories to our CPT
		register_taxonomy_for_object_type( 'product_cat', 'cielo_product_fields' );
	}
}

// Initialize the core plugin
new Cielo_Product_Fields();

// Load all of our integration bridges
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-meta-box.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-frontend-render.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cart-handler.php';

// Only load license manager if it exists (allows you to use universal server logic)
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';
}