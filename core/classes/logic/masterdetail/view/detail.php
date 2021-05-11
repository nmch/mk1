<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * マスタデータ編集
 *
 * @property Actionform af
 *
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
