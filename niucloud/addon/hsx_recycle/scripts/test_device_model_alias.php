<?php
declare(strict_types=1);

// Isolated regression: execute the real alias service without loading the app or database.
namespace core\base {
    class BaseAdminService
    {
        public int $site_id = 100000;
        public int $uid = 10;
        public function __construct() {}
    }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
}
namespace think\db\exception {
    class PDOException extends \RuntimeException
    {
        public function getData(): array
        {
            return ['PDO Error Info' => ['Driver Error Code' => $this->getCode()]];
        }
    }
}
namespace app\service\core\sys {
    class CoreConfigService
    {
        public static array $values = [];
        public static int $reads = 0;
        public function getConfigValue(int $siteId, string $key): array
        {
            self::$reads++;
            return self::$values[$siteId][$key] ?? [];
        }
        public function setConfig(int $siteId, string $key, array $value): void
        {
            throw new \RuntimeException('Alias binding must not write sys_config.value');
        }
    }
}
namespace addon\hsx_recycle\app\service\core\device {
    class CoreRecycleDeviceModelDictService {}
}
namespace addon\hsx_recycle\app\model\device {
    class RecycleDeviceModelAlias
    {
        public static array $rows = [];
        public static int $errorCode = 0;
        private int $siteId = 0;
        private array $keys = [];
        private array $updates = [];
        public function where(string $field, string $operator, int $value): self
        {
            if ($field !== 'site_id' || $operator !== '=') throw new \RuntimeException('Unscoped alias query');
            $this->siteId = $value;
            return $this;
        }
        public function whereIn(string $field, array $keys): self
        {
            if ($field !== 'normalized_alias') throw new \RuntimeException('Unexpected alias lookup');
            $this->keys = $keys;
            return $this;
        }
        public function column(string $value, string $key): array
        {
            $this->throwIfUnavailable();
            if ($value !== 'category_id' || $key !== 'normalized_alias') throw new \RuntimeException('Unexpected columns');
            $result = [];
            foreach (self::$rows as $row) {
                if ($row['site_id'] === $this->siteId && in_array($row[$key], $this->keys, true)) {
                    $result[$row[$key]] = $row[$value];
                }
            }
            return $result;
        }
        public function duplicate(array $updates): self
        {
            $this->updates = $updates;
            return $this;
        }
        public function insertAll(array $rows): void
        {
            $this->throwIfUnavailable();
            foreach ($rows as $row) {
                $key = $row['site_id'] . ':' . $row['normalized_alias'];
                if (isset(self::$rows[$key])) {
                    foreach ($this->updates as $field) self::$rows[$key][$field] = $row[$field];
                } else {
                    self::$rows[$key] = ['id' => count(self::$rows) + 1] + $row;
                }
            }
        }
        private function throwIfUnavailable(): void
        {
            if (self::$errorCode) throw new \think\db\exception\PDOException('Simulated DB error', self::$errorCode);
        }
    }

    class RecycleDeviceModelDict
    {
        public static array $rows = [];
        private array $conditions = [];
        public function where(array $conditions): self
        {
            $query = clone $this;
            $query->conditions = array_merge($query->conditions, $conditions);
            return $query;
        }
        private function matches(): array
        {
            return array_values(array_filter(self::$rows, function (array $row): bool {
                foreach ($this->conditions as [$field, $operator, $value]) {
                    if ($operator !== '=') throw new \RuntimeException('Unexpected query operator');
                    if (($row[$field] ?? null) !== $value) return false;
                }
                return true;
            }));
        }
        public function findOrEmpty(): object
        {
            return new class($this->matches()[0] ?? []) {
                public function __construct(private array $row) {}
                public function toArray(): array { return $this->row; }
            };
        }
        public function count(): int { return count($this->matches()); }
        public function __call(string $method, array $args)
        {
            throw new \RuntimeException('Alias binding must not mutate the catalog: ' . $method);
        }
    }
}
namespace {
    use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
    use addon\hsx_recycle\app\model\device\RecycleDeviceModelAlias as Alias;
    use addon\hsx_recycle\app\model\device\RecycleDeviceModelDict as Model;
    use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
    use app\service\core\sys\CoreConfigService;
    use core\exception\CommonException;

    require dirname(__DIR__) . '/app/dict/config/RecycleConfigKeyDict.php';
    require dirname(__DIR__) . '/app/service/admin/device/RecycleDeviceModelDictService.php';

    $check = static function (bool $value, string $message): void {
        if (!$value) throw new \RuntimeException($message);
    };
    Model::$rows = [
        ['id' => 1, 'pid' => 0, 'site_id' => 100000, 'status' => 1, 'node_name' => '手机'],
        ['id' => 2, 'pid' => 1, 'site_id' => 100000, 'status' => 1, 'node_name' => '魅族'],
        ['id' => 3, 'pid' => 2, 'site_id' => 100000, 'status' => 1, 'node_name' => '魅族16th', 'model_full_name' => '手机/魅族/魅族16th'],
        ['id' => 4, 'pid' => 2, 'site_id' => 100000, 'status' => 1, 'node_name' => '魅族16th Plus'],
        ['id' => 5, 'pid' => 2, 'site_id' => 100000, 'status' => 0, 'node_name' => '停用型号'],
        ['id' => 6, 'pid' => 0, 'site_id' => 100001, 'status' => 1, 'node_name' => '其他站点型号'],
    ];
    $before = Model::$rows;
    $service = new RecycleDeviceModelDictService();
    $bound = $service->bindAliases(['16th', '16 TH'], 3);
    $check($bound['learned_count'] === 1, 'Normalized aliases should be deduplicated');
    $check($bound['node']['category_path'] === [1, 2, 3], 'Bind returns the existing leaf path');
    $check(Model::$rows === $before, 'Binding must not create or change catalog nodes');
    $check(count(Alias::$rows) === 1, 'Each normalized alias is a single database row');
    $originalBinding = Alias::$rows['100000:16th'];
    $service->bindAliases(['16th'], 3);
    $resolved = $service->resolveAliases(['16-th']);
    $check($resolved['matched'] && $resolved['node']['id'] === 3, 'Next device resolves the saved binding');
    $check(Model::$rows === $before, 'Repeated binding must not create categories');
    $check(count(Alias::$rows) === 1, 'Repeated bind must update, not duplicate');
    $check(CoreConfigService::$reads === 0, 'New bindings resolve without reading the config blob');

    foreach ([1, 2, 5, 6, 999] as $invalidId) {
        $bindings = Alias::$rows;
        try {
            $service->bindAliases(['16th'], $invalidId);
            throw new \RuntimeException('Invalid target unexpectedly accepted: ' . $invalidId);
        } catch (CommonException $error) {
            $check(Alias::$rows === $bindings, 'Invalid target must not change a mapping');
        }
    }
    $service->site_id = 100001;
    $check($service->resolveAliases(['16th']) === ['matched' => false], 'Mappings are tenant isolated');
    $service->bindAliases(['16th'], 6);
    $check($service->resolveAliases(['16th'])['node']['id'] === 6, 'Same alias can target another tenant own model');
    $service->site_id = 100000;
    $service->uid = 20;
    $service->bindAliases(['16th'], 4);
    $check($service->resolveAliases(['16th'])['node']['id'] === 4, 'Manual correction only changes the mapping');
    $corrected = Alias::$rows['100000:16th'];
    $check($corrected['id'] === $originalBinding['id'] && $corrected['create_at'] === $originalBinding['create_at'], 'Correction preserves identity and creation time');
    $check($corrected['operator_uid'] === 20, 'Correction records the last operator');

    $configKey = RecycleConfigKeyDict::DEVICE_MODEL_ALIAS;
    CoreConfigService::$values[100000][$configKey] = ['mappings' => [
        'legacy' => ['category_id' => 3],
        '16th' => ['category_id' => 3],
    ]];
    $oldConfig = CoreConfigService::$values;
    $check($service->resolveAliases(['legacy'])['node']['id'] === 3, 'Existing config bindings remain readable');
    $check($service->resolveAliases(['16th'])['node']['id'] === 4, 'New correction takes precedence over old config');
    $service->bindAliases(['legacy'], 4);
    $check($service->resolveAliases(['legacy'])['node']['id'] === 4, 'Rebinding a legacy alias writes the table');
    $check(CoreConfigService::$values === $oldConfig, 'No old config rewrite, deletion or backfill');

    Model::$rows[3]['status'] = 0;
    $check($service->resolveAliases(['16th']) === ['matched' => false], 'Disabled new target must not resurrect an older config binding');
    $service->bindAliases(['valid'], 3);
    $check($service->resolveAliases(['16th', 'valid'])['node']['id'] === 3, 'Skip invalid target and try the next candidate');
    Model::$rows = $before;

    $check($service->resolveAliases([[], null, new \stdClass(), str_repeat('x', 121), "\xFF"]) === ['matched' => false], 'Invalid input is rejected before a query');
    $emptyBefore = Alias::$rows;
    try {
        $service->bindAliases(['---', [], str_repeat('x', 121)], 3);
        throw new \RuntimeException('Invalid aliases unexpectedly accepted');
    } catch (CommonException $error) {
        $check(Alias::$rows === $emptyBefore, 'Invalid aliases never write');
    }
    $longAlias = str_repeat('型', 120);
    $service->bindAliases([$longAlias], 3);
    $check($service->resolveAliases([$longAlias])['matched'], '120-character Unicode aliases fit the binary index');
    $service->bindAliases(['é'], 3);
    $service->bindAliases(['e'], 4);
    $check($service->resolveAliases(['é'])['node']['id'] === 3, 'Accent-distinct aliases must not collide');

    for ($i = 0; $i < 1100; $i += 10) {
        $service->bindAliases(array_map(static fn(int $n): string => 'bulk-model-' . $n, range($i, $i + 9)), 3);
    }
    $check($service->resolveAliases(['bulk-model-0'])['matched'], 'More than 1000 bindings never evict the oldest mapping');
    $check($service->resolveAliases(['bulk-model-1099'])['matched'], 'Large collections still resolve the newest mapping');
    $check(CoreConfigService::$values === $oldConfig, 'Large collections do not grow sys_config.value');

    Alias::$errorCode = 1146;
    foreach (['bind', 'resolve'] as $operation) {
        try {
            $operation === 'bind' ? $service->bindAliases(['failure'], 3) : $service->resolveAliases(['valid']);
            throw new \RuntimeException('Missing table was silently ignored');
        } catch (CommonException $error) {
            $check(str_contains($error->getMessage(), 'update_0.0.4.sql'), 'Missing table returns an actionable upgrade instruction');
        }
    }
    Alias::$errorCode = 1045;
    try {
        $service->resolveAliases(['valid']);
        throw new \RuntimeException('Unexpected DB failure was swallowed');
    } catch (\think\db\exception\PDOException $error) {
        $check($error->getCode() === 1045, 'Non-schema database errors remain visible');
    }
    Alias::$errorCode = 0;
    $check(Model::$rows === $before, 'All alias operations leave the catalog unchanged');
    echo "PASS alias service: table upsert, tenant/leaf/status guards, legacy read-only, Unicode, 1100 bindings and schema errors; no catalog/config writes\n";
}
