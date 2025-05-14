<?php

if (!defined('ABSPATH')) exit;

class WCUPH_Load_Scripts
{
  public function __construct()
  {
    add_action('admin_enqueue_scripts', [$this, 'wcuph_cargar_script_admin']);
    add_action('wp_enqueue_scripts', [$this, 'wcuph_cargar_estilos_frontend']);
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
  
  /**
   * Carga los estilos CSS en el frontend para los botones del plugin
   */
  public function wcuph_cargar_estilos_frontend() {
      // Solo cargar en páginas de WooCommerce para optimizar rendimiento
      if (function_exists('is_woocommerce') && 
          (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) {
          
          wp_enqueue_style(
              'wcuph-styles',
              plugins_url('assets/wcuph-styles.css', dirname(__FILE__)),
              [],
              filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/wcuph-styles.css')
          );
      }
  }
}
