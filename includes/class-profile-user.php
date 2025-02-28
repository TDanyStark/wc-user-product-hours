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
    echo '<div class="wcuph-user-hours">';
    echo '<h2>Seccion de horas por usuario - plugin DanielAmado</h2>';
    $horas_acumuladas = get_user_meta($user->ID, 'wc_horas_acumuladas', true);

    // Sección de Horas Acumuladas
    echo '<h3>Horas Acumuladas por Producto</h3>';

    if (!$horas_acumuladas || !is_array($horas_acumuladas)) {
      echo '<p>No hay horas registradas.</p>';
    } else {
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

    // Sección de Historial de Reservas
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
    wcuph_log('Reservas encontradas: ' . count($reservas) . ' para el usuario ' . $user->ID);

    echo '<h2>Historial de Reservas</h2>';

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

        $duracion = (strtotime($fin) - strtotime($inicio)) / 3600;

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

      echo '</tbody>
          </table>';

      // Mostrar total de horas reservadas por producto
      echo '<h3>Total de horas reservadas por producto</h3>';
      echo '<table class="form-table">
              <tr>
                  <th>Producto</th>
                  <th>Horas Reservadas</th>
              </tr>';

      foreach ($horas_reservadas_por_producto as $producto => $total_horas) {
        echo '<tr>
                  <td>' . esc_html($producto) . '</td>
                  <td>' . esc_html($total_horas) . ' horas</td>
              </tr>';
      }

      echo '</table>';
    } else {
      echo '<p>No hay reservas registradas para este usuario.</p>';
    }

    // 🔥 Nueva sección: Productos comprados de la categoría "horas-ensambles"
    echo '<h2>Productos Comprados - Categoría "Horas Ensambles"</h2>';

    $productos_comprados = $this->obtener_productos_comprados_por_categoria($user->ID, 'horas-ensambles');

    if ($productos_comprados) {
      echo '<table class="widefat fixed">
              <thead>
                  <tr>
                      <th>Producto</th>
                      <th>Cantidad Comprada</th>
                      <th>Total Gastado</th>
                  </tr>
              </thead>
              <tbody>';

      foreach ($productos_comprados as $producto) {
        echo '<tr>
                  <td>' . esc_html($producto['nombre']) . '</td>
                  <td>' . esc_html($producto['cantidad']) . '</td>
                  <td>$' . esc_html(number_format($producto['total'], 2)) . '</td>
              </tr>';
      }

      echo '</tbody>
          </table>';
    } else {
      echo '<p>No ha comprado productos de la categoría "horas-ensambles".</p>';
    }

    echo '</div>'; // Cierre del div principal
  }

  private function obtener_productos_comprados_por_categoria($user_id, $category_slug)
  {
    global $wpdb;

    // Consulta para obtener productos comprados de la categoría específica
    $query = $wpdb->prepare("
        SELECT order_items.order_item_name AS nombre_producto,
               SUM(order_item_meta_qty.meta_value) AS cantidad_comprada,
               SUM(order_item_meta_total.meta_value) AS total_gastado
        FROM {$wpdb->prefix}woocommerce_order_items AS order_items
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_qty
            ON order_items.order_item_id = order_item_meta_qty.order_item_id
            AND order_item_meta_qty.meta_key = '_qty'
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_total
            ON order_items.order_item_id = order_item_meta_total.order_item_id
            AND order_item_meta_total.meta_key = '_line_total'
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_product_id
            ON order_items.order_item_id = order_item_meta_product_id.order_item_id
            AND order_item_meta_product_id.meta_key = '_product_id'
        INNER JOIN {$wpdb->prefix}term_relationships AS tr
            ON order_item_meta_product_id.meta_value = tr.object_id
        INNER JOIN {$wpdb->prefix}term_taxonomy AS tt
            ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->prefix}terms AS t
            ON tt.term_id = t.term_id
        WHERE tt.taxonomy = 'product_cat'
          AND t.slug = %s
          AND order_items.order_id IN (
              SELECT posts.ID FROM {$wpdb->prefix}posts AS posts
              WHERE posts.post_type = 'shop_order'
              AND posts.post_status IN ('wc-completed', 'wc-processing')
              AND posts.post_author = %d
          )
        GROUP BY order_items.order_item_name
    ", $category_slug, $user_id);

    $resultados = $wpdb->get_results($query, ARRAY_A);

    return $resultados ?: [];
  }
}
