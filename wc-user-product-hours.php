<?php

/**
 * Plugin Name: WC User Product Hours
 * Plugin URI: https://daniel-amado.com/
 * Description: Guarda el ID del producto y las horas de la variación en metadatos de usuario después de cada compra.
 * Version: 1.0.0
 * Author: Tu Nombre
 * GitHub Plugin URI: https://github.com/TDanyStark/wc-user-product-hours
 * Author URI: https://daniel-amado.com/
 * License: GPLv2 or later
 * Text Domain: WC-User-Product-Hours
 */


defined('ABSPATH') || exit;

class WC_User_Product_Hours
{

    // IDs de los productos que acumulan horas
    private $productos_con_horas = array(123, 456, 789, 101112); // Cambia estos IDs por los tuyos

    public function __construct()
    {
        add_action('woocommerce_order_status_completed', array($this, 'wc_da_guardar_datos_compra'));
    }

    public function wc_da_guardar_datos_compra($order_id) {
        error_log('Orden completada. ID de la orden: ' . $order_id);
    
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
    
        if (!$user_id) {
            error_log('No se encontró un usuario asociado a la orden. Saliendo...');
            return;
        }
    
        error_log('Usuario asociado a la orden: ' . $user_id);
    
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            error_log('Procesando producto ID: ' . $product_id);
    
            // Solo procesar si el producto está en la lista de productos con horas
            if (in_array($product_id, $this->productos_con_horas)) {
                error_log('El producto está en la lista de productos con horas.');
    
                $variation_id = $item->get_variation_id();
                error_log('ID de la variación: ' . $variation_id);
    
                if ($variation_id) {
                    $this->guardar_meta_usuario(
                        $user_id,
                        $product_id,
                        $variation_id
                    );
                }
            } else {
                error_log('El producto NO está en la lista de productos con horas.');
            }
        }
    }

    private function guardar_meta_usuario($user_id, $product_id, $variation_id) {
    error_log('Iniciando guardar_meta_usuario para el usuario: ' . $user_id);

    $horas_compradas = $this->obtener_horas_variacion($variation_id);
    error_log('Horas compradas: ' . $horas_compradas);

    if (!$horas_compradas) {
        error_log('No se encontraron horas compradas. Saliendo...');
        return;
    }

    // Obtener horas acumuladas actuales
    $horas_acumuladas = $this->obtener_horas_acumuladas($user_id);
    error_log('Horas acumuladas actuales: ' . $horas_acumuladas);

    // Sumar las nuevas horas
    $nuevas_horas = $horas_acumuladas + $horas_compradas;
    error_log('Nuevas horas acumuladas: ' . $nuevas_horas);

    // Guardar o actualizar las horas acumuladas
    update_user_meta(
        $user_id,
        'wc_horas_acumuladas',
        $nuevas_horas
    );
    error_log('Horas acumuladas actualizadas correctamente.');

    // Guardar registro individual de la compra
    $meta_data = array(
        'product_id'    => $product_id,
        'variation_id'  => $variation_id,
        'horas'         => $horas_compradas,
        'fecha_compra'  => current_time('mysql')
    );

    add_user_meta(
        $user_id,
        'wc_producto_horas',
        $meta_data
    );
    error_log('Meta datos de la compra guardados correctamente.');
}

    private function obtener_horas_variacion($variation_id)
    {
        $variation = wc_get_product($variation_id);
        $nombre_variacion = $variation->get_name();
        preg_match('/\d+/', $nombre_variacion, $matches);
        return $matches[0] ?? 0;
    }

    private function obtener_horas_acumuladas($user_id)
    {
        $horas_acumuladas = get_user_meta($user_id, 'wc_horas_acumuladas', true);
        return $horas_acumuladas ? (int)$horas_acumuladas : 0;
    }
}

new WC_User_Product_Hours();
