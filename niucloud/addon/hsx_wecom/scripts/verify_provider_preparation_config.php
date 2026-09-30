<?php
declare(strict_types=1);

/**
 * 服务商创建前的配置回归：直接调用真实配置服务，仅替换存储、事务、密文和缓存边界。
 * php niucloud/addon/hsx_wecom/scripts/verify_provider_preparation_config.php
 * 不加载框架入口或环境变量，不连接数据库、缓存或企业微信。
 */
namespace core\exception {
    class CommonException extends \RuntimeException {}
}

namespace addon\hsx_wecom\app\model {
    final class MemoryProviderQuery
    {
        private array $conditions = [];

        public function where($field, $operator = null, $value = null): self
        {
            if (is_array($field)) {
                foreach ($field as $condition) $this->conditions[] = $condition;
            } else {
                $this->conditions[] = [$field, $operator, $value];
            }
            return $this;
        }

        public function order(...$arguments): self { return $this; }

        public function findOrEmpty(): WecomProviderSuite
        {
            $rows = WecomProviderSuite::$rows;
            ksort($rows);
            foreach ($rows as $row) {
                if ($this->matches($row)) return new WecomProviderSuite($row);
            }
            return new WecomProviderSuite();
        }

        public function update(array $data): int
        {
            // 计入写调用，即便没有命中数据，保证非法输入在所有写操作前被拒绝。
            WecomProviderSuite::$writes++;
            $count = 0;
            foreach (WecomProviderSuite::$rows as $id => $row) {
                if (!$this->matches($row)) continue;
                WecomProviderSuite::$rows[$id] = array_replace($row, $data);
                $count++;
            }
            return $count;
        }

        private function matches(array $row): bool
        {
            foreach ($this->conditions as [$field, $operator, $value]) {
                $actual = $row[$field] ?? null;
                if ($operator === '=' && $actual != $value) return false;
                if ($operator === '<>' && $actual == $value) return false;
                if (!in_array($operator, ['=', '<>'], true)) {
                    throw new \RuntimeException('Unexpected query operator: ' . (string)$operator);
                }
            }
            return true;
        }
    }

    class WecomProviderSuite
    {
        public static array $rows = [];
        public static int $writes = 0;
        private array $attributes;

        public function __construct(array $attributes = []) { $this->attributes = $attributes; }
        public function __get(string $key) { return $this->attributes[$key] ?? null; }
        public function __set(string $key, $value): void { $this->attributes[$key] = $value; }
        public function __isset(string $key): bool { return isset($this->attributes[$key]); }
        public function isEmpty(): bool { return $this->attributes === []; }
        public function toArray(): array { return $this->attributes; }

        public static function where($field, $operator = null, $value = null): MemoryProviderQuery
        {
            return (new MemoryProviderQuery())->where($field, $operator, $value);
        }

        public static function order(...$arguments): MemoryProviderQuery { return new MemoryProviderQuery(); }

        public static function create(array $data): self
        {
            $data['id'] = self::$rows === [] ? 1 : max(array_keys(self::$rows)) + 1;
            self::assertUnique($data);
            self::$rows[$data['id']] = $data;
            self::$writes++;
            return new self($data);
        }

        public function save(array $data): void
        {
            if (!isset($this->attributes['id'])) throw new \RuntimeException('Cannot save missing provider');
            $updated = array_replace($this->attributes, $data);
            self::assertUnique($updated);
            $this->attributes = $updated;
            self::$rows[$this->attributes['id']] = $this->attributes;
            self::$writes++;
        }

        private static function assertUnique(array $candidate): void
        {
            // 对齐 install.sql 的两个唯一索引，避免内存替身掩盖空 SuiteID 的冲突。
            foreach (self::$rows as $row) {
                if ($row['id'] === $candidate['id']) continue;
                foreach (['channel_code', 'suite_id'] as $field) {
                    if ($row[$field] === $candidate[$field]) {
                        throw new \RuntimeException('Mock unique constraint violated: ' . $field);
                    }
                }
            }
        }
    }

    final class EmptyRelatedQuery
    {
        public function field(...$arguments): self { return $this; }
        public function select(): self { return $this; }
        public function toArray(): array { return []; }
        public function findOrEmpty(): WecomProviderSuite { return new WecomProviderSuite(); }
    }

    class WecomCorpAuthorization
    {
        public static function where(...$arguments): EmptyRelatedQuery { return new EmptyRelatedQuery(); }
    }
    class WecomStaffBinding {}
    class WecomMessageLog {}
}

namespace think\facade {
    class Db
    {
        public static function transaction(callable $callback)
        {
            $before = \addon\hsx_wecom\app\model\WecomProviderSuite::$rows;
            try {
                return $callback();
            } catch (\Throwable $error) {
                \addon\hsx_wecom\app\model\WecomProviderSuite::$rows = $before;
                throw $error;
            }
        }
    }
}

namespace addon\hsx_wecom\app\support {
    class WecomProviderCipher
    {
        // 非真实加密，只验证配置服务是否通过密文边界读写，绝不读取生产 APP_KEY。
        public static function encrypt(string $plain): string
        {
            return $plain === '' ? '' : 'memory-cipher:' . base64_encode($plain);
        }

        public static function decrypt(string $cipher): string
        {
            if ($cipher === '') return '';
            if (strpos($cipher, 'memory-cipher:') !== 0) throw new \RuntimeException('Unknown mock cipher');
            $plain = base64_decode(substr($cipher, strlen('memory-cipher:')), true);
            if ($plain === false) throw new \RuntimeException('Invalid mock cipher');
            return $plain;
        }
    }
}

namespace addon\hsx_wecom\app\service\core {
    class WecomProviderCredentialService
    {
        public static array $clearedSuiteIds = [];
        public function clearSuiteToken(\addon\hsx_wecom\app\model\WecomProviderSuite $suite): void
        {
            self::$clearedSuiteIds[] = (string)$suite->suite_id;
        }
    }
}

namespace {
    use addon\hsx_wecom\app\model\WecomProviderSuite;
    use addon\hsx_wecom\app\service\core\WecomProviderConfigService;
    use addon\hsx_wecom\app\service\core\WecomProviderCredentialService;
    use addon\hsx_wecom\app\support\WecomProviderCipher;
    use core\exception\CommonException;

    require __DIR__ . '/../app/service/core/WecomProviderConfigService.php';

    $checks = 0;
    $check = static function (bool $condition, string $label) use (&$checks): void {
        if (!$condition) throw new \RuntimeException('FAIL: ' . $label);
        $checks++;
    };
    $reset = static function (): void {
        WecomProviderSuite::$rows = [];
        WecomProviderSuite::$writes = 0;
        WecomProviderCredentialService::$clearedSuiteIds = [];
    };
    $service = new WecomProviderConfigService();
    $aesKey = rtrim(base64_encode(str_repeat("\x19", 32)), '=');
    $prepare = [
        'prepare_only' => 1,
        'enabled' => 0,
        'channel_code' => 'default',
        'provider_corp_id' => 'ww0123456789abcdef',
        'callback_token' => 'CallbackToken2026',
        'encoding_aes_key' => $aesKey,
        'web_base_url' => 'https://wecom.example.test',
    ];
    $complete = array_replace($prepare, [
        'prepare_only' => 0,
        'enabled' => 1,
        'suite_id' => 'wwsuite0123456789',
        'suite_secret' => 'suite-secret-for-memory-test-only',
        'admin_miniapp_appid' => 'wx0123456789abcdef',
    ]);
    $reject = static function (array $input, string $label) use ($service, $check): void {
        $rowsBefore = WecomProviderSuite::$rows;
        $writesBefore = WecomProviderSuite::$writes;
        $cacheBefore = WecomProviderCredentialService::$clearedSuiteIds;
        try {
            $service->save($input);
            throw new \RuntimeException('FAIL: accepted ' . $label);
        } catch (CommonException $error) {
            $check(trim($error->getMessage()) !== '', $label . ' reports a validation error');
        }
        $check(WecomProviderSuite::$writes === $writesBefore, $label . ' rejected before any write');
        $check(WecomProviderSuite::$rows === $rowsBefore, $label . ' preserves existing rows');
        $check(WecomProviderCredentialService::$clearedSuiteIds === $cacheBefore, $label . ' preserves token caches');
    };

    $reset();
    $check($service->active()->isEmpty(), 'no active provider before configuration');
    $saved = $service->save($prepare);
    $stored = WecomProviderSuite::$rows[1];
    $check($stored['status'] === 'preparing', 'preparation persists preparing status');
    $check($stored['suite_id'] === '' && $stored['suite_secret_cipher'] === '', 'preparation needs no Suite credentials');
    $check($stored['admin_miniapp_appid'] === '', 'preparation needs no miniapp AppID');
    $check($service->active()->isEmpty(), 'preparing provider cannot serve tenant authorization');
    $check((int)$service->siteStatus(100005)['configured'] === 0, 'preparation is not exposed as tenant configured');
    $check($service->byChannel('default')->status === 'preparing', 'preparing provider can be found by callback channel');
    $check((int)$saved['enabled'] === 0 && $saved['setup_stage'] === 'preparing', 'info distinguishes preparation from enabled');
    $check((int)$saved['callback_ready'] === 1, 'preparing provider reports callbacks ready');
    $check($saved['installation_callback_domain'] === 'wecom.example.test', 'installation domain contains host only');
    $check($saved['data_callback_url'] === $prepare['web_base_url'] . '/api/wecom/provider/data/default', 'data callback URL is derived');
    $check($saved['event_callback_url'] === $prepare['web_base_url'] . '/api/wecom/provider/event/default', 'event callback URL is derived');
    $check($saved['auth_callback_url'] === $prepare['web_base_url'] . '/api/wecom/provider/authorize/complete/default', 'authorization callback URL is derived');
    $check($saved['application_settings_url'] === $prepare['web_base_url'] . '/site/hsx_wecom/config', 'application settings URL points to tenant settings');
    $check($saved['callback_token'] === '******' && $saved['encoding_aes_key'] === '******', 'preparation secrets are masked');
    $check($saved['suite_secret'] === '' && (int)$saved['suite_secret_configured'] === 0, 'absent SuiteSecret is not shown as configured');
    $check((int)$saved['callback_token_configured'] === 1 && (int)$saved['encoding_aes_key_configured'] === 1, 'stored callback keys are configured');
    $check(!array_key_exists('encoding_aes_key_cipher', $saved) && !array_key_exists('suite_secret_cipher', $saved), 'ciphertext fields never leave info');
    $check(WecomProviderCipher::decrypt($stored['encoding_aes_key_cipher']) === $aesKey, 'callback AES key is retained through cipher boundary');
    $check(strpos(json_encode($saved), $aesKey) === false && strpos(json_encode($saved), $prepare['callback_token']) === false, 'masked preparation info contains no raw keys');

    $frozenAes = $stored['encoding_aes_key_cipher'];
    $maskedPrepare = array_replace($prepare, [
        'id' => 1, 'callback_token' => '******', 'encoding_aes_key' => '******',
        'web_base_url' => 'https://new.example.test/',
        'data_callback_url' => 'https://stale.example.test/wrong',
        'event_callback_url' => 'https://stale.example.test/wrong',
        'auth_callback_url' => 'https://stale.example.test/wrong',
    ]);
    $saved = $service->save($maskedPrepare);
    $check(WecomProviderSuite::$rows[1]['encoding_aes_key_cipher'] === $frozenAes, 'masked preparation reuses stored AES key');
    $check(WecomProviderSuite::$rows[1]['callback_token'] === $prepare['callback_token'], 'masked preparation reuses stored token');
    $check($saved['web_base_url'] === 'https://new.example.test', 'root trailing slash is normalized');
    $check($saved['data_callback_url'] === 'https://new.example.test/api/wecom/provider/data/default', 'changed domain refreshes data callback');
    $check($saved['event_callback_url'] === 'https://new.example.test/api/wecom/provider/event/default', 'stale event callback input is ignored');
    $check($saved['auth_callback_url'] === 'https://new.example.test/api/wecom/provider/authorize/complete/default', 'stale authorization callback input is ignored');

    $saved = $service->save(array_replace($complete, [
        'id' => 1, 'callback_token' => '******', 'encoding_aes_key' => '******',
    ]));
    $check(count(WecomProviderSuite::$rows) === 1, 'activation updates the prepared record without duplication');
    $check(WecomProviderSuite::$rows[1]['status'] === 'enabled', 'complete credentials activate preparation');
    $check((int)$service->active()->id === 1 && (int)$saved['enabled'] === 1, 'activated provider becomes available');
    $check($saved['suite_secret'] === '******', 'activation masks SuiteSecret');
    $check(WecomProviderSuite::$rows[1]['callback_token'] === $prepare['callback_token'], 'activation reuses prepared callback token');
    $check(WecomProviderCipher::decrypt(WecomProviderSuite::$rows[1]['encoding_aes_key_cipher']) === $aesKey, 'activation reuses prepared AES key');
    WecomProviderSuite::$rows[1]['suite_ticket'] = 'private-suite-ticket-for-memory-test';
    $saved = $service->info();
    $check(!array_key_exists('suite_ticket', $saved), 'SuiteTicket is never returned');
    $check(strpos(json_encode($saved), $complete['suite_secret']) === false, 'info never exposes active SuiteSecret');

    $reject(array_replace($prepare, ['id' => 1]), 'enabled provider cannot return to preparation');
    $frozenSecret = WecomProviderSuite::$rows[1]['suite_secret_cipher'];
    $maskedEnabled = array_replace($complete, [
        'id' => 1, 'suite_secret' => '******', 'callback_token' => '******', 'encoding_aes_key' => '******',
    ]);
    $service->save($maskedEnabled);
    $check(WecomProviderSuite::$rows[1]['suite_secret_cipher'] === $frozenSecret, 'masked enabled save reuses SuiteSecret');
    $check(WecomProviderSuite::$rows[1]['suite_ticket'] === 'private-suite-ticket-for-memory-test', 'unchanged identity preserves SuiteTicket');
    $service->save(array_replace($maskedEnabled, ['enabled' => 0]));
    $check(WecomProviderSuite::$rows[1]['status'] === 'disabled', 'ordinary disabled save remains supported');
    $check($service->active()->isEmpty(), 'disabled provider is unavailable for tenant authorization');
    $check((int)$service->info()['callback_ready'] === 0, 'disabled provider does not advertise ready callbacks');
    $reject(array_replace($prepare, ['id' => 1]), 'disabled configured provider cannot lose Suite credentials through preparation');

    foreach (['suite_id', 'suite_secret', 'admin_miniapp_appid'] as $field) {
        $reset();
        $reject(array_replace($complete, [$field => '']), 'enabled requires ' . $field);
    }
    $reset();
    $reject(array_replace($prepare, ['enabled' => 1]), 'prepare_only conflicts with enabled');
    $reject(array_replace($prepare, ['suite_id' => $complete['suite_id']]), 'preparation cannot accept an issued SuiteID');
    $reject(array_replace($prepare, ['suite_secret' => $complete['suite_secret']]), 'preparation cannot accept an issued SuiteSecret');

    $invalidPreparation = [
        'missing provider CorpID' => ['provider_corp_id' => ''],
        'missing callback token' => ['callback_token' => ''],
        'callback token with whitespace' => ['callback_token' => 'bad callback token'],
        'callback token with punctuation' => ['callback_token' => 'bad?callback'],
        'callback token below three characters' => ['callback_token' => 'ab'],
        'callback token above thirty-two characters' => ['callback_token' => str_repeat('A', 33)],
        'missing AES key' => ['encoding_aes_key' => ''],
        'short AES key' => ['encoding_aes_key' => str_repeat('A', 42)],
        'long AES key' => ['encoding_aes_key' => str_repeat('A', 44)],
        'invalid base64 AES key' => ['encoding_aes_key' => str_repeat('#', 43)],
        'missing root domain' => ['web_base_url' => ''],
        'invalid root domain' => ['web_base_url' => 'not-a-url'],
        'remote HTTP domain' => ['web_base_url' => 'http://wecom.example.test'],
        'root domain with path' => ['web_base_url' => 'https://wecom.example.test/admin'],
        'root domain with query' => ['web_base_url' => 'https://wecom.example.test?source=setup'],
        'root domain with fragment' => ['web_base_url' => 'https://wecom.example.test#setup'],
        'root domain with userinfo' => ['web_base_url' => 'https://operator:password@wecom.example.test'],
    ];
    foreach ($invalidPreparation as $label => $invalid) {
        $reset();
        $reject(array_replace($prepare, $invalid), 'preparation: ' . $label);
        $reset();
        $reject(array_replace($complete, $invalid), 'activation: ' . $label);
    }

    foreach (['suite_id' => 'wwexisting012345', 'suite_secret_cipher' => WecomProviderCipher::encrypt('existing-suite-secret')] as $field => $value) {
        $reset();
        $service->save($prepare);
        WecomProviderSuite::$rows[1]['status'] = 'disabled';
        WecomProviderSuite::$rows[1][$field] = $value;
        $reject(array_replace($prepare, ['id' => 1]), 'existing ' . $field . ' prevents preparation downgrade');
    }

    $reset();
    $service->save($prepare);
    $reject(array_replace($prepare, ['channel_code' => 'another_draft']), 'second empty Suite draft is rejected explicitly');
    $renamedDraft = $service->save(array_replace($prepare, ['id' => 1, 'channel_code' => 'renamed_draft']));
    $check(count(WecomProviderSuite::$rows) === 1 && (int)$renamedDraft['id'] === 1, 'editing draft by id does not create a duplicate');
    $check($renamedDraft['channel_code'] === 'renamed_draft', 'existing draft can change channel when identified');
    $check($service->byChannel('default')->isEmpty(), 'draft channel rename removes obsolete lookup');
    $check($renamedDraft['event_callback_url'] === $prepare['web_base_url'] . '/api/wecom/provider/event/renamed_draft', 'draft channel rename refreshes callback URLs');

    $reset();
    $service->save($complete);
    $secondDraft = $service->save(array_replace($prepare, ['channel_code' => 'next_channel']));
    $check((int)$service->active()->id === 1, 'preparing another channel does not disable active channel');
    $check(WecomProviderSuite::$rows[2]['status'] === 'preparing', 'second prepared channel remains isolated');
    $check((int)$secondDraft['id'] === 2 && $secondDraft['setup_stage'] === 'preparing', 'save returns prepared record rather than another active channel');
    $service->save(array_replace($complete, ['id' => 2, 'channel_code' => 'next_channel', 'suite_id' => 'wwsecondsuite0123']));
    $check((int)$service->active()->id === 2 && WecomProviderSuite::$rows[1]['status'] === 'disabled', 'activation still enforces single active channel');

    echo "PASS {$checks} provider preparation configuration checks (mock storage/cipher/cache, no DB/network/env)\n";
}
