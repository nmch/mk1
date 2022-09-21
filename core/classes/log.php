<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * ログ
 *
 * @method static void emergency($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void alert($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void critical($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void error($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void warning($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void notice($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void info($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug1($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug2($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug3($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug4($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug5($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void coredebug($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 *
 * @method static Log instance()
 */
class Log
{
    use Singleton;

    const ALL = 0;
    const COREDEBUG = 100;
    const DEBUG = 200;
    const DEBUG5 = 214;
    const DEBUG4 = 215;
    const DEBUG3 = 216;
    const DEBUG2 = 217;
    const DEBUG1 = 218;
    const INFO = 300;
    const NOTICE = 350;
    const WARNING = 400;
    const ERROR = 500;
    const CRITICAL = 600;
    const ALERT = 650;
    const EMERGENCY = 700;

    const LEVEL_COREDEBUG = 'COREDEBUG';
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_DEBUG5 = 'DEBUG5';
    const LEVEL_DEBUG4 = 'DEBUG4';
    const LEVEL_DEBUG3 = 'DEBUG3';
    const LEVEL_DEBUG2 = 'DEBUG2';
    const LEVEL_DEBUG1 = 'DEBUG1';
    const LEVEL_INFO = 'INFO';
    const LEVEL_NOTICE = 'NOTICE';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    const LEVEL_ALERT = 'ALERT';
    const LEVEL_EMERGENCY = 'EMERGENCY';

    const BEFORE_FUNCTION = 'BEFORE_FUNCTION'; // Log::$threshold 判定後、ログドライバ呼び出し前
    const AFTER_WRITE_FUNCTION = 'AFTER_WRITE_FUNCTION'; // 各ログドライバ呼び出し直後 (ドライバに送信したデータが手に入る)
    const AFTER_FUNCTION = 'AFTER_FUNCTION'; // 全てのログドライバ呼び出し後

    protected array $functions = [
        'BEFORE_FUNCTION' => [],
        'AFTER_WRITE_FUNCTION' => [],
        'AFTER_FUNCTION' => [],
    ];

    static $threshold;
    static $date_format;
    static $makelogstr_function;
    static private $drivers = [];

    function __construct()
    {
        $uniqid = uniqid();
        $drivers = Config::get('log.drivers', []);
        $global_driver_config = Config::get('log', []);
        if (is_array($drivers)) {
            foreach ($drivers as $index => $config_value) {
                /**
                 * driver_name => threshold 形式と
                 * index => config array 形式に対応する
                 * $config_valueが配列かどうかで判断する
                 * nullの場合は未定義として処理を飛ばす
                 */
                if ($config_value === null) {
                    continue;
                }

                if (!is_array($config_value)) {
                    $config_value = [
                        'name' => $index,
                        'threshold' => $config_value,
                    ];
                }

                $driver_name = Arr::get($config_value, 'driver') ?: Arr::get($config_value, 'name');
                if (!strlen($driver_name)) {
                    throw new MkException('invalid driver name');
                }
                $default_driver_config = Arr::get($global_driver_config, "default.{$driver_name}", []);

                $driver_config = array_merge($global_driver_config, $default_driver_config, $config_value);

                $driver_class_name = 'Log_'.ucfirst($driver_name);
                $driver_config['uniqid'] = $uniqid;
                $driver_config['driver_object'] = new $driver_class_name($driver_config);
                unset($driver_config['default']);

                static::$drivers[] = $driver_config;
            }
        }
        static::$date_format = Config::get('log.date_format', 'Y-m-d H:i:s');
        //static::$makelogstr_function = ['Log','make_log_string'];
        static::set_make_log_string_function(['Log', 'make_log_string']);
    }

    /**
     * 以降のログ記録を抑制する
     *
     * @param  null  $target_name
     * @param  null  $target_driver
     */
    public static function suppress($target_name = null, $target_driver = null)
    {
        foreach (static::$drivers as $index => $driver_config) {
            $name = Arr::get($driver_config, 'name');
            if ($target_name && $target_name !== $name) {
                continue;
            }

            $driver = Arr::get($driver_config, 'driver');
            if ($target_driver && $target_driver !== $driver) {
                continue;
            }

            Log::coredebug("Log [name={$name} / driver={$driver}] suppressed");
            static::$drivers[$index]['suppress'] = true;
        }
    }

    /**
     * 以降のログ記録の抑制を解除する
     *
     * @param  null  $target_name
     * @param  null  $target_driver
     */
    public static function unsuppress($target_name = null, $target_driver = null)
    {
        foreach (static::$drivers as $index => $driver_config) {
            $name = Arr::get($driver_config, 'name');
            if ($target_name && $target_name !== $name) {
                continue;
            }

            $driver = Arr::get($driver_config, 'driver');
            if ($target_driver && $target_driver !== $driver) {
                continue;
            }

            Log::coredebug("Log [name={$name} / driver={$driver}] unsuppressed");
            static::$drivers[$index]['suppress'] = false;
        }
    }

    static function set_make_log_string_function($function_name)
    {
        static::$makelogstr_function = $function_name;
    }

    /**
     * ログに記録する文字列を生成する
     *
     * set_make_log_string_function()で書き換え可能
     */
    static function make_log_string($data)
    {
        $caller = [];
        $backtraces = debug_backtrace();
        foreach ($backtraces as $backtrace) {
            if (Arr::get($backtrace, 'class') !== 'Log') {
                $caller = $backtrace;
                break;
            }
        }
        $data['source'] = $caller;

        if (!is_scalar($data['message'])) {
            if (is_object($data['message']) && method_exists($data['message'], '__toString')) {
                $data['message'] = (string) $data['message'];
            } else {
                $data['message'] = var_export($data['message'], true);
            }
        }

        $log_str = $log_format = Arr::get($data, 'config.log_format');
        if (preg_match_all('/\{([a-z0-9_.]+|@[^}]+)}/', $log_format, $vars)) {
            foreach ($vars[1] as $var) {
                $var_value = '';

                if (strlen($var) >= 1 && $var[0] === '@') {
                    // 先頭に@があった場合はPHPの関数として解釈する
                    $php_function = 'return '.ltrim($var, '@').';';
                    try {
                        $var_value = eval($php_function);
                    } catch (Exception $e) {
                        $var_value = $var;
                    }
                } else {
                    $var_value = Arr::get($data, $var);
                }

                $log_str = str_replace('{'.$var.'}', $var_value, $log_str);
            }
        }

        return $log_str;
    }

    static function set_global_threshold($threshold)
    {
        $original_threshold = static::get_global_threshold();

        static::$threshold = $threshold;

        return $original_threshold;
    }

    static function get_global_threshold()
    {
        return static::$threshold;
    }

    static function get_level_num_to_string(int $level_num): string
    {
        $map = [
            static::COREDEBUG => static::LEVEL_COREDEBUG,
            static::DEBUG => static::LEVEL_DEBUG,
            static::DEBUG5 => static::LEVEL_DEBUG5,
            static::DEBUG4 => static::LEVEL_DEBUG4,
            static::DEBUG3 => static::LEVEL_DEBUG3,
            static::DEBUG2 => static::LEVEL_DEBUG2,
            static::DEBUG1 => static::LEVEL_DEBUG1,
            static::INFO => static::LEVEL_INFO,
            static::NOTICE => static::LEVEL_NOTICE,
            static::WARNING => static::LEVEL_WARNING,
            static::ERROR => static::LEVEL_ERROR,
            static::CRITICAL => static::LEVEL_CRITICAL,
            static::ALERT => static::LEVEL_ALERT,
            static::EMERGENCY => static::LEVEL_EMERGENCY,
        ];

        return $map[$level_num];
    }

    static function capture(Closure $func, Closure $callback, int $threshold = Log::ALL, $type = Log::AFTER_FUNCTION)
    {
        $log = Log::instance();

        $id = $log->register_function($type, $callback, $threshold);

        try {
            return $func();
        } finally {
            $log->unregister_function($id);
        }
    }

    function register_function($type, Closure $func, int $threshold = Log::ALL): string
    {
        $id = uniqid('LOG_FUNC_');
        $key = "{$type}.{$id}";
        Arr::set($this->functions, $key, [
            'key' => $key,
            'threshold' => $threshold,
            'function' => $func,
        ]);

        return $key;
    }

    function unregister_function($key)
    {
        Arr::delete($this->functions, $key);
    }

    static function log($level, ...$messages)
    {
        $log = Log::instance();
        $log->write($level, ...$messages);
    }

    function write($level, ...$messages)
    {
        try {
            $level = strtoupper($level);
            $level_const_name = 'Log::'.$level;
            if (!defined($level_const_name)) {
                throw new MkException('undefined log level');
            }
            $level_num = constant($level_const_name);
            if ($level_num < static::$threshold) {
                return false;
            }

            $timestamp_unixtime = time();
            // キーで使える文字はmake_log_string()内で[a-z0-9_.]に制限されている
            /** @see \Log::make_log_string */
            $base_log_data = [
                'timestamp_unixtime' => $timestamp_unixtime,
                'timestamp_string' => date(static::$date_format, $timestamp_unixtime),
                'level' => $level,
                'level_num' => $level_num,
            ];

            $this->call_functions(Log::BEFORE_FUNCTION, $level_num, $base_log_data, $messages);

            foreach (static::$drivers as $driver_config) {
                if (Arr::get($driver_config, 'suppress')) {
                    continue;
                }
                if ($driver_config['threshold'] <= $level_num) {
                    $log_data = $base_log_data + [
                            'config' => $driver_config,
                        ];

                    if ($add_env = ($driver_config['add_backtrace'] ?? null)) {
                        $log_data['backtrace'] = debug_backtrace(0);
                    }

                    if ($add_env = ($driver_config['add_env'] ?? null)) {
                        $log_data['env'] = [];
                        if (is_array($add_env)) {
                            foreach ($add_env as $add_env_key => $add_env_item) {
                                $log_data['env'][$add_env_key] = getenv($add_env_item);
                            }
                        } else {
                            $log_data['env'] = getenv();
                        }
                    }

                    $write_data = $log_data;
                    if (Arr::get($driver_config, 'aggregate')) {
                        $write_data['messages'] = [];
                        foreach ($messages as $message) {
                            $write_data['messages'][] = [
                                'message' => $message,
                                'str' => call_user_func_array(static::$makelogstr_function, [
                                    $log_data + ['message' => $message],
                                ]),
                            ];
                        }

                        /** @var Logic_Interface_Log_Driver $driver */
                        $driver = $driver_config['driver_object'];
                        $driver->write($write_data);
                        $this->call_functions(Log::AFTER_WRITE_FUNCTION, $level_num, $write_data);
                    } else {
                        foreach ($messages as $message) {
                            $write_data['message'] = $message;
                            $write_data['str'] = call_user_func_array(static::$makelogstr_function, [$write_data]);

                            /** @var Logic_Interface_Log_Driver $driver */
                            $driver = $driver_config['driver_object'];
                            $driver->write($write_data);
                            $this->call_functions(Log::AFTER_WRITE_FUNCTION, $level_num, $write_data);
                        }
                    }
                }

                unset($log_data);
                unset($log_str);
            }

            $this->call_functions(Log::AFTER_FUNCTION, $level_num, $base_log_data, $messages);

            unset($messages);
        } catch (Exception $e) {
            if ($e instanceof \PHPUnit\Framework\Exception) {
                throw $e;
            } else {
                echo $e;
            }
        }
    }

    /**
     * @param  string  $type
     * @param  int  $log_level
     *
     * @return Generator<Closure>
     */
    protected function functions(string $type, int $log_level): Generator
    {
        $functions = $this->functions[$type] ?? [];
        foreach ($functions as $item) {
            $function = Arr::get($item, 'function');
            if ($function instanceof Closure) {
                $threshold = $item['threshold'] ?? Log::ALL;
                if ($threshold <= $log_level) {
                    yield $log_level => $function;
                }
            }
        }
    }

    protected function call_functions(string $type, int $log_level, ...$params)
    {
        foreach ($this->functions($type, $log_level) as $function) {
            $function($log_level, ...$params);
        }
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(['static', 'log'], array_merge([$name], $arguments));
    }
}
