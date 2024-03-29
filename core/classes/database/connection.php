<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Database_Connection
{
    /** @var Database_Connection[] $instances */
    static $instances = [];
    /** @var resource $connection */
    protected $connection;
    protected $savepoint_counter = 0;
    protected $current_database_name;
    /** @var array 最後のエラー(最後のクエリが成功した場合は空配列) */
    protected $last_error_details = [];

    function __construct(array $config)
    {
        $connection_config = static::get_connection_config($config);

        //Log::coredebug("[db connection] try connect to [{$connection_config}]");
        $connect_retry = intval(Arr::get($config, 'connect_retry'), 0);
        $connect_retry_interval = intval(Arr::get($config, 'connect_retry_interval'), 0);

        $this->connection = \Mk::retry(function ($connection_config) {
            return pg_connect($connection_config, PGSQL_CONNECT_FORCE_NEW);
        }, [$connection_config], $connect_retry, $connect_retry_interval);


        if ($this->connection === false) {
            $connection_config_to_display = preg_replace("/password=[^ ]+/", "password=*SECRET*", $connection_config);
            throw new MkException("failed establish to db (connection config = [{$connection_config_to_display}])");
        }

        pg_set_client_encoding($this->connection, 'UTF-8');

        return $this;
    }

    function get_current_database_name()
    {
        if ($this->current_database_name === null) {
            $this->current_database_name = DB::query("select current_database()")->execute($this)->get('current_database');
        }

        return $this->current_database_name;
    }

    static function get_template1_connection(?string $name = null): Database_Connection
    {
        $config = \Database_Connection::get_config($name);

        Arr::set($config, "connection.dbname", 'template1');

        $conn = new Database_Connection($config);

        return $conn;
    }

    static function get_connection_config(array $config): string
    {
        if (empty($config['connection'])) {
            $config['connection'] = "";
        }

        $connection_config = "";
        if (is_array($config['connection'])) {
            foreach ($config['connection'] as $key => $value) {
                $connection_config .= "{$key}='{$value}' ";
            }
        } else {
            $connection_config = $config['connection'];
        }

        return $connection_config;
    }

    function get_connection()
    {
        return $this->connection;
    }

    static function get_config($name = null): array
    {
        if (!$name) {
            $name = Config::get('db.active', 'default');
        }
        $config = \Config::get("db.{$name}", []);

        return $config;
    }

    /**
     * @param  string|null  $name
     *
     * @param  bool  $force_new
     *
     * @return Database_Connection
     * @throws MkException
     */
    static function instance($name = null, $force_new = false): Database_Connection
    {
        if (!$name) {
            $name = Config::get('db.active');
        }
        //Log::coredebug("[db connection] try get a connection named $name");
        $config = \Config::get("db.{$name}");
        if ($force_new) {
            return new static($config);
        } else {
            if (empty(static::$instances[$name])) {
                static::$instances[$name] = new static($config);
            }

            return static::$instances[$name];
        }
    }

    function dbname()
    {
        return pg_dbname($this->connection);
    }

    function escape_literal($value)
    {
        return pg_escape_literal($this->connection, $value);
    }

    function copy_from($table_name, $rows, $delimiter = "\t", $null_as = '')
    {
        return pg_copy_from($this->connection, $table_name, $rows, $delimiter, $null_as);
    }

    function copy_to($table_name, $delimiter = "\t", $null_as = '')
    {
        return pg_copy_to($this->connection, $table_name, $delimiter, $null_as);
    }

    function put_line($line)
    {
        pg_put_line($this->connection, $line);
    }

    function end_copy()
    {
        pg_end_copy($this->connection);
    }

    function query($sql, $parameters = [], $suppress_debug_log = false, $return_raw_result = false)
    {
        return Sentry::span("sql.query", function () use ($sql, $parameters, $suppress_debug_log, $return_raw_result) {
            if (!$suppress_debug_log) {
                Log::coredebug(sprintf("[dbconn] SQL = {%s} / %s", $sql, var_export($parameters, true)));
            }

            /**
             * クエリ送信
             * pg_result_error()を使うためにはpg_send_query()を使用するようにとドキュメントには書いてあるが
             * pg_send_query()を使うと接続が非同期モードに変換される。非同期モードになった接続はAWS Aurora Serverlessで使うと
             * プログラム終了時にコネクションが切られない問題が発生したため同期モードのまま使えるpg_query()を使うように変更した (2019.10)
             *
             * @see https://www.php.net/manual/ja/function.pg-result-error.php
             */
            if ($parameters) {
                $query_result = pg_query_params($this->connection, $sql, $parameters);
            } else {
                $query_result = pg_query($this->connection, $sql);
            }

            if ($query_result === false) {
                $error_msg = trim(pg_last_error($this->connection));
                $error_details = [
                    'message' => $error_msg,
                ];
                // ここでERRORレベルでログを記録した場合、MUTEXのためのロック獲得エラー時に正常処理のなかでERRORログが残ってしまう
                Log::coredebug("Query Error", $error_details);
                $this->last_error_details = $error_details;
                throw new DatabaseQueryError($error_msg);
            }
            $this->last_error_details = [];

            return $return_raw_result ? $query_result : (new Database_Resultset($query_result, $this));
        }, $sql, [
            'sql' => $sql,
            'last_error_details' => $this->last_error_details,
        ]);
    }

    function rollback_transaction()
    {
        DB::query("ABORT")->execute($this);
    }

    function connection_reset()
    {
        pg_connection_reset($this->connection);
    }

    /**
     * セーブポイントを作成する
     *
     * @return string セーブポイント名
     * @throws DatabaseQueryError
     * @throws MkException
     */
    function place_savepoint()
    {
        if (!$this->in_transaction()) {
            $this->start_transaction();
        }

        $point_name = ('sp_'.preg_replace('/[^0-9a-z]/', '', strtolower(uniqid(gethostname()))));
        DB::query('SAVEPOINT '.$point_name)->execute($this);
        Log::coredebug('[dbconn] SAVEPOINT '.$point_name);
        $this->savepoint_counter++;

        return $point_name;
    }

    function in_transaction()
    {
        return ($this->get_transaction_status() != PGSQL_TRANSACTION_IDLE);
    }

    function get_transaction_status()
    {
        return pg_transaction_status($this->connection);
    }

    function start_transaction()
    {
        DB::query("BEGIN")->execute($this);
    }

    function commit_savepoint($point_name)
    {
        if (!$this->in_transaction()) {
            throw new MkException('not in transaction');
        }
        if (!$point_name) {
            throw new MkException('invalid point name');
        }

        DB::query('RELEASE SAVEPOINT '.$point_name)->execute($this);
        $this->savepoint_counter--;
        if ($this->savepoint_counter < 1) {
            $this->savepoint_counter = 0;
            $this->commit_transaction();
        }
    }

    function commit_transaction()
    {
        DB::query("COMMIT")->execute($this);
    }

    function rollback_savepoint($point_name)
    {
        if (!$this->in_transaction()) {
            throw new MkException('not in transaction');
        }
        if (!$point_name) {
            throw new MkException('invalid point name');
        }

        $this->destruct_all_results();

        DB::query('ROLLBACK TO SAVEPOINT '.$point_name)->execute($this);
        $this->savepoint_counter--;
        if ($this->savepoint_counter < 1) {
            $this->savepoint_counter = 0;
            $this->rollback_transaction();
        }
    }

    function transaction(Closure $callback)
    {
        $savepoint = DB::place_savepoint();
        try {
            $result = $callback();

            DB::commit_savepoint($savepoint);

            return $result;
        } catch (Throwable $e) {
            DB::rollback_savepoint($savepoint);
            throw $e;
        }
    }

    function rollback(Closure $callback)
    {
        $savepoint = DB::place_savepoint();
        try {
            $result = $callback();

            return $result;
        } finally {
            DB::rollback_savepoint($savepoint);
        }
    }

    /**
     * 指定されたデータベースが存在しているか取得する
     */
    function database_exists($db_name): bool
    {
        $r = DB::select()
            ->from('pg_database')
            ->where('datname', $db_name)
            ->execute($this);

        return boolval($r->count());
    }

    /**
     * 指定されたテーブルが存在しているか取得する
     */
    function table_exists($table_name): bool
    {
        $r = DB::select()
            ->from('information_schema.tables')
            ->where('table_name', $table_name)
            ->execute($this);

        return boolval($r->count());
    }

    /**
     * コネクションに残っているクエリ結果をすべて破棄する
     *
     * @return $this
     */
    function destruct_all_results()
    {
        while (pg_get_result($this->connection)) {
            // nop
        }

        return $this;
    }
}
