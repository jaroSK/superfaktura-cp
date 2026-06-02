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
			'root_url' => get_site_url(),
			'ajax_url' => admin_url('admin-ajax.php')
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

// Create Cenová ponuka and return CP pdf url

function sfapi_create_cp() {
	DEFINE('SFAPI_EMAIL', get_option('woocommerce_sf_email'));		// LOGIN EMAIL TO SUPERFAKTURA
	DEFINE('SFAPI_KEY', get_option('woocommerce_sf_apikey'));		// SFAPI KEY
	DEFINE('SFAPI_MODULE', 'SUPERFAKTURA_CP');						// TITLE OF MODULE FE. 'WOOCOMMERCE MODULE'
	DEFINE('SFAPI_APPTITLE', 'SUPERFAKTURA_CP');					// TITLE OF YOUR APPLICATION FE. 'SUPERFAKTURA.SK'
	DEFINE('COMPANY_ID', get_option('woocommerce_sf_company_id'));	// COMPANY_ID (optional)
	DEFINE('USE_SANDBOX', get_option('woocommerce_sf_sandbox'));

	require_once plugin_dir_path(__FILE__) .'/vendor/superfaktura/apiclient/SFAPIclient/SFAPIclient.php';

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$cart_data = json_decode(file_get_contents('php://input'));
		$cart_items_data = $cart_data->cartItemsData;
		$discount_data = $cart_data->discountData;

		// Platnosť CP je 7 dní
		$datum = new DateTime();
		$datum->modify('+7 days');
		$datum_platnosti = $datum->format('Y-m-d');

		// Create and init SFAPIclient
		$api = new SFAPIclient(SFAPI_EMAIL, SFAPI_KEY, SFAPI_APPTITLE, SFAPI_MODULE, COMPANY_ID);
		if (USE_SANDBOX === 'yes') {
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
			$unit_price = ($cart_item->totals->line_subtotal / $cart_item->quantity) / (10 ** $cart_item->totals->currency_minor_unit);
			$description = $cart_item->wck_meta && $cart_item->woo_meta ? $cart_item->wck_meta ."\n". $cart_item->woo_meta : $cart_item->wck_meta . $cart_item->woo_meta;

			$api->addItem(array(
				'name' => str_replace('&#8211;', '-', $cart_item->name),
				'description' => $description,
				'quantity' => $cart_item->quantity,
				'unit' => $cart_item->extensions->sfapi_cp->unit,
				'unit_price' => $unit_price,
				'tax' => 23
			));
		}

		if (is_array($discount_data) && !empty($discount_data)) {
			$api->addItem(array(
				'name' => $discount_data[0]->name,
				'unit_price' => $discount_data[0]->totals->total / (10 ** $discount_data[0]->totals->currency_minor_unit),
				'tax' => 23
			));
		}

		// save invoice
		$response = $api->save();

		if ($response->error === 0) {
			// Cenová ponuka pdf url
			$sfapi_cp_pdf = $api->getPDF($response->data->Invoice->id, $response->data->Invoice->token);

			wp_send_json_success([
				'url' => $sfapi_cp_pdf->url
			]);
		} else {
			wp_send_json_error(['error_message' => $response->error_message]);
		}
	}
}
add_action('wp_ajax_sfapi_create_cp_action', 'sfapi_create_cp');
add_action('wp_ajax_nopriv_sfapi_create_cp_action','sfapi_create_cp');