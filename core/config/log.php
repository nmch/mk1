<?php
return [
	'drivers'     => [
		'file' => [
			'name'      => 'file',
			'driver'    => 'file',
			'threshold' => Log::ALL,
		],

		// 旧形式
		//'file' => Log::ALL,
	],
	'path'        => PROJECTPATH . 'logs/',
	'filename'    => 'Ymd',
	'fileext'     => 'log',
	'date_format' => 'Y-m-d H:i:s',
	'log_format'  => '{timestamp_string} [{config.uniqid}] - {level} :  {message}',
];
