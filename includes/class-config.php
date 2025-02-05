<?php
if (!defined('ABSPATH')) exit;

class WCUPH_Config {
    const RELACION_PRODUCTOS = [
        3722 => 3701, // Booking => Producto de horas
        // Agrega más relaciones aquí
    ];
    
    const PRODUCTOS_CON_HORAS = [
        3701, 3802, 3855, 3844
    ];
    
    public static function get_relacion_productos() {
        return self::RELACION_PRODUCTOS;
    }
    
    public static function get_productos_con_horas() {
        return self::PRODUCTOS_CON_HORAS;
    }
}