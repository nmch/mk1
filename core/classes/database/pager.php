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
		$rows = $this->get('rows');	// 1ページあたりの行数
		$page = $this->get('page');	// 指定ページ(1 origin)
		if( ! $rows )
			$rows = 10;
		if( ! $page )
			$page = 1;
		
		$r1 = $this->db_query->execute();
		
		$total_rows = $r1->count();					// 結果の全行数
		$total_pages = ceil($total_rows / $rows);	// 結果の全ページ数
		unset($r1);
		
		if($total_pages < $page)
			$page = $total_pages;
		
		$offset = $rows * ($page - 1);
		$r2 = $this->db_query->offset($offset)->limit($rows)->execute();
		
		$this->set('total_rows',$total_rows);
		$this->set('total_pages',$total_pages);
		$this->set('page',$page);
		$this->set('rows',$rows);
		$this->set('is_first_page', $page == 1 );
		$this->set('is_last_page', $page >= $total_pages );
		
		return $r2;
	}
	function __set($name,$value)
	{
		return $this->set($name,$value);
	}
	function set($name,$value)
	{
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
