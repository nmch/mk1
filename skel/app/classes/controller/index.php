<?php
/**
 * Indexコントローラ
 * 
 * 
 * 
 * @package    App
 * @subpackage Controller
 * @author     Hakonet Inc
 */
class Controller_Index extends Controller_Common
{
	function action_index()
	{
		return new View_Index_Index();
	}
	function action_404()
	{
		return new Response(new View_Common('index/404'),404);
	}
	function action_500()
	{
		return new Response(new View_Common('index/500'),500);
	}
}
