<?php

if (!defined('ABSPATH')) exit;

class ExtraFunctions
{
  public function __construct()
  {
    add_action('woocommerce_thankyou', [$this, 'auto_complete_paid_orders']);
    add_action('woocommerce_thankyou', [$this, 'agregar_boton_agendar_ensamble'], 5);

  }

  public function auto_complete_paid_orders($order_id)
  {
    if (!$order_id) return;

    $order = wc_get_order($order_id);

    // Verificar si el pedido ya está completado
    if ($order->get_status() !== 'processing') return;

    // Verificar si el método de pago es Bold
    if ($order->get_payment_method() === 'bold_co') {
      $order->update_status('completed');
    }
  }


  public function agregar_boton_agendar_ensamble($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    // solo si hay un pedido de la categoria agenda mostrar
    $items = $order->get_items();
    $agenda = false;

    foreach ($items as $item) {
      $product_id = $item->get_product_id();
      $product = wc_get_product($product_id);
      $categories = $product->get_category_ids();
      if (in_array(46, $categories)) {
        $agenda = true;
        break;
      }
    }
    if (!$agenda) return;

    $agendar_url = '/ensambles#agenda'; // Cambia esta URL por la correcta

    echo '<div style="margin: 20px 0; text-align: center;">';
    echo '<a href="' . esc_url($agendar_url) . '" class="btn-agenda-thank">2. Agendar Ensamble</a>';
    echo '</div>';
}

}
