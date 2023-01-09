<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * Class DB
 *
 * @method static Database_Query select(mixed $col = null)
 * @method static Database_Query insert(string $table, array $cols = [])
 * @method static Database_Query delete()
 * @method static Database_Query update(string $table)
 */
class DB
{
    const INT_MAX = 2147483647;
    const ROWS_NOLIMIT = 'nolimit';

    private $connections = [];

    /**
     * @param $name
     * @param $args
     *
     * @return mixed
     * @throws MkException
     * @see Database_Query
     */
    static function __callStatic($name, $args)
    {
        $query = new Database_Query();
        if (!method_exists($query, $name)) {
            throw new MkException("method $name not found");
        }

        return call_user_func_array([$query, $name], $args);
    }

    static function escape($value)
    {
        return static::escape_literal($value);
    }

    static function escape_literal($value)
    {
        $dbconn = static::get_database_connection();

        return $dbconn->escape_literal($value);
    }

    /**
     * データベース接続を取得する
     *
     * @param  string|Database_Connection|null  $connection
     *
     * @return Database_Connection
     * @throws MkException
     */
    static function get_database_connection($connection = null)
    {
        if (!is_object($connection)) {
            $connection = Database_Connection::instance($connection);
        }
        if (!$connection instanceof Database_Connection) {
            throw new MkException('invalid connection');
        }

        return $connection;
    }

    static function expr($expr)
    {
        return new Database_Expression($expr);
    }

    static function array_to_pgarraystr($data, $delimiter = ',', $typecat = 'S')
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::array_to_pgarraystr($value, $delimiter);
            }
        }
        if ($typecat == 'S') {
            $data = array_map(function ($str) {
                return '"'.pg_escape_string($str).'"';
            }, $data
            );
        } else {
            $data = array_map(function ($str) {
                return is_numeric($str) ? $str : 'NULL';
            }, $data
            );
        }
        $str = '{'.implode($delimiter, $data).'}';

        return $str;
    }

    static function copy_from($table_name, $rows, $connection = null, $delimiter = "\t", $null_as = '')
    {
        //Log::coredebug($table_name,$rows);
        $dbconn = static::get_database_connection($connection);

        return $dbconn->copy_from($table_name, $rows, $delimiter, $null_as);
    }

    static function copy_to($table_name, $connection = null, $delimiter = "\t", $null_as = '')
    {
        $dbconn = static::get_database_connection($connection);

        return $dbconn->copy_to($table_name, $delimiter, $null_as);
    }

    static function connection_reset($connection = null)
    {
        $dbconn = static::get_database_connection($connection);

        $dbconn->connection_reset();
    }

    /**
     * DBスキーマのキャッシュを消去する
     */
    static function clear_schema_cache()
    {
        Database_Schema::clear_cache();
    }

    /**
     * スキーマに存在する全テーブルを削除する
     */
    static function delete_all_tables($schema = 'public')
    {
        DB::query("drop schema $schema cascade")->execute();
        DB::query("create schema $schema")->execute();
        Database_Schema::clear_cache();
    }

    /**
     * クエリオブジェクトを作成する
     *
     * @param       $query
     * @param  array  $parameters
     *
     * @return Database_Query
     */
    static function query($query, $parameters = [])
    {
        return new Database_Query($query, $parameters);
    }

    static function pager($db_query, $options, $query_options = [])
    {
        return new Database_Pager($db_query, $options, $query_options);
    }

    static function in_transaction($connection = null)
    {
        return static::get_database_connection($connection)->in_transaction();
    }

    static function start_transaction($connection = null)
    {
        static::get_database_connection($connection)->start_transaction();
    }

    static function commit_transaction($connection = null)
    {
        static::get_database_connection($connection)->commit_transaction();
    }

    static function rollback_transaction($connection = null)
    {
        static::get_database_connection($connection)->rollback_transaction();
    }

    static function place_savepoint($connection = null)
    {
        return static::get_database_connection($connection)->place_savepoint();
    }

    static function commit_savepoint($point_name, $connection = null)
    {
        static::get_database_connection($connection)->commit_savepoint($point_name);
    }

    static function rollback_savepoint($point_name, $connection = null)
    {
        static::get_database_connection($connection)->rollback_savepoint($point_name);
    }

    static function transaction(Closure $callback, $connection = null)
    {
        return static::get_database_connection($connection)->transaction($callback);
    }

    static function rollback(Closure $callback, $connection = null)
    {
        return static::get_database_connection($connection)->rollback($callback);
    }

    static function table_exists($table_name, $connection = null)
    {
        return static::get_database_connection($connection)->table_exists($table_name);
    }

    static function database_exists($db_name, $name = null)
    {
        return static::get_template1_connection($name)->database_exists($db_name);
    }
}
