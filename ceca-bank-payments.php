<?php

/**
 * @link              https://mildai.com
 * @since             1.0.0
 * @package           CECA_Bank_Payment_Gateway
 *
 * @wordpress-plugin
 * Plugin Name:       CECA Bank Payment Gateway
 * Plugin URI:        https://mildai.com/ceca-bank-payments/
 * Description:       CECA Bank Payment Gateway for Woocommerce.
 * Version:           1.0.0
 * Author:            Mildai Beauty Solutions
 * Author URI:        https://mildai.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ceca-bank-payments
 * Domain Path:       /languages
 * WC tested up to: 4.7
 * WC requires at least: 4.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'CECA_BANK_PAYMENTS_VERSION', '1.0.0' );
define( 'PLUGIN_NAME', 'ceca_bank_payments' );

add_action( 'init', 'init_cbp' );
add_action( 'plugins_loaded', 'load_cbp' );

function init_cbp() {
	load_plugin_textdomain( "ceca-bank-payments", false, dirname( plugin_basename( __FILE__ ) . '/languages/' ));
}

function load_cbp() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) {
		exit;
	}

	require_once ('includes/class-wc-gateway-cbp.php');

	add_filter( 'woocommerce_payment_gateways', 'add_payment_wc_cbp' );
}

function add_payment_wc_cbp($methods) {
	$methods[] = 'WC_Gateway_CBP';
	return $methods;
}
