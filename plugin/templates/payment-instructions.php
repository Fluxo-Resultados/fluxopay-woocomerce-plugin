<?php if (!defined('ABSPATH')) exit; ?>

<div class="woocommerce-message">
    <span>
        <a class="button" href="<?php echo esc_url($link); ?>" target="_blank">
            <?php esc_html_e('Visualizar link de pagamento', 'woo-fluxopay'); ?>
            <br/>
        </a>

        <?php esc_html_e('Clique no botão para visualizar o link para fazer o pagamento.
                Após a confirmação do pagamento, seu pedido será processado.', 'woo-fluxopay'); ?>
    </span>
</div>

