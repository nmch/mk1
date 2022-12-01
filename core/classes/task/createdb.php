<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * DB作成
 */
class Task_Createdb extends Task
{
    function run($name = null)
    {
        Mk::retry(function ($name) {
            if ($name) {
                $db_config = \Database_Connection::get_config($name);
                $this->create($name, $db_config);
            } else {
                $db_configs = Config::get('db');
                foreach ($db_configs ?: [] as $name => $db_config) {
                    if (is_array($db_config) && ($db_config['connection'] ?? null)) {
                        try {
                            $this->create($name, $db_config);
                        } catch (Exception $e) {
                            Log::error($e->getMessage());
                        }
                    }
                }
            }
        }, [$name], 5, 1);
    }

    function create($name, $db_config)
    {
        $hooks = Arr::get($db_config, "hooks") ?: [];

        $target_database_name = Arr::get($db_config, "connection.dbname");
        if (!$target_database_name) {
            throw new MkException("データベース名が取得できませんでした");
        }

        $conn = Database_Connection::get_template1_connection($name);

        // 対象のデータベースが存在しているか確認
        $r = DB::select()
            ->from('pg_database')
            ->where('datname', $target_database_name)
            ->execute($conn);

        // 対象のデータベースが存在していなければ作成する
        if (!$r->count()) {
            Log::info("[db create] データベース {$target_database_name} を作成します");
            DB::query("create database \"{$target_database_name}\"")->execute($conn);

            if (isset($hooks['created'])) {
                forward_static_call_array($hooks['created'], [$conn, $db_config]);
            }
        }
    }
}
