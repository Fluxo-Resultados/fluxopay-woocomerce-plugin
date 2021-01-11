<?php

class WC_FluxoPay
{
    public static function init()
    {
        add_action('rest_api_init', function () {
            register_rest_route('fluxopay/v1/', 'callback/(?P<order_id>\d+)', array(
                'methods' => 'POST',
                'callback' => function ($callback) {

                    $callback = $callback->get_params();
                    $gateway = new WC_FluxoPay_Gateway();
                    $order_id = $callback['order_id'];

                    $UpdateOrder = array(
                        "id" => $callback['id'],
                        "status" => $callback['status'],
                        "message" => $callback['status'],
                        "description" => $callback['status'],
                    );

                    $object = new stdClass();

                    foreach ($UpdateOrder as $key => $value) {
                        $object->$key = $value;
                    }

                    $gateway->update_order_status($object, $order_id);

                    $response = new WP_REST_Response(true);
                    $response->set_status(200);

                    return $response;
                },
                'permission_callback' => function ($callback) {

                    $callback = $callback->get_params();

                    if ($callback == null) {
                        return new WP_Error('The request has not been applied because it lacks valid authentication credentials for the target resource.', '', array('status' => 401));
                    } else {
                        return true;
                    }
                }
            ));
        });

        if (class_exists('WC_Payment_Gateway')) {
            self::includes();

            add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));
            add_filter('plugin_action_links_' . plugin_basename(WC_FLUXOPAY_PLUGIN_FILE), array(__CLASS__, 'plugin_action_links'));
        } else {
            add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice'));
        }
    }

    public static function get_templates_path()
    {
        return plugin_dir_path(WC_FLUXOPAY_PLUGIN_FILE) . 'templates/';
    }

    public static function load_plugin_textdomain()
    {
        load_plugin_textdomain('woo-fluxopay', false, dirname(plugin_basename(WC_FLUXOPAY_PLUGIN_FILE)) . '/languages/');
    }

    public static function plugin_action_links($links)
    {
        $plugin_links = array();
        $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=fluxopay')) . '">' . __('Settings', 'woo-fluxopay') . '</a>';

        return array_merge($plugin_links, $links);
    }

    private static function includes()
    {
        include_once dirname(__FILE__) . '/class-wc-fluxopay-api.php';
        include_once dirname(__FILE__) . '/class-wc-fluxopay-gateway.php';
    }

    public static function add_gateway($methods)
    {
        $methods[] = 'WC_FluxoPay_Gateway';

        return $methods;
    }

    public static function ecfb_missing_notice()
    {
        $settings = get_option('woocommerce_fluxopay_settings', array('method' => ''));

        if ('transparent' === $settings['method'] && !class_exists('Extra_Checkout_Fields_For_Brazil')) {
            include dirname(__FILE__) . '/admin/views/html-notice-missing-ecfb.php';
        }
    }

    public static function woocommerce_missing_notice()
    {
        include dirname(__FILE__) . '/admin/views/html-notice-missing-woocommerce.php';
    }
}
