<?php
class Facebook_Accesstoken
{
	protected $token;
	protected $expire;
	
	function __construct($data,$auto_extend = false)
	{
		//Log::coredebug("Facebook_Accesstoken data",$data);
		if($data instanceof Facebook_Signedrequest){
			if( ! $data->oauth_token )
				throw new FacebookException('SignedRequestにアクセストークンがありません');
			$this->token = $data->oauth_token;
			$this->expire = $data->expires;
		}
		else if(is_scalar($data)){
			$this->token = $data;
			$this->expire = NULL;
		}
		else if(is_array($data)){
			
			if(isset($data['token']))
				$this->token = $data['token'];
			else if(isset($data['access_token']))
				$this->token = $data['access_token'];
			else{
				//Log::coredebug($data);
				throw new FacebookException('empty access token');
			}
			
			if(isset($data['expire']))
				$this->expire = $data['expire'];
			else if(isset($data['expires']))
				$this->expire = $data['expires'];
			
			if($this->expire && $this->expire < 10000000)
				$this->expire += time();
		}
		else{
			//Log::coredebug($data);
			throw new FacebookException('invalid data');
		}
			
		return $this;
	}
	public function auto_extend($min_expire = NULL)
	{
		if( ! $min_expire )
			$min_expire = Config::get('facebook.autoextend_expire');
		if( ! is_numeric($min_expire) )
			$min_expire = strtotime($min_expire);
		//Log::coredebug("[fb token] min=$min_expire / expire={$this->expire}");
		if( ! $this->expire || $this->expire < $min_expire ){
			//Log::coredebug('[fb token] auto extend');
			$this->extend();
		}
		return $this;
	}
	protected function extend() {
		$access_token_response = Facebook::_oauthRequest(
			Facebook::getUrl('graph', '/oauth/access_token'),
			$params = array(
				'client_id' => Facebook_Config::getAppId(),
				'client_secret' => Facebook_Config::getAppSecret(),
				'grant_type' => 'fb_exchange_token',
				'fb_exchange_token' => $this->token,
			)
		);

		if (empty($access_token_response))
			throw new FacebookException('empty response');
		
		$response_params = array();
		parse_str($access_token_response, $response_params);
		
		if(empty($response_params['access_token']))	//本当はここでsession destroyしたほうが良いのかもしれない
			throw new FacebookException('empty access token');
		
		$this->token = $response_params['access_token'];
		$this->expire = empty($response_params['expires']) ? NULL : $response_params['expires'] + time();
		
		// Expect
		// array (
		//   'access_token' => '...',
		//   'expires' => '5183398',
		// )
		//return $response_params;
		return $this;	
	}
	
	public function get_token()
	{
		return $this->token;
	}
	public function get_expire()
	{
		return $this->expire;
	}

	public function __toString()
	{
		return $this->token;
	}
}