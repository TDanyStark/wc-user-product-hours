<?php
if (!defined('ABSPATH')) exit;

class WCUPH_Config {
    const RELACION_PRODUCTOS = [
        3722 => 3701, // Booking => Producto de horas
        3861 => 3701, // Booking => Producto de horas
        3862 => 3701, // Booking => Producto de horas
        3771 => 3772, // Booking => Producto de horas
        3791 => 3782, // Booking => Producto de horas
        3801 => 3792, // Booking => Producto de horas
        4621 => 4611, // Booking => Producto de horas
    ];
    
    const PRODUCTOS_CON_HORAS = [
        3701, 3772, 3782, 3792, 4611, // Productos de horas
    ];
    
    public static function get_relacion_productos() {
        return self::RELACION_PRODUCTOS;
    }
    
    public static function get_productos_con_horas() {
        return self::PRODUCTOS_CON_HORAS;
    }
}