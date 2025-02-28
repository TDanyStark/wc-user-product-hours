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
        plugins_url('assets/admin-script.js', dirname(__FILE__)),  
        ['jquery'], 
        time(),  // Usar timestamp evita caché
        true
      );

      wp_localize_script('wcuph-admin-script', 'wcuph_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'user_id' => get_current_user_id(),
    ]);
  }
  
}
