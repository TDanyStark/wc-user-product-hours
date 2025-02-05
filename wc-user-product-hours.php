<?php

/**
 * Plugin Name: WC User Product Hours
 * Plugin URI: https://daniel-amado.com/
 * Description: Guarda el ID del producto y las horas de la variación en metadatos de usuario después de cada compra.
 * Version: 1.0.0
 * Author: Tu Nombre
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

class WC_User_Product_Hours
{
    public function __construct()
    {
        new Product_Hours_Handler();
        new Booking_Validation();
        new Shortcodes_DA();
    }
}

new WC_User_Product_Hours();
