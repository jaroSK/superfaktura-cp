<?php

/*
	Plugin Name: SuperFaktúra API CP
	Description: Plugin vytvorí cenovú ponuku v SuperFaktúre.
	Author:      Jaroslav Kvantik
	Author URI:  https://jaroslavkvantik.eu
	Version:     1.0
	Text Domain: sfapi-cp
	Requires Plugins: woocommerce, woocommerce-superfaktura
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) .'/includes/woocommerce-api-functions.php';

// Load scripts

function sfapi_cp_load_scripts() {
	if (is_cart()) {
		wp_enqueue_script('sfapi_cp_scripts', plugin_dir_url(__FILE__) .'build/index.js', array(), '1.0', array('strategy' => 'defer'));
		wp_localize_script('sfapi_cp_scripts', 'sfapi_cp_data', array(
			'root_url' => get_site_url()
		));
	}
}
add_action('wp_enqueue_scripts', 'sfapi_cp_load_scripts');

// Stiahnuť CP button 

function sfapi_cp_stiahnut_cp_button() {
	if ((!is_cart() && !is_checkout()) || WC()->cart->is_empty()) {
		return;
	}
	?>

	<button type="button" class="stiahnut-cp-button" style="width: 100%;"><?php _e('Stiahnuť cenovú ponuku', 'sfapi-cp'); ?></button>

	<?php
}
add_action('woocommerce_proceed_to_checkout', 'sfapi_cp_stiahnut_cp_button', 21);

// superfaktura-cp REST route

function sfapi_register_rest_route() {
	register_rest_route('superfaktura-cp/v1', 'create', array(
		'methods' => WP_REST_Server::EDITABLE, // 'POST'
		'callback' => 'sfapi_create_cp',
		'permission_callback' => '__return_true'
	));
}
add_action('rest_api_init', 'sfapi_register_rest_route');

// Create Cenová ponuka and return CP pdf url

function sfapi_create_cp($request) {
	require_once plugin_dir_path(__FILE__) .'/vendor/superfaktura/apiclient/SFAPIclient/SFAPIclient.php';
	
	$data = $request->get_json_params();
	$cart_items_data = $data['cartItemsData'];
	$discount_data = $data['discountData'];

	// Platnosť CP je 7 dní
	$datum = new DateTime();
	$datum->modify('+7 days');
	$datum_platnosti = $datum->format('Y-m-d');

	// Sadzba DPH
	$tax_rates = WC_Tax::get_rates('');
	$tax_rate = array_shift($tax_rates);
	$sadzba_dph = $tax_rate['rate'];

	// Create and init SFAPIclient
	$api = new SFAPIclient(get_option('woocommerce_sf_email'), get_option('woocommerce_sf_apikey'), 'SUPERFAKTURA_CP', 'SUPERFAKTURA_CP', get_option('woocommerce_sf_company_id'));
	if (get_option('woocommerce_sf_sandbox') === 'yes') {
		$api->useSandBox();
	}

	// Setup client data
	$api->setClient(array(
		'name' => 'Adverti E-SHOP'
	));

	// Setup invoice data
	$api->setInvoice(array(
		'name' => 'Cenová ponuka',
		'type' => 'estimate', // Cenová ponuka je invoice type estimate
		'due' => $datum_platnosti
	));

	// add invoice item, this can be called multiple times
	// if you are not a VAT registered, use tax = 0
	foreach ($cart_items_data as $cart_item) {
		$unit_price = ($cart_item['totals']['line_subtotal'] / $cart_item['quantity']) / (10 ** $cart_item['totals']['currency_minor_unit']);
		$description = $cart_item['wck_meta'] && $cart_item['woo_meta'] ? $cart_item['wck_meta'] ."\n". $cart_item['woo_meta'] : $cart_item['wck_meta'] . $cart_item['woo_meta'];

		$api->addItem(array(
			'name' => str_replace('&#8211;', '-', $cart_item['name']),
			'description' => $description,
			'quantity' => $cart_item['quantity'],
			'unit' => $cart_item['extensions']['sfapi_cp']['unit'],
			'unit_price' => $unit_price,
			'tax' => $sadzba_dph
		));
	}

	if (is_array($discount_data) && !empty($discount_data)) {
		$api->addItem(array(
			'name' => $discount_data[0]->name,
			'unit_price' => $discount_data[0]->totals->total / (10 ** $discount_data[0]->totals->currency_minor_unit),
			'tax' => $sadzba_dph
		));
	}

	// save invoice
	$response = $api->save();

	if ($response->error === 0) {
		// Cenová ponuka pdf url
		$sfapi_cp_pdf = $api->getPDF($response->data->Invoice->id, $response->data->Invoice->token);
		$data = ['success' => true, 'url' => $sfapi_cp_pdf->url];
	} else {
		$data = ['success' => false, 'error_message' => $response->error_message];
	}

	return rest_ensure_response($data);
}