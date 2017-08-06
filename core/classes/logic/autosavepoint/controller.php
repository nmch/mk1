<?php

/**
 * Class Logic_Controller_Autosavepoint
 *
 * @mixin Controller
 */
trait Logic_Autosavepoint_Controller
{
	protected $autostart_transaction  = false;
	protected $transaction_savepoint;
	protected $on_appexception_return = null;
	
	function before_execute($method_name)
	{
		$ref = new ReflectionMethod($this, $method_name);
		$doc = $ref->getDocComment();
		if( strpos($doc, '@autostart_transaction') !== null ){
			$this->autostart_transaction = true;
		}
		
		if( $this->autostart_transaction ){
			$this->transaction_savepoint = DB::place_savepoint();
		}
	}
	
	function after_execute()
	{
		if( $this->autostart_transaction && $this->transaction_savepoint ){
			DB::commit_savepoint($this->transaction_savepoint);
		}
	}
	
	function onerror(Exception $e)
	{
		if( $this->autostart_transaction && $this->transaction_savepoint ){
			DB::rollback_savepoint($this->transaction_savepoint);
			$this->transaction_savepoint = null;
		}
		
		if( $e instanceof AppException || $e instanceof ValidateErrorException ){
			if( $this->on_appexception_return ){
				$this->af->set_message("error", $e->getMessage());
				
				if( is_callable($this->on_appexception_return) ){
					return call_user_func($this->on_appexception_return);
				}
				if( $this->on_appexception_return instanceof View || $this->on_appexception_return instanceof Response ){
					return $this->on_appexception_return;
				}
				if( is_string($this->on_appexception_return) ){
					return new Response_Redirect($this->on_appexception_return);
				}
			}
		}
		
		throw $e;
	}
}