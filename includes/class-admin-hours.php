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

    /**
     * Resuelve el rango de fechas a partir de $_GET, con el semestre actual como valor por defecto.
     *
     * @return array{0:string,1:string} [fecha_desde, fecha_hasta] en formato Y-m-d
     */
    private function get_rango_fechas()
    {
        list($def_desde, $def_hasta) = wcuph_get_current_semester_range();

        $desde = isset($_GET['fecha_desde']) ? sanitize_text_field(wp_unslash($_GET['fecha_desde'])) : '';
        $hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field(wp_unslash($_GET['fecha_hasta'])) : '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = $def_desde;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $def_hasta;
        }

        return [$desde, $hasta];
    }

    /**
     * Obtiene los datos agregados por usuario (excluyendo administradores).
     *
     * @param string $fecha_desde
     * @param string $fecha_hasta
     * @param bool   $solo_con_horas
     * @return array<int,array{id:int,nombre:string,email:string,compradas:int,reservadas:float,diferencia:float}>
     */
    private function obtener_datos_usuarios($fecha_desde, $fecha_hasta, $solo_con_horas)
    {
        $user_query = new WP_User_Query([
            'role__not_in' => ['administrator'],
            'fields'       => ['ID', 'display_name', 'user_email'],
            'orderby'      => 'display_name',
            'order'        => 'ASC',
        ]);

        $datos = [];

        foreach ($user_query->get_results() as $usuario) {
            $compradas  = wcuph_get_purchased_hours_in_range($usuario->ID, $fecha_desde, $fecha_hasta);
            $reservadas = wcuph_get_reserved_hours_in_range($usuario->ID, $fecha_desde, $fecha_hasta);

            if ($solo_con_horas && $compradas == 0 && $reservadas == 0) {
                continue;
            }

            $datos[] = [
                'id'         => (int) $usuario->ID,
                'nombre'     => $usuario->display_name,
                'email'      => $usuario->user_email,
                'compradas'  => $compradas,
                'reservadas' => $reservadas,
                'diferencia' => $compradas - $reservadas,
            ];
        }

        return $datos;
    }

    public function render_admin_page()
    {
        // Exportar CSV si se solicita
        if (isset($_GET['export_csv'])) {
            $this->export_csv();
            exit;
        }

        list($fecha_desde, $fecha_hasta) = $this->get_rango_fechas();
        $solo_con_horas = isset($_GET['solo_con_horas']);

        $datos = $this->obtener_datos_usuarios($fecha_desde, $fecha_hasta, $solo_con_horas);

        echo '<div class="wrap"><h1>Horas por Usuario</h1>';

        echo '<p class="description" style="margin: 10px 0 20px;">Las horas <strong>compradas</strong> se calculan desde las órdenes completadas dentro del rango. Las horas <strong>reservadas</strong> consideran solo reservas confirmadas y pagadas cuyo inicio cae en el rango. No se muestran usuarios administradores.</p>';

        // Formulario de filtros
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="horas-usuarios">';
        echo '<label style="margin-right: 10px;">Desde: <input type="date" name="fecha_desde" value="' . esc_attr($fecha_desde) . '"></label>';
        echo '<label style="margin-right: 10px;">Hasta: <input type="date" name="fecha_hasta" value="' . esc_attr($fecha_hasta) . '"></label>';
        echo '<label style="margin-right: 10px;"><input type="checkbox" name="solo_con_horas" value="1" ' . checked($solo_con_horas, true, false) . '> Mostrar solo usuarios con horas</label> ';
        submit_button('Filtrar', 'secondary', '', false);

        $export_url = add_query_arg([
            'page'           => 'horas-usuarios',
            'export_csv'     => 1,
            'fecha_desde'    => $fecha_desde,
            'fecha_hasta'    => $fecha_hasta,
            'solo_con_horas' => $solo_con_horas ? 1 : null,
        ], admin_url('users.php'));
        echo ' <a href="' . esc_url($export_url) . '" class="button button-primary">Exportar CSV</a>';
        echo '</form>';

        // Estilos para filas clicables
        echo '<style>
            .wcuph-row-clickable { cursor: pointer; }
            .wcuph-row-clickable:hover { background-color: #f0f6fc !important; }
            .wcuph-negativo { color: #b32d2e; font-weight: 600; }
        </style>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Usuario</th><th>Email</th><th>Horas Compradas</th><th>Horas Reservadas</th><th>Diferencia</th>';
        echo '</tr></thead><tbody>';

        if (empty($datos)) {
            echo '<tr><td colspan="5">No se encontraron usuarios para el rango seleccionado.</td></tr>';
        } else {
            foreach ($datos as $fila) {
                $url_usuario   = get_edit_user_link($fila['id']);
                $clase_dif     = $fila['diferencia'] < 0 ? ' class="wcuph-negativo"' : '';
                $reservadas    = $this->formatear_horas($fila['reservadas']);
                $diferencia    = $this->formatear_horas($fila['diferencia']);

                echo '<tr class="wcuph-row-clickable" data-href="' . esc_url($url_usuario) . '">';
                echo '<td>' . esc_html($fila['nombre']) . '</td>';
                echo '<td>' . esc_html($fila['email']) . '</td>';
                echo '<td>' . esc_html($fila['compradas']) . '</td>';
                echo '<td>' . esc_html($reservadas) . '</td>';
                echo '<td' . $clase_dif . '>' . esc_html($diferencia) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        // Navegación al hacer clic en la fila
        echo '<script>
            document.querySelectorAll(".wcuph-row-clickable").forEach(function (row) {
                row.addEventListener("click", function () {
                    var href = this.getAttribute("data-href");
                    if (href) { window.location.href = href; }
                });
            });
        </script>';

        echo '</div>';
    }

    /**
     * Formatea las horas eliminando decimales innecesarios (10.0 -> 10, 10.5 -> 10.5).
     */
    private function formatear_horas($valor)
    {
        if (floor($valor) == $valor) {
            return (string) (int) $valor;
        }
        return rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
    }

    public function export_csv()
    {
        list($fecha_desde, $fecha_hasta) = $this->get_rango_fechas();
        $solo_con_horas = isset($_GET['solo_con_horas']);

        $datos = $this->obtener_datos_usuarios($fecha_desde, $fecha_hasta, $solo_con_horas);

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

        fputcsv($output, ['Usuario', 'Email', 'Horas Compradas', 'Horas Reservadas', 'Diferencia']);

        foreach ($datos as $fila) {
            fputcsv($output, [
                $fila['nombre'],
                $fila['email'],
                $fila['compradas'],
                $this->formatear_horas($fila['reservadas']),
                $this->formatear_horas($fila['diferencia']),
            ]);
        }

        fclose($output);
        exit;
    }
}
