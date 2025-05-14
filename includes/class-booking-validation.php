<?php
if (!defined('ABSPATH')) exit;

class Booking_Validation
{  public function __construct()
  {
    add_action('woocommerce_add_to_cart_validation', [$this, 'wc_da_validar_horas_reserva'], 10, 5);
    add_action('woocommerce_checkout_process', [$this, 'wc_da_verificar_horas_antes_pago']);
    add_action('woocommerce_order_status_completed', [$this, 'wc_da_descontar_horas_al_completar_orden'], 10, 1);
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
          $enlace_compra = $producto_horas ? $producto_horas->get_permalink() : '#';          wc_add_notice(sprintf(
            __('Necesitas comprar %1$s horas para esta reserva. <a href="%2$s" class="btn-wc-user-product-hours-checkout">Compra más horas</a>', 'WC-User-Product-Hours'),
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
          $enlace_compra = $producto_horas ? $producto_horas->get_permalink() : '#';          wc_add_notice(sprintf(
            __('Necesitas %1$s horas para esta reserva. Dispones de %2$s. <a href="%3$s" class="btn-wc-user-product-hours-checkout">Compra más horas</a>', 'WC-User-Product-Hours'),
            $duracion,
            $horas_disponibles,
            $enlace_compra
          ), 'error');

          return false;
        }        wcuph_log('[VALIDACIÓN EXITOSA] Horas suficientes'. ' - '. $user_log);
        // Guardar la duración en el carrito para usarla después
        $cart_item_data['duracion_reserva'] = $duracion;
      }

      return $passed;
    } catch (Exception $e) {
      wcuph_log('[EXCEPCIÓN] Error: ' . $e->getMessage());
      return false;
    }
  }  // Las funciones wc_da_restaurar_horas_al_eliminar y wc_da_borrar_horas_al_deshacer fueron eliminadas
  // ya que ahora las horas se descuentan al confirmar la reserva
  
  public function wc_da_verificar_horas_antes_pago()
  {
    $user_id = get_current_user_id();
    $user_log = $user_id ? $user_id : 'SINUSER';
    
    if ($user_id === 0) {
      // No es necesario verificar si no hay usuario (ya se validó al añadir al carrito)
      return;
    }
    
    wcuph_log('[DEBUG] Verificando horas disponibles antes del pago - Usuario: ' . $user_log);
    
    $relaciones = WCUPH_Config::get_relacion_productos();
    $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
    $horas_necesarias = [];
    
    // Recorrer todos los items del carrito
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
      $product_id = $cart_item['product_id'];
      
      // Verificar si es un producto de reserva que requiere horas
      if (array_key_exists($product_id, $relaciones)) {
        wcuph_log('[DEBUG] Producto reservable en carrito: ' . $product_id . ' - '. $user_log);
        $producto_horas_id = $relaciones[$product_id];
        
        // Obtener la duración desde los datos del carrito
        $duracion = isset($cart_item['duracion_reserva']) ? $cart_item['duracion_reserva'] : 0;
        
        // Si no está en los datos del carrito, intentar obtenerla de los datos de WC Bookings
        if ($duracion <= 0 && isset($cart_item['booking'])) {
          $duracion = isset($cart_item['booking']['_duration']) ? (int)$cart_item['booking']['_duration'] : 0;
        }
        
        wcuph_log('[DEBUG] Duración detectada en carrito: ' . $duracion . ' - '. $user_log);
        
        if ($duracion > 0) {
          if (!isset($horas_necesarias[$producto_horas_id])) {
            $horas_necesarias[$producto_horas_id] = 0;
          }
          
          $horas_necesarias[$producto_horas_id] += $duracion;
        }
      }
    }
    
    // Verificar si hay suficientes horas disponibles para cada producto
    foreach ($horas_necesarias as $producto_id => $horas) {
      $horas_disponibles = isset($horas_acumuladas[$producto_id]) ? $horas_acumuladas[$producto_id] : 0;
      
      wcuph_log('[DEBUG] Verificando producto: ' . $producto_id . ' - Horas necesarias: ' . $horas . ' - Horas disponibles: ' . $horas_disponibles . ' - '. $user_log);
      
      if ($horas_disponibles < $horas) {
        wcuph_log('[VERIFICACIÓN FALLIDA] Horas insuficientes para el producto: ' . $producto_id . ' - '. $user_log);
        
        $producto_horas = wc_get_product($producto_id);
        $enlace_compra = $producto_horas ? $producto_horas->get_permalink() : '#';
        $enlace_carrito = wc_get_cart_url();
        
        wc_add_notice(sprintf(
          __('No tienes suficientes horas para completar la compra. Necesitas %1$s horas para las reservas en tu carrito, pero solo dispones de %2$s. <a href="%3$s" class="btn-wc-user-product-hours-checkout">Compra más horas</a> o <a href="%4$s" class="btn-wc-user-product-hours-checkout">Ve a tu carrito</a> y elimina las reservas que no necesites.', 'WC-User-Product-Hours'),
          $horas,
          $horas_disponibles,
          $enlace_compra,
          $enlace_carrito
        ), 'error');
        
        return;
      }
    }
    
    wcuph_log('[VERIFICACIÓN EXITOSA] Horas suficientes para todas las reservas en el carrito. - ' . $user_log);
  }
  public function wc_da_descontar_horas_al_completar_orden($order_id)
  {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $user_log = $user_id ? $user_id : 'SINUSER';

    wcuph_log('[DEBUG] Procesando descuento de horas al completar orden: ' . $order_id. ' - '. $user_log);

    if (!$user_id) {
      wcuph_log('[ERROR] No hay usuario asociado a la orden: ' . $order_id. ' - '. $user_log);
      return;
    }

    $relaciones = WCUPH_Config::get_relacion_productos();
    $horas_a_descontar = [];

    // Recorrer todos los items de la orden
    foreach ($order->get_items() as $item) {
      $product_id = $item->get_product_id();
      
      // Verificar si es un producto de reserva que requiere horas
      if (array_key_exists($product_id, $relaciones)) {
        wcuph_log('[DEBUG] Producto de reserva detectado: ' . $product_id. ' - '. $user_log);
        $producto_horas_id = $relaciones[$product_id];
        
        // Obtener la duración desde los datos de la reserva
        $item_data = $item->get_meta_data();
        $duracion = 0;
        
        // Buscar la duración en los metadatos del item
        foreach ($item_data as $meta) {
          $data = $meta->get_data();
          if ($data['key'] === '_booking_duration') {
            $duracion = (int)$data['value'];
            break;
          }
        }

        if ($duracion > 0) {
          wcuph_log('[DEBUG] Duración detectada: ' . $duracion. ' - '. $user_log);
          
          if (!isset($horas_a_descontar[$producto_horas_id])) {
            $horas_a_descontar[$producto_horas_id] = 0;
          }
          
          $horas_a_descontar[$producto_horas_id] += $duracion;
        }
      }
    }

    // Si hay horas para descontar, actualizamos los metadatos del usuario
    if (!empty($horas_a_descontar)) {
      $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
      
      foreach ($horas_a_descontar as $producto_id => $horas) {
        $horas_actuales = isset($horas_acumuladas[$producto_id]) ? $horas_acumuladas[$producto_id] : 0;
        $horas_acumuladas[$producto_id] = $horas_actuales - $horas;
        
        wcuph_log('[DESCONTAR HORAS] Producto: ' . $producto_id . ', Horas antes: ' . $horas_actuales . 
          ', Horas descontadas: ' . $horas . ', Horas después: ' . $horas_acumuladas[$producto_id]. ' - '. $user_log);
      }
      
      update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);
      wcuph_log('[DEBUG] Horas actualizadas para el usuario: ' . $user_id. ' - '. $user_log);
    }
  }
}
