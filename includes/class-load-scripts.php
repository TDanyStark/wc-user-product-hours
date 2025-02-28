<?php

if (!defined('ABSPATH')) exit;

class WCUPH_Load_Scripts
{
  public function __construct()
  {
    add_action('admin_enqueue_scripts', [$this, 'wcuph_cargar_script_admin']);
  }

  public function wcuph_cargar_script_admin($hook)
  {
      error_log("Cargando script en: " . $hook);
  
      wp_enqueue_script(
          'wcuph-admin-script',
          plugin_dir_url(__FILE__) . 'assets/admin-script.js',
          ['jquery'],
          '1.0',
          true
      );
  
      wp_localize_script('wcuph-admin-script', 'wcuph_ajax', [
          'ajaxurl' => admin_url('admin-ajax.php'),
          'user_id' => get_current_user_id(),
      ]);
  }
  
}
