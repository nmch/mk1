<?php

/**
 *
 * @property Actionform af
 * @property array      export_settings
 *
 * @package    App
 * @subpackage Logic
 * @author     Hakonet Inc
 */
trait Logic_View_Pager
{
//	public $nolimit_rows;
//	public $total_result;
//	public $offset;

//	public $total_rows;
//	public $total_pages;
//	public $page;
	/** @var int */
	public $default_rows = 10;
//	public $is_first_page;
//	public $is_last_page;

	public $list = [];

	function view()
	{
		parent::view();

		return $this->retrieve();
	}

	/**
	 * @return null|Response_File
	 * @throws Exception
	 */
	function retrieve()
	{
		$query = $this->get_query();
		if( $query === null ){
			return null;
		}

		foreach($this->search_elements() as list($key, $value)){
			$method_name = 'search_' . $key;
			if( method_exists($this, $method_name) ){
				$this->$method_name($key, $value, $query);
			}
		}

		$this->af->rows = $this->af->rows ?: $this->default_rows;

		if( $this->af->export && property_exists($this, 'export_settings') ){
			$export_settings = $this->export_settings;

			$dataexchange_format         = Arr::get($export_settings, 'dataexchange_format', []);
			$default_dataexchange_format = Arr::get($export_settings, 'default_dataexchange_format', 'default');
			$export_filename             = Arr::get($export_settings, 'export_filename', 'export');
			$functions                   = Arr::get($export_settings, 'functions', []);

			return Logic_File::respond_obj_as_csv(
				$query->get(),
				Arr::get($dataexchange_format, $default_dataexchange_format, []),
				$export_filename,
				$functions
			);
		}
		else{
			$this->list = DB::pager($query, $this->af)->execute();
		}

		return null;
	}

	/**
	 * ページング対象のデータ取得クエリ作成
	 *
	 * @return Database_Query|Model_Query
	 */
	abstract protected function get_query();

	/**
	 * 検索キーワードをループさせるときに使うジェネレータ
	 *
	 * @return Generator
	 */
	function search_elements()
	{
		if( empty($this->af) || ! $this->af instanceof Actionform ){
			$filters = [];
		}
		else{
			$filters = $this->af->as_array();
		}
		
		foreach($filters as $key => $values){
			// スカラー値でない場合は無視する
			if( ! is_scalar($values) ){
				continue;
			}
			// ページャで使うパラメータは無視する
			if( in_array($key, ['page', 'rows']) ){
				continue;
			}

			$search_options = property_exists($this, 'search_options') ? $this->search_options : [];

			$option = Arr::get($search_options, "items.{$key}", []);
			if( ! is_array($option) ){
				$option = [$option];
			}

			// スペースによる複数指定
			if( is_scalar($values) ){
				$values = explode(' ', $values);
			}
			if( ! $values ){
				$values = [];
			}

			// 空文字列の要素は削除する
			foreach($values as $values_key => $str){
				if( $str === '' ){
					unset($values[$values_key]);
				}
			}

			// 分割した配列ごと返す
			if( in_array('array_agg', $option) ){
				yield [$key, $values];
			}
			else{
				foreach($values as $value){
					yield [$key, $value];
				}
			}
		}

	}
}
