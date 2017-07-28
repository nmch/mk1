<?php

/**
 * マスタデータ編集
 *
 * @property Actionform af
 *
 * @package    App
 * @subpackage Logic
 * @author
 */
trait Logic_Masterdetail_View_Detail
{
	/** @var Model */
	public $item;

	function before_render_set_default()
	{
		$this->af->set_default($this->item);
	}
}
