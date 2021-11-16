<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Cache_Driver_File extends Cache_Driver
{
	public function set(string $key, $value, ?string $group = null, ?int $expire = null)
	{
		if( $expire !== null ){
			throw new MkException("Fileキャッシュドライバではset時のexpire指定はサポートされていません");
		}
		
		$cache_dir = $this->cache_dir($key, $group);
		$filepath  = $cache_dir . $this->key($key, $group);
		
		if( ! file_exists($cache_dir) ){
			try {
				$r = mkdir($cache_dir, 0777, true);
				if( $r === false ){
					throw new MkException();
				}
			} catch(Exception $e){
				throw new MkException("キャッシュ保存ディレクトリ({$cache_dir})が作成できませんでした", $e->getCode(), $e);
			}
		}
		
		$r = file_put_contents($filepath, serialize($value));
		if( $r === false ){
			throw new MkException('キャッシュファイルに書き込めませんでした');
		}
	}
	
	public function get(string $key, ?string $group = null)
	{
		$cache_dir = $this->cache_dir($key, $group);
		$filepath  = $cache_dir . $this->key($key, $group);
		
		if( file_exists($filepath) ){
			$expire = $this->config('default_expire', 0);
			if( $expire ){
				$filemtime = filemtime($filepath);
				$live_time = (time() - $filemtime);
				if( $live_time < $expire ){
					$data = unserialize(file_get_contents($filepath));
					
					return $data;
				}
				else{
					unlink($filepath);
				}
			}
		}
		
		throw new CacheMissException();
	}
	
	public function clear(?string $key = null, ?string $group = null)
	{
		$cache_dir = $this->cache_dir($key, $group);
		
		if( $key ){
			$filepath = $cache_dir . $this->key($key, $group);
		}
		else{
			$filepath = $cache_dir;
		}
		
		try {
			File::rm($filepath);
		} catch(Exception $e){
			// 削除できなくてもエラーにはしない
		}
	}
	
	protected function key($key, $group = null)
	{
		if( ! $key ){
			throw new Exception('invalid key');
		}
		$key = $this->hash($key);
		
		if( $group ){
			$key = $group . '_' . $key;
		}
		
		return $key;
	}
	
	protected function cache_dir($key, $group = null)
	{
		$cache_dir = $this->config('cache_dir');
		if( ! $cache_dir ){
			throw new Exception("不正なキャッシュディレクトリ({$cache_dir})です");
		}
		if( $group ){
			$cache_dir .= $this->hash($group) . '/';
		}
		if( $key ){
			// ここでkeyが必須ではないのは、migration時等にディレクトリだけ特定して全部消す用途でも使われるから。
			$cache_dir .= substr($this->hash($key), 0, 3) . '/';
		}
		
		return $cache_dir;
	}
	
}
