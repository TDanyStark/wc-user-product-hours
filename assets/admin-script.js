jQuery(document).ready(function($) {
  console.log('WCUPH script cargado correctamente.');

  $('.wc-horas-input').on('change blur', function() {
      console.log('Evento change/blur detectado en input.');

      var productId = $(this).data('product-id');
      var newHours = $(this).val();
      var userId = $(this).data('user-id');

      console.log('Enviando AJAX con:', { productId, newHours, userId });

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
