<?php
class Task_Init
{
	function run()
	{
		if(func_num_args() == 0){
			echo "プロジェクト名を指定して下さい\n";
			exit;
		}
		$name = func_get_arg(0);
		echo "プロジェクト $name を作成します\n";
		if(file_exists($name)){
			echo "ディレクトリ $name はすでに存在しています\n";
			exit;
		}
		if(mkdir($name) === false){
			echo "ディレクトリ $name が作成できませんでした\n";
			exit;
		}
		if(chdir($name) === false){
			echo "ディレクトリ $name に移動出来ません\n";
			exit;
		}
		if(symlink(FWPATH, FWNAME) === false){
			echo "フレームワーク ".FWPATH." のシンボリックリンクが作成できませんでした\n";
			exit;
		}
		
		// スケルトンのコピー
		$cmd = "cp -ar ".FWPATH."skel/* ./";
		passthru($cmd);
		
		// mkコマンドのリンク
		if(symlink('./mk1/mk.php', 'mk') === false){
			echo "mkコマンドのシンボリックリンクが作成できませんでした\n";
			exit;
		}
		
		// Git 初期化
		passthru("git init");
	}
	
	protected $initial_directories = array(
	);
}