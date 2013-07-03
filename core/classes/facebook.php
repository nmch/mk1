<?
class FacebookRequestException extends FacebookException {}
class FacebookInvalidAccessTokenException extends FacebookException {}

class Facebook
{
	use Singleton;
	
	public static $DOMAIN_MAP = array(
		'api'         => 'https://api.facebook.com/',
		'api_video'   => 'https://api-video.facebook.com/',
		'api_read'    => 'https://api-read.facebook.com/',
		'graph'       => 'https://graph.facebook.com/',
		'graph_video' => 'https://graph-video.facebook.com/',
		'www'         => 'https://www.facebook.com/',
	);
	public static $CURL_OPTS = array(
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_USERAGENT      => 'facebook-php-3.2',
	);
	
	protected $appId;
	protected $appSecret;
	protected $user;
	protected $signedRequest;
	protected $state;
	protected $accessToken = null;
	protected $fileUploadSupport = false;
	protected $trustForwarded = false;

	protected static $default_accesstoken;
	protected static $kSupportedKeys = array('state', 'code', 'access_token', 'user_id');
	
	function __construct($config = array())
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
	
	public static function _oauthRequest($url, $params) {
		//Log::coredebug("[fb] OAuthRequest [$url]",$params);
		// json_encode all params values that are not strings
		foreach ($params as $key => $value) {
			if (!is_string($value)) {
				$params[$key] = json_encode($value);
			}
		}
		return static::makeRequest($url, $params);
	}
	protected static function makeRequest($url, $params, $ch=null) {
		if (!$ch) {
			$ch = curl_init();
		}

		$opts = self::$CURL_OPTS;
		if(is_array($params))
			$params = http_build_query($params, null, '&');
		$opts[CURLOPT_POST] = true;
		$opts[CURLOPT_POSTFIELDS] = $params;
		$opts[CURLOPT_URL] = $url;
		curl_setopt_array($ch, $opts);
		
		$result = curl_exec($ch);

		if (curl_errno($ch) == 60)  // CURLE_SSL_CACERT
			throw new FacebookException('Invalid or no certificate authority found, using bundled information');

		if ($result === false) {
			$e = new FacebookException(array(
				'error_code' => curl_errno($ch),
				'error' => array(
				'message' => curl_error($ch),
				'type' => 'CurlException',
				),
			));
			curl_close($ch);
			throw $e;
		}
		curl_close($ch);
		return $result;
	}
	
	public static function setDefaultAccesstoken($token)
	{
		static::$default_accesstoken = $token;
	}
	public static function getDefaultAccesstoken()
	{
		return static::$default_accesstoken;
	}
	
	public static function base64UrlDecode($input) {
		return base64_decode(strtr($input, '-_', '+/'));
	}
	public static function base64UrlEncode($input) {
		$str = strtr(base64_encode($input), '+/', '-_');
		$str = str_replace('=', '', $str);
		return $str;
	}
	
	public static function getUrl($name, $path='', $params=array())
	{
		$url = static::$DOMAIN_MAP[$name];
		if ($path) {
			if ($path[0] === '/') {
				$path = substr($path, 1);
			}
			$url .= $path;
		}
		if ($params) {
			$url .= '?' . http_build_query($params, null, '&');
		}

		return $url;
	}
	protected static function establishCSRFTokenState() {
		$state = Session::get('facebook_state');
		if( ! $state ){
			$state = md5(uniqid(mt_rand(), true));
			Session::set('facebook_state',$state);
			//Log::coredebug("new state = $state");
		}
		return $state;
	}
	public static function getCSRFTokenState()
	{
		return static::establishCSRFTokenState();
	}
	public static function getLoginUrl($params=array()) {
		$state = static::establishCSRFTokenState();
		//$currentUrl = $this->getCurrentUrl();

		$params = Arr::merge(Config::get('facebook.login'),$params);
		if ($params['scope'] && is_array($params['scope'])) {
			$params['scope'] = implode(',', $params['scope']);
		}

		return static::getUrl(
			'www',
			'dialog/oauth',
			array_merge(array(
				'client_id' => Facebook_Config::getAppId(),
				//'redirect_uri' => $currentUrl, // possibly overwritten
				'state' => $state
			),$params)
		);
	}
	
	public static function throwException($result)
	{
		Log::coredebug('Facebook Exception',$result);
		throw new FacebookException(isset($result['message']) ? $result['message'] : '');
		/*
		$e = new FacebookException($result);
		switch ($e->getType()) {
			// OAuth 2.0 Draft 00 style
			case 'OAuthException':
			// OAuth 2.0 Draft 10 style
			case 'invalid_token':
			// REST server errors are just Exceptions
			case 'Exception':
				$message = $e->getMessage();
				if ((strpos($message, 'Error validating access token') !== false) ||
				(strpos($message, 'Invalid OAuth access token') !== false) ||
				(strpos($message, 'An active access token must be used') !== false)
				) {
					$this->destroySession();
					$e = new FacebookInvalidAccessTokenException($result);
				}
				break;
		}

		throw $e;
		 * 
		 */
	}
	
	public static function getApplicationAccessToken() {
		return Facebook_Config::getAppId().'|'.Facebook_Config::getAppSecret();
	}
}
