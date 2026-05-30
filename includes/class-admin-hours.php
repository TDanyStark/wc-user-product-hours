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
     * Resuelve el estado de los checkboxes de filtro.
     *
     * Como un checkbox desmarcado no se envía en $_GET, usamos un campo oculto
     * "wcuph_filtros" como marcador de envío del formulario:
     *  - Sin marcador (primera carga): se aplican los valores por defecto (ambos activos).
     *  - Con marcador: se respeta exactamente lo que el usuario marcó/desmarcó.
     *
     * @return array{0:bool,1:bool} [solo_con_horas, solo_con_diferencia]
     */
    private function get_filtros_checkbox()
    {
        $formulario_enviado = isset($_GET['wcuph_filtros']);

        if (!$formulario_enviado) {
            // Primera carga: ambos filtros activos por defecto.
            return [true, true];
        }

        return [
            isset($_GET['solo_con_horas']),
            isset($_GET['solo_con_diferencia']),
        ];
    }

    /**
     * Obtiene los datos agregados por usuario (excluyendo administradores).
     *
     * @param string $fecha_desde
     * @param string $fecha_hasta
     * @param bool   $solo_con_horas
     * @param bool   $solo_con_diferencia
     * @return array<int,array{id:int,nombre:string,email:string,compradas:int,reservadas:float,diferencia:float}>
     */
    private function obtener_datos_usuarios($fecha_desde, $fecha_hasta, $solo_con_horas, $solo_con_diferencia)
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
            $diferencia = $compradas - $reservadas;

            if ($solo_con_horas && $compradas == 0 && $reservadas == 0) {
                continue;
            }

            if ($solo_con_diferencia && $diferencia == 0) {
                continue;
            }

            $datos[] = [
                'id'         => (int) $usuario->ID,
                'nombre'     => $usuario->display_name,
                'email'      => $usuario->user_email,
                'compradas'  => $compradas,
                'reservadas' => $reservadas,
                'diferencia' => $diferencia,
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
        list($solo_con_horas, $solo_con_diferencia) = $this->get_filtros_checkbox();

        $datos = $this->obtener_datos_usuarios($fecha_desde, $fecha_hasta, $solo_con_horas, $solo_con_diferencia);

        echo '<div class="wrap"><h1>Horas por Usuario</h1>';

        echo '<p class="description" style="margin: 10px 0 20px;">Las horas <strong>compradas</strong> se calculan desde las órdenes completadas dentro del rango. Las horas <strong>reservadas</strong> consideran solo reservas confirmadas y pagadas cuyo inicio cae en el rango. No se muestran usuarios administradores.</p>';

        // Formulario de filtros
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="horas-usuarios">';
        // Marcador de envío: permite detectar checkboxes desmarcados intencionalmente.
        echo '<input type="hidden" name="wcuph_filtros" value="1">';
        echo '<label style="margin-right: 10px;">Desde: <input type="date" name="fecha_desde" value="' . esc_attr($fecha_desde) . '"></label>';
        echo '<label style="margin-right: 10px;">Hasta: <input type="date" name="fecha_hasta" value="' . esc_attr($fecha_hasta) . '"></label>';
        echo '<label style="margin-right: 10px;"><input type="checkbox" name="solo_con_horas" value="1" ' . checked($solo_con_horas, true, false) . '> Mostrar solo usuarios con horas</label> ';
        echo '<label style="margin-right: 10px;"><input type="checkbox" name="solo_con_diferencia" value="1" ' . checked($solo_con_diferencia, true, false) . '> Mostrar solo diferencia distinta de 0</label> ';
        submit_button('Filtrar', 'secondary', '', false);

        $export_url = add_query_arg([
            'page'                => 'horas-usuarios',
            'export_csv'          => 1,
            'wcuph_filtros'       => 1,
            'fecha_desde'         => $fecha_desde,
            'fecha_hasta'         => $fecha_hasta,
            'solo_con_horas'      => $solo_con_horas ? 1 : null,
            'solo_con_diferencia' => $solo_con_diferencia ? 1 : null,
        ], admin_url('users.php'));
        echo ' <a href="' . esc_url($export_url) . '" class="button button-primary">Exportar CSV</a>';
        echo '</form>';

        echo '<style>
            .wcuph-negativo { color: #b32d2e; font-weight: 600; }
        </style>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Usuario</th><th>Email</th><th>Horas Compradas</th><th>Horas Reservadas</th><th>Diferencia</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        if (empty($datos)) {
            echo '<tr><td colspan="6">No se encontraron usuarios para el rango seleccionado.</td></tr>';
        } else {
            foreach ($datos as $fila) {
                $url_usuario   = get_edit_user_link($fila['id']);
                $clase_dif     = $fila['diferencia'] < 0 ? ' class="wcuph-negativo"' : '';
                $reservadas    = $this->formatear_horas($fila['reservadas']);
                $diferencia    = $this->formatear_horas($fila['diferencia']);

                echo '<tr>';
                echo '<td>' . esc_html($fila['nombre']) . '</td>';
                echo '<td>' . esc_html($fila['email']) . '</td>';
                echo '<td>' . esc_html($fila['compradas']) . '</td>';
                echo '<td>' . esc_html($reservadas) . '</td>';
                echo '<td' . $clase_dif . '>' . esc_html($diferencia) . '</td>';
                echo '<td><a href="' . esc_url($url_usuario) . '" class="button button-small" target="_blank" rel="noopener">Ver usuario</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

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
        list($solo_con_horas, $solo_con_diferencia) = $this->get_filtros_checkbox();

        $datos = $this->obtener_datos_usuarios($fecha_desde, $fecha_hasta, $solo_con_horas, $solo_con_diferencia);

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
