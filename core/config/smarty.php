<?

return [
    'extension' => 'smarty',
    'auto_encode' => true,
    'delimiters' => ['left' => '{', 'right' => '}'],
    'environment' => [
        'compile_dir' => PROJECTPATH.'tmp'.DS.'Smarty'.DS.'templates_c'.DS,
        'config_dir' => PROJECTPATH.'tmp'.DS.'Smarty'.DS.'configs'.DS,
        'cache_dir' => PROJECTPATH.'tmp'.DS.'Smarty'.DS,
        'plugins_dir' => [],
        'caching' => false,
        'cache_lifetime' => 0,
        'force_compile' => false,
        'compile_check' => true,
        'debugging' => false,
        'autoload_filters' => [],
        'default_modifiers' => ['escape:"html"'],
    ],
];
