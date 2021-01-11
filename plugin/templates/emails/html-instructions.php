<?php if (!defined('ABSPATH')) exit; ?>

    <h2><?php _e('Payment', 'woo-fluxopay'); ?></h2>


	<div class="order_details">
    <span>
		<a class="button" href="<?php echo esc_url($link); ?>" target="_blank">
			<?php _e('Visualizar Link de Pagamento', 'woo-fluxopay'); ?>
			<br/>
		</a>

		<?php _e('Clique no botão para visualizar o link para realizar o pagamento.
					Após a confirmação do pagamento, seu pedido será processado.', 'woo-fluxopay'); ?>
	</span>
    </div>
