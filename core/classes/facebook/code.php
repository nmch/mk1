<?php
class Facebook_Code
{
	protected $code;
	protected $token;
	
	function __construct($code = NULL,$state = NULL)
	{
		/*
		if( ! $code ){
			$code = Actionform::get('code');
			$state = Actionform::get('state');
			Log::coredebug("code=$code / state=$state",Facebook::getCSRFTokenState());
		}
		*/
		if( ! $code || ! $state )
			throw new FacebookException('empty code');
		//Log::coredebug("code=$code / state=$state",Facebook::getCSRFTokenState());
		if($state != Facebook::getCSRFTokenState())
			throw new FacebookException('invalid state');
		
		$this->code = $code;
		
	}
	function code()
	{
		return $this->code;
	}
	function get_access_token()
	{
		if($this->token)
			return $this->token;
		
		$response_params = Session::get('facebook_code2token_'.$this->code);
		if( ! is_array($response_params) ){
			$response_params = [];
		}
		if( ! Arr::get($response_params,'token', Arr::get($response_params,'access_token')) ){	// セッションに保存したデータにトークンがなければコードから変換
			
			// redirect_uriはFacebook::getLoginUrl()を実行したときにセッションにセットされるので
			// もしセットされていればそれを使う
			$redirect_uri = Session::get('facebook_redirect_uri') ?: Facebook_Config::getRedirectUrl();
			
			$params = array(
				'client_id' => Facebook_Config::getAppId(),
				'client_secret' => Facebook_Config::getAppSecret(),
				'redirect_uri' => $redirect_uri,
				'code' => $this->code
			);
			$access_token_response = Facebook::_oauthRequest(Facebook::getUrl('graph', '/oauth/access_token'),$params);
			Log::coredebug("Facebook_Code::get_access_token() access_token_response : ".$access_token_response);
			
			if ( ! $access_token_response )
				throw new FacebookException('empty result');

			$response_params = array();
			parse_str($access_token_response, $response_params);
			// code→tokenの変換は一度きりなので、セッションにキャッシュする
			Session::set('facebook_code2token_'.$this->code,$response_params);
		}
		//Log::coredebug("response_params",$response_params);
		return new Facebook_Accesstoken($response_params);
	}
}
