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

        try {
            // Selector de producto de horas a validar
            $productos_horas = WCUPH_Config::get_productos_con_horas();
            $selected_horas = isset($_GET['product_horas']) ? intval($_GET['product_horas']) : (in_array(3701, $productos_horas) ? 3701 : $productos_horas[0]);

            echo '<form method="get" style="margin-bottom:12px;">';
            echo '<input type="hidden" name="page" value="validation-hours">';
            echo '<label for="product_horas">Producto de horas: </label>';
            echo '<select id="product_horas" name="product_horas">';
            foreach ($productos_horas as $ph) {
                $prod = function_exists('wc_get_product') ? wc_get_product($ph) : false;
                $name = $prod ? $prod->get_name() : ('Producto #' . intval($ph));
                $sel = $ph === $selected_horas ? ' selected' : '';
                echo '<option value="' . intval($ph) . '"' . $sel . '>' . esc_html($name) . ' (ID:' . intval($ph) . ')</option>';
            }
            echo '</select> ';
            submit_button('Filtrar', 'secondary', '', false);
            echo '</form>';

            // Mostrar la relación para el producto seleccionado
            echo '<p>Booking => Producto de horas seleccionadas:</p>';
            echo '<ul>';
            $relaciones = WCUPH_Config::get_relacion_productos();
            foreach ($relaciones as $booking_id => $horas_id) {
                if ($horas_id !== $selected_horas) continue;
                $booking = function_exists('wc_get_product') ? wc_get_product($booking_id) : false;
                $booking_name = $booking ? $booking->get_name() : "Producto #$booking_id";
                echo '<li>' . esc_html($booking_name) . ' (ID: ' . intval($booking_id) . ')</li>';
            }
            echo '</ul>';

            global $wpdb;
            // Preferir usuarios que ya tengan meta 'wc_horas_acumuladas' para rendimiento
            $usuarios = $wpdb->get_results("SELECT u.ID, u.display_name, u.user_email FROM {$wpdb->users} u JOIN {$wpdb->usermeta} um ON u.ID = um.user_id WHERE um.meta_key = 'wc_horas_acumuladas'");
            if (empty($usuarios)) {
                // No traemos todos los usuarios por defecto (podría ser muy costoso)
                if (isset($_GET['show_all']) && $_GET['show_all'] == '1') {
                    $usuarios = $wpdb->get_results("SELECT u.ID, u.display_name, u.user_email FROM {$wpdb->users} u");
                } else {
                    echo '<div class="notice notice-info"><p>No se encontraron usuarios con meta <code>wc_horas_acumuladas</code>. Si quieres revisar todos los usuarios (consulta pesada), <a href="' . esc_url(add_query_arg('show_all', '1')) . '">haz clic aquí</a>.</p></div>';
                    // cerrar tabla y wrapper
                    echo '</div>';
                    return;
                }
            }

            // Requiere acción explícita para ejecutar la validación (evita cargas pesadas)
            $do_validate = isset($_GET['do_validate']) && $_GET['do_validate'] == '1';
            echo '<form method="get" style="margin-bottom:12px;">';
            echo '<input type="hidden" name="page" value="validation-hours">';
            echo '<input type="hidden" name="product_horas" value="' . intval($selected_horas) . '">';
            echo '<input type="hidden" name="do_validate" value="1">';
            submit_button('Calcular validación', 'primary', '', false);
            echo '</form>';

            if (!$do_validate) {
                echo '<div class="notice notice-warning"><p>Pulsa "Calcular validación" para procesar las órdenes y calcular horas usadas por usuario. Esto puede ser costoso en sitios con muchas órdenes.</p></div>';
                echo '</div>';
                return;
            }

            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>Usuario</th><th>Email</th><th>Producto horas</th><th>Horas compradas</th><th>Horas usadas</th><th>Saldo</th></tr></thead><tbody>';


            // Productos de horas configurados
            $productos_horas = WCUPH_Config::get_productos_con_horas();

            // Determinar la lista de productos booking que consumen el producto de horas seleccionado
            $bookings_para_horas = [];
            foreach ($relaciones as $b => $h) {
                if ($h === $selected_horas) $bookings_para_horas[] = (int)$b;
            }

            foreach ($usuarios as $usuario) {
                $user_id = intval($usuario->ID);

                // Calcular horas compradas y usadas para el producto seleccionado
                $compradas = 0;
                $usadas = 0;

                // Intentar leer horas compradas desde meta (rápido)
                $meta = get_user_meta($user_id, 'wc_horas_acumuladas', true);
                if (is_array($meta) && isset($meta[$selected_horas])) {
                    $compradas = (int)$meta[$selected_horas];
                }

                // Obtener órdenes del usuario si la función existe
                $orders = [];
                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders([
                        'customer' => $user_id,
                        'limit' => -1,
                        'status' => ['completed', 'processing', 'on-hold', 'pending']
                    ]);
                    if (!is_array($orders)) $orders = [];
                }

                // Siempre calcular horas usadas: recorrer todas las órdenes del usuario y sumar duraciones
                if (function_exists('wc_get_orders')) {
                    try {
                        $orders_all = wc_get_orders([
                            'customer' => $user_id,
                            'limit' => -1,
                            'status' => ['completed', 'processing', 'on-hold', 'pending']
                        ]);

                        if (is_array($orders_all)) {
                            foreach ($orders_all as $order) {
                                if (!is_object($order) || !method_exists($order, 'get_items')) continue;
                                foreach ($order->get_items() as $item) {
                                    $pid = (int)(method_exists($item, 'get_product_id') ? $item->get_product_id() : 0);
                                    $qty = (int)(method_exists($item, 'get_quantity') ? $item->get_quantity() : 1);

                                    // Si el item es el producto de horas seleccionado y no tenemos meta, sumarlo para 'compradas'
                                    if ($compradas === 0 && $pid === $selected_horas) {
                                        $horas_unit = $this->obtener_horas_desde_item_variation($item);
                                        if ($horas_unit > 0) $compradas += $horas_unit * $qty;
                                    }

                                    // Si el item es un booking relacionado al producto de horas seleccionado, sumar usada
                                    if (in_array($pid, $bookings_para_horas, true)) {
                                        $dur = $this->obtener_duracion_desde_item($item);
                                        if ($dur > 0) $usadas += $dur * $qty;
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        if (function_exists('wcuph_log')) wcuph_log('[WARN] Error al sumar horas en órdenes: ' . $e->getMessage());
                    }
                }

                // Mostrar solo una fila por usuario para el producto seleccionado
                $saldo = $compradas - $usadas;
                if ($compradas === 0 && $usadas === 0) continue;

                $prod_sel = function_exists('wc_get_product') ? wc_get_product($selected_horas) : false;
                $nombre_producto = $prod_sel ? $prod_sel->get_name() : ('Producto #' . intval($selected_horas));

                echo '<tr>';
                echo '<td>' . esc_html($usuario->display_name) . '</td>';
                echo '<td>' . esc_html($usuario->user_email) . '</td>';
                echo '<td>' . esc_html($nombre_producto) . ' (ID: ' . intval($selected_horas) . ')</td>';
                echo '<td>' . esc_html($compradas) . '</td>';
                echo '<td>' . esc_html($usadas) . '</td>';
                echo '<td>' . esc_html($saldo) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } catch (Exception $e) {
            // Registrar el error y mostrar un aviso en admin
            if (function_exists('wcuph_log')) {
                wcuph_log('[ERROR] Error en pantalla Validación de Horas: ' . $e->getMessage());
            }
            echo '<div class="notice notice-error"><p>Error al generar la pantalla de validación. Revisa los logs.</p></div>';
        }

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
