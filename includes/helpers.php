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