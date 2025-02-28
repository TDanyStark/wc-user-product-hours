<?php
if (!defined('ABSPATH')) exit;

class Booking_Validation
{
  public function __construct()
  {
    add_action('woocommerce_add_to_cart_validation', [$this, 'wc_da_validar_horas_reserva'], 10, 5);
    add_action('woocommerce_remove_cart_item', [$this, 'wc_da_restaurar_horas_al_eliminar'], 10, 2);
    add_action('woocommerce_cart_item_restored', [$this, 'wc_da_borrar_horas_al_deshacer'], 10, 2);
    add_action('before_delete_post', [$this, 'wc_da_deleted_booking_data']);
  }

  public function wc_da_validar_horas_reserva($passed, $product_id, $quantity, $variation_id = null, $variations = null, $cart_item_data = [])
  {
    try {
      $user_id = get_current_user_id();
      $user_log = $user_id ? $user_id : 'SINUSER';
      wcuph_log('[DEBUG] Inicio validación. Producto ID: ' . $product_id. ' - '. $user_log);


      if (array_key_exists($product_id, WCUPH_Config::get_relacion_productos())) {
        wcuph_log('[DEBUG] Producto reservable detectado: ' . $product_id. ' - '. $user_log);

        $duracion = isset($_POST['wc_bookings_field_duration']) ? (int)$_POST['wc_bookings_field_duration'] : 0;
        wcuph_log('[DEBUG] Duración seleccionada: ' . $duracion . ' horas'. ' - '. $user_log);

        if ($duracion <= 0) {
          wcuph_log('[ERROR] Duración no detectada'. ' - '. $user_log);
          wc_add_notice(__('Selecciona una duración válida', 'WC-User-Product-Hours'), 'error');
          return false;
        }
        
        wcuph_log('[DEBUG] Usuario ID: ' . $user_id);
        $producto_horas_id = WCUPH_Config::get_relacion_productos()[$product_id];

        if ($user_id === 0) {
          wcuph_log('[ERROR] Usuario no autenticado '.$user_log);
          $producto_horas = wc_get_product($producto_horas_id);
          $enlace_compra = $producto_horas ? $producto_horas->get_permalink() : '#';

          wc_add_notice(sprintf(
            __('Necesitas comprar %1$s horas para esta reserva. <a href="%2$s">Compra más horas</a>', 'WC-User-Product-Hours'),
            $duracion,
            $enlace_compra
          ), 'error');
          return false;
        }

        // Obtener horas específicas para este producto
        $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
        $horas_disponibles = isset($horas_acumuladas[$producto_horas_id]) ? $horas_acumuladas[$producto_horas_id] : 0;

        wcuph_log('[DEBUG] Horas disponibles (producto ' . $producto_horas_id . '): ' . $horas_disponibles. ' - '. $user_log);

        if ($horas_disponibles < $duracion) {
          wcuph_log('[VALIDACIÓN FALLIDA] Horas solicitadas: ' . $duracion . ' | Disponibles: ' . $horas_disponibles. ' - '. $user_log);

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

        wcuph_log('[VALIDACIÓN EXITOSA] Horas suficientes'. ' - '. $user_log);
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
    $user_id = get_current_user_id();
    $user_log = $user_id ? $user_id : 'SINUSER';

    wcuph_log('[DEBUG] Inicio de eliminación de item. Clave: ' . $cart_item_key. ' - '. $user_log);

    // Obtener el item eliminado
    $cart_item = $cart->removed_cart_contents[$cart_item_key];

    wcuph_log('[DEBUG] Reserva a eliminar: ' . $cart_item['booking']['_booking_id']. ' - '. $user_log);

    // Verificar si el producto está en la lista de relaciones
    $product_id = $cart_item['product_id'];
    $relaciones = WCUPH_Config::get_relacion_productos();

    wcuph_log('[DEBUG] Producto eliminado: ' . $product_id. ' - '. $user_log);

    if (array_key_exists($product_id, $relaciones)) {
      wcuph_log('[DEBUG] Producto con horas detectado: ' . $product_id. ' - '. $user_log);
      $producto_horas_id = $relaciones[$product_id];

      // Obtener la duración desde los datos del carrito
      $duracion = isset($cart_item['booking']['_duration']) ? (int)$cart_item['booking']['_duration'] : 0;
      wcuph_log('[DEBUG] Duración del item eliminado: ' . $duracion. ' - '. $user_log);

      if ($duracion > 0 && $user_id) {
        wcuph_log('[DEBUG] Restaurando horas...'. ' - '. $user_log);

        // Obtener horas acumuladas actuales
        $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
        $horas_actuales = $horas_acumuladas[$producto_horas_id] ?? 0;

        // Restaurar las horas
        $horas_acumuladas[$producto_horas_id] = $horas_actuales + $duracion;
        update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);

        wcuph_log('[RESTAURAR HORAS] Horas restauradas: ' . $duracion. ' - '. $user_log);
        wcuph_log('[DEBUG] Nuevas horas disponibles: ' . $horas_acumuladas[$producto_horas_id]. ' - '. $user_log);
      }
    }
  }

  public function wc_da_borrar_horas_al_deshacer($cart_item_key, $cart)
  {
    $user_id = get_current_user_id();
    $user_log = $user_id ? $user_id : 'SINUSER';
    wcuph_log('[DEBUG] Inicio de deshacer eliminación de item. Clave: ' . $cart_item_key. ' - '. $user_log);

    // Obtener el item restaurado
    $cart_item = $cart->cart_contents[$cart_item_key];

    wcuph_log('[DEBUG] Reserva a eliminar: ' . $cart_item['booking']['_booking_id']. ' - '. $user_log);

    // Verificar si el producto está en la lista de relaciones
    $product_id = $cart_item['product_id'];
    $relaciones = WCUPH_Config::get_relacion_productos();

    wcuph_log('[DEBUG] Producto restaurado: ' . $product_id. ' - '. $user_log);

    if (array_key_exists($product_id, $relaciones)) {
      wcuph_log('[DEBUG] Producto con horas detectado: ' . $product_id. ' - '. $user_log);
      $producto_horas_id = $relaciones[$product_id];

      // Obtener la duración desde los datos del carrito
      $duracion = isset($cart_item['booking']['_duration']) ? (int)$cart_item['booking']['_duration'] : 0;
      wcuph_log('[DEBUG] Duración del item restaurado: ' . $duracion. ' - '. $user_log);

      if ($duracion > 0 && $user_id) {
        wcuph_log('[DEBUG] Borrando horas...'. ' - '. $user_log);

        // Obtener horas acumuladas actuales
        $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
        $horas_actuales = $horas_acumuladas[$producto_horas_id] ?? 0;

        // Borrar las horas
        $horas_acumuladas[$producto_horas_id] = $horas_actuales - $duracion;
        update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);

        wcuph_log('[BORRAR HORAS] Horas borradas: ' . $duracion. ' - '. $user_log);
        wcuph_log('[DEBUG] Nuevas horas disponibles: ' . $horas_acumuladas[$producto_horas_id]. ' - '. $user_log);
      }
    }
  }

  public function wc_da_deleted_booking_data($post_id)
  {
    wcuph_log('[DEBUG] Inicio de eliminación de booking. ID por CRON: ' . $post_id);
    // Verificar que sea un booking de WooCommerce
    if ('wc_booking' !== get_post_type($post_id)) return;

    // Obtener los metadatos
    $customer_id = get_post_meta($post_id, '_booking_customer_id', true);
    $user_log = $customer_id ? $customer_id : 'SINUSER';
    $product_id = get_post_meta($post_id, '_booking_product_id', true);
    $start = get_post_meta($post_id, '_booking_start', true);
    $end = get_post_meta($post_id, '_booking_end', true);
    wcuph_log('[DEBUG] Metadatos obtenidos: ' . print_r([$customer_id, $product_id, $start, $end], true). ' - '. $user_log);

    $start_date = DateTime::createFromFormat('YmdHis', $start);
    $end_date = DateTime::createFromFormat('YmdHis', $end);

    $relaciones = WCUPH_Config::get_relacion_productos();

    if (array_key_exists($product_id, $relaciones)) {
      wcuph_log('[DEBUG] Producto con horas detectado: ' . $product_id. ' - '. $user_log);
      $horas_acumuladas = wcuph_get_accumulated_hours($customer_id);
      wcuph_log('[DEBUG] Horas acumuladas antes: ' . $horas_acumuladas[$relaciones[$product_id]]. ' - '. $user_log);
      $producto_horas_id = $relaciones[$product_id];
      $horas_acumuladas[$producto_horas_id] = $horas_acumuladas[$producto_horas_id] + $start_date->diff($end_date)->h;
      update_user_meta($customer_id, 'wc_horas_acumuladas', $horas_acumuladas);
      wcuph_log('[DEBUG] Horas actualizadas: ' . $horas_acumuladas[$producto_horas_id]. ' - '. $user_log);
    }
  }
}
