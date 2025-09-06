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

    // Productos comprados de la categoría "horas-ensambles"
    echo '<h2 style="margin-top: 30px;">Productos Comprados</h2>';

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
    $productos_debug = []; // para depuración (admin)

    foreach ($pedidos as $pedido) {
      foreach ($pedido->get_items() as $item) {
        $producto = $item->get_product();
        if (!$producto) {
          continue;
        }
        // Si es una variación, usar el ID del padre porque las variaciones no tienen categorías
        $product_id_for_terms = (method_exists($producto, 'is_type') && $producto->is_type('variation')) ? $producto->get_parent_id() : $producto->get_id();

        // Obtener nombres y slugs; si no hay términos, intentar con el padre (por si hay inconsistencia)
        $categorias = wp_get_post_terms($product_id_for_terms, 'product_cat', ['fields' => 'names']);
        $categoria_slugs = wp_get_post_terms($product_id_for_terms, 'product_cat', ['fields' => 'slugs']);
        if (empty($categorias)) {
          $parent_id = wp_get_post_parent_id($product_id_for_terms);
          if ($parent_id) {
            $categorias = wp_get_post_terms($parent_id, 'product_cat', ['fields' => 'names']);
            $categoria_slugs = wp_get_post_terms($parent_id, 'product_cat', ['fields' => 'slugs']);
            // actualizar el id usado para referencia
            $product_id_for_terms = $parent_id;
          }
        }
        $categoria_nombre = !empty($categorias) ? implode(', ', $categorias) : 'Sin categoría';

        // Guardar info para depuración (visible solo a administradores)
        $productos_debug[] = [
          'order_id' => $pedido->get_id(),
          'item_name' => $producto->get_name(),
          'product_lookup_id' => $product_id_for_terms,
          'term_names' => $categorias,
          'term_slugs' => $categoria_slugs,
        ];

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

    // Pedidos de Productos Horas
    echo '<h2 style="margin-top: 30px;">Pedidos de Productos Horas</h2>';
    $productos_horas = [];
    foreach ($pedidos as $pedido) {
      foreach ($pedido->get_items() as $item) {
        $producto = $item->get_product();
        if (!$producto) continue;

        // Usar ID del producto padre si es variación
        $product_id_for_terms = (method_exists($producto, 'is_type') && $producto->is_type('variation')) ? $producto->get_parent_id() : $producto->get_id();
        $categorias = wp_get_post_terms($product_id_for_terms, 'product_cat', ['fields' => 'slugs']);
        if (in_array('horas-ensambles', (array) $categorias)) {
          $productos_horas[] = [
            'producto' => $producto->get_name(),
            'cantidad' => $item->get_quantity(),
            'pedido_id' => $pedido->get_id(),
            'fecha' => $pedido->get_date_created()->date('Y-m-d'),
          ];
        }
      }
    }
    if (!empty($productos_horas)) {
      echo '<table class="widefat fixed">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Pedido</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>';
      foreach ($productos_horas as $item) {
        echo '<tr>
                <td>' . esc_html($item['producto']) . '</td>
                <td>' . esc_html($item['cantidad']) . '</td>
                <td><a href="' . esc_url(get_edit_post_link($item['pedido_id'])) . '">#' . $item['pedido_id'] . '</a></td>
                <td>' . esc_html($item['fecha']) . '</td>
              </tr>';
      }
      echo '</tbody></table>';
    } else {
      echo '<p>No hay pedidos de productos horas.</p>';
    }

    echo '</div>';
  }
}
