<?php

if (!defined('ABSPATH')) exit;

class ExtraFunctions
{
  public function __construct()
  {
    add_action('woocommerce_thankyou', [$this, 'auto_complete_paid_orders']);
  }

  function auto_complete_paid_orders($order_id)
  {
    if (!$order_id) return;

    $order = wc_get_order($order_id);

    // Verificar si el pedido ya está completado
    if ($order->get_status() === 'completed') return;

    // Verificar si el método de pago es Bold
    if ($order->get_payment_method() === 'bold_co') {
      $order->update_status('completed');
    }
  }
}
