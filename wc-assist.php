<?php
/*
  Plugin Name: Assist Payment Gateway
  Plugin URI:
  Description: Allows you to use Assist payment gateway with the WooCommerce plugin.
  Version: 0.1
 */

//TODO: Выбор платежной системы на стороне магазина

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly


//function assist_rub_currency_symbol( $currency_symbol, $currency ) {
//    if($currency == "RUB") {
//        $currency_symbol = 'р.';
//    }
//    return $currency_symbol;
//}

function assist_rub_currency($currencies)
{
//    $currencies["RUB"] = 'Russian Roubles';
    $currencies["BYN"] = 'BYN Roubles';
    return $currencies;
}

add_filter('woocommerce_currencies', 'assist_rub_currency', 10, 1);
//add_filter( 'woocommerce_currency_symbol', 'assist_rub_currency_symbol', 10, 2 );


add_action('plugins_loaded', 'woocommerce_assist', 0);
function woocommerce_assist()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // if the WC payment gateway class is not available, do nothing

    if (class_exists('WCAssist')) {
        return;
    }

    /**
     * Add the gateway to WooCommerce
     **/
    add_filter('woocommerce_payment_gateways', function ($methods) {
        require_once __DIR__ . '/WCAssist.php';
        $methods[] = 'WCAssist';
        return $methods;
    });
}
