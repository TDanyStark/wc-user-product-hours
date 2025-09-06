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
    echo '<h2 style="margin-top: 30px; font-size: 1.6rem;">Sección de horas por usuario - plugin Daniel Amado</h2>';
    $horas_acumuladas = get_user_meta($user->ID, 'wc_horas_acumuladas', true);

    // Sección de Horas Acumuladas
    echo '<h3>Horas Acumuladas por Producto</h3>';

    if (!$horas_acumuladas || !is_array($horas_acumuladas)) {
      echo '<p>No hay horas registradas.</p>';
    } else {
      echo '<table class="widefat fixed">
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
                <td>
                    <input type="number" min="0" value="' . esc_attr($horas) . '" 
                    data-product-id="' . esc_attr($product_id) . '" 
                    data-user-id="' . esc_attr($user->ID) . '" 
                    class="wc-horas-input">
                </td>
            </tr>';
    }

      echo '</tbody></table>';
    }

    // No mostramos la tabla general de "Productos Comprados" aquí.
    // Solo recuperamos los pedidos del usuario para usarlos en las secciones siguientes.
    $user_id = $user->ID;
    $pedidos = wc_get_orders([
      'customer_id' => $user_id,
      'status'      => 'completed',
      'limit'       => -1,
    ]);

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
      echo '<table class="widefat fixed">
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

    // Pedidos de Productos Horas: sumar horas por producto (agrupar por producto padre)
    echo '<h2 style="margin-top: 30px;">Pedidos de Productos Horas</h2>';
    $horas_por_producto = [];
    foreach ($pedidos as $pedido) {
      foreach ($pedido->get_items() as $item) {
        $producto = $item->get_product();
        if (!$producto) continue;

        // Usar ID del producto padre si es variación (las variaciones no tienen taxonomías)
        $product_id_for_terms = (method_exists($producto, 'is_type') && $producto->is_type('variation')) ? $producto->get_parent_id() : $producto->get_id();

        // Solo considerar productos en la categoría horas-ensambles
        $categorias = wp_get_post_terms($product_id_for_terms, 'product_cat', ['fields' => 'slugs']);
        if (!in_array('horas-ensambles', (array) $categorias)) continue;

        // Agrupar por el producto padre (mostrar nombre del padre)
        $group_id = (method_exists($producto, 'is_type') && $producto->is_type('variation')) ? $producto->get_parent_id() : $producto->get_id();
        $parent_post = get_post($group_id);
        $group_name = $parent_post ? $parent_post->post_title : $producto->get_name();

        $cantidad = floatval($item->get_quantity());
        if (!isset($horas_por_producto[$group_id])) {
          $horas_por_producto[$group_id] = [
            'name' => $group_name,
            'total' => 0,
          ];
        }
        $horas_por_producto[$group_id]['total'] += $cantidad;
      }
    }

    if (!empty($horas_por_producto)) {
      echo '<table class="widefat fixed">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Horas Totales</th>
                </tr>
              </thead>
              <tbody>';

      foreach ($horas_por_producto as $row) {
        echo '<tr>
                <td>' . esc_html($row['name']) . '</td>
                <td>' . esc_html($row['total']) . ' horas</td>
              </tr>';
      }

      echo '</tbody></table>';
    } else {
      echo '<p>No hay pedidos de productos horas.</p>';
    }

    echo '</div>';
  }
}
