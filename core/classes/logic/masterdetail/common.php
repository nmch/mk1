<?php

/**
 * マスタデータ編集
 *
 * @package    App
 * @subpackage Logic
 * @author     Hakonet Inc
 */
trait Logic_Masterdetail_Common
{
	private function get_base_class_name()
	{
		$current_class_name = get_class();
		if( ! preg_match('/^Controller_(.+)$/', $current_class_name, $match) ){
			throw new Exception('unexpected class name');
		}

		return $match[1];
	}
}
