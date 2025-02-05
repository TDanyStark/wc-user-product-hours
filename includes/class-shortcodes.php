<?php

class Shortcodes_DA{
    public function __construct(){
        add_shortcode('vaciar_carrito_btn', 'boton_vaciar_carrito');
        add_action('wp_ajax_vaciar_carrito', 'vaciar_carrito');
        add_action('wp_ajax_nopriv_vaciar_carrito', 'vaciar_carrito'); // Permitir para usuarios no logueados
    }

    public function boton_vaciar_carrito() {
        ob_start(); ?>
        <button id="vaciar-carrito" class="button">Vaciar Carrito</button>
        
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("vaciar-carrito").addEventListener("click", function() {
                    fetch("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "action=vaciar_carrito"
                    }).then(response => response.json()).then(data => {
                        if (data.success) {
                            location.reload(); // Recargar la página después de vaciar el carrito
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function vaciar_carrito() {
        WC()->cart->empty_cart();
        wp_send_json_success();
    }
} 



