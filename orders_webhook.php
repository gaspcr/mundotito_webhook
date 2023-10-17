<?php
/**
 * Plugin Name: Custom WooCommerce Webhook
 * Description: Un plugin para enviar webhooks personalizados cuando un pedido de WooCommerce cambia a estado "procesando".
 * Version: 1.0
 * Author: Gasper Development
 */

add_action('woocommerce_order_status_processing', 'custom_webhook_post_on_processing', 10, 1);

function custom_webhook_post_on_processing($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    $items = $order->get_items();

    $first_item = array_values($items)[0];
    $product_name = $first_item->get_name();
    $product_quantity = $first_item->get_quantity();

    $scent = $first_item->get_meta('pa_aroma') ? $first_item->get_meta('pa_aroma') : 'Desconocido';
    $format = $first_item->get_meta('manga-de') ? $first_item->get_meta('manga-de') : 'Desconocido';

    $all_meta_data = $first_item->get_meta_data();

    $address_parts = explode(',', $order->get_billing_address_1());
    $address = trim($address_parts[0]);

    $suburb_parts = explode('_', $order->get_billing_state());
    $suburb = isset($suburb_parts[1]) ? $suburb_parts[1] : $order->get_billing_state();

    $phone = preg_replace('/[^0-9]/', '', $order->get_billing_phone());
    $phone = preg_replace('/^56/', '', $phone);

    $combined_product_name = "$product_name - $format";

    $price = floatval($order->get_total());

    $data = array(
        "orderNumber" => 1000 + $order_id,
        "name" => $order->get_billing_first_name(),
        "address" => $address,
        "suburb" => $suburb,
        "phone" => $phone,
        "orderAmount" => strval($product_quantity),
        "orderProduct" => $combined_product_name,
        "scent" => $scent,
        "price" => $price,
        "paymentMethod" => $order->get_payment_method_title(),
        "seller" => "web",
        "comments" => $order->get_shipping_address_2(),
        "allMetaData" => json_encode($all_meta_data)
    );

    $response = wp_safe_remote_post('https://app.abakro.com/webhooks/orders/create/64f0c3ff4e19b1ff3cdcdc6b', array(
        'body' => json_encode($data),
        'headers' => array('Content-Type' => 'application/json'),
    ));
    
    error_log('Webhook Body: ' . json_encode($data));

    if (is_wp_error($response)) {
        error_log('Webhook Error: ' . $response->get_error_message());
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('Webhook Response: ' . wp_remote_retrieve_body($response));
        error_log('HTTP Status Code: ' . $status_code);

        if ($status_code === 200) {
            error_log('Webhook successfully sent.');
        } else {
            error_log('Failed to send webhook.');
        }
    }
}
