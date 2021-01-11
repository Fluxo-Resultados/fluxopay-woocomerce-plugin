<?php

if (!defined('ABSPATH')) exit;

$order_total = esc_attr(number_format($cart_total, 2, '.', ''));
$installments = isset($tc_installments) ? $tc_installments : 12;

?>

<fieldset id="fluxopauy-payment-form">

    <ul id="fluxopay-payment-methods" style="margin-bottom: 5%;">
        <?php if ($tc_ticket == 'yes') : ?>
            <li><label>
                    <input id="fluxopay-payment-method-boleto" type="radio" name="fluxopay_payment_method"
                           value="boleto" <?php checked(true, ('no' == $tc_card && 'yes' == $tc_ticket), true); ?> />
                    <?php _e('Boleto ou Pix', 'woo-fluxopay'); ?>

                </label></li>
        <?php endif; ?>

        <?php if ($tc_card == 'yes') : ?>
            <li><label>
                    <input id="fluxopay-payment-method-card" type="radio" name="fluxopay_payment_method"
                           value="card" <?php checked(true, ('yes' == $tc_card), true); ?> />
                    <?php _e('Cartão de Crédito ou Débito', 'woo-fluxopay'); ?>
                </label></li>
        <?php endif; ?>

    </ul>

    <div class="clear"></div>

    <?php if ('yes' == $tc_ticket) : ?>
        <div id="fluxopay-boleto-form" class="fluxopay-method-form">
            <p>
                <i id="fluxopay-icon-ticket"></i>
                <?php _e('Realize o pagamento através de boleto bancário ou pix.', 'woo-fluxopay'); ?>
            </p>

            <div class="clear"></div>

        </div>
    <?php endif; ?>

    <div class="clear"></div>

    <?php if ('yes' == $tc_card) : ?>

        <div id="fluxopay-card-form" class="fluxopay-method-form">
            <p>
                <i id="fluxopay-icon-card"></i>
                <?php _e('Realize o pagamento através do seu cartão de crédito ou débito.', 'woo-fluxopay'); ?>
            </p>

            <div class="clear"></div>

        </div>

    <?php endif; ?>

    <div class="clear"></div>

    <?php if (!is_plugin_active('woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php'))  : ?>
        <?php if (!is_plugin_active('woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php'))  : ?>

            <p id="fluxopay-identity-field" class="form-row form-row-first" style="margin-bottom: 4%;">
                <label for="fluxopay-card-cvc">CPF/CNPJ do titular <span class="required">*</span></label>
                <input onkeypress="MaskcpfCnpj(this)" id="fluxopay-customer-identity" name="customer_identity" type="tel"
                       autocomplete="off" maxlength="18" style="font-size: 1.5em; padding: 8px;width: 100%; heigth: 100%;"/>
            </p>

        <?php endif; ?>
    <?php endif; ?>

</fieldset>