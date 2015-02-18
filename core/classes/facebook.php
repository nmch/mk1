<?

class FacebookRequestException extends FacebookException
{
}

class FacebookInvalidAccessTokenException extends FacebookException
{
}

class Facebook
{
	use Singleton;

	public static $DOMAIN_MAP = [
		'api'         => 'https://api.facebook.com/',
		'api_video'   => 'https://api-video.facebook.com/',
		'api_read'    => 'https://api-read.facebook.com/',
		'graph'       => 'https://graph.facebook.com/',
		'graph_video' => 'https://graph-video.facebook.com/',
		'www'         => 'https://www.facebook.com/',
	];
	public static $CURL_OPTS  = [];
	protected static $default_accesstoken;
	protected static $kSupportedKeys = ['state', 'code', 'access_token', 'user_id'];
	protected $appId;
	protected $appSecret;
	protected $user;
	protected $signedRequest;
	protected $state;
	protected $accessToken       = null;
	protected $fileUploadSupport = false;
	protected $trustForwarded    = false;

	function __construct($config = [])
	{
		/*
		$config = array_merge(Config::get('facebook.init'),$config);
		
		$this->setAppId($config['appId']);
		$this->setAppSecret($config['secret']);
		
		/*
		$state = $this->getPersistentData('state');
		if (!empty($state)) {
			$this->state = $state;
		}
		 * 
		 */
	}

	static function set_curl_opt($key, $value)
	{
		static::$CURL_OPTS[$key] = $value;
	}

	static function get_curl_opt($key)
	{
		return Arr::get(static::$CURL_OPTS, $key);
	}

	public static function _oauthRequest($url, $params)
	{
		//Log::coredebug("[fb] OAuthRequest [$url]",$params);
		// json_encode all params values that are not strings
		foreach($params as $key => $value){
			if( ! is_string($value) ){
				$params[$key] = json_encode($value);
			}
		}
		$result = static::makeRequest($url, $params);

		//Log::coredebug("[fb] OAuthRequest result",$result);
		return $result;
	}

	protected static function makeRequest($url, $params, $ch = null)
	{
		if( ! $ch ){
			$ch = curl_init();
		}
		if( is_array($params) ){
			$params = http_build_query($params, null, '&');
		}
		$opts                     = Config::get('facebook.curl_options');
		$opts[CURLOPT_POST]       = true;
		$opts[CURLOPT_POSTFIELDS] = $params;
		$opts[CURLOPT_URL]        = $url;
		$opts[CURLOPT_CAINFO]     = __DIR__ . '/fb_ca_chain_bundle.crt';
		//Log::coredebug("Facebook makeRequest",$opts);
		curl_setopt_array($ch, $opts);

		$result = curl_exec($ch);

		if( curl_errno($ch) == 60 )  // CURLE_SSL_CACERT
		{
			throw new FacebookException('Invalid or no certificate authority found, using bundled information');
		}

		if( $result === false ){
			Log::error("Facebook Request Error : " . curl_errno($ch), curl_error($ch));
			$e = new FacebookException([
				'error_code' => curl_errno($ch),
				'error'      => [
					'message' => curl_error($ch),
					'type'    => 'CurlException',
				],
			]
			);
			curl_close($ch);
			throw $e;
		}
		curl_close($ch);

		return $result;
	}

	public static function getDefaultAccesstoken()
	{
		return static::$default_accesstoken;
	}

	public static function setDefaultAccesstoken($token)
	{
		static::$default_accesstoken = $token;
	}

	public static function base64UrlDecode($input)
	{
		return base64_decode(strtr($input, '-_', '+/'));
	}

	public static function base64UrlEncode($input)
	{
		$str = strtr(base64_encode($input), '+/', '-_');
		$str = str_replace('=', '', $str);

		return $str;
	}

	public static function getCSRFTokenState()
	{
		return static::establishCSRFTokenState();
	}

	protected static function establishCSRFTokenState()
	{
		$state = Session::get('facebook_state');
		if( ! $state ){
			$state = md5(uniqid(mt_rand(), true));
			Session::set('facebook_state', $state);
			//Log::coredebug("new state = $state");
		}

		return $state;
	}

	public static function getLoginUrl($params = [])
	{
		$state = static::establishCSRFTokenState();
		//$currentUrl = $this->getCurrentUrl();

		$params = Arr::merge(Config::get('facebook.login'), $params);
		if( $params['scope'] && is_array($params['scope']) ){
			$params['scope'] = implode(',', $params['scope']);
		}

		// redirect_uriをセッションに保存しておく (トークン取得時に再利用する)
		Session::set('facebook_redirect_uri', Arr::get($params, 'redirect_uri'));

		return static::getUrl(
			'www',
			'dialog/oauth',
			array_merge([
				'client_id' => Facebook_Config::getAppId(),
				//'redirect_uri' => $currentUrl, // possibly overwritten
				'state'     => $state
			], $params
			)
		);
	}

	public static function getUrl($name, $path = '', $params = [])
	{
		$url = static::$DOMAIN_MAP[$name];
		if( $path ){
			if( $path[0] === '/' ){
				$path = substr($path, 1);
			}
			$url .= $path;
		}
		if( $params ){
			$url .= '?' . http_build_query($params, null, '&');
		}

		return $url;
	}

	public static function throwException($result, $request_params = [])
	{
		Log::coredebug('Facebook Exception', $result);
		//$e = new FacebookException(isset($result['message']) ? $result['message'] : '');
		$e = new FacebookException($result, $request_params);
		switch($e->type()){
			// OAuth 2.0 Draft 00 style
			case 'OAuthException':
				// OAuth 2.0 Draft 10 style
			case 'invalid_token':
				// REST server errors are just Exceptions
			case 'Exception':
				$message = $e->getMessage();
				if( (strpos($message, 'Error validating access token') !== false) ||
					(strpos($message, 'Invalid OAuth access token') !== false) ||
					(strpos($message, 'An active access token must be used') !== false)
				){
					$this->destroySession();
					$e = new FacebookInvalidAccessTokenException($result, $request_params);
				}
				break;
		}
		throw $e;
	}

	public static function getApplicationAccessToken()
	{
		return Facebook_Config::getAppId() . '|' . Facebook_Config::getAppSecret();
	}
}
