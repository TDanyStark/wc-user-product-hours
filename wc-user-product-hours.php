<?php
/**
 * Plugin Name: WC User Product Hours
 * Plugin URI: https://daniel-amado.com/
 * Description: Guarda el ID del producto y las horas de la variación en metadatos de usuario después de cada compra.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://daniel-amado.com/
 * License: GPLv2 or later
 * Text Domain: wc-user-product-hours
 */

defined('ABSPATH') || exit;

class WC_User_Product_Hours {

    public function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'guardar_datos_compra'));
    }

    public function guardar_datos_compra($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        if (!$user_id) return;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            if ($variation_id) {
                $this->guardar_meta_usuario(
                    $user_id,
                    $product_id,
                    $variation_id
                );
            }
        }
    }

    private function guardar_meta_usuario($user_id, $product_id, $variation_id) {
        $horas = $this->obtener_horas_variacion($variation_id);
        
        if (!$horas) return;

        $meta_data = array(
            'product_id'    => $product_id,
            'variation_id'  => $variation_id,
            'horas'         => $horas,
            'fecha_compra'  => current_time('mysql')
        );

        add_user_meta(
            $user_id,
            'wc_producto_horas',
            $meta_data
        );
    }

    private function obtener_horas_variacion($variation_id) {
        $variation = wc_get_product($variation_id);
        
        // Obtener el nombre de la variación (que es el número de horas)
        $nombre_variacion = $variation->get_name();
        
        // Extraer el número de horas del nombre de la variación
        preg_match('/\d+/', $nombre_variacion, $matches);
        
        return $matches[0] ?? false;
    }
}

new WC_User_Product_Hours();