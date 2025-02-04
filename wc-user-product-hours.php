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
 * Text Domain: wc-user-product-hours
 */


defined('ABSPATH') || exit;

class WC_User_Product_Hours
{   
    // Relación entre productos reservables y productos de horas
    private $relacion_productos = array(
        3722 => 3701, // Producto reservable => Producto de horas
        // Agrega más relaciones según necesites: 3733 => 3711, etc.
    );

    // IDs de los productos que acumulan horas
    private $productos_con_horas = array(3701); // Cambia estos IDs por los tuyos

    public function __construct()
    {
        add_action('woocommerce_order_status_completed', array($this, 'wc_da_guardar_datos_compra'));
        add_action('woocommerce_add_to_cart_validation', array($this, 'wc_da_validar_horas_reserva'), 10, 5);
    }

    public function wc_da_guardar_datos_compra($order_id)
    {
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

    private function guardar_meta_usuario($user_id, $product_id, $variation_id)
    {
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
        error_log('[DEBUG] Horas obtenidas de usuario ' . $user_id . ': ' . print_r($horas_acumuladas, true));
        return $horas_acumuladas ? (int)$horas_acumuladas : 0;
    }

    // Nueva función de validación
    public function wc_da_validar_horas_reserva($passed, $product_id, $quantity, $variation_id = null, $variations = null) {
        error_log('[DEBUG] Inicio validación. Producto ID: ' . $product_id);
        
        try {
            // Solo si es producto reservable relacionado
            if (array_key_exists($product_id, $this->relacion_productos)) {
                error_log('[DEBUG] Producto reservable detectado: ' . $product_id);
                
                // Obtener duración desde el formulario de booking
                $duracion = isset($_POST['wc_bookings_field_duration']) ? (int)$_POST['wc_bookings_field_duration'] : 0;
                error_log('[DEBUG] Duración seleccionada: ' . $duracion . ' horas');

                // Validar duración válida
                if ($duracion <= 0) {
                    error_log('[ERROR] Duración no detectada en $_POST');
                    wc_add_notice(__('Selecciona una duración válida', 'WC-User-Product-Hours'), 'error');
                    return false;
                }

                $user_id = get_current_user_id();
                error_log('[DEBUG] Usuario ID: ' . $user_id);
                
                if ($user_id === 0) {
                    error_log('[ERROR] Usuario no autenticado');
                    wc_add_notice(__('Debes iniciar sesión para reservar', 'WC-User-Product-Hours'), 'error');
                    return false;
                }

                // Obtener horas disponibles
                $horas_disponibles = $this->obtener_horas_acumuladas($user_id);
                error_log('[DEBUG] Horas disponibles: ' . $horas_disponibles);
                
                // Comparar con duración seleccionada
                if ($horas_disponibles < $duracion) {
                    error_log('[VALIDACIÓN FALLIDA] Horas solicitadas: ' . $duracion . ' | Disponibles: ' . $horas_disponibles);
                    
                    $producto_horas_id = $this->relacion_productos[$product_id];
                    $producto_horas = wc_get_product($producto_horas_id);
                    $enlace_compra = $producto_horas->get_permalink();
                    
                    wc_add_notice(sprintf(
                        __('Necesitas %1$s horas para esta reserva. Dispones de %2$s. <a href="%3$s">Compra más horas</a>', 'WC-User-Product-Hours'),
                        $duracion,
                        $horas_disponibles,
                        $enlace_compra
                    ), 'error');
                    
                    return false;
                }
                
                error_log('[VALIDACIÓN EXITOSA] Horas suficientes');
            }
            
            return $passed;
            
        } catch (Exception $e) {
            error_log('[EXCEPCIÓN] Error: ' . $e->getMessage());
            return false;
        }
    }

}

new WC_User_Product_Hours();
