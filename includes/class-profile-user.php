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
    echo '<div style="border-top: 2px solid #ddd; border-bottom: 2px solid #ddd; padding: 15px; margin: 40px 0;">';
    echo '<h2 style="margin-top: 30px;">Sección de horas por usuario - plugin DanielAmado</h2>';
    $horas_acumuladas = get_user_meta($user->ID, 'wc_horas_acumuladas', true);

    // Sección de Horas Acumuladas
    echo '<h3>Horas Acumuladas por Producto</h3>';

    if (!$horas_acumuladas || !is_array($horas_acumuladas)) {
      echo '<p>No hay horas registradas.</p>';
    } else {
      echo '<table class="form-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Horas Disponibles</th>
                </tr>
              </thead>
              <tbody>';

      foreach ($horas_acumuladas as $product_id => $horas) {
        $producto = get_post($product_id);
        $nombre_producto = $producto ? $producto->post_title : 'Producto Desconocido';

        echo '<tr>
                  <td>' . esc_html($nombre_producto) . '</td>
                  <td>' . esc_html($horas) . '</td>
              </tr>';
      }

      echo '</tbody></table>';
    }

    // Productos comprados de la categoría "horas-ensambles"
    echo '<h2 style="margin-top: 30px;">Productos Comprados - Categoría "Horas Ensambles"</h2>';

    $user_id = $user->ID;
    $pedidos = wc_get_orders([
      'customer_id' => $user_id,
      'status'      => 'completed',
      'limit'       => -1,
    ]);

    if (empty($pedidos)) {
      echo '<p>Este usuario no ha comprado productos aún.</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat fixed" style="margin-top:10px;">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Cantidad</th>
                <th>Pedido</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($pedidos as $pedido) {
      foreach ($pedido->get_items() as $item) {
        $producto = $item->get_product();
        if (!$producto) {
          continue;
        }

        $categorias = wp_get_post_terms($producto->get_id(), 'product_cat', ['fields' => 'names']);
        $categoria_nombre = !empty($categorias) ? implode(', ', $categorias) : 'Sin categoría';

        echo '<tr>
                <td>' . esc_html($producto->get_name()) . '</td>
                <td>' . esc_html($categoria_nombre) . '</td>
                <td>' . esc_html($item->get_quantity()) . '</td>
                <td><a href="' . esc_url(get_edit_post_link($pedido->get_id())) . '">#' . $pedido->get_id() . '</a></td>
                <td>' . esc_html($pedido->get_date_created()->date('Y-m-d')) . '</td>
            </tr>';
      }
    }

    echo '</tbody></table>';

    // Historial de Reservas
    $args = [
      'post_type'      => 'wc_booking',
      'posts_per_page' => -1,
      'post_status'    => ['any'],
      'meta_query'     => [
        [
          'key'   => '_booking_customer_id',
          'value' => $user->ID,
        ]
      ]
    ];
    $reservas = get_posts($args);

    echo '<h2 style="margin-top: 30px;">Historial de Reservas</h2>';

    if ($reservas) {
      echo '<table class="widefat fixed">
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

      $horas_reservadas_por_producto = [];

      foreach ($reservas as $reserva) {
        $producto_id = get_post_meta($reserva->ID, '_booking_product_id', true);
        $producto    = get_the_title($producto_id);
        $inicio      = get_post_meta($reserva->ID, '_booking_start', true);
        $fin         = get_post_meta($reserva->ID, '_booking_end', true);

        $inicio_fecha = date('d-m-Y H:i', strtotime($inicio));
        $fin_fecha    = date('d-m-Y H:i', strtotime($fin));
        $duracion     = (strtotime($fin) - strtotime($inicio)) / 3600;

        if (!isset($horas_reservadas_por_producto[$producto])) {
          $horas_reservadas_por_producto[$producto] = 0;
        }
        $horas_reservadas_por_producto[$producto] += $duracion;

        echo '<tr>
                  <td>#' . esc_html($reserva->ID) . '</td>
                  <td>' . esc_html($producto) . '</td>
                  <td>' . esc_html($inicio_fecha) . '</td>
                  <td>' . esc_html($fin_fecha) . '</td>
                  <td>' . esc_html($duracion) . ' horas</td>
              </tr>';
      }

      echo '</tbody></table>';

      // Total de horas reservadas por producto
      echo '<h3 style="margin-top: 30px;">Total de horas reservadas por producto</h3>';
      echo '<table class="form-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Horas Reservadas</th>
                </tr>
              </thead>
              <tbody>';

      foreach ($horas_reservadas_por_producto as $producto => $total_horas) {
        echo '<tr>
                  <td>' . esc_html($producto) . '</td>
                  <td>' . esc_html($total_horas) . ' horas</td>
              </tr>';
      }

      echo '</tbody></table>';
    } else {
      echo '<p>No hay reservas registradas para este usuario.</p>';
    }
    echo '</div>';
  }
}
