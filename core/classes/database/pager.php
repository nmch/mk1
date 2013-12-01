<?
class Database_Pager
{
	var $db_query;
	var $options;
	
	function __construct(&$db_query, &$options)
	{
		if( ! is_object($options) )
			$options = (object)$options;
		
		$this->db_query = $db_query;
		$this->options = $options;
	}
	/**
	 * ページングを実行
	 *
	 * @todo 最初のクエリ実行時にlimit, offsetのクリアが必要
	 */
	function execute()
	{
		$rows = (int) $this->get('rows');	// 1ページあたりの行数
		$page = (int) $this->get('page');	// 指定ページ(1 origin)
		if( ! $rows )
			$rows = 10;
		if( ! $page )
			$page = 1;
		//Log::coredebug("[db pager] rows=$rows / page=$page");
		
		if($this->db_query instanceof Model_Query)
			$db_query_clone = clone $this->db_query->get_query();
		else
			$db_query_clone = clone $this->db_query;
		$total_rows = $db_query_clone->clear_order_by()->clear_select()->select('count(*) as count')->set_fetch_as(NULL)->execute()->get('count');
		unset($db_query_clone);
		$total_pages = ceil($total_rows / $rows);	// 結果の全ページ数
		
		if($total_pages < $page)
			$page = $total_pages;
		
		$offset = $page ? $rows * ($page - 1) : 0;
		//Log::coredebug("[db pager] total_pages=$total_pages / page=$page / offset=$offset");
		$r2 = $this->db_query->offset($offset)->limit($rows)->execute();
		
		$paging_data = array(
			'total_rows'    => $total_rows,
			'total_pages'   => $total_pages,
			'page'          => $page,
			'rows'          => $rows,
			'is_first_page' => ($page <= 1) ,
			'is_last_page'  => ($page >= $total_pages),
		);
		
		/*
		$this->set('total_rows',$total_rows);
		$this->set('total_pages',$total_pages);
		$this->set('page',$page);
		$this->set('rows',$rows);
		$this->set('is_first_page', $page <= 1 );
		$this->set('is_last_page', $page >= $total_pages );
		*/
		$this->set($paging_data);
		$this->set('paging_data',$paging_data);
		return $r2;
	}
	function __set($name,$value)
	{
		return $this->set($name,$value);
	}
	function set($name,$value = NULL)
	{
		if(method_exists($this->options,'set'))
			$this->options->set($name,$value);
		else
			$this->options->$name = $value;
		return $this;
	}
	function get($name)
	{
		if(method_exists($this->options,'get'))
			return $this->options->get($name);
		if(property_exists($this->options,$name))
			return $this->options->$name;
		
		return NULL;
	}
	function __call(string $name , array $arguments)
	{
		return call_user_func_array(array($this->db_query,$name), $arguments);
	}
}
