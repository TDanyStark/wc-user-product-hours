<?php

if (!defined('ABSPATH')) exit;

class WCUPH_Admin_Validation_Hours
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu()
    {
        add_users_page(
            'Validar Horas',
            'Validar Horas',
            'manage_options',
            'validation-hours',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        echo '<div class="wrap"><h1>Validación de Horas - Compradas vs Usadas</h1>';

        echo '<p>Relación Booking => Producto de horas:</p>';
        echo '<ul>';
        foreach (WCUPH_Config::get_relacion_productos() as $booking_id => $horas_id) {
            $booking = wc_get_product($booking_id);
            $horas = wc_get_product($horas_id);
            $booking_name = $booking ? $booking->get_name() : "Producto #$booking_id";
            $horas_name = $horas ? $horas->get_name() : "Producto #$horas_id";
            echo '<li>' . esc_html($booking_name) . ' (ID: ' . $booking_id . ') => ' . esc_html($horas_name) . ' (ID: ' . $horas_id . ')</li>';
        }
        echo '</ul>';

        // Obtener usuarios que tengan meta o todos si no
        global $wpdb;
        $usuarios = $wpdb->get_results("SELECT u.ID, u.display_name, u.user_email FROM {$wpdb->users} u");

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Usuario</th><th>Email</th><th>Producto horas</th><th>Horas compradas</th><th>Horas usadas</th><th>Saldo</th></tr></thead><tbody>';

        $productos_horas = WCUPH_Config::get_productos_con_horas();

        foreach ($usuarios as $usuario) {
            $user_id = $usuario->ID;

            // Calcular horas compradas y usadas por producto de horas para este usuario
            $compradas = [];
            $usadas = [];

            // Obtener órdenes del usuario (varios estados relevantes)
            $orders = wc_get_orders([
                'customer' => $user_id,
                'limit' => -1,
                'status' => ['completed', 'processing', 'on-hold', 'pending']
            ]);

            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $qty = (int)$item->get_quantity();

                    // Si el item es un producto de horas (compra de horas)
                    if (in_array($product_id, $productos_horas)) {
                        $horas_unit = $this->obtener_horas_desde_item_variation($item);
                        if ($horas_unit > 0) {
                            if (!isset($compradas[$product_id])) $compradas[$product_id] = 0;
                            $compradas[$product_id] += $horas_unit * $qty;
                        }
                    }

                    // Si el item es un producto booking que consume horas
                    $rel = WCUPH_Config::get_relacion_productos();
                    if (array_key_exists($product_id, $rel)) {
                        $producto_horas_id = $rel[$product_id];
                        $duracion = $this->obtener_duracion_desde_item($item);
                        if ($duracion > 0) {
                            if (!isset($usadas[$producto_horas_id])) $usadas[$producto_horas_id] = 0;
                            $usadas[$producto_horas_id] += $duracion * $qty;
                        }
                    }
                }
            }

            // Unir productos a mostrar: union de compradas/usadas/productos configurados
            $productos_mostrar = array_unique(array_merge(array_keys($compradas), array_keys($usadas), $productos_horas));

            foreach ($productos_mostrar as $pid) {
                $nombre_producto = null;
                $prod = wc_get_product($pid);
                $nombre_producto = $prod ? $prod->get_name() : ('Producto #' . $pid);

                $horas_compradas = isset($compradas[$pid]) ? $compradas[$pid] : 0;
                $horas_usadas = isset($usadas[$pid]) ? $usadas[$pid] : 0;
                $saldo = $horas_compradas - $horas_usadas;

                // Solo mostrar filas relevantes (si el usuario no tiene nada y saldo 0, mostrar en collapsed)
                if ($horas_compradas === 0 && $horas_usadas === 0) continue;

                echo '<tr>';
                echo '<td>' . esc_html($usuario->display_name) . '</td>';
                echo '<td>' . esc_html($usuario->user_email) . '</td>';
                echo '<td>' . esc_html($nombre_producto) . ' (ID: ' . $pid . ')</td>';
                echo '<td>' . esc_html($horas_compradas) . '</td>';
                echo '<td>' . esc_html($horas_usadas) . '</td>';
                echo '<td>' . esc_html($saldo) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function obtener_horas_desde_item_variation($item)
    {
        // Intentar obtener horas desde la variación o nombre del producto
        $variation_id = $item->get_variation_id();
        $product_id = $item->get_product_id();

        $product = null;
        if ($variation_id) {
            $product = wc_get_product($variation_id);
        }
        if (!$product) {
            $product = wc_get_product($product_id);
        }

        if (!$product) return 0;

        $nombre = $product->get_name();
        // Buscar número en el nombre
        if (preg_match('/(\d+)/', $nombre, $m)) {
            return (int)$m[1];
        }

        return 0;
    }

    private function obtener_duracion_desde_item($item)
    {
        // Buscar en metadatos del item
        $duracion = 0;
        $meta_keys = ['_booking_duration', 'duracion_reserva', '_duration'];

        foreach ($meta_keys as $key) {
            $value = $item->get_meta($key);
            if ($value) {
                $duracion = (int)$value;
                if ($duracion > 0) return $duracion;
            }
        }

        // Intentar con ID de booking
        $booking_id = $item->get_meta('_booking_id');
        if ($booking_id && class_exists('WC_Booking')) {
            try {
                $booking = new WC_Booking($booking_id);
                if ($booking) {
                    return (int)$booking->get_duration();
                }
            } catch (Exception $e) {
                // ignorar
            }
        }

        return 0;
    }
}
