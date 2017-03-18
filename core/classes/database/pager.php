<?php

class Database_Pager
{
	const ROWS_NO_LIMIT = 'nolimit';
	
	/** @var Database_Query $db_query */
	var $db_query;
	/** @var Actionform $options */
	var $options;
	/** @var array $query_options */
	var $query_options;
	
	function __construct(&$db_query, &$options, $query_options = [])
	{
		if( ! is_object($options) ){
			$options = (object)$options;
		}
		
		$this->db_query      = $db_query;
		$this->options       = $options;
		$this->query_options = $query_options;
	}
	
	/**
	 * ページングを実行
	 *
	 * @returns Database_Resultset
	 * @todo 最初のクエリ実行時にlimit, offsetのクリアが必要
	 */
	function execute()
	{
		$rows         = $this->get('rows');                                     // 1ページあたりの行数
		$nolimit_rows = ($rows === 'nolimit' || $this->get('nolimit_rows'));    // 1ページあたりの行数を無制限にするフラグ
		$page         = (int)$this->get('page');                                // 指定ページ(1 origin)
		$page         = $page ?: 1;
		$rows         = intval($rows) ?: 10;
		//Log::coredebug("[db pager] rows=$rows / page=$page");
		
		/**
		 * レコード数・オフセット計算
		 */
		{
			if( $this->db_query instanceof Model_Query ){
				$query_for_total = clone $this->db_query->get_query();
			}
			else{
				$query_for_total = clone $this->db_query;
			}
			$query_for_total->clear_order_by()->clear_select()->clear_into()
			                ->select('count(*) as count')->set_fetch_as(null);
			
			// 合計数といっしょに計算する内容の追加
			$add_col_to_total = Arr::get($this->query_options, 'add_col_to_total');
			if( $add_col_to_total && ! is_array($add_col_to_total) ){
				$add_col_to_total = [$add_col_to_total];
			}
			if( $add_col_to_total ){
				foreach($add_col_to_total as $add_key => $add_item){
					$query_for_total->select("{$add_item} as {$add_key}");
				}
			}
			
			$total_result = $query_for_total->execute();
			$total_rows   = $total_result->get('count');
			unset($query_for_total);
			if( $nolimit_rows ){
				// 行数無制限
				$rows        = $total_rows;
				$total_pages = 1;
				$offset      = 0;
			}
			else{
				$total_pages = ceil($total_rows / $rows);    // 結果の全ページ数
				if( $total_pages < $page ){
					$page = $total_pages;
				}
				
				$offset = $page ? $rows * ($page - 1) : 0;
			}
		}
		
		//Log::coredebug("[db pager] total_pages=$total_pages / page=$page / offset=$offset");
		$result_list = $this->db_query->offset($offset)->limit($rows)->execute();
		
		$paging_data = [
			'total_rows'    => $total_rows,
			'total_pages'   => $total_pages,
			'page'          => $page,
			'rows'          => $rows,
			'is_first_page' => ($page <= 1),
			'is_last_page'  => ($page >= $total_pages),
		];
		
		// 合計数といっしょに計算した内容の追加
		if( $add_col_to_total ){
			foreach($add_col_to_total as $add_key => $add_item){
				$paging_data[$add_key] = $total_result->get($add_key);
			}
		}
		
		/*
		$this->set('total_rows',$total_rows);
		$this->set('total_pages',$total_pages);
		$this->set('page',$page);
		$this->set('rows',$rows);
		$this->set('is_first_page', $page <= 1 );
		$this->set('is_last_page', $page >= $total_pages );
		*/
		$this->set($paging_data);
		$this->set('paging_data', $paging_data);
		
		return $result_list;
	}
	
	function get($name)
	{
		if( method_exists($this->options, 'get') ){
			return $this->options->get($name);
		}
		if( property_exists($this->options, $name) ){
			return $this->options->$name;
		}
		
		return null;
	}
	
	function set($name, $value = null)
	{
		if( method_exists($this->options, 'set') ){
			$this->options->set($name, $value);
		}
		else{
			$this->options->$name = $value;
		}
		
		return $this;
	}
	
	function __set($name, $value)
	{
		return $this->set($name, $value);
	}
	
	function __call(string $name, array $arguments)
	{
		return call_user_func_array([$this->db_query, $name], $arguments);
	}
}
