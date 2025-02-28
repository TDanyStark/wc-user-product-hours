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

    // Obtener todas las reservas asociadas a este usuario
    $args = [
      'post_type'      => 'wc_booking',
      'posts_per_page' => -1,
      'meta_query'     => [
        [
          'key'   => '_booking_customer_id',
          'value' => $user->ID,
        ]
      ]
    ];
    $reservas = get_posts($args);

    echo '<h2>Historial de Reservas</h2>';

    if ($reservas) {
      echo '<table class="widefat fixed" style="margin-top: 10px;">
              <thead>
                  <tr>
                      <th>ID de Reserva</th>
                      <th>Producto</th>
                      <th>Fecha de Inicio</th>
                      <th>Fecha de Fin</th>
                      <th>Duración (horas)</th>
                  </tr>
              </thead>
              <tbody>';

      $total_horas_reservadas = 0;

      foreach ($reservas as $reserva) {
        $producto_id = get_post_meta($reserva->ID, '_booking_product_id', true);
        $producto    = get_the_title($producto_id);
        $inicio      = get_post_meta($reserva->ID, '_booking_start', true);
        $fin         = get_post_meta($reserva->ID, '_booking_end', true);

        // Convertir fechas al formato legible
        $inicio_fecha = date('d-m-Y H:i', strtotime($inicio));
        $fin_fecha    = date('d-m-Y H:i', strtotime($fin));

        // Calcular duración en horas
        $duracion = (strtotime($fin) - strtotime($inicio)) / 3600;
        $total_horas_reservadas += $duracion;

        echo '<tr>
                  <td>#' . $reserva->ID . '</td>
                  <td>' . esc_html($producto) . '</td>
                  <td>' . esc_html($inicio_fecha) . '</td>
                  <td>' . esc_html($fin_fecha) . '</td>
                  <td>' . esc_html($duracion) . ' horas</td>
              </tr>';
      }

      echo '</tbody>
          </table>';

      // Mostrar total de horas reservadas
      echo '<p><strong>Total de horas reservadas: ' . esc_html($total_horas_reservadas) . ' horas</strong></p>';
    } else {
      echo '<p>No hay reservas registradas para este usuario.</p>';
    }
  }
}
