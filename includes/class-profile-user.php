<?php

if (!defined('ABSPATH')) exit;

class WCUPH_User_Hours_Display
{
    public function __construct()
    {
        add_action('show_user_profile', [$this, 'mostrar_horas_usuario']);
        add_action('edit_user_profile', [$this, 'mostrar_horas_usuario']);
    }

    public function mostrar_horas_usuario($user)
    {
        $horas_acumuladas = get_user_meta($user->ID, 'wc_horas_acumuladas', true);
        
        if (!$horas_acumuladas || !is_array($horas_acumuladas)) {
            echo '<h3>Horas Acumuladas</h3><p>No hay horas registradas.</p>';
            return;
        }

        echo '<h3>Horas Acumuladas por Producto</h3>';
        echo '<table class="form-table">
                <tr>
                    <th>Producto</th>
                    <th>Horas Disponibles</th>
                </tr>';

        foreach ($horas_acumuladas as $product_id => $horas) {
            $producto = get_post($product_id);
            $nombre_producto = $producto ? $producto->post_title : 'Producto Desconocido';
            
            echo '<tr>
                    <td>' . esc_html($nombre_producto) . '</td>
                    <td>' . esc_html($horas) . '</td>
                </tr>';
        }

        echo '</table>';
    }
}
