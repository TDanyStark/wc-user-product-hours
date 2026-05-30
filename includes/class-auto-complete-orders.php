<?php
if (!defined('ABSPATH')) exit;

/**
 * Marca automáticamente como "completado" cualquier pedido que contenga
 * un producto de horas. Así las horas (que solo se suman en estado
 * "completed") se acreditan sin intervención manual.
 */
class WCUPH_Auto_Complete_Orders
{
    public function __construct()
    {
        // Al confirmarse el pago (pasa a processing en la mayoría de pasarelas).
        add_action('woocommerce_payment_complete', [$this, 'autocompletar_pedido'], 20);
        // Red de seguridad: cuando el pedido entra en estado "processing".
        add_action('woocommerce_order_status_processing', [$this, 'autocompletar_pedido'], 20);
    }

    /**
     * Completa el pedido si contiene al menos un producto de horas.
     *
     * @param int $order_id
     */
    public function autocompletar_pedido($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Si ya está completado, no hacer nada.
        if ($order->has_status('completed')) {
            return;
        }

        if (!$this->pedido_tiene_producto_de_horas($order)) {
            return;
        }

        wcuph_log('[AUTOCOMPLETE] Pedido ' . $order_id . ' contiene producto de horas. Marcando como completado.');
        $order->update_status('completed', 'Autocompletado: contiene producto de horas.');
    }

    /**
     * Determina si el pedido incluye algún producto de la lista de productos con horas.
     *
     * @param WC_Order $order
     * @return bool
     */
    private function pedido_tiene_producto_de_horas($order)
    {
        $productos_con_horas = WCUPH_Config::get_productos_con_horas();

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (in_array($product_id, $productos_con_horas, true)) {
                return true;
            }
        }

        return false;
    }
}
