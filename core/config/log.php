<?
return array(
	'drivers'		=> array(
		'file' => Log::ALL,
	),
	'path'			=> PROJECTPATH.'logs/',
	'filename'		=> 'Y-m-d',
	'date_format'	=> 'Y-m-d H:i:s',
	'log_format'	=> '{level} - {timestamp_string} --> {message}',
);
