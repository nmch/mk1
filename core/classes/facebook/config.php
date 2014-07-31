<?php
class Facebook_Config
{
	public static function setAppId($appId) {
		Config::set('facebook.app_id',$appId);
		return $this;
	}
	public static function getAppId() {
		return Config::get('facebook.app_id');
	}
	public static function setAppSecret($appSecret) {
		Config::set('facebook.secret',$appSecret);
		return $this;
	}
	public static function getAppSecret() {
		return Config::get('facebook.secret');
	}
	public static function getRedirectUrl()
	{
		return Config::get('facebook.login.redirect_uri');
	}
	public static function setRedirectUrl($url)
	{
		return Config::set('facebook.login.redirect_uri',$url);
	}
	
}