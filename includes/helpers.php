<?php
if (!defined('ABSPATH')) exit;

// Funciones reutilizables
function wcuph_get_accumulated_hours($user_id) {
    $horas = get_user_meta($user_id, 'wc_horas_acumuladas', true);
    return is_array($horas) ? $horas : [];
}

/**
 * Extrae las horas a partir del nombre de una variación.
 * Lógica compartida (antes duplicada en Product_Hours_Handler::obtener_horas_variacion).
 *
 * @param int $variation_id
 * @return int Horas detectadas (0 si no se encuentran).
 */
function wcuph_parse_horas_from_variation($variation_id) {
    $variation = wc_get_product($variation_id);
    if (!$variation) {
        return 0;
    }

    $nombre = $variation->get_name();

    // 1. Intentar extraer del último segmento separado por "-"
    $partes          = explode('-', $nombre);
    $ultimo_segmento = trim(end($partes));
    preg_match('/\d+/', $ultimo_segmento, $matches);
    if (!empty($matches[0])) {
        return (int) $matches[0];
    }

    // 2. Último recurso: cualquier número en el nombre completo
    preg_match('/\d+/', $nombre, $matches);
    return isset($matches[0]) ? (int) $matches[0] : 0;
}

/**
 * Devuelve el rango [desde, hasta] del semestre calendario actual en formato Y-m-d.
 * Enero–Junio o Julio–Diciembre según la fecha actual.
 *
 * @return array{0:string,1:string}
 */
function wcuph_get_current_semester_range() {
    $year  = (int) wp_date('Y');
    $month = (int) wp_date('n');

    if ($month <= 6) {
        $desde = sprintf('%d-01-01', $year);
        $hasta = sprintf('%d-06-30', $year);
    } else {
        $desde = sprintf('%d-07-01', $year);
        $hasta = sprintf('%d-12-31', $year);
    }

    return [$desde, $hasta];
}

/**
 * Calcula las horas COMPRADAS por un usuario dentro de un rango de fechas,
 * re-derivándolas desde las órdenes WooCommerce completadas.
 *
 * @param int    $user_id
 * @param string $fecha_desde Y-m-d
 * @param string $fecha_hasta Y-m-d
 * @return int Total de horas compradas en el rango.
 */
function wcuph_get_purchased_hours_in_range($user_id, $fecha_desde, $fecha_hasta) {
    $productos_con_horas = WCUPH_Config::get_productos_con_horas();
    $total = 0;

    $pedidos = wc_get_orders([
        'customer_id'  => $user_id,
        'status'       => 'completed',
        'limit'        => -1,
        // Rango inclusivo: desde 00:00:00 del primer día hasta 23:59:59 del último.
        'date_created' => $fecha_desde . '...' . $fecha_hasta . ' 23:59:59',
    ]);

    foreach ($pedidos as $pedido) {
        foreach ($pedido->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!in_array($product_id, $productos_con_horas, true)) {
                continue;
            }

            $variation_id = $item->get_variation_id();
            if (!$variation_id) {
                continue;
            }

            $horas_unitarias = wcuph_parse_horas_from_variation($variation_id);
            $total          += $horas_unitarias * (int) $item->get_quantity();
        }
    }

    return $total;
}

/**
 * Calcula las horas RESERVADAS por un usuario dentro de un rango de fechas,
 * a partir de los bookings (solo estados confirmados y pagados).
 *
 * @param int    $user_id
 * @param string $fecha_desde Y-m-d
 * @param string $fecha_hasta Y-m-d
 * @return float Total de horas reservadas en el rango.
 */
function wcuph_get_reserved_hours_in_range($user_id, $fecha_desde, $fecha_hasta) {
    $reservas = get_posts([
        'post_type'      => 'wc_booking',
        'posts_per_page' => -1,
        'post_status'    => ['confirmed', 'paid'],
        'meta_query'     => [
            [
                'key'   => '_booking_customer_id',
                'value' => $user_id,
            ],
        ],
    ]);

    $ts_desde = strtotime($fecha_desde . ' 00:00:00');
    $ts_hasta = strtotime($fecha_hasta . ' 23:59:59');
    $total    = 0;

    foreach ($reservas as $reserva) {
        $inicio = get_post_meta($reserva->ID, '_booking_start', true);
        $fin    = get_post_meta($reserva->ID, '_booking_end', true);
        if (!$inicio || !$fin) {
            continue;
        }

        $ts_inicio = strtotime($inicio);
        $ts_fin    = strtotime($fin);

        // Solo contar reservas cuyo inicio cae dentro del rango.
        if ($ts_inicio < $ts_desde || $ts_inicio > $ts_hasta) {
            continue;
        }

        $total += ($ts_fin - $ts_inicio) / 3600;
    }

    return $total;
}

function wcuph_log($message) {
    $log_file = WP_CONTENT_DIR . '/wcuph-logs.log'; // Ruta del archivo de log

    // Obtener la fecha en la zona horaria configurada en WordPress
    $formatted_message = '[' . wp_date('Y-m-d H:i:s', time(), new DateTimeZone('America/Bogota')) . '] ' . $message . PHP_EOL;

    // Escribir en el archivo de log
    file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
}