<?php

class Shortcodes_DA {
    public function __construct() {
        add_shortcode('vaciar_carrito_btn', [$this, 'boton_vaciar_carrito']);
        add_action('wp_ajax_vaciar_carrito', [$this, 'vaciar_carrito']);
        add_action('wp_ajax_nopriv_vaciar_carrito', [$this, 'vaciar_carrito']); // Permitir para usuarios no logueados
    }

    public function boton_vaciar_carrito() {
        ob_start(); ?>
        <button id="vaciar-carrito" class="button" style="background-color: red; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-top: 10px;">
            Vaciar Carrito
        </button>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let boton = document.getElementById("vaciar-carrito");
                if (boton) {
                    boton.addEventListener("click", function() {
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
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function vaciar_carrito() {
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
        wp_send_json_success();
    }
}

