<?php

class Response_Redirect extends Response
{
	protected $redirect_url;

	function __construct($url = '', $method = 'location', $redirect_code = 302)
	{
		$this->redirect_url = $url;
		$this->set_status($redirect_code);

		if( $method == 'location' ){
			$this->set_header('Location', $url);
		}
		elseif( $method == 'refresh' ){
			$this->set_header('Refresh', '0;url=' . $url);
		}
	}

	function get_redirect_url()
	{
		return $this->redirect_url;
	}
}
