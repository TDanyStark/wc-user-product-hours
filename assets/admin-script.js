jQuery(document).ready(function($) {
  $('.wc-horas-input').on('change blur', function() {
      var productId = $(this).data('product-id');
      var newHours = $(this).val();
      var userId = $(this).data('user-id');

      $.ajax({
          url: wcuph_ajax.ajaxurl, // URL correcta
          type: 'POST',
          data: {
              action: 'actualizar_horas_usuario',
              product_id: productId,
              new_hours: newHours,
              user_id: userId,
          },
          success: function(response) {
              if (response.success) {
                  console.log('Horas actualizadas:', response.data);
              } else {
                  console.log('Error en respuesta:', response.data);
              }
          },
          error: function(error) {
              console.log('Error en AJAX:', error);
          }
      });
  });
});
