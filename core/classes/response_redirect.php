<?php

class Response_Redirect extends Response
{
	function __construct($url = '', $method = 'location', $redirect_code = 302)
	{
		$this->set_status($redirect_code);

		if( $method == 'location' ){
			$this->set_header('Location', $url);
		}
		elseif( $method == 'refresh' ){
			$this->set_header('Refresh', '0;url=' . $url);
		}
	}
}
