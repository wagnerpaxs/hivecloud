<?php

/**
 * Classe para comunicação com a API Hivecloud.
 */

if( ! defined( 'ABSPATH' ) ) {
    die( 'Silence is golden.');
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
  
  class HivecloudApi {

    const HiveCloudApiUrl = "http://t3pl.hivecloud.com.br/t3pl/simulacaoFrete/simular";
    const HiveCloudApiSearch = "http://t3pl.hivecloud.com.br/t3pl/agrupamentoCotacao";
    public $tipoServico = 'DISTRIBUICAO';
    public $tipoCarga = 'FRACIONADA';
    public $profileIdentifier = 'EMBARCADORA';

    public function __construct() {
        $WC_Hivecloud = new WC_Hivecloud();

        $this->identificacao =  isset( $WC_Hivecloud->settings['identificacao'] ) ? $WC_Hivecloud->settings['identificacao'] : '';
        $this->empresaId = isset( $WC_Hivecloud->settings['empresaId'] ) ? $WC_Hivecloud->settings['empresaId'] : '';
        $this->tenantIdentifier = isset( $WC_Hivecloud->settings['tenantIdentifier'] ) ? $WC_Hivecloud->settings['tenantIdentifier'] : '';
        $this->xAuthToken = isset( $WC_Hivecloud->settings['xAuthToken'] ) ? $WC_Hivecloud->settings['xAuthToken'] : '';
        $this->postalCodeOrigin = isset( $WC_Hivecloud->settings['postalCodeOrigin'] ) ? $WC_Hivecloud->settings['postalCodeOrigin'] : '';
        $this->countryCodeOrigin = isset( $WC_Hivecloud->settings['countryCodeOrigin'] ) ? $WC_Hivecloud->settings['countryCodeOrigin'] : '1058';
        $this->stateCodeOrigin = isset( $WC_Hivecloud->settings['stateCodeOrigin'] ) ? $WC_Hivecloud->settings['stateCodeOrigin'] : '';
        $this->cityCodeOrigin = isset( $WC_Hivecloud->settings['cityCodeOrigin'] ) ? $WC_Hivecloud->settings['cityCodeOrigin'] : '';
    }

    public function getHeaders() {
      return array(
        'tenantIdentifier' => $this->tenantIdentifier,
        'X-AUTH-TOKEN' => $this->xAuthToken,
        'profileIdentifier' => $this->profileIdentifier,
        'Content-Type' => 'application/json',
      );
    }

    public function simulacaoFrete( $params = null ) {
      $method = 'POST';
      $url = self::HiveCloudApiUrl;

      $data = array( "carga" => array( 
              "tipoServico" => $this->tipoServico, 
              "tipoCarga" => $this->tipoCarga 
          )
      );

      $pesoCubado = $params['height']*$params['width']*$params['length']*300;
      $data["identificacao"] = $this->identificacao;

      $data["empresaId"] = $this->empresaId;

      $data["entregaList"] = array(
        array(
          "medidaVolumeList" => array(
            array(
              "altura" => $params['height'], 
              "largura" => $params['width'],
              "comprimento" => $params['length'],
              "quantidadeVolumes" => $params['quantidadeVolumes']
            )
          ),
          "origem" => array( 
            "cep" => preg_replace( '/[^0-9]/', '', $this->postalCodeOrigin ), 
            "municipio" => array( "codigo" => $this->cityCodeOrigin ),                              
            "uf" => array( "codigo" => $this->stateCodeOrigin ),
            "pais" => array( "codigo" => $this->countryCodeOrigin ) 
          ),
          "destino" => array( 
            "cep" => preg_replace( '/[^0-9]/', '', $params['destination']['postcode'] ), 
            "municipio" => array( "codigo" => $params['destination']['city'] ), 
            "uf" => array( "codigo" => $params['destination']['state'] ),
            "pais" => array( "codigo" => $this->countryCodeOrigin )
          ),
          "totaisMercadoria" => array(
            "pesoLiquidoTotal" => $params['weight'],
            "pesoCubadoTotal" => $pesoCubado,
            "pesoBrutoTotal" => $params['weight'],
            "quantidadeVolumeTotal" => $params['quantidadeVolumes'],
            "volumeTotal" => $params['quantidadeVolumes'],
            "valorTotal" => $params['preco'],
          ),
        ),
      );

      $data = wp_json_encode( $data );
      $headers = $this->getHeaders();

      $response = wp_remote_post($url, array(
        'headers'     => $headers,
        'body'        => $data,
        'method'      => $method,
        'data_format' => 'body',
      ));
      
      return $response;
    }

    public function buscarCotacoes( $idSimulacao = null) {
      $method = 'GET';
      $url = self::HiveCloudApiSearch;

      $headers = $this->getHeaders();

      $response = wp_remote_get( $url, array(
          'headers'     => $headers,
          'id' => $idSimulacao,
          'firstResult' => 0,
          'maxResult' => 10
      ) );
      
      return $response;
    }

  }

}
