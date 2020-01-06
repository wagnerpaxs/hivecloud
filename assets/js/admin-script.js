(function($) {
    $(document).ready(function() {
        $(document).on( 'change', '#woocommerce_hivecloudhivecloud_stateCodeOrigin', function() {
            let stateUF = $(this).val();
            $.ajax({
                url: adminHivecloud.adminAjax,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'getCitiesByStateCode',
                    'state': stateUF,
                },
                beforeSend: function() {
                    $( '#woocommerce_hivecloudhivecloud_cityCodeOrigin' ).html('<option value="">Aguarde, carregando...</option>');
                },
                success: function( data ) {
                    if( ! data.error ) {
                        let cities = data.cities;
                        $( '#woocommerce_hivecloudhivecloud_cityCodeOrigin' ).html('<option value="">Selecione a cidade</option>');
                        $.each( cities, function( k, v ) {
                            let optionHtml = '<option value="' + v.codigo + '">' + v.nome + '</option>';
                            $( '#woocommerce_hivecloudhivecloud_cityCodeOrigin' ).append( optionHtml );
                        });
                    } else {
                        alert( data.message );
                    }
                },
                error: function( error ) {
                    console.log( error );
                }
            });
        });
    });
})(jQuery);