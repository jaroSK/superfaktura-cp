<?php

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

// Add custom data to Woocommerce Cart Items API

function sfapi_cp_cart_items_custom_attributes() {
	woocommerce_store_api_register_endpoint_data(
		array(
			'endpoint' => CartItemSchema::IDENTIFIER,
			'namespace' => 'sfapi_cp',
			'data_callback' => 'sfapi_cp_data_callback',
			'schema_type' => ARRAY_A
		)
	);
}
add_action('woocommerce_blocks_loaded', 'sfapi_cp_cart_items_custom_attributes');

function sfapi_cp_data_callback($cart_item) {
	$product = $cart_item['data'];
	
	$zobrazit_bal = get_post_meta($product->get_id(), 'zobrazit_bal', true);
	$zobrazit_bm = get_post_meta($product->get_id(), 'zobrazit_bm', true);
	$unit = '';
	if ($zobrazit_bal === 'yes') {
		$unit = 'bal';
	} elseif ($zobrazit_bm === 'yes') {
		$unit = 'bm';
	} else {
		$unit = 'ks';
	}
	
	return array(
		'unit' => $unit
	);
}