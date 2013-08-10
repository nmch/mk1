<?
class Image
{
	static $ext_to_mime_map = array(
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpe' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
	);
	
	static function ext_to_mime($ext)
	{
		
		return Arr::get(static::$ext_to_mime_map, strtolower( array_reverse(explode('.',$ext))[0] ));
	}
}
