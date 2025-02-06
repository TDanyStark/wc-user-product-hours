<?php
if (!defined('ABSPATH')) exit;

class Booking_Validation
{
  public function __construct()
  {
    add_action('woocommerce_add_to_cart_validation', [$this, 'wc_da_validar_horas_reserva'], 10, 5);
    add_action('woocommerce_remove_cart_item', [$this, 'wc_da_restaurar_horas_al_eliminar'], 10, 2);
    add_action('woocommerce_cart_item_restored', [$this, 'wc_da_borrar_horas_al_deshacer'], 10, 2);
    add_action('before_delete_post', [$this, 'log_deleted_booking_data']);
  }

  public function wc_da_validar_horas_reserva($passed, $product_id, $quantity, $variation_id = null, $variations = null, $cart_item_data = [])
  {
    wcuph_log('[DEBUG] Inicio validación. Producto ID: ' . $product_id);

    try {
      if (array_key_exists($product_id, WCUPH_Config::get_relacion_productos())) {
        wcuph_log('[DEBUG] Producto reservable detectado: ' . $product_id);

        $duracion = isset($_POST['wc_bookings_field_duration']) ? (int)$_POST['wc_bookings_field_duration'] : 0;
        wcuph_log('[DEBUG] Duración seleccionada: ' . $duracion . ' horas');

        if ($duracion <= 0) {
          wcuph_log('[ERROR] Duración no detectada');
          wc_add_notice(__('Selecciona una duración válida', 'WC-User-Product-Hours'), 'error');
          return false;
        }

        $user_id = get_current_user_id();
        wcuph_log('[DEBUG] Usuario ID: ' . $user_id);

        if ($user_id === 0) {
          wcuph_log('[ERROR] Usuario no autenticado');
          wc_add_notice(__('Debes iniciar sesión para reservar', 'WC-User-Product-Hours'), 'error');
          return false;
        }

        // Obtener horas específicas para este producto
        $producto_horas_id = WCUPH_Config::get_relacion_productos()[$product_id];
        $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
        $horas_disponibles = isset($horas_acumuladas[$producto_horas_id]) ? $horas_acumuladas[$producto_horas_id] : 0;

        wcuph_log('[DEBUG] Horas disponibles (producto ' . $producto_horas_id . '): ' . $horas_disponibles);

        if ($horas_disponibles < $duracion) {
          wcuph_log('[VALIDACIÓN FALLIDA] Horas solicitadas: ' . $duracion . ' | Disponibles: ' . $horas_disponibles);

          $producto_horas = wc_get_product($producto_horas_id);
          $enlace_compra = $producto_horas ? $producto_horas->get_permalink() : '#';

          wc_add_notice(sprintf(
            __('Necesitas %1$s horas para esta reserva. Dispones de %2$s. <a href="%3$s">Compra más horas</a>', 'WC-User-Product-Hours'),
            $duracion,
            $horas_disponibles,
            $enlace_compra
          ), 'error');

          return false;
        }

        wcuph_log('[VALIDACIÓN EXITOSA] Horas suficientes');
        // Guardar la duración en el carrito
        $cart_item_data['duracion_reserva'] = $duracion;
        // Deducir horas después de validación exitosa
        $horas_acumuladas[$producto_horas_id] = $horas_disponibles - $duracion;
        update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);
      }

      return $passed;
    } catch (Exception $e) {
      wcuph_log('[EXCEPCIÓN] Error: ' . $e->getMessage());
      return false;
    }
  }

  public function wc_da_restaurar_horas_al_eliminar($cart_item_key, $cart)
  {
    wcuph_log('[DEBUG] Inicio de eliminación de item. Clave: ' . $cart_item_key);

    // Obtener el item eliminado
    $cart_item = $cart->removed_cart_contents[$cart_item_key];

    wcuph_log('[DEBUG] Item eliminado: ' . $cart_item['booking']['_booking_id']);

    // Verificar si el producto está en la lista de relaciones
    $product_id = $cart_item['product_id'];
    $relaciones = WCUPH_Config::get_relacion_productos();

    wcuph_log('[DEBUG] Producto eliminado: ' . $product_id);

    if (array_key_exists($product_id, $relaciones)) {
      wcuph_log('[DEBUG] Producto con horas detectado: ' . $product_id);
      $user_id = get_current_user_id();
      $producto_horas_id = $relaciones[$product_id];

      // Obtener la duración desde los datos del carrito
      $duracion = isset($cart_item['booking']['_duration']) ? (int)$cart_item['booking']['_duration'] : 0;
      wcuph_log('[DEBUG] Duración del item eliminado: ' . $duracion);

      if ($duracion > 0 && $user_id) {
        wcuph_log('[DEBUG] Restaurando horas...');

        // Obtener horas acumuladas actuales
        $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
        $horas_actuales = $horas_acumuladas[$producto_horas_id] ?? 0;

        // Restaurar las horas
        $horas_acumuladas[$producto_horas_id] = $horas_actuales + $duracion;
        update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);

        wcuph_log('[RESTAURAR HORAS] Horas restauradas: ' . $duracion);
        wcuph_log('[DEBUG] Nuevas horas disponibles: ' . $horas_acumuladas[$producto_horas_id]);
      }
    }
  }

  public function wc_da_borrar_horas_al_deshacer($cart_item_key, $cart)
  {
    wcuph_log('[DEBUG] Inicio de deshacer eliminación de item. Clave: ' . $cart_item_key);

    // Obtener el item restaurado
    $cart_item = $cart->cart_contents[$cart_item_key];

    wcuph_log('[DEBUG] Item restaurado: ' . $cart_item['booking']['_booking_id']);

    // Verificar si el producto está en la lista de relaciones
    $product_id = $cart_item['product_id'];
    $relaciones = WCUPH_Config::get_relacion_productos();

    wcuph_log('[DEBUG] Producto restaurado: ' . $product_id);

    if (array_key_exists($product_id, $relaciones)) {
      wcuph_log('[DEBUG] Producto con horas detectado: ' . $product_id);
      $user_id = get_current_user_id();
      $producto_horas_id = $relaciones[$product_id];

      // Obtener la duración desde los datos del carrito
      $duracion = isset($cart_item['booking']['_duration']) ? (int)$cart_item['booking']['_duration'] : 0;
      wcuph_log('[DEBUG] Duración del item restaurado: ' . $duracion);

      if ($duracion > 0 && $user_id) {
        wcuph_log('[DEBUG] Borrando horas...');

        // Obtener horas acumuladas actuales
        $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
        $horas_actuales = $horas_acumuladas[$producto_horas_id] ?? 0;

        // Borrar las horas
        $horas_acumuladas[$producto_horas_id] = $horas_actuales - $duracion;
        update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);

        wcuph_log('[BORRAR HORAS] Horas borradas: ' . $duracion);
        wcuph_log('[DEBUG] Nuevas horas disponibles: ' . $horas_acumuladas[$producto_horas_id]);
      }
    }
  }

  public function log_deleted_booking_data($post_id)
  {
    wcuph_log('[DEBUG] Inicio de eliminación de booking. ID por CRON: ' . $post_id);
    // Verificar que sea un booking de WooCommerce
    if ('wc_booking' !== get_post_type($post_id)) return;

    // Obtener los metadatos
    $customer_id = get_post_meta($post_id, '_booking_customer_id', true);
    $product_id = get_post_meta($post_id, '_booking_product_id', true);
    $start = get_post_meta($post_id, '_booking_start', true);
    $end = get_post_meta($post_id, '_booking_end', true);
    wcuph_log('[DEBUG] Metadatos obtenidos: ' . print_r([$customer_id, $start, $end], true));

    $start_date = DateTime::createFromFormat('YmdHis', $start);
    $end_date = DateTime::createFromFormat('YmdHis', $end);

    $relaciones = WCUPH_Config::get_relacion_productos();

    if (array_key_exists($product_id, $relaciones)) {
      wcuph_log('[DEBUG] Producto con horas detectado: ' . $product_id);
      $horas_acumuladas = wcuph_get_accumulated_hours($customer_id);
      wcuph_log('[DEBUG] Horas acumuladas antes: ' . $horas_acumuladas[$relaciones[$product_id]]);
      $producto_horas_id = $relaciones[$product_id];
      $horas_acumuladas[$producto_horas_id] = $horas_acumuladas[$producto_horas_id] + $start_date->diff($end_date)->h;
      update_user_meta($customer_id, 'wc_horas_acumuladas', $horas_acumuladas);
      wcuph_log('[DEBUG] Horas actualizadas: ' . $horas_acumuladas[$producto_horas_id]);
    }

    if ($start_date && $end_date) {
      $interval = $start_date->diff($end_date);
      wcuph_log('[DEBUG] Intervalo de fechas: ' . $interval->format('%H horas %i minutos'));
    } else {
      wcuph_log('[ERROR] No se pudo obtener el intervalo de fechas');
    }


    // Crear mensaje para el log
    $log_message = sprintf(
      "Booking eliminado - ID: %s | Cliente: %s | Inicio: %s | Fin: %s",
      $post_id,
      $customer_id,
      $start_date->format('Y-m-d H:i:s'),
      $end_date->format('Y-m-d H:i:s')
    );

    // Escribir en el log de debug de WordPress
    wcuph_log($log_message);
  }

}
