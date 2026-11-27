<?php
declare(strict_types=1);

namespace addon\tk_jhkd\app\service\core;

use think\facade\Cache;

/**
 * Redis is a safety gate, not an optional cache. No connection => no WeChat mutation.
 * Before transport, persist unknown WITHOUT TTL. A crash/timeout cannot silently unlock resending.
 */
class RedisWeappDeliveryGuard
{
    private $client;
    private string $prefix;

    public function __construct($client = null, string $prefix = '')
    {
        $this->client = $client ?? Cache::store('redis')->handler();
        $this->prefix = $prefix !== '' ? $prefix : 'tk_jhkd:weapp_delivery:v1:' . hash('sha256', (string)config('database.connections.mysql.database') . '|' . (string)config('database.connections.mysql.prefix')) . ':';
    }

    public function claim(string $identity): array
    {
        $token = bin2hex(random_bytes(16));
        $result = $this->evaluate(<<<'LUA'
local state = redis.call('GET', KEYS[1])
if state then return state end
if redis.call('SET', KEYS[2], ARGV[1], 'NX', 'EX', 120) then return 'acquired' end
return 'busy'
LUA
            , $identity, [$token]);
        if (!in_array($result, ['acquired', 'busy', 'done', 'unknown', 'rejected'], true)) {
            throw new \RuntimeException('redis_guard_state_invalid');
        }
        return ['status' => $result, 'token' => $token];
    }

    public function beforeSend(string $identity, string $token): void
    {
        $result = $this->evaluate(<<<'LUA'
if redis.call('GET', KEYS[2]) ~= ARGV[1] then return 0 end
if redis.call('EXISTS', KEYS[1]) == 1 then return 0 end
redis.call('SET', KEYS[1], 'unknown')
return 1
LUA
            , $identity, [$token]);
        if ((int)$result !== 1) throw new \RuntimeException('redis_guard_not_held');
    }

    public function complete(string $identity, string $token, string $status): void
    {
        if (!in_array($status, ['done', 'unknown', 'rejected'], true)) throw new \InvalidArgumentException('invalid_delivery_state');
        $result = $this->evaluate(<<<'LUA'
if redis.call('GET', KEYS[2]) ~= ARGV[1] then return 0 end
if redis.call('GET', KEYS[1]) ~= 'unknown' then return 0 end
if ARGV[2] == 'rejected' then redis.call('SET', KEYS[1], ARGV[2], 'EX', 300)
else redis.call('SET', KEYS[1], ARGV[2]) end
redis.call('DEL', KEYS[2])
return 1
LUA
            , $identity, [$token, $status]);
        if ((int)$result !== 1) throw new \RuntimeException('redis_delivery_outcome_not_persisted');
    }

    private function evaluate(string $script, string $identity, array $arguments)
    {
        $key = $this->prefix . hash('sha256', $identity);
        $keys = [$key . ':state', $key . ':lease'];
        if ($this->client instanceof \Redis || !is_a($this->client, 'Predis\\Client')) {
            return $this->client->eval($script, array_merge($keys, $arguments), count($keys));
        }
        return $this->client->eval($script, count($keys), ...array_merge($keys, $arguments));
    }
}
