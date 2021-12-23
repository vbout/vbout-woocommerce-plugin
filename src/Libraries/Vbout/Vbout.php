<?php

namespace App\Libraries\Vbout;

class Vbout {
    /**
     * Vbout API endpoint
     */
    private $api_endpoint = "api.vbout.com";
	
	/**
     * Vbout API version
     */
	private $api_version = "1";
	
	/**
     * Vbout API ACCESS TOKENS
	 *	\_ CURRENTLY ONLY user_key
     */
    private $auth_tokens;
    
	protected $api_url;
	/**
     * Response type: JSON / XML
     */
	protected $api_response = 'JSON';
	
    /**
     * Query method: POST / GET / PUT / DELETE
     */
    protected $method = 'GET';

	/**
     * URL protocol: always HTTPS unless specify otherwise
     */
    protected $protocol = 'https://';

    /**
     */
    public function __construct($tokens = NULL, $api_endpoint = NULL, $api_version = NULL) {
        $this->auth_tokens = array();
		
		if ($api_endpoint != NULL) $this->api_endpoint;
		if ($api_version != NULL) $this->api_version;

		$this->init();
		
		if (is_array($tokens)) {
			//	WIP:: DON'T USE THIS METHOD YET
            if (array_key_exists('access_code', $tokens)) {
                $this->auth_tokens = $this->oauth_handshake( $tokens );
            } else {
                $this->auth_tokens = $tokens;
            }
        } else {
            $this->auth_tokens['user_key'] = $tokens;
        }
    }
	
	protected function init() { /* NOTHING */ }

    public function set_method($method)
    {
        $this->method = strtoupper($method);
    }

    public function set_protocol($protocol)
    {
        $this->protocol = strtolower($protocol);
    }

	////////////////////////////////////////////////////////////////////////////////////////////////////////	
    private function oauth_handshake($tokens)
	{
        $params = array(
            'grant_type'=>'authorization_code',
            'client_id'=> $tokens['app_key'],
            'client_secret'=> $tokens['client_secret'],
            'code'=> $tokens['access_code'] 
		);

        $request_url = $this->protocol . $this->api_endpoint . '/oauth/token';

        // CURL-POST implementation
        // WARNING: This code may require you to install the php5-curl package
        $ch = curl_init();
		
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		
        $json_data = curl_exec($ch);
        $resp_info = curl_getinfo($ch);
		
        curl_close($ch);

        $response = get_object_vars(json_decode($json_data));
		
        if (!array_key_exists('access_token', $response) || array_key_exists('error', $response)) {
            throw new VboutException($response['error_description']);
        }
		
        return array_merge($tokens, $response);
    }
	////////////////////////////////////////////////////////////////////////////////////////////////////////
	
    public function __call($name, $args)
    {
        $response = $this->safeCall($name, $args);

        if( isset($response['Error']) )
        {
            throw new VboutException($response);
        }

        return $response;
    }

	public function safeCall($name, $args)
    {
		$header = array();
		$getParams = array();
		$postParams = '';

		// Unpack our arguments
		if( $this->method == 'GET' ) {
			if (is_array($args) && array_key_exists(0, $args) && is_array($args[0])) {
				$getParams = $args[0];
			}
		} else {
			$fields = $args[0];

			$postParams = is_array($fields) && !empty($fields) ? json_encode($fields) : '{}';
			$header[] = 'Content-Type: application/json';
		}

        // Add authentication tokens to querystring
		if (!isset($this->auth_tokens['access_token'])) {
			$getParams = array_merge($getParams, $this->auth_tokens);
		}

        // Build our request url, urlencode querystring params
        $request_url = $this->protocol . $this->api_endpoint . '/' . $this->api_version . $this->api_url . $name . '.' . strtolower($this->api_response) . '?' . http_build_query($getParams);

		if(defined('VBOUT_WOOCOMMERCE_API_URL')) {
			$request_url = VBOUT_WOOCOMMERCE_API_URL . trim($this->api_url . $name, '/') . '&' . http_build_query($getParams) . '&';
		}

		$ch = curl_init();

		if( $this->method == 'GET' ) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		} else {
			curl_setopt($ch, CURLOPT_POST, count($fields));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch,CURLOPT_POSTFIELDS, $postParams);
		}

        $header[] = "Accept: application/json";

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
		
        $response = json_decode($result, true);

		if (isset($response['response']) && $response['response']['header']['status'] == 'error')
			throw new VboutException($response['response']['data']);
		
        return $response['response'];
    }
}
