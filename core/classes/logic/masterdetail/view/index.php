<?php

/**
 * マスタデータ編集
 *
 * @mixin View_Common
 *
 * @package    App
 * @subpackage Logic
 * @author
 */
trait Logic_Masterdetail_View_Index
{
	use Logic_Masterdetail_Common, Logic_View_Pager;
	
	/** @var bool 新規作成ボタン表示有無 */
	public $can_create = true;
	/** @var bool 詳細リンク表示有無 */
	public $can_access_detail = true;
	
	function after_view()
	{
		parent::after_view();
		
		$this->app_data['can_create']        = $this->can_create;
		$this->app_data['can_access_detail'] = $this->can_access_detail;
		$this->app_data['id_name']           = $this->id_name;
		
		$this->app_data['form']        = $this->af->as_array();
		$this->app_data['paging_data'] = $this->af->paging_data;
		$this->app_data['list']        = is_object($this->list) ? $this->list->as_array() : $this->list;
	}
}
