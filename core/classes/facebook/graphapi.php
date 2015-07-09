<?php

class Facebook_Graphapi
{
	protected $query;
	protected $method;
	protected $params = [];

	function __construct($query, $method = 'GET', $params = [])
	{
		if( is_array($method) && empty($params) ){
			$params = $method;
			$method = 'GET';
		}
		$params['method'] = $method;
		$this->method     = $method;
		$this->params     = $params;
		$this->query      = $query;

		return $this;
	}

	public static function query($query, $method = 'GET', $params = [])
	{
		return new static($query, $method, $params);
	}

	function get_query()
	{
		return $this->query;
	}

	function get_params()
	{
		return $this->params;
	}

	function get_method()
	{
		return $this->method;
	}

	function get_api_string()
	{
		$str = $this->query;
		if( $this->params ){
			$str .= '?' . http_build_query($this->params);
		}

		return $str;
	}

	function param($name, $value)
	{
		if( is_array($value) ){
			$value = implode(',', $value);
		}
		$this->params[$name] = $value;

		return $this;
	}

	function execute($token = null, $return_as = 'Facebook_Result')
	{
		if( $token ){
			$this->params['access_token'] = $token;
		}
		if( empty($this->params['access_token']) ){
			$this->params['access_token'] = Facebook::getDefaultAccesstoken() ?: Facebook::getApplicationAccessToken();
		}

		if( isset($this->params['access_token']) && is_object($this->params['access_token']) ){
			$this->params['access_token'] = (string)$this->params['access_token'];
		}

		//Log::coredebug("[facebook graphapi] execute {$this->query}",$this->params);
		$result = Facebook::_oauthRequest(Facebook::getUrl('graph', $this->query), $this->params);
		$result = json_decode($result, true);

		if( is_array($result) && isset($result['error']) ){
			Facebook::throwException($result, $this->params);
		}

		return new $return_as($result, $this);
	}
}
