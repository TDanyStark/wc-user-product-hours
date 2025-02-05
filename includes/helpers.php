<?php
if (!defined('ABSPATH')) exit;

// Funciones reutilizables
function wcuph_get_accumulated_hours($user_id) {
    $horas = get_user_meta($user_id, 'wc_horas_acumuladas', true);
    return is_array($horas) ? $horas : [];
}

function wcuph_log($message) {
    error_log('[WCUPH] ' . $message);
}

function wcuph_get_cart_total_horas($user_id, $producto_horas_id) {
    $total = 0;
    
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['horas_deducidas'])) {
            $total += $cart_item['horas_deducidas'];
        }
    }
    
    $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
    return ($horas_acumuladas[$producto_horas_id] ?? 0) - $total;
}