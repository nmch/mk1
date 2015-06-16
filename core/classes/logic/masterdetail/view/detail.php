<?php

/**
 * マスタデータ編集
 *
 * @package    App
 * @subpackage Logic
 * @author     Hakonet Inc
 */
trait Logic_Masterdetail_View_Detail
{
	public $item;

	function before_render_set_default()
	{
		$this->af->set_default($this->item);
	}
}
