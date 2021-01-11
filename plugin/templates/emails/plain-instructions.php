<?php

if (!defined('ABSPATH')) {
    exit;
}

_e('Payment', 'woo-fluxopay');

echo "\n\n";

_e('Please use the link below to view your payment link:', 'woo-fluxopay');

echo "\n";

echo esc_url($link);

echo "\n";

_e('After we receive the ticket payment confirmation, your order will be processed.', 'woo-fluxopay');

echo "\n\n****************************************************\n\n";
