<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Cielo_Admin_Meta_Box' ) ) {

	class Cielo_Admin_Meta_Box {
		
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_builder_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_builder_data' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_react_app' ) );
		}
		
		public function add_builder_meta_box() {
			add_meta_box( 'cielo_template_builder', 'Product Field Template Builder', array( $this, 'render_meta_box' ), 'cielo_product_fields', 'normal', 'high' );
		}
		
		public function render_meta_box( $post ) {
			wp_nonce_field( 'cielo_save_template_data', 'cielo_template_nonce' );
			
			// Get React JSON Array
			$existing_data = get_post_meta( $post->ID, '_cielo_template_data', true ) ?: '';
			
			// Get Phase 6 & 7 Global Settings
			$display_mode = get_post_meta( $post->ID, '_cielo_display_mode', true ) ?: 'flat';
			$sku_action   = get_post_meta( $post->ID, '_cielo_sku_action', true ) ?: 'append';
			$sku_delim    = get_post_meta( $post->ID, '_cielo_sku_delimiter', true ) ?: '-';
			
			// Global Options UI
			echo '<div style="background:#f1f5f9; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #cbd5e1; display:flex; gap: 20px;">';
			
			echo '<div><label><b>Display Mode:</b><br><select name="cielo_display_mode">';
			echo '<option value="flat" ' . selected($display_mode, 'flat', false) . '>Flat List (Seamless)</option>';
			echo '<option value="sectioned" ' . selected($display_mode, 'sectioned', false) . '>Sectioned by Origin</option>';
			echo '</select></label></div>';

			echo '<div><label><b>SKU Action:</b><br><select name="cielo_sku_action">';
			echo '<option value="append" ' . selected($sku_action, 'append', false) . '>Append to Base SKU</option>';
			echo '<option value="replace" ' . selected($sku_action, 'replace', false) . '>Replace Base SKU</option>';
			echo '</select></label></div>';

			echo '<div><label><b>SKU Delimiter:</b><br><input type="text" name="cielo_sku_delimiter" value="'.esc_attr($sku_delim).'" style="width:50px;"></label></div>';

			echo '</div>';

			// React Mount Point
			echo '<div id="cielo-react-root"></div>';
			echo '<textarea name="cielo_template_data" id="cielo_template_data" style="display:none;">' . esc_textarea( $existing_data ) . '</textarea>';
		}
		
		public function save_builder_data( $post_id ) {
			if ( ! isset( $_POST['cielo_template_nonce'] ) || ! wp_verify_nonce( $_POST['cielo_template_nonce'], 'cielo_save_template_data' ) ) return;
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;
			
			// Save React JSON
			if ( isset( $_POST['cielo_template_data'] ) ) {
				$json_data = wp_unslash( $_POST['cielo_template_data'] );
				if ( is_string( $json_data ) && is_array( json_decode( $json_data, true ) ) ) {
					update_post_meta( $post_id, '_cielo_template_data', $json_data );
				}
			}

			// Save Global Settings
			if(isset($_POST['cielo_display_mode'])) update_post_meta( $post_id, '_cielo_display_mode', sanitize_text_field($_POST['cielo_display_mode']) );
			if(isset($_POST['cielo_sku_action'])) update_post_meta( $post_id, '_cielo_sku_action', sanitize_text_field($_POST['cielo_sku_action']) );
			if(isset($_POST['cielo_sku_delimiter'])) update_post_meta( $post_id, '_cielo_sku_delimiter', sanitize_text_field($_POST['cielo_sku_delimiter']) );
		}
		
		public function enqueue_react_app( $hook ) {
			global $post;
			if ( ( $hook === 'post-new.php' || $hook === 'post.php' ) && isset($post) && 'cielo_product_fields' === $post->post_type ) {
				// Fixed paths: Step out of includes/ into build/
				wp_enqueue_script( 'cielo-react-builder', plugins_url( '../build/index.js', __FILE__ ), array( 'wp-element' ), '2.0.0', true );
				
				// NOTE: Change style-index.css to index.css here if your build folder names it that way!
				wp_enqueue_style( 'cielo-react-styles', plugins_url( '../build/style-index.css', __FILE__ ), array(), '2.0.0' );
				
				$existing_data = get_post_meta( $post->ID, '_cielo_template_data', true );
				wp_localize_script( 'cielo-react-builder', 'cieloTemplateData', array( 'fields' => $existing_data ) );
			}
		}
	}
	new Cielo_Admin_Meta_Box();
}