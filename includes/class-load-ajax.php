<?php

if (!defined('ABSPATH')) exit;

class WCUPH_load_AJAX{
  public function __construct()
  {
    add_action('wp_ajax_actualizar_horas_usuario', [$this, 'wcuph_actualizar_horas_usuario']);
  }

  public function wcuph_actualizar_horas_usuario()
  {
    if (!current_user_can('edit_users')) {
      wp_send_json_error('No tienes permisos');
      wp_die();
    }

    $user_id = intval($_POST['user_id']);
    $product_id = intval($_POST['product_id']);
    $new_hours = intval($_POST['new_hours']);

    $horas_acumuladas = get_user_meta($user_id, 'wc_horas_acumuladas', true);
    if (!is_array($horas_acumuladas)) {
      $horas_acumuladas = [];
    }

    $horas_acumuladas[$product_id] = $new_hours;
    update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);

    wp_send_json_success(['mensaje' => 'Horas actualizadas', 'data' => $horas_acumuladas]);
    wp_die();
  }
}
