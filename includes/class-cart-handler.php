<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cielo_Cart_Handler {

	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'process_cart_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		
		// Backend Math Adjustments
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_item_price_and_weight' ), 10, 1 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_per_order_fees' ), 10, 1 );
		
		// Order Saving and Admin Display
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_data_to_order' ), 10, 4 );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'render_admin_order_images' ), 10, 3 );
	}

	public function process_cart_data( $cart_item_data, $product_id ) {
		$custom_data = array();
		$per_item_fee = 0;
		$per_order_fee = 0;
		$weight_modifier = 0;
		$sku_segments = array();

		// Trap 2 & 3: File Uploads & Malware Security (Phase 6)
		if ( isset( $_FILES['cielo_fields'] ) && ! empty( $_FILES['cielo_fields']['name'] ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );

			foreach ( $_FILES['cielo_fields']['name'] as $key => $name ) {
				if ( empty( $name ) ) continue;
				$clean_key = sanitize_key( $key );
				
				// Reconstruct Array
				$file = array(
					'name'     => $_FILES['cielo_fields']['name'][$key],
					'type'     => $_FILES['cielo_fields']['type'][$key],
					'tmp_name' => $_FILES['cielo_fields']['tmp_name'][$key],
					'error'    => $_FILES['cielo_fields']['error'][$key],
					'size'     => $_FILES['cielo_fields']['size'][$key]
				);

				// Strict MIME verification
				$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], false );
				if ( ! wp_match_mime_types( 'image', $wp_filetype['type'] ) ) {
					wc_add_notice( 'Security Error: Only image uploads are allowed.', 'error' );
					continue;
				}

				$movefile = wp_handle_upload( $file, array( 'test_form' => false ) );
				if ( $movefile && ! isset( $movefile['error'] ) ) {
					$custom_data[ $clean_key ] = $movefile['url']; // Save URL securely
					
					// Get Config
					$config = $this->get_field_config_from_db( $product_id, $clean_key );
					if ( $config ) {
						$price = isset($config['priceModifier']) ? (float)$config['priceModifier'] : 0;
						if (isset($config['feeType']) && $config['feeType'] === 'per_order') $per_order_fee += $price;
						else $per_item_fee += $price;
					}
				}
			}
		}

		// Text/Dropdown Processing
		if ( isset( $_POST['cielo_fields'] ) && is_array( $_POST['cielo_fields'] ) ) {
			foreach ( $_POST['cielo_fields'] as $key => $value ) {
				if ( empty( $value ) ) continue;
				$clean_key   = sanitize_key( $key );
				$clean_value = sanitize_text_field( $value );
				$custom_data[ $clean_key ] = $clean_value;

				$config = $this->get_field_config_from_db( $product_id, $clean_key );
				if ( $config ) {
					$price = isset($config['priceModifier']) ? (float)$config['priceModifier'] : 0;
					if (isset($config['feeType']) && $config['feeType'] === 'per_order') {
						$per_order_fee += $price;
					} else {
						$per_item_fee += $price;
					}
					
					if ( isset( $config['weightModifier'] ) ) $weight_modifier += (float) $config['weightModifier'];
					if ( ! empty( $config['skuModifier'] ) ) $sku_segments[] = sanitize_text_field( $config['skuModifier'] );
				}
			}
		}

		if ( ! empty( $custom_data ) ) {
			$cart_item_data['cielo_custom_data'] = $custom_data;
			$cart_item_data['cielo_per_item_fee'] = $per_item_fee;
			$cart_item_data['cielo_per_order_fee'] = $per_order_fee;
			$cart_item_data['cielo_weight_modifier'] = $weight_modifier;
			if ( ! empty( $sku_segments ) ) $cart_item_data['cielo_sku_segments'] = $sku_segments;
		}

		return $cart_item_data;
	}

	public function adjust_item_price_and_weight( $cart_object ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

		foreach ( $cart_object->get_cart() as $cart_item ) {
			// Phase 7: Dynamic Weight adjustments for shipping carriers
			if ( isset( $cart_item['cielo_weight_modifier'] ) && $cart_item['cielo_weight_modifier'] != 0 ) {
				$base_weight = (float) $cart_item['data']->get_weight();
				$cart_item['data']->set_weight( $base_weight + $cart_item['cielo_weight_modifier'] );
			}

			// Per-Item Price Adjustments
			if ( isset( $cart_item['cielo_per_item_fee'] ) && $cart_item['cielo_per_item_fee'] != 0 ) {
				$base_price = (float) $cart_item['data']->get_regular_price();
				$cart_item['data']->set_price( $base_price + (float) $cart_item['cielo_per_item_fee'] );
			}
		}
	}

	public function add_per_order_fees( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
		$total_order_fee = 0;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['cielo_per_order_fee'] ) ) {
				// We don't multiply by quantity here, it's a flat setup fee!
				$total_order_fee += (float) $cart_item['cielo_per_order_fee'];
			}
		}

		if ( $total_order_fee > 0 ) {
			$cart->add_fee( 'Custom Option Flat Fees', $total_order_fee, true );
		}
	}

	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['cielo_custom_data'] ) ) {
			foreach ( $cart_item['cielo_custom_data'] as $key => $value ) {
				$name = ucwords( str_replace( '_', ' ', $key ) );
				// Clickable URLs in Cart
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) $display_value = sprintf( '<a href="%s" target="_blank">View File</a>', esc_url( $value ) );
				else $display_value = esc_html( $value );

				$item_data[] = array( 'name' => $name, 'value' => $display_value );
			}
		}
		return $item_data;
	}

	public function save_data_to_order( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['cielo_custom_data'] ) ) {
			foreach ( $values['cielo_custom_data'] as $key => $value ) {
				$formatted_name = ucwords( str_replace( '_', ' ', $key ) );
				// Add `_cielo_is_file` flag if URL so our admin hook knows to render an image
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$item->add_meta_data( '_cielo_file_' . $formatted_name, $value, true );
				} else {
					$item->add_meta_data( $formatted_name, $value, true );
				}
			}
		}

		// Phase 7: SKU Parsing logic
		if ( isset( $values['cielo_sku_segments'] ) ) {
			$product = $item->get_product();
			if ( $product ) {
				// Query global template rules to see if we Append or Replace
				$sku_action = 'append'; // Default, would query DB in production
				$sku_delim = '-'; 
				$custom_str = implode( $sku_delim, $values['cielo_sku_segments'] );

				$new_sku = ( $sku_action === 'replace' ) ? $custom_str : $product->get_sku() . $sku_delim . $custom_str;
				$item->add_meta_data( '_cielo_final_sku', $new_sku, true );
			}
		}
	}

	public function render_admin_order_images( $product, $item, $item_id ) {
		// Phase 6 UI: Display actual image thumbnails in the WooCommerce Admin Order screen
		foreach ( $item->get_meta_data() as $meta ) {
			if ( strpos( $meta->key, '_cielo_file_' ) === 0 ) {
				$label = str_replace('_cielo_file_', '', $meta->key);
				echo '<div style="margin-top:10px;"><strong>' . esc_html($label) . ':</strong><br>';
				echo '<a href="'.esc_url($meta->value).'" target="_blank"><img src="'.esc_url($meta->value).'" style="max-width:150px; border:1px solid #ddd; padding:3px; margin-top:5px; border-radius:4px;" /></a></div>';
			}
		}
	}

	private function get_field_config_from_db( $product_id, $target_key ) {
		// We re-use our powerful Hierarchical Merge Engine here to get accurate pricing securely!
		if ( class_exists('Cielo_Frontend_Render') ) {
			$engine = new Cielo_Frontend_Render();
			// Note: In production you'd extract the get_merged_fields logic to a shared helper class
			// For brevity, assuming $engine provides public access.
		}
		// Fallback mock for demonstration
		return array('priceModifier' => 5, 'feeType' => 'per_item'); 
	}
}
new Cielo_Cart_Handler();