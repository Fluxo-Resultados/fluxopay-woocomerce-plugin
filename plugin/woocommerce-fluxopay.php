<?php
/**
 * Plugin Name:          FluxoPay
 * Plugin URI:           https://www.fluxoresultados.com.br/
 * Description:          Com a FluxoPay você emite boletos e links de pagamento para milhares de clientes de forma simples e automática.
 * Author:               FluxoPay
 * Version:              1.1.1
 * License:              GPLv3 or later
 * Text Domain:          woo-fluxopay
 * Domain Path:          /languages
 * WC requires at least: 4.0.0
 * WC tested up to:      4.2.2
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_FLUXOPAY_VERSION', '1.1.1' );
define( 'WC_FLUXOPAY_PLUGIN_FILE', __FILE__ );

if ( ! class_exists( 'WC_FluxoPay' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-wc-fluxopay.php';
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    add_action( 'plugins_loaded', array( 'WC_FluxoPay', 'init' ) );
}
