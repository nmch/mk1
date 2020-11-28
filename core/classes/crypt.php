<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

require_once('phpseclib/Crypt/AES.php');

class Crypt
{
	function __construct()
	{
		$this->cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
		$this->cipher->setKeyLength(256);
		$this->cipher->setKey(Config::get('crypt.crypto_key'));
	}

	function encode($value, $key = null)
	{
		if( $key ){
			$this->cipher->setKey($key);
		}

		return base64_encode($this->cipher->encrypt($value));
	}

	function decode($value, $key = null)
	{
		if( $key ){
			$this->cipher->setKey($key);
		}

		return $this->cipher->decrypt(base64_decode($value));
	}
}
