<?php

/**
 * Mk1 Cache Handler
 * Allows you to store Smarty Cache files into your db.
 * Example table :
 * CREATE TABLE `smarty_cache` (
 * `id` char(40) NOT NULL COMMENT 'sha1 hash',
 * `name` varchar(250) NOT NULL,
 * `cache_id` varchar(250) DEFAULT NULL,
 * `compile_id` varchar(250) DEFAULT NULL,
 * `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 * `expire` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
 * `content` mediumblob NOT NULL,
 * PRIMARY KEY (`id`),
 * KEY `name` (`name`),
 * KEY `cache_id` (`cache_id`),
 * KEY `compile_id` (`compile_id`),
 * KEY `modified` (`modified`),
 * KEY `expire` (`expire`)
 * ) ENGINE=InnoDB
 * Example usage :
 *      $cnx    =   new PDO("mysql:host=localhost;dbname=mydb", "username", "password");
 *      $smarty->setCachingType('pdo');
 *      $smarty->loadPlugin('Smarty_CacheResource_Pdo');
 *      $smarty->registerCacheResource('pdo', new Smarty_CacheResource_Pdo($cnx, 'smarty_cache'));
 *
 * @author Beno!t POLASZEK - 2014
 */
class Smarty_CacheResource_Mk1 extends Smarty_CacheResource_KeyValueStore
{
    protected $cache_group = 'smarty';

    /**
     * Read values for a set of keys from cache
     *
     * @param  array  $keys  list of keys to fetch
     *
     * @return array   list of values with the given keys used as indexes
     * @return boolean true on success, false on failure
     */
    protected function read(array $keys)
    {
        $res = [];
        foreach ($keys as $key) {
            $res[$key] = Cache::get($key, $this->cache_group);
        }

        return $res;
    }

    /**
     * Save values for a set of keys to cache
     *
     * @param  array  $keys  list of values to save
     * @param  int  $expire  expiration time
     *
     * @return boolean true on success, false on failure
     */
    protected function write(array $keys, $expire = null)
    {
        foreach ($keys as $k => $v) {
            Cache::set_with_ttl($k, $this->cache_group, $v, $expire);
        }

        return true;
    }

    /**
     * Remove values from cache
     *
     * @param  array  $keys  list of keys to delete
     *
     * @return boolean true on success, false on failure
     */
    protected function delete(array $keys)
    {
        foreach ($keys as $k) {
            Cache::clear($k, $this->cache_group);
        }

        return true;
    }

    /**
     * Remove *all* values from cache
     *
     * @return boolean true on success, false on failure
     */
    protected function purge()
    {
        Cache::clear(null, $this->cache_group);

        return true;
    }
}
