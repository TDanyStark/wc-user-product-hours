<?php

/**
 * Plugin Name: WC User Product Hours
 * Plugin URI: https://daniel-amado.com/
 * Description: Guarda el ID del producto y las horas de la variación en metadatos de usuario después de cada compra.
 * Version: 2.0.0
 * Author: Daniel Amado
 * GitHub Plugin URI: https://github.com/TDanyStark/wc-user-product-hours
 * Author URI: https://daniel-amado.com/
 * License: GPLv2 or later
 * Text Domain: wc-user-product-hours
 */


defined('ABSPATH') || exit;

// Cargar dependencias
require_once plugin_dir_path(__FILE__) . 'includes/class-config.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-product-hours-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-booking-validation.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-extra-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-profile-user.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-load-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-load-ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin-hours.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin-validation-hours.php';

class WC_User_Product_Hours
{
    public function __construct()
    {
        new Product_Hours_Handler();
        new Booking_Validation();
        new Shortcodes_DA();
        new ExtraFunctions();
        new WCUPH_User_Hours_Display();
        new WCUPH_Load_Scripts();
        new WCUPH_load_AJAX();
        new WCUPH_Admin_Hours();
    new WCUPH_Admin_Validation_Hours();
    }
}

new WC_User_Product_Hours();
