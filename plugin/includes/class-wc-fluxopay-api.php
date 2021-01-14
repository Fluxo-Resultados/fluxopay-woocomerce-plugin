<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_FluxoPay_API
{
    protected $gateway;

    public function __construct($gateway = null)
    {
        $this->gateway = $gateway;
    }

    protected function HttpClient($url, $method = 'POST', $data = array(), $headers = array())
    {
        $params = array(
            'method' => $method,
            'timeout' => 60,
        );

        if ('POST' == $method && !empty($data)) {
            $params['body'] = $data;
        }

        if (!empty($headers)) {
            $params['headers'] = $headers;
        }

        return wp_safe_remote_post($url, $params);
    }

    protected function GetPaymentURI()
    {
        return 'https://api.app.fluxoresultados.com.br/v1/public/charge';
    }

    public function get_callback_uri($orderId)
    {
        return get_home_url() . "/" . 'wp-json/fluxopay/v1/callback/' . $orderId;
    }

    public function GetPaymentMethod($method)
    {
        $methods = [
            'boleto' => 'boleto',
            'card' => 'card'
        ];

        return isset($methods[$method]) ? $methods[$method] : '';
    }

    protected function GetAvailablePaymentMethods()
    {
        $methods = array();
        $methods[] = 'boleto';
        $methods[] = 'card';

        return $methods;
    }

    protected function get_payload($order, $posted, $IsSandbox)
    {
        $method = isset($posted['fluxopay_payment_method']) ? $this->GetPaymentMethod($posted['fluxopay_payment_method']) : '';
        $woo = new WooCommerce();
        $paymentMethod = $method;

        $description = "Pedido #" . $order->get_id();

        $identity = null;

        if (!empty($posted['billing_cpf'])) {
            $identity = sanitize_text_field($posted['billing_cpf']);
        } else if (!empty($posted['billing_cnpj'])) {
            $identity = sanitize_text_field();
        } else if (!empty($posted['customer_identity'])) {
            $identity = sanitize_text_field($posted['customer_identity']);
        };

        if (isset($this->gateway->send_email)) {
            if ($this->gateway->send_email === "yes") {
                $send_email = true;
            } else {
                $send_email = false;
            }
        } else {
            $send_email = true;
        };

        if (isset($this->gateway->fee_payed_by_customer)) {
            if ($this->gateway->fee_payed_by_customer === "yes") {
                $fee_payed_by_customer = true;
            } else {
                $fee_payed_by_customer = false;
            }
        } else {
            $fee_payed_by_customer = true;
        };


        $payload = [
            'dry_run' => $IsSandbox,
            'transaction_type' => $paymentMethod,
            'customer' => $CustomerObject,
            'amount' => floatval($order->get_total()),
            'description' => $description,
            'notification_url' => $this->get_callback_uri($order->get_id()),
            'send_email' => $send_email,
            'fee_payed_by_customer' => $fee_payed_by_customer,
            'customer' => [
                'name' => sanitize_text_field($posted['billing_first_name'] . ' ' . $posted['billing_last_name']),
                'email' => sanitize_text_field($posted['billing_email']),
                'telephone' => sanitize_text_field($posted['billing_phone']),
                'cpf_cnpj_number' => $identity,
                'address_cep' => sanitize_text_field($posted['billing_postcode']),
                'address_street' => sanitize_text_field($posted['billing_address_1']),
                'address_number' => sanitize_text_field(isset($posted['billing_number']) ? $posted['billing_number'] : 'S/N'),
                'address_neighborhood' => sanitize_text_field(isset($posted['billing_neighborhood']) ? $posted['billing_neighborhood'] : 'Não informado'),
                'address_city' => sanitize_text_field($posted['billing_city']),
                'address_state' => sanitize_text_field($posted['billing_state'])
            ]
        ];

        return json_encode($payload);
    }

    public function CheckoutController($order, $posted)
    {
        try {
            $IsSandbox = strtoupper($this->gateway->settings['sandbox']) !== "NO";

            $payload = $this->get_payload($order, $posted, $IsSandbox);

            if ('yes' == $this->gateway->debug) {
                $this->gateway->log->add($this->gateway->id, 'Requesting token for order ' . $order->get_order_number());
            }

            $token = $this->gateway->token;

            $response = $this->HttpClient($this->GetPaymentURI(), 'POST', $payload, array('Content-Type' => 'application/json', 'Authorization' => $token));

            if ($response['response']['code'] === 200) {

                $response = json_decode($response["body"]);

                return array(
                    'url' => $this->gateway->GetPaymentURI(),
                    'data' => $response,
                    'error' => '',
                );
            } else {
                wc_add_notice(__('Erro ', 'woo-fluxopay') . json_encode($response));
            }
        } catch (Exception $e) {

            // Return error message.
            return array(
                'url' => '',
                'token' => '',
                'error' => array('<strong>' . __('FluxoPay', 'woo-fluxopay') . '</strong>: ' . __('An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woo-fluxopay')),
            );
        }
    }

    public function PaymentController($order, $posted)
    {
        $payment_method = isset($posted['fluxopay_payment_method']) ? $posted['fluxopay_payment_method'] : '';

        $IsSandbox = strtoupper($this->gateway->settings['sandbox']) !== "NO";

        $payload = $this->get_payload($order, $posted, $IsSandbox);

        if ('yes' == $this->gateway->debug) {
            $this->gateway->log->add($this->gateway->id, 'Requesting direct payment for order ' . $order->get_order_number());
        }

        $token = $this->gateway->token;

        $response = $this->HttpClient($this->GetPaymentURI(), 'POST', $payload, array('Content-Type' => 'application/json', 'Authorization' => $token));
        $statusCode = $response['response']['code'];

        if (is_wp_error($response)) {
            if ('yes' == $this->gateway->debug) {
                $this->gateway->log->add($this->gateway->id, 'WP_Error in requesting the direct payment:');
            }
        } else {
            try {
                $response = json_decode($response["body"]);

	            if ( $statusCode >= 300 ) {
		            if ( $this->gateway->debug == 'yes' ) {
			            $this->gateway->log->add( $this->gateway->id, print_r($response, true ) );
		            }

		            wc_add_notice( __( 'Erro: ', 'woo-fluxopay' ) . json_encode($response), 'error' );
	            } else {
		            if ( $this->gateway->debug == 'yes' ) {
			            $this->gateway->log->add( $this->gateway->id, 'Transação gerada para o pedido ' . $order->get_order_number() . ' com o seguinte conteúdo: ' . print_r($response, true ) );
		            }

		            return array(
			            'url'   => $this->gateway->get_return_url( $order ),
			            'data'  => $response,
			            'error' => '',
		            );
	            }
            } catch (Exception $e) {
                $data = '';

                if ('yes' == $this->gateway->debug) {
                    $this->gateway->log->add($this->gateway->id, 'Error while parsing the FluxoPay response: ' . print_r($e->getMessage(), true));
                }
            }
        }
    }

    public function get_direct_payment_url()
    {
        return 'https://api.app.fluxoresultados.com.br/v1/public/charge';
    }
}
