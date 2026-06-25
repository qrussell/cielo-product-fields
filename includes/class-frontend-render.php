<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cielo_Frontend_Render {

	public function __construct() {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_fields' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_image_modal' ) );
	}

	public function render_fields() {
		global $product;
		if ( ! $product ) return;

		$fields_data = $this->get_merged_fields( $product->get_id() );
		if ( empty( $fields_data['fields'] ) ) return;

		$fields = $fields_data['fields'];
		$display_mode = $fields_data['display_mode']; // 'flat' or 'sectioned'

		echo '<div class="cielo-product-fields-wrapper" style="margin-bottom: 25px;">';
		
		$current_origin = '';
		$current_user_roles = is_user_logged_in() ? wp_get_current_user()->roles : array('guest');

		foreach ( $fields as $field ) {
			// Role Restriction Check (Phase 7)
			if ( ! empty( $field['allowedRoles'] ) ) {
				$allowed = array_map('trim', explode(',', strtolower($field['allowedRoles'])));
				if ( ! array_intersect( $allowed, $current_user_roles ) ) continue; // Skip rendering
			}

			// Section Headers (Phase 6 Layout Engine)
			if ( $display_mode === 'sectioned' && $field['origin_name'] !== $current_origin ) {
				$current_origin = $field['origin_name'];
				echo '<h4 class="cielo-section-title" style="margin-top:20px; border-bottom:1px solid #eee; padding-bottom:5px;">' . esc_html( $current_origin ) . '</h4>';
			}

			$this->render_single_field( $field );
		}

		// The Live Price Preview Box
		echo '<div id="cielo-live-price-preview" style="font-size: 1.1em; font-weight: bold; margin-top: 15px; padding: 15px; background: #f8fafc; display: none; border-radius: 4px;">';
		echo 'Options Total: <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span><span class="val">0.00</span></bdi></span>';
		echo '<div id="cielo-per-order-notice" style="font-size:0.8em; color:#64748b; font-weight:normal; display:none;"></div>';
		echo '</div>';

		echo '</div>';
	}

	private function get_merged_fields( $product_id ) {
		$final_render_array = array();
		$tracked_keys = array();
		$display_mode = 'flat'; // default

		$terms = wp_get_post_terms( $product_id, 'product_cat' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) return array('fields' => array(), 'display_mode' => 'flat');

		// Sort by depth for hierarchical merging
		usort( $terms, function( $a, $b ) {
			return count( get_ancestors( $b->term_id, 'product_cat' ) ) - count( get_ancestors( $a->term_id, 'product_cat' ) );
		});

		foreach ( $terms as $term ) {
			$query = new WP_Query( array(
				'post_type' => 'cielo_product_fields',
				'posts_per_page' => -1,
				'tax_query' => array( array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id) ),
			));

			if ( $query->have_posts() ) {
				foreach ( $query->posts as $template ) {
					// Capture the display mode of the highest priority template
					if ( empty($final_render_array) ) {
						$display_mode = get_post_meta( $template->ID, '_cielo_display_mode', true ) ?: 'flat';
					}

					$template_fields = json_decode( get_post_meta( $template->ID, '_cielo_template_data', true ), true );
					if ( is_array( $template_fields ) ) {
						foreach ( $template_fields as $field ) {
							if ( ! in_array( $field['key'], $tracked_keys ) ) {
								$field['origin_name'] = $template->post_title; // Track origin for Sectioned UI
								$final_render_array[] = $field;
								$tracked_keys[] = $field['key'];
							}
						}
					}
				}
			}
			wp_reset_postdata();
		}

		return array('fields' => $final_render_array, 'display_mode' => $display_mode);
	}

	private function render_single_field( $field ) {
		$key = esc_attr( $field['key'] );
		$price = isset( $field['priceModifier'] ) ? (float) $field['priceModifier'] : 0;
		$fee_type = isset( $field['feeType'] ) ? esc_attr( $field['feeType'] ) : 'per_item';
		
		// Setup Conditional Logic Rules (Phase 7)
		$rules_attr = '';
		$wrapper_style = 'margin-bottom: 15px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;';
		if ( ! empty( $field['conditionTarget'] ) && ! empty( $field['conditionValue'] ) ) {
			$rules = array( 'target' => $field['conditionTarget'], 'value' => $field['conditionValue'] );
			$rules_attr = "data-rules='" . esc_attr( wp_json_encode( $rules ) ) . "'";
			$wrapper_style .= ' display:none;'; // Hidden by default, JS will reveal
		}

		// UI Labels for Fees
		$price_label = '';
		if ( $price != 0 ) {
			$sign = $price > 0 ? '+' : '';
			$fee_text = ( $fee_type === 'per_order' ) ? ' Flat Fee' : '';
			$price_label = ' <span style="color:#16a34a; font-size: 0.9em;">(' . $sign . wc_price( $price ) . $fee_text . ')</span>';
		}

		echo '<div class="cielo-field-wrapper" id="wrapper_cielo_' . $key . '" style="' . $wrapper_style . '" ' . $rules_attr . '>';
		echo '<label for="cielo_' . $key . '" style="font-weight:600; display:block; margin-bottom:5px;">' . esc_html( $field['label'] ) . $price_label . '</label>';

		$data_attrs = 'data-price="' . $price . '" data-feetype="' . $fee_type . '"';

		switch ( $field['type'] ) {
			case 'text':
				echo '<input type="text" name="cielo_fields[' . $key . ']" id="cielo_' . $key . '" class="input-text cielo-input" ' . $data_attrs . ' style="width:100%;" />';
				break;
			case 'file':
				echo '<input type="file" name="cielo_fields[' . $key . ']" id="cielo_' . $key . '" class="cielo-input cielo-file-input" ' . $data_attrs . ' />';
				echo '<div class="cielo-file-preview" style="margin-top:10px;"></div>'; // Mount point for JS thumbnails
				echo '<script>document.addEventListener("DOMContentLoaded", function() { var f = document.querySelector("form.cart"); if(f) f.setAttribute("enctype", "multipart/form-data"); });</script>';
				break;
			case 'select':
				echo '<select name="cielo_fields[' . $key . ']" id="cielo_' . $key . '" class="cielo-input" ' . $data_attrs . ' style="width:100%;">';
				echo '<option value="">Select...</option>';
				if ( ! empty( $field['options'] ) ) {
					foreach ( explode( ',', $field['options'] ) as $opt ) {
						echo '<option value="' . esc_attr( trim($opt) ) . '">' . esc_html( trim($opt) ) . '</option>';
					}
				}
				echo '</select>';
				break;
			case 'radio':
				if ( ! empty( $field['options'] ) ) {
					foreach ( explode( ',', $field['options'] ) as $opt ) {
						echo '<label style="display:inline-block; margin-right: 15px; font-weight:normal;">';
						echo '<input type="radio" name="cielo_fields[' . $key . ']" value="' . esc_attr( trim($opt) ) . '" class="cielo-input" ' . $data_attrs . ' style="margin-right:5px;" />';
						echo esc_html( trim($opt) ) . '</label>';
					}
				}
				break;
		}
		echo '</div>';
	}

	public function render_image_modal() {
		// Phase 6 UX: The Full-Size Image Preview Modal (Hidden by default)
		if ( ! is_product() ) return;
		echo '<div id="cielo-image-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center;">';
		echo '<span id="cielo-modal-close" style="position:absolute; top:20px; right:30px; color:white; font-size:40px; font-weight:bold; cursor:pointer;">&times;</span>';
		echo '<img id="cielo-modal-img" src="" style="max-width:90%; max-height:90%; border-radius:8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">';
		echo '</div>';
	}

	public function enqueue_assets() {
		if ( ! is_product() ) return;
		// Use our updated JS file that handles conditionals and pricing together
		wp_enqueue_script( 'cielo-frontend-fields', plugin_dir_url( __DIR__ ) . 'assets/js/frontend-fields.js', array(), '2.0.0', true );
	}
}
new Cielo_Frontend_Render();