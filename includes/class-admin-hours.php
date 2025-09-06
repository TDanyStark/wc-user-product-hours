<?php

if (!defined('ABSPATH')) exit;

class WCUPH_Admin_Hours
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu()
    {
        add_users_page(
            'Horas Acumuladas',
            'Horas Acumuladas',
            'manage_options',
            'horas-usuarios',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        global $wpdb;

        // Exportar CSV si se solicita
        if (isset($_GET['export_csv'])) {
            $this->export_csv();
            exit;
        }

        // Filtrar usuarios con horas > 0
        $solo_con_horas = isset($_GET['solo_con_horas']) ? true : false;

        $usuarios = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_email, um.meta_value
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wc_horas_acumuladas'
        ");

        echo '<div class="wrap"><h1>Horas Acumuladas por Usuario</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="horas-usuarios">';
        echo '<label><input type="checkbox" name="solo_con_horas" value="1" ' . checked($solo_con_horas, true, false) . '> Mostrar solo usuarios con horas</label> ';
        submit_button('Filtrar', 'secondary', '', false);
        echo ' <a href="' . admin_url('users.php?page=horas-usuarios&export_csv=1') . '" class="button button-primary">Exportar CSV</a>';
        echo '</form>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Usuario</th><th>Email</th><th>Producto</th><th>Horas</th></tr></thead><tbody>';

        foreach ($usuarios as $usuario) {
            $horas = maybe_unserialize($usuario->meta_value);
            if (!is_array($horas)) continue;

            $tiene_horas = false;

            foreach ($horas as $producto_id => $cantidad) {
                if ($cantidad > 0) {
                    $tiene_horas = true;
                    $producto = wc_get_product($producto_id);
                    $nombre_producto = $producto ? $producto->get_name() : "Producto #$producto_id";

                    echo '<tr>';
                    echo '<td>' . $usuario->display_name . '</td>';
                    echo '<td>' . $usuario->user_email . '</td>';
                    echo '<td>' . $nombre_producto . '</td>';
                    echo '<td>' . $cantidad . '</td>';
                    echo '</tr>';
                }
            }

            if (!$tiene_horas && !$solo_con_horas) {
                echo '<tr>';
                echo '<td>' . $usuario->display_name . '</td>';
                echo '<td>' . $usuario->user_email . '</td>';
                echo '<td>-</td>';
                echo '<td>0</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function export_csv()
    {
        global $wpdb;

        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=horas_usuarios.csv');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['Usuario', 'Email', 'Producto', 'Horas']);

        // Verificar si se está filtrando por usuarios con horas
        $solo_con_horas = isset($_GET['solo_con_horas']) ? true : false;

        $usuarios = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_email, um.meta_value
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wc_horas_acumuladas'
        ");

        foreach ($usuarios as $usuario) {
            $horas = maybe_unserialize($usuario->meta_value);
            if (!is_array($horas)) continue;

            $tiene_horas = false;

            foreach ($horas as $producto_id => $cantidad) {
                if ($cantidad > 0) {
                    $tiene_horas = true;
                    $producto = wc_get_product($producto_id);
                    $nombre_producto = $producto ? $producto->get_name() : "Producto #$producto_id";
                    fputcsv($output, [$usuario->display_name, $usuario->user_email, $nombre_producto, $cantidad]);
                }
            }

            // Si no tiene horas y estamos filtrando solo usuarios con horas, no incluir
            if (!$tiene_horas && $solo_con_horas) {
                continue;
            }
        }

        fclose($output);
        exit;
    }
}
