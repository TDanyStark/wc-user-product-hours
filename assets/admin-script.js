jQuery(document).ready(function($) {
  $('.wc-horas-input').on('change blur', function() {
      var productId = $(this).data('product-id');
      var newHours = $(this).val();

      $.ajax({
          url: ajaxurl, // WordPress AJAX URL
          type: 'POST',
          data: {
              action: 'actualizar_horas_usuario',
              product_id: productId,
              new_hours: newHours,
              user_id: $(this).data('user-id'),
          },
          success: function(response) {
              console.log('Horas actualizadas:', response);
          },
          error: function(error) {
              console.log('Error actualizando horas:', error);
          }
      });
  });
});
