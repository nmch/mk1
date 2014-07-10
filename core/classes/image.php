<?
class Image
{
	const ERROR_LOAD_IMAGE	= 1;
	const ERROR_CALL		= 2;
	
	static protected $ext_to_mime_map = array(
		'jpg'	=> 'image/jpeg',
		'jpeg'	=> 'image/jpeg',
		'jpe'	=> 'image/jpeg',
		'png'	=> 'image/png',
		'gif'	=> 'image/gif',
		'bmp'	=> 'image/bmp',
		'tiff'	=> 'image/tiff',
		'tif'	=> 'image/tiff',
	);
	protected $img;
	protected $saved;
	
	/**
	 * アップロードされた画像ファイルを指定ディレクトリへ移動させる
	 *
	 * 保存先のファイルが存在する場合は上書きする
	 *
	 * @throws ImageErrorException
	 * @param 保存先ディレクトリ。存在しない場合は作成を試みる。
	 * @param 保存するファイル名。省略した場合はランダム32文字の文字列を設定する。
	 * @param アップロードされたファイルのname
	 */
	static function move_uploaded_picture($dir, $filename = NULL, $name = 'picture', $options = [])
	{
		if( ! file_exists($dir) ){
			$r = mkdir($dir, 0777, true);
			if($r === FALSE){
				throw new ImageErrorException('mkdir failed');
			}
		}
		if( ! is_dir($dir) || ! is_writable($dir) ){
			throw new ImageErrorException('directory not writable');
		}
		
		if(empty($_FILES[$name]) || Arr::get($_FILES,"{$name}.error") !== UPLOAD_ERR_OK){
			Log::error( Arr::get($_FILES,$name) );
			throw new ImageErrorException('picture not uploaded normally');
		}
		$uploaded_file = $_FILES[$name];
		if( ! is_uploaded_file( Arr::get($uploaded_file,'tmp_name') ) ){
			throw new ImageErrorException('invalid upload file');
		}
		$img = new static(Arr::get($uploaded_file,'tmp_name'));
		
		if( ! $filename ){
			$filename = Mk::make_random_code();
		}
		
		$filepath = rtrim($dir,DS).DS.$filename;
		
		$img->writeImage($filepath);
		
		return $img;
	}
	
	/**
	 * 指定サイズにスケールした画像イメージを得る
	 *
	 * @param width
	 * @param height
	 * @param 中心点からの正方形切り抜きを行う
	 *
	 */
	function fit_and_cut($width,$height = NULL,$square = false,$round = false)
	{
		$width = (int)$width;
		$height = (int)$height ?: 0;
		if( ! $width ){
			throw new ImageErrorException('empty width or height');
		}
		
		if($square){
			$w = $this->img->getImageWidth();
			$h = $this->img->getImageHeight();
			
			$center_x = (int)($w / 2);
			$center_y = (int)($h / 2);
			
			$distances = [
				$w,
				$h,
			];
			sort($distances);
			$distance = reset($distances);
			Log::coredebug("[Image::fit_and_cut] width=$w, height=$h, center_x=$center_x, center_y=$center_y, distance=$distance");
			
			$this->img->cropImage($distance, $distance, (int)($center_x - ($distance/2)), (int)($center_y - ($distance/2)));
		}
		
		if($round){
			$w = $this->img->getImageWidth();
			$h = $this->img->getImageHeight();
			
			$bgcolor = "#000001";
			$triangle_width = $w/5;
			$triangle = new ImagickDraw();
			$triangle->setFillColor($bgcolor);
			$triangle->polygon(array(
				array('x' => 0, 'y' => $h-$triangle_width),
				array('x' => $triangle_width*2, 'y' => $h-$triangle_width),
				array('x' => $triangle_width*2+$triangle_width/2, 'y' => $h),
				array('x' => $triangle_width*3, 'y' => $h-$triangle_width),
				array('x' => $w, 'y' => $h-$triangle_width),
				array('x' => $w, 'y' => $h),
				array('x' => 0, 'y' => $h),
				array('x' => 0, 'y' => $h-$triangle_width),
			));
			$this->img->drawImage($triangle);
			$this->img->transparentPaintImage($bgcolor,0,0,false);
			
			$shadow = clone $this->img;
			$shadow->setImageBackgroundColor('#000000');
			$shadow->shadowImage( 80, 3, 5, 5 ); 
			$shadow->compositeImage( $this->img, Imagick::COMPOSITE_OVER, 0, 0 ); 
			$this->img = $shadow;
		}
		
		//$this->img->scaleImage($width,$height,($width && $height));	//bestfitさせる
		$this->img->scaleImage($width,$height,false);	//bestfitさせない
		
		return $this;
	}
	
	static function ext_to_mime($ext)
	{
		return Arr::get(static::$ext_to_mime_map, strtolower( array_reverse(explode('.',$ext))[0] ));
	}
	
	function __construct($filepath = NULL,$do_not_auto_rotate = false)
	{
		$this->img = new Imagick;
		
		if($filepath){
			$r = $this->readImage($filepath);
			if($r !== true){
				throw new ImageErrorException("Load Error",static::ERROR_LOAD_IMAGE,$e);
			}
			if( ! $do_not_auto_rotate ){
				$this->auto_rotate();
			}
		}
	}
	function auto_rotate($use_exif_read_data = false)
	{
		if($use_exif_read_data && function_exists('exif_read_data')){
			// ImageMagickのgetImageOrientation()は未定義のOrientationに対して6を返すバグ?があるので
			// 一度テンポラリファイルに保存してからexif_read_data()で判定する
			try {
				$tmp_filename = tempnam(NULL, 'IMG');
				$this->writeImage($tmp_filename);
				//file_put_contents($tmp_filename, $this->getImageBlob());
				$orientation = Arr::get(exif_read_data($tmp_filename), 'Orientation', imagick::ORIENTATION_TOPLEFT);
				unlink($tmp_filename);
			} catch(Exception $e){
				Log::error("Image::auto_rotate()でエラーが発生しました",$e);
			}
		}
		
		if(empty($orientation)){
			$orientation = $this->getImageOrientation();
		}
		
		//Log::coredebug("Image auto_rotate : orientation=$orientation");
		switch ($orientation) {
			case imagick::ORIENTATION_UNDEFINED:	// 0
				break;
			case imagick::ORIENTATION_TOPLEFT:		// 1	そのまま
				break;
			case imagick::ORIENTATION_TOPRIGHT:		// 2	左右の鏡像
				$this->flopImage();
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
			case imagick::ORIENTATION_BOTTOMRIGHT:	// 3	180度回転
				$this->rotateImage(new ImagickPixel(), 180);
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
			case imagick::ORIENTATION_BOTTOMLEFT:	// 4	3+鏡像
				$this->rotateImage(new ImagickPixel(), 270);
				$this->flopImage();
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
			case imagick::ORIENTATION_LEFTTOP:		// 5	6+鏡像
				$this->rotateImage(new ImagickPixel(), 90);
				$this->flopImage();
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
			case imagick::ORIENTATION_RIGHTTOP:		// 6	右に90度回転
				$this->rotateImage(new ImagickPixel(), 90);
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
			case imagick::ORIENTATION_RIGHTBOTTOM:	// 7	8+鏡像
				$this->rotateImage(new ImagickPixel(), 270);
				$this->flopImage();
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
			case imagick::ORIENTATION_LEFTBOTTOM:	// 8	右に270度回転
				$this->rotateImage(new ImagickPixel(), 270);
				$this->setimageorientation(imagick::ORIENTATION_TOPLEFT);
				break;
		}
		$this->setImageOrientation(imagick::ORIENTATION_TOPLEFT);
		return $this;
	}
	function __call($name , array $arguments)
	{
		try {
			$r = call_user_func_array(array($this->img,$name), $arguments);
			return $r;
		} catch(ImagickException $e){
			throw new ImageErrorException("Call Error",static::ERROR_CALL,$e);
		}
	}
}
