<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Cache_Driver_Redis extends Cache_Driver
{
    protected Redis $redis;

    function __construct(array $config)
    {
        parent::__construct($config);

        $redis_endpoint = $this->config('endpoint');
        $redis_port = $this->config('port');
        $redis_timeout = $this->config('timeout');
        $this->redis = new Redis();
        $this->redis->connect($redis_endpoint, $redis_port, $redis_timeout);
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
    }

    public function set(string $key, $value, ?string $group = null, ?int $expire = null)
    {
        $cache_key = sprintf('%s:%s', $this->hash($group), $this->hash($key));
        $cache_payload = base64_encode(serialize($value));

        if ($expire === null) {
            $expire = ($this->config['default_ttl'] ?? null);
        }

        if ($expire > 0) {
            $r = $this->redis->setex($cache_key, $expire, $cache_payload);
        } else {
            $r = $this->redis->set($cache_key, $cache_payload);
        }
        if ($r !== true) {
            throw new MkException(sprintf("Redisに保存できませんでした(key=%s / payload size=%d", $cache_key, strlen($cache_payload)));
        }
    }

    public function get(string $key, ?string $group = null)
    {
        $cache_key = sprintf('%s:%s', $this->hash($group), $this->hash($key));

        $r = $this->redis->get($cache_key);

        if ($r !== false) {
            $data = unserialize(base64_decode($r));

            return $data;
        }

        throw new CacheMissException();
    }

    public function clear(?string $key = null, ?string $group = null)
    {
        $target_key_pattern = ($this->hash($group).':');

        if ($key) {
            // キーが決まっている場合は直接unlinkする
            $target_key_pattern .= $key;
            $this->redis->unlink($target_key_pattern);
        } else {
            // キーが指定されていない場合は指定グループのパターン(GROUP_HASH:*)に一致するデータをスキャンして削除する
            $target_key_pattern .= '*';
            $iterator = null;
            while ($keys = $this->redis->scan($iterator, $target_key_pattern)) {
                foreach ($keys as $redis_key) {
                    $this->redis->unlink($redis_key);
                }
            }
        }
    }
}
