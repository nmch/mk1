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
	/** @var string 取得した結果にas_object_array()を実行するかどうか */
	public $as_object_array = null;
	//	public $is_first_page;
	//	public $is_last_page;

	/** @var Database_Query|Model_Query */
	protected $query;
	/** @var array */
	public $list = [];

	function view()
	{
		parent::view();

		$method_name = 'before_retrieve';
		if( method_exists($this, $method_name) ){
			call_user_func([$this, $method_name]);
		}

		$r = $this->retrieve();

		$method_name = 'after_retrieve';
		if( method_exists($this, $method_name) ){
			call_user_func([$this, $method_name]);
		}

		return $r;
	}

	/**
	 * @return null|Response_File
	 * @throws Exception
	 */
	function retrieve()
	{
		$query = $this->query = $this->get_query();
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

		if( $this->af->export ){
			$export_method_name = "export_{$this->af->export}";
			if( method_exists($this, $export_method_name) ){
				$r = call_user_func_array([$this, $export_method_name], [$query, $this->af]);

				return $r;
			}
			elseif( property_exists($this, 'export_settings') ){
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
		}
		else{
			$r          = DB::pager($query, $this->af)->execute();
			$this->list = $this->as_object_array ? $r->as_object_array($this->as_object_array) : $r;
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
			// スカラー値か配列でない場合は無視する
			if( ! is_scalar($values) && ! is_array($values) ){
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
				$values = explode(' ', trim($values));
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
