<?php
/**
 * Admin View: Notice - Currency not supported.
 *
 * @package WooCommerce_Fluxopay/Admin/Notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="error inline">
	<p><strong><?php _e( 'FluxoPay Disabled', 'woo-fluxopay' ); ?></strong>: <?php printf( __( 'Currency <code>%s</code> is not supported. Works only with Brazilian Real.', 'woo-fluxopay' ), get_woocommerce_currency() ); ?>
	</p>
</div>
