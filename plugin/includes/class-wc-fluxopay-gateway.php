<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_FluxoPay_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'fluxopay';
        $this->icon = apply_filters('woocommerce_fluxopay_icon', plugins_url('assets/images/fluxopay.png', plugin_dir_path(__FILE__)));
        $this->method_title = __('FluxoPay', 'woo-fluxopay');
        $this->method_description = __('Aceite pagamentos por boleto e cartões de crédito e débito pela FluxoPay.', 'woo-fluxopay');
        $this->order_button_text = __('Finalizar', 'woo-fluxopay');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->token = $this->get_option('token');
        $this->method = $this->get_option('method', 'direct');
        $this->tc_card = $this->get_option('tc_card', 'no');
        $this->tc_installments = $this->get_option('tc_installments');
        $this->tc_ticket = $this->get_option('tc_ticket', 'no');
        $this->sandbox = $this->get_option('sandbox', 'no');
        $this->debug = $this->get_option('debug');

        if ('yes' === $this->debug) {
            if (function_exists('wc_get_logger')) {
                $this->log = wc_get_logger();
            } else {
                $this->log = new WC_Logger();
            }
        }

        $this->api = new WC_FluxoPay_API($this);

        add_action('valid_fluxopay_ipn_request', array($this, 'update_order_status'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_after_order_table', array($this, 'email_instructions'), 10, 3);
        add_action('wp_enqueue_scripts', array($this, 'checkout_scripts'));
    }

    public function using_supported_currency()
    {
        return 'BRL' === get_woocommerce_currency();
    }

    public function GetAPIKEY()
    {
        return 'yes' === $this->token;
    }

    public function IsNullOrEmptyString($str)
    {
        return (!isset($str) || trim($str) === '');
    }

    public function IsAvailable()
    {
        return 'yes' === $this->get_option('enabled') && $this->using_supported_currency();
    }

    public function checkout_scripts()
    {
        if ($this->IsAvailable()) {
            if (!get_query_var('order-received')) {
                wp_enqueue_style('fluxopay-checkout', plugins_url('assets/css/frontend/transparent-checkout.css', plugin_dir_path(__FILE__)), array(), WC_FLUXOPAY_VERSION);
                wp_enqueue_script('fluxopay-checkout', plugins_url('assets/js/frontend/transparent-checkout.js', plugin_dir_path(__FILE__)), array('jquery', 'fluxopay-library'), WC_FLUXOPAY_VERSION, true);

                wp_enqueue_script('fluxopay-library', $this->api->get_direct_payment_url(), array(), WC_FLUXOPAY_VERSION, true);

                wp_localize_script(
                    'fluxopay-checkout',
                    'wc_fluxopay_params',
                    array(
                        'interest_free' => __('interest free', 'woo-fluxopay'),
                        'invalid_card' => __('Número de cartão inválido.', 'woo-fluxopay'),
                        'invalid_expiry' => __('Data de expiração inválida, use o formato MM / AAAA.', 'woo-fluxopay'),
                        'expired_date' => __('Por favor, preencha a data no formato MM / AAAA.', 'woo-fluxopay'),
                        'general_error' => __('Não foi possível processar sua compra com os dados fornecidos, entre em contato para maiores informações.', 'woo-fluxopay'),
                    )
                );
            }
        }
    }

    protected function get_log_view()
    {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.2', '>=')) {
            return '<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' . esc_attr($this->id) . '-' . sanitize_file_name(wp_hash($this->id)) . '.log')) . '">' . __('System Status &gt; Logs', 'woo-fluxopay') . '</a>';
        }

        return '<code>woocommerce/logs/' . esc_attr($this->id) . '-' . sanitize_file_name(wp_hash($this->id)) . '.txt</code>';
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Ativar/Desativar', 'woo-fluxopay'),
                'type' => 'checkbox',
                'label' => __('Ativar FluxoPay', 'woo-fluxopay'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Título', 'woo-fluxopay'),
                'type' => 'text',
                'description' => __('Título do método de pagamento', 'woo-fluxopay'),
                'desc_tip' => true,
                'default' => __('FluxoPay', 'woo-fluxopay'),
            ),
            'description' => array(
                'title' => __('Descrição do método de pagamento', 'woo-fluxopay'),
                'type' => 'textarea',
                'description' => __('Descrição do método de pagamento durante o checkout.', 'woo-fluxopay'),
                'default' => __('Pagar via FluxoPay', 'woo-fluxopay'),
            ),
            'integration' => array(
                'title' => __('Integração', 'woo-fluxopay'),
                'type' => 'title',
                'description' => '',
            ),
            'method' => array(
                'title' => __('Método de integração', 'woo-fluxopay'),
                'type' => 'select',
                'description' => __('Choose how the customer will interact with the FluxoPay. Redirect (Client goes to FluxoPay page) or Lightbox (Inside your store)', 'woo-fluxopay'),
                'desc_tip' => true,
                'default' => 'direct',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'transparent' => __('Transparent Checkout', 'woo-fluxopay'),
                ),
            ),
            'sandbox' => array(
                'title' => __('FluxoPay Sandbox', 'woo-fluxopay'),
                'type' => 'checkbox',
                'label' => __('Ativar/Desativar FluxoPay Sandbox', 'woo-fluxopay'),
                'desc_tip' => true,
                'default' => 'no',
                'description' => __('FluxoPay Sandbox pode ser utilizado para testes de pagamento.', 'woo-fluxopay'),
            ),
            'token' => array(
                'title' => __('FluxoPay Token', 'woo-fluxopay'),
                'type' => 'text',
                /* translators: %s: link to FluxoPay settings */
                'description' => sprintf(__('Insira seu Token aqui. Isso é necessário para processar os pagamentos.', 'woo-fluxopay'), '<a href="https://developers.fluxoresultados.com.br/">' . __('here', 'woo-fluxopay') . '</a>'),
                'default' => '',
            ),
            'transparent_checkout' => array(
                'title' => __('Opções de pagamento', 'woo-fluxopay'),
                'type' => 'title',
                'description' => '',
            ),
            'tc_ticket' => array(
                'title' => __('Boleto Bancário e Pix', 'woo-fluxopay'),
                'type' => 'checkbox',
                'label' => __('Boleto Bancário e Pix', 'woo-fluxopay'),
                'default' => 'yes',
            ),
            'tc_card' => array(
                'title' => __('Cartão de Crédito e Débito', 'woo-fluxopay'),
                'type' => 'checkbox',
                'label' => __('Cartão de crédito e Débito', 'woo-fluxopay'),
                'default' => 'no',
            ),
            'tc_installments' => array(
                'title' => __('Número máximo de parcelas', 'woo-fluxopay'),
                'type' => 'select',
                'label' => __('Número máximo de parcelas.', 'woo-fluxopay'),
                'default' => 1,
                'options' => array(
                    1 => 'Sem parcelamento',
                )
            ),
            'behavior' => array(
                'title' => __('Integração', 'woo-fluxopay'),
                'type' => 'title',
                'description' => '',
            ),
            'debug' => array(
                'title' => __('Log de Debug', 'woo-fluxopay'),
                'type' => 'checkbox',
                'label' => __('Habilitar Log', 'woo-fluxopay'),
                'default' => 'no',
                /* translators: %s: log page link */
                'description' => sprintf(__('Log para chamadas na API da FluxoPay, dentro de %s', 'woo-fluxopay'), $this->get_log_view()),
            ),
        );
    }

    public function admin_options()
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_script('fluxopay-admin', plugins_url('assets/js/admin/admin' . $suffix . '.js', plugin_dir_path(__FILE__)), array('jquery'), WC_FLUXOPAY_VERSION, true);

        include dirname(__FILE__) . '/admin/views/html-admin-page.php';
    }

    protected function send_email($subject, $title, $message)
    {
        $mailer = WC()->mailer();
        $mailer->send(get_option('admin_email'), $subject, $mailer->wrap_message($title, $message));
    }

    public function payment_fields()
    {
        wp_enqueue_script('wc-card-form');

        $description = $this->get_description();

        if ($description) {
            echo wpautop(wptexturize($description));
        }

        $cart_total = $this->get_order_total();

        wc_get_template(
            'checkout-form.php',
            array(
                'cart_total' => $cart_total,
                'tc_card' => $this->tc_card,
                'tc_installments' => $this->tc_installments,
                'tc_ticket' => $this->tc_ticket,

                'flag' => plugins_url('assets/images/brazilian-flag.png', plugin_dir_path(__FILE__)),
            ),
            'woocommerce/fluxopay/', WC_FluxoPay::get_templates_path()
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ('transparent' === $this->method) {

            $response = $this->api->PaymentController($order, $_POST);

            if ($response['data']) {
                $this->update_order_status($response['data'], $order_id);
            }

            if ($response['url']) {
                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $response['url'],
                );
            } else {
                wc_add_notice($response['error']);

                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
            }
        } else {
            $use_shipping = isset($_POST['ship_to_different_address']);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg(array('use_shipping' => $use_shipping), $order->get_checkout_payment_url(true)),
            );
        }
    }

    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);
        $request_data = $_POST;

        if (isset($_GET['use_shipping']) && true === (bool)$_GET['use_shipping']) {
            $request_data['ship_to_different_address'] = true;
        }

        $response = $this->api->CheckoutController($order, $request_data);

        if ($response['url']) {
            wc_enqueue_js(
                '
				$( "#browser-has-javascript" ).show();
				$( "#browser-no-has-javascript, #cancel-payment, #submit-payment" ).hide();
				var isOpenLightbox = FluxoPayLightbox({
						code: "' . esc_js($response['token']) . '"
					}, {
						success: function ( transactionCode ) {
							window.location.href = "' . str_replace('&amp;', '&', esc_js($this->get_return_url($order))) . '";
						},
						abort: function () {
							window.location.href = "' . str_replace('&amp;', '&', esc_js($order->get_cancel_order_url())) . '";
						}
				});
				if ( ! isOpenLightbox ) {
					window.location.href = "' . esc_js($response['url']) . '";
				}
			'
            );

            wc_get_template(
                'lightbox-checkout.php',
                array(
                    'cancel_order_url' => $order->get_cancel_order_url(),
                    'payment_url' => $response['url'],
                    'lightbox_script_url' => '',
                ),
                'woocommerce/fluxopay/',
                WC_FluxoPay::get_templates_path()
            );
        } else {
            include dirname(__FILE__) . '/views/html-receipt-page-error.php';
        }
    }

    protected function SavePaymentData($order, $posted)
    {
        $data = $order->get_data();

        if (isset($posted->invoice_url)) {
            $invoice_url = $posted->invoice_url;
        } else {
            $invoice_url = 'https://app.fluxoresultados.com.br/checkout/' . $posted->id;
        };

        $meta_data = array();
        $payment_data = array(
            'paymenttype' => '',
            'description' => '',
            'method' => '',
            'installments' => '',
            'link' => '',
            'walletaddress' => '',
            'amount' => '',
            'symbol' => ''
        );

        if ($posted->id != null) {
            $meta_data[__('Código da transação', 'woo-fluxopay')] = sanitize_text_field((string)$posted->id);
        }

        if ($posted->message != null) {
            $meta_data[__('Status', 'woo-fluxopay')] = sanitize_text_field((string)$posted->message);
        }

        if ($data['billing']['email'] != null) {
            $meta_data[__('Email', 'woo-fluxopay')] = sanitize_text_field($data['billing']['email']);
        }

        if ($data['billing']['first_name'] != null) {
            $meta_data[__('Nome', 'woo-fluxopay')] = sanitize_text_field($data['billing']['first_name'] . ' ' . $data['billing']['last_name']);
        }

        $method = sanitize_text_field($_POST['fluxopay_payment_method']);

        $payment_data['paymenttype'] = $method;
        $meta_data[__('', 'woo-fluxopay')] = sanitize_text_field((string)$invoice_url);
        $payment_data['link'] = sanitize_text_field((string)$invoice_url);

        $meta_data['_wc_fluxopay_payment_data'] = $payment_data;

        if (method_exists($order, 'update_meta_data')) {
            foreach ($meta_data as $key => $value) {
                $order->update_meta_data($key, $value);
            }

            $order->save();
        } else {
            foreach ($meta_data as $key => $value) {
                update_post_meta($order->id, $key, $value);
            }
        }
    }

    public function update_order_status($posted, $order_id)
    {
        if (isset($posted->event)) {
            $event = $posted->event;
        } else {
            $event = $posted;
        };

        if (isset($event->id)) {
            $id = $event->id;

            $order = wc_get_order($order_id);

            if (!$order) {
                return;
            }

            if (isset($id)) {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'FluxoPay payment status for order ' . $order->get_order_number() . ' is: ' . intval($event->status));
                }

                $this->SavePaymentData($order, $event);

                switch ($event->status) {
                    case 'pending':
                        $order->update_status('on-hold', __('FluxoPay: Processamento.', 'woo-fluxopay'));

                        if (function_exists('wc_reduce_stock_levels')) {
                            wc_reduce_stock_levels($order_id);
                        }

                        $order->add_order_note(__('FluxoPay: Pagamento em processamento..', 'woo-fluxopay'));

                        break;
                    case 'payed':
                        if (method_exists($order, 'get_status') && 'cancelled' === $order->get_status()) {
                            $order->update_status('processing', __('FluxoPay: Payment approved.', 'woo-fluxopay'));
                            wc_reduce_stock_levels($order_id);
                        } else {
                            $order->add_order_note(__('FluxoPay: Autorizado.', 'woo-fluxopay'));
                            $order->payment_complete(sanitize_text_field((string)$event->id));
                        }

                        break;
                    case 'canceled':
                        $order->update_status('refunded', __('FluxoPay: Devolvido.', 'woo-fluxopay'));
                        $this->send_email(
                        /* translators: %s: order number */
                            sprintf(__('Payment for order %s refunded', 'woo-fluxopay'), $order->get_order_number()),
                            __('Payment refunded', 'woo-fluxopay'),
                            /* translators: %s: order number */
                            sprintf(__('Order %s has been marked as refunded by FluxoPay.', 'woo-fluxopay'), $order->get_order_number())
                        );

                        if (function_exists('wc_increase_stock_levels')) {
                            wc_increase_stock_levels($order_id);
                        }

                        break;
                    default:
                        break;
                }
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Error: Order Key does not match with FluxoPay reference.');
                }
            }
        }
    }

    public function thankyou_page($order_id)
    {
        $order            = new WC_Order( $order_id );

        $data = is_callable([$order, 'get_meta'])
            ? $order->get_meta('_wc_fluxopay_payment_data')
            : get_post_meta($order_id, '_wc_fluxopay_payment_data', true);

        wc_get_template('payment-instructions.php',
        [
            'paymenttype' => $data['paymenttype'],
            'link' => $data['link']
        ], 'woocommerce/fluxopay/', WC_FluxoPay::get_templates_path());
    }

    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if (method_exists($order, 'get_meta')) {
            if ($sent_to_admin || 'on-hold' !== $order->get_status() || $this->id !== $order->get_payment_method()) {
                return;
            }

            $data = $order->get_meta('_wc_fluxopay_payment_data');
        } else {
            if ($sent_to_admin || 'on-hold' !== $order->status || $this->id !== $order->payment_method) {
                return;
            }

            $data = get_post_meta($order->get_id(), '_wc_fluxopay_payment_data', true);
        }

        if ($plain_text) {
            wc_get_template(
                'emails/plain-instructions.php',
                array(
                    'paymenttype' => $data['paymenttype'],
                    'installments' => $data['installments'],
                    'method' => $data['method'],
                    'link' => $data['link'],
                    'walletaddress' => $data['walletaddress'],
                    'amount' => $data['amount'],
                    'symbol' => $data['symbol']
                ),
                'woocommerce/fluxopay/',
                WC_FluxoPay::get_templates_path()
            );
        } else {
            wc_get_template(
                'emails/html-instructions.php',
                array(
                    'paymenttype' => $data['paymenttype'],
                    'installments' => $data['installments'],
                    'method' => $data['method'],
                    'link' => $data['link'],
                    'walletaddress' => $data['walletaddress'],
                    'amount' => $data['amount'],
                    'symbol' => $data['symbol']
                ),
                'woocommerce/fluxopay/',
                WC_FluxoPay::get_templates_path()
            );
        }
    }
}
