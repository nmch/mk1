<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Controller_Error extends Controller
{
	public function action_404()
	{
		return new Response("Not Found", 404);
	}

	public function action_500()
	{
		$body = "Error";

		if( Mk::$env == Mk::DEVELOPMENT ){
			if( $this->af->error && $this->af->error instanceof Exception ){
				$body = "<PRE>" . (string)$this->af->error . "</PRE>";
			}
		}

		return new Response($body, 500);
	}
}