<?
return array(
	'extension' => 'smarty',
	'auto_encode' => true,
	'delimiters'    => array('left' => '{', 'right' => '}'),
	'environment'   => array(
		'compile_dir'       => PROJECTPATH.'tmp'.DS.'Smarty'.DS.'templates_c'.DS,
		'config_dir'        => PROJECTPATH.'tmp'.DS.'Smarty'.DS.'configs'.DS,
		'cache_dir'         => PROJECTPATH.'tmp'.DS.'Smarty'.DS,
		'plugins_dir'       => array(COREPATH.'plugin'.DS.'Smarty'.DS),
		'caching'           => false,
		'cache_lifetime'    => 0,
		'force_compile'     => false,
		'compile_check'     => true,
		'debugging'         => false,
		'autoload_filters'  => array(),
		'default_modifiers' => array('escape:"htmlall"'),
	),
);
