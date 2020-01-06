<?php

/**
 * Plugin Name: Hivecloud Shipping
 * Plugin URI: https://hivecloud.com.br/woocommerce/
 * Description: Plugin para cálculo de fretes utilizando a API/Plataforma da Hivecloud.
 * Version: 1.0.0
 * Author: Hivecloud
 * Author URI: https://hivecloud.com.br/
 * Developer: XARX
 * Developer URI: https://hivecloud.com.br/
 * Text Domain: hivecloud
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.0
 *
 * Copyright: © 2019 by Hivecloud.
 * License: Proprietary
 * License URI: https://hivecloud.com.br/woocommerce/terms/
 */

if( ! defined( 'ABSPATH' ) ) {
    die( 'Silence is golden.');
}

/*
 * Checa se o plugin WooCommerce está ativo.
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    /*
     * Define as constantes utilizadas pelo Plugin.
     */
    define( 'PLUGIN_HIVECLOUD_WOOCOMMERCE_DIR', plugin_dir_path( __FILE__ ) );
    define( 'PLUGIN_HIVECLOUD_WOOCOMMERCE_URL', plugin_dir_url( __FILE__ ) );

    /*
     * Faz a inclusão da classe de comunicação com a API da Hivecloud.
     */
    require_once 'hivecloud-api.php';

    /*
     * Função que cria o método (class) de entrega Hivecloud.
     */
    function WC_Hivecloud_Method() {
        if( ! class_exists( 'WC_Hivecloud' ) ) {

            class WC_Hivecloud extends WC_Shipping_Method {

                public function __construct( $instance_id = 0 ) {
                    $this->id = 'hivecloud';
                    $this->plugin_id = 'woocommerce_' . $this->id;
                    $this->instance_id = absint( $instance_id );
                    $this->method_title = __( 'Hivecloud Shipping', 'hivecloud' );  
                    $this->method_description = __( 'Cálculo de fretes utilizando a API/Plataforma da Hivecloud.', 'hivecloud' );
                    $this->availability = 'including';
                    $this->countries = array( 
                        'BR', 
                    );
                    $this->supports = array(
                        'settings',
                        'settings-modal',
                        'shipping-zones',
                    );

                    $this->init();
                }

                public function init() {
                    $this->init_settings();
                    $this->init_form_fields();

                    $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Hivecloud Shipping', 'hivecloud' );
                    $this->tenantIdentifier = isset( $this->settings['tenantIdentifier'] ) ? $this->settings['tenantIdentifier'] : '';
                    $this->xAuthToken = isset( $this->settings['xAuthToken'] ) ? $this->settings['xAuthToken'] : '';
                    $this->postalCodeOrigin = isset( $this->settings['postalCodeOrigin'] ) ? $this->settings['postalCodeOrigin'] : '';
                    $this->countryCodeOrigin = isset( $this->settings['countryCodeOrigin'] ) ? $this->settings['countryCodeOrigin'] : '1058';
                    $this->stateCodeOrigin = isset( $this->settings['stateCodeOrigin'] ) ? $this->settings['stateCodeOrigin'] : '26';
                    $this->cityCodeOrigin = isset( $this->settings['cityCodeOrigin'] ) ? $this->settings['cityCodeOrigin'] : '';
                    $this->showShippingCalcCountry = isset( $this->settings['showShippingCalcCountry'] ) ? $this->settings['showShippingCalcCountry'] : 'yes';
                    
                    // Hooks
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                    add_filter( 'woocommerce_default_address_fields', array( $this, 'order_address_field' ) );
                    add_filter( 'woocommerce_checkout_fields' , array( $this, 'override_checkout_city_fields' ) );

                    // Scripts e CSS
                    add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
                    add_action( 'admin_enqueue_scripts', array( $this, 'adminScripts' ) );

                    // Verifica se deve exibir/ocultar o campo de País na calculadora de frete
                    if( $this->showShippingCalcCountry != 'yes' ){
                        add_filter('woocommerce_shipping_calculator_enable_country','__return_false');
                    }
                }

                public function init_form_fields() {
                    $countries = $this->getCountry();
                    $arrPais = array();
                    foreach( $countries as $country ):
                        $arrPais[$country->bacen] = $country->nome;
                    endforeach;

                    $states = $this->getStateByCountry();
                    $arrState = array();
                    foreach( $states as $state):
                        $arrState[$state->id] = $state->nome_estado;
                    endforeach;

                    $cidades = $this->getCityByState( $this->settings['stateCodeOrigin'] );
                    $arrCidade = array();
                    foreach( $cidades as $cidade ):
                        $arrCidade[$cidade->codigo_municipio] = $cidade->nome_municipio;
                    endforeach;

                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __( 'Ativar', 'hivecloud' ),
                            'type' => 'checkbox',
                            'description' => __( 'Ative este método de envio.', 'hivecloud' ),
                            'default' => 'yes'
                        ),
            
                        'title' => array(
                            'title' => __( 'Título', 'hivecloud' ),
                            'type' => 'text',
                            'description' => __( 'Título que aparecerá no seu site.', 'hivecloud' ),
                            'default' => __( 'Hivecloud Shipping', 'hivecloud' )
                        ),

                        'identificacao' => array(
                            'title' => __( 'Identificação', 'hivecloud' ),
                            'type' => 'text',
                            'description' => __( 'Texto demonstrativo para identificação', 'hivecloud' ),
                        ), 

                        'empresaId' => array(
                            'title' => __( 'Id da empresa', 'hivecloud' ),
                            'type' => 'text',
                        ),                         

                        'tenantIdentifier' => array(
                            'title' => __( 'Tenant ID', 'hivecloud' ),
                            'type' => 'text',
                            'description' => __( 'Chave do cliente.', 'hivecloud' ),
                            'default' => ''
                        ),

                        'xAuthToken' => array(
                            'title' => __( 'Token', 'hivecloud' ),
                            'type' => 'text',
                            'description' => __( 'Token do cliente.', 'hivecloud' ),
                            'default' => ''
                        ),

                        'postalCodeOrigin' => array(
                            'id' => 'postalCodeOrigin',
                            'title' => __( 'CEP', 'hivecloud' ),
                            'type' => 'text',
                            'description' => __( 'CEP do cliente.', 'hivecloud' ),
                            'default' => ''
                        ),

                        'countryCodeOrigin' => array(
                            'id' => 'countryCodeOrigin',
                            'title' => __( 'País', 'hivecloud' ),
                            'type' => 'select',
                            'options' => $arrPais,
                            'description' => __( 'Informe o País de origem da mercadoria.', 'hivecloud' ),
                            'default' => $this->settings['countryCodeOrigin'],
                        ),

                        'stateCodeOrigin' => array(
                            'id' => 'stateCodeOrigin',
                            'title' => __( 'Estado', 'hivecloud' ),
                            'type' => 'select',
                            'options' => $arrState,
                            'description' => __( 'Informe o estádo (UF) de origem da mercadoria.', 'hivecloud' ),
                            'default' => $this->settings['stateCodeOrigin'],
                        ),

                        'cityCodeOrigin' => array(
                            'id' => 'cityCodeOrigin',
                            'title' => __( 'Cidade', 'hivecloud' ),
                            'type' => 'select',
                            'options' => $arrCidade,
                            'description' => __( 'Informe a cidade de origem da mercadoria.', 'hivecloud' ),
                            'default' => $this->settings['cityCodeOrigin'],
                        ),

                        'showShippingCalcCountry' => array(
                            'id' => 'showShippingCalcCountry',
                            'title' => 'Exibir país na calculadora',
                            'type' => 'checkbox',
                            'description' => __( 'Permitir selecionar o país para o cáculo de entrega.', 'hivecloud' ),
                            'default' => 'yes'
                        ),

                    );

                }

                public function scripts() {
                    wp_enqueue_style( 'hivecloud', PLUGIN_HIVECLOUD_WOOCOMMERCE_URL . 'assets/css/style.css', array(), false, 'screen' );
                    wp_enqueue_script( 'hivecloud', PLUGIN_HIVECLOUD_WOOCOMMERCE_URL . 'assets/js/script.js', array( 'jquery' ), false, true );
                    wp_localize_script( 'hivecloud', 'hivecloud', array(
                        'adminAjax' => admin_url( 'admin-ajax.php' ),
                    ));
                }

                public function adminScripts() {
                    wp_enqueue_script( 'adminHivecloud', PLUGIN_HIVECLOUD_WOOCOMMERCE_URL . 'assets/js/admin-script.js', array( 'jquery' ), false, true );
                    wp_localize_script( 'adminHivecloud', 'adminHivecloud', array(
                        'adminAjax' => admin_url( 'admin-ajax.php' ),
                    ));
                }

                public function calculate_shipping( $package = array() ) {
                    $weight = 0;
                    $length = 0;
                    $width = 0;
                    $height = 0;                    
                    $quantidadeVolumes = 0;
                    foreach ( $package['contents'] as $item_id => $values ) { 
                        $_product = $values['data']; 
                        $weight = $weight + $_product->get_weight() * $values['quantity']; 
                        $length = $length + $_product->get_length(); 
                        $width = $width + $_product->get_width();
                        $height = $height + $_product->get_height();
                        $quantidadeVolumes += $values['quantity'];
                        $price += $_product->get_price();
                    }

                    $mlength  = (float)$length/100;
                    $mwidth = (float)$width/100;
                    $mheight = (float)$height/100;
                    $weight = wc_get_weight( $weight, 'kg' );

                    $state = $this->getStateCode( $package['destination']['state'] );
                    $city = $this->getCityCode( $package['destination']['city'] );
                    $package['destination']['state'] = $state;
                    $package['destination']['city'] = $city;

                    $params = array( 'weight' => $weight, 'length' => $mlength, 'width' => $mwidth, 
                                     'height' => $mheight, 'quantidadeVolumes' => $quantidadeVolumes,
                                     'destination' => $package['destination'], 'preco' => $price );

                    $HivecloudApi = new HivecloudApi();

                    if( !empty( $package['destination']['postcode'] ) ){
                        $simulacaoFrete = $HivecloudApi->simulacaoFrete( $params );
                        
                        $simulacaoFreteBody = json_decode( $simulacaoFrete['body'] );
                        $simulacaoRetorno = $HivecloudApi->buscarCotacoes( $simulacaoFreteBody->numero );
                        $simulacaoRetornoBody = json_decode( $simulacaoRetorno['body'] );
                        
                        /*
                        $simulacaoRetornoBody é um array de objetos relacionados a cotação.
                        */
                        if( count( $simulacaoRetornoBody ) > 0 ) {
                            foreach( $simulacaoRetornoBody as $k => $simulacao ) {
                                if( $simulacao->status == 'FINALIZADA_SUCESSO' ) {
                                    $cost = $simulacao->freteTotal;
                                    if( $simulacao->prazoMinimo == $simulacao->prazoMaximo ) {
                                        $prazo = '(Até ' . $simulacao->prazoMaximo . ' dias)';
                                    } else {
                                        $prazo = '(De ' . $simulacao->prazoMinimo . ' até ' . $simulacao->prazoMaximo . ' dias)';
                                    }

                                    $title = $simulacao->servico->nomeServico . ' ' . $prazo;
                                    $this->add_rate( array(
                                            'id' 	=> $this->id . '_' . $simulacao->servico->id,
                                            'label' => $title,
                                            'cost' 	=> $cost,
                                        )
                                    );
                                }
                            }
                        }
                    }
                    
                }

                public function is_available( $package ){
                    return true;
                }

                public function getStateCode( $uf ){
                    if( !empty( $uf ) ){    
                        global $wpdb;
                        $sql = "SELECT id FROM estado WHERE uf = '".$uf."'";
                        $result = $wpdb->get_var( $sql );
                        return $result;
                    }else{
                        return '';
                    }
                }

                public function getStateByCode( $code ){
                    if( !empty( $code ) ){    
                        global $wpdb;
                        $sql = "SELECT uf FROM estado WHERE id = '".$code."'";
                        $result = $wpdb->get_var( $sql );
                        return $result;
                    }else{
                        return '';
                    }
                }

                public function getCityCode( $city ) {
                    if( !empty( $city ) ){
                        global $wpdb;
                        $sql = "SELECT codigo_municipio FROM municipio WHERE nome_municipio = '".$city."'";
                        $result = $wpdb->get_var( $sql );

                        return $result;
                    }else{
                        return '';
                    }
                }

                public function getStateByCountry( $country = null ) {
                    global $wpdb;
                    $sql = "SELECT * FROM estado ORDER BY nome_estado";
                    $result = $wpdb->get_results( $sql );
                    return $result;
                }

                public function getCountry() {
                    global $wpdb;
                    $sql = "SELECT * FROM pais ";
                    $result = $wpdb->get_results( $sql );
                    return $result;
                }                

                public function getCityByState( $state = null ){
                    if( !empty( $state ) ){
                        global $wpdb;
                        $sql = "SELECT * FROM municipio WHERE codigo_estado = '".$state."'";
                        $result = $wpdb->get_results( $sql );

                        return $result;
                    }else{
                        return '';
                    }
                }

                function order_address_field( $address_fields ) {
                    $address_fields['first_name']['priority'] = 1;
                    $address_fields['last_name']['priority'] = 2;
                    $address_fields['company']['priority'] = 3;
                    $address_fields['postcode']['priority'] = 4;
                    $address_fields['address_1']['priority'] = 5;
                    $address_fields['address_2']['priority'] = 6;
                    $address_fields['state']['priority'] = 7;
                    $address_fields['city']['priority'] = 8;                    
                    return $address_fields;
                }

                public function override_checkout_city_fields( $fields ) {
                    $WC_Hivecloud = new WC_Hivecloud();
                    $stateCodeOrigin = isset( $WC_Hivecloud->settings['stateCodeOrigin'] ) ? $WC_Hivecloud->settings['stateCodeOrigin'] : '';

                    if( is_user_logged_in() ) {
                        $billing_state = get_user_meta( get_current_user_id(), 'billing_state', true );
                        $shipping_state = get_user_meta( get_current_user_id(), 'shipping_state', true );

                        if( empty( $billing_state ) ) {
                            if( ! empty( $stateCodeOrigin ) ) {
                                $stateUF = $this->getStateByCode( $stateCodeOrigin );
                                $billing_state = $stateUF;
                            }
                        }

                        if( empty( $shipping_state ) ) {
                            if( ! empty( $stateCodeOrigin ) ) {
                                $stateUF = $this->getStateByCode( $stateCodeOrigin );
                                $shipping_state = $stateUF;
                            }
                        }
                    } else {
                        if( ! empty( $stateCodeOrigin ) ) {
                            $stateUF = $this->getStateByCode( $stateCodeOrigin );
                        }

                        $billing_state = $stateUF;
                        $shipping_state = $stateUF;
                    }

                    $billingStateCode = $this->getStateCode( $billing_state );
                    $shippingStateCode = $this->getStateCode( $shipping_state );

                    $billing_cities = $this->getCityByState( $billingStateCode );
                    $shipping_cities = $this->getCityByState( $shippingStateCode );

                    $arr_billing_cities = array( '' => __( 'Selecione a cidade' ) );
                    if( ! empty( $billing_cities ) ){
                        foreach( $billing_cities as $city ):
                            $arr_billing_cities[$city->nome_municipio] = $city->nome_municipio;
                        endforeach;
                    }

                    $arr_shipping_cities = array( '' => __( 'Selecione a cidade' ) );
                    if( !empty( $shipping_cities ) ){
                        foreach( $shipping_cities as $city ):
                            $arr_shipping_cities[$city->nome_municipio] = $city->nome_municipio;
                        endforeach;
                    }

                    $fields['billing']['billing_city']['type'] = 'select';
                    $fields['billing']['billing_city']['options'] = $arr_billing_cities;
                    $fields['shipping']['shipping_city']['type'] = 'select';
                    $fields['shipping']['shipping_city']['options'] = $arr_shipping_cities;
                
                    return $fields;
                }
            }
        }
    }

    /*
     * Hook de inicialização do método de entrega Hivecloud
     */
    add_action( 'woocommerce_shipping_init', 'WC_Hivecloud_Method' );

    /*
     * Hook para registrar e ativar o método de entrega Hivecloud.
     */
    add_filter( 'woocommerce_shipping_methods', 'WC_Hivecloud_Add_Method' );
    function WC_Hivecloud_Add_Method( $methods ) {
        $methods['hivecloud'] = 'WC_Hivecloud';
        return $methods;
    }

    /*
     * Registra o endpoint AJAX para preencher o combo de cidades de acordo com o estado selecionado.
     */
    add_action( 'wp_ajax_getCitiesByState', 'getCitiesByState' );
    add_action( 'wp_ajax_nopriv_getCitiesByState', 'getCitiesByState' );
    function getCitiesByState() {
        global $wpdb;
        $estadoUF = $_REQUEST['state'];
        $sql = "SELECT `nome_municipio` FROM `municipio` WHERE `codigo_estado` = ( SELECT `id` FROM `estado` WHERE `uf`='" . $estadoUF . "' )";
        $res = $wpdb->get_results( $sql );
        $response = array( 'error' => true, 'sql' => $sql );

        if( count( $res ) > 0 ) {
            $cities = array();
            foreach( $res as $k => $v ) {
                $cities[] = $v->nome_municipio;
            }
            $response = array( 'error' => false, 'cities' => $cities, 'message' => 'Sucesso' );
        } else {
            $response = array( 'error' => true, 'cities' => array(), 'message' => 'Nenhuma cidade encontrada.' );
        }

        echo wp_json_encode( $response );
        die;
    }

    /*
     * Registra o endpoint AJAX para pegar as cidades de acordo com o código IBGE do estado.
     * OBS: Utilizado apenas no WP-Admin para configuração do plugin.
     */
    add_action( 'wp_ajax_getCitiesByStateCode', 'getCitiesByStateCode' );
    function getCitiesByStateCode() {
        global $wpdb;
        $estadoUF = $_REQUEST['state'];
        $sql = "SELECT `codigo_municipio`, `nome_municipio` FROM `municipio` WHERE `codigo_estado` = " . $estadoUF;
        $res = $wpdb->get_results( $sql );
        $response = array( 'error' => true );

        if( count( $res ) > 0 ) {
            $cities = array();
            foreach( $res as $k => $v ) {
                $cities[] = array( 'codigo' => $v->codigo_municipio, 'nome' => $v->nome_municipio );;
            }
            $response = array( 'error' => false, 'cities' => $cities, 'message' => 'Sucesso' );
        } else {
            $response = array( 'error' => true, 'cities' => array(), 'message' => 'Nenhuma cidade encontrada.' );
        }

        echo wp_json_encode( $response );
        die;
    }
    
    /*
     * Faz enqueue dos scripts utilizados pelo plugin nas páginas do carrinho e do checkout.
     */
    add_action( 'wp_enqueue_scripts', 'hiveCloudEnqueueScripts' );
    function hiveCloudEnqueueScripts() {
        if ( is_page( 'cart' ) || is_cart() || is_checkout() ){
            wp_enqueue_style( 'hivecloud', PLUGIN_HIVECLOUD_WOOCOMMERCE_URL . 'assets/css/style.css', array(), false, 'screen' );
            wp_enqueue_script( 'hivecloud', PLUGIN_HIVECLOUD_WOOCOMMERCE_URL . 'assets/js/script.js', array( 'jquery' ), false, true );
            wp_localize_script( 'hivecloud', 'hivecloud', array(
                'adminAjax' => admin_url( 'admin-ajax.php' ),
            ));
        }
    }

    /*
     * Hook para criação das tabelas no banco de dados.
     */
    add_action( 'init', 'hivecloudCreateDatabaseTables' );
    function hivecloudCreateDatabaseTables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $pais = "pais";
        $paisExiste = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pais ) ) === $pais ) ? true : false;
        $estado = "estado";
        $estadoExiste = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $estado ) ) === $estado ) ? true : false;
        $cidade = "cidade";
        $cidadeExiste = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $cidade ) ) === $cidade ) ? true : false;

        if ( ! $paisExiste && ! $estadoExiste && ! $cidadeExiste ) {
            $sqlFile = PLUGIN_HIVECLOUD_WOOCOMMERCE_DIR . '/assets/sql/create-tables.sql';
            $sqlContents = file_get_contents( $sqlFile );
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sqlContents );
        }
    }
}