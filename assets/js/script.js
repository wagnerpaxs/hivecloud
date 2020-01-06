(function($) {
    $(document).ready(function() {
        changeCityShippingCalculatorField();

        $(document).on( 'wc_fragments_refreshed', function() {
            changeCityShippingCalculatorField();
        } );

        function requestCitiesByState( state, selectId, defaultValue = '' ) {
            if( state && state != '' && selectId && selectId != '' ) {
                let optionSelected = '';
                $.ajax({
                    url: hivecloud.adminAjax,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        'action': 'getCitiesByState',
                        'state': state,
                    },
                    beforeSend: function() {
                        $( selectId ).html('<option value="">Aguarde, carregando...</option>');
                    },
                    success: function( data ) {
                        if( ! data.error ) {
                            let cities = data.cities;
                            $( selectId ).html('<option value="">Selecione a cidade</option>');
                            $.each( cities, function( k, v ) {
                                if( defaultValue == v ) {
                                    let optionSelected = ' selected';
                                } else {
                                    let optionSelected = '';
                                }
                                let optionHtml = '<option value="' + v + '"' + optionSelected + '>' + v + '</option>';
                                $( selectId ).append( optionHtml );
                            });
                        } else {
                            console.log( data.message );
                        }
                    },
                    error: function( error ) {
                        console.log( error );
                    }
                });
            }
        }

        function changeCityShippingCalculatorField() {
            if( $("#calc_shipping_city_field").length > 0 ) {
                if( $("#calc_shipping_city").is( 'input:text' ) ) {
                    let calc_shipping_city = $("#calc_shipping_city").val();

                    let html = '<select name="calc_shipping_city" id="calc_shipping_city"><option value="">Carregando cidades...</option></select>';
                    $("#calc_shipping_city_field").html( html );
    
                    let stateUF = $("#calc_shipping_state").val();
    
                    if( stateUF != '' ) {
                        requestCitiesByState( stateUF, '#calc_shipping_city', calc_shipping_city );
                    }
                }
            }
        }

        if ( jQuery.fn.select2 ) {
            /*
             * Billing
             */
            $( '#billing_state' ).on( 'select2:select', function(e) {
                var data = e.params.data;
                let stateUF = data.id;

                if( stateUF != '' ) {
                    requestCitiesByState( stateUF, '#billing_city' );
                }
            });

            /*
             * Shipping
             */
            $( '#shipping_state' ).on( 'select2:select', function(e) {
                var data = e.params.data;
                let stateUF = data.id;
                if( stateUF != '' ) {
                    requestCitiesByState( stateUF, '#shipping_city' );
                }
            });

            /*
             * Cálculo de frete na página do carrinho
             */
            if( $("#calc_shipping_city_field").length > 0 ) {
                $( '#calc_shipping_state' ).on( 'select2:select', function(e) {
                    var data = e.params.data;
                    let stateUF = data.id;

                    if( stateUF != '' ) {
                        requestCitiesByState( stateUF, '#calc_shipping_city' );
                    }
                });
            }

        }
    });
})(jQuery);