<?php
declare(strict_types=1);

// Isolated regression: execute the real alias service without loading the app or database.
namespace core\base {
    class BaseAdminService
    {
        public int $site_id = 100000;
        public function __construct() {}
    }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
}
namespace app\service\core\sys {
    class CoreConfigService
    {
        public static array $values = [];
        public function getConfigValue(int $siteId, string $key): array
        {
            return self::$values[$siteId][$key] ?? [];
        }
        public function setConfig(int $siteId, string $key, array $value): void
        {
            self::$values[$siteId][$key] = $value;
        }
    }
}
namespace addon\hsx_recycle\app\service\core\device {
    class CoreRecycleDeviceModelDictService {}
}
namespace addon\hsx_recycle\app\model\device {
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
    $service->bindAliases(['16th'], 3);
    $resolved = $service->resolveAliases(['16-th']);
    $check($resolved['matched'] && $resolved['node']['id'] === 3, 'Next device resolves the saved binding');
    $check(Model::$rows === $before, 'Repeated binding must not create categories');

    foreach ([1, 2, 5, 6, 999] as $invalidId) {
        $config = CoreConfigService::$values;
        try {
            $service->bindAliases(['16th'], $invalidId);
            throw new \RuntimeException('Invalid target unexpectedly accepted: ' . $invalidId);
        } catch (CommonException $error) {
            $check(CoreConfigService::$values === $config, 'Invalid target must not change a mapping');
        }
    }
    $service->site_id = 100001;
    $check($service->resolveAliases(['16th']) === ['matched' => false], 'Mappings are tenant isolated');
    $service->site_id = 100000;
    $service->bindAliases(['16th'], 4);
    $check($service->resolveAliases(['16th'])['node']['id'] === 4, 'Manual correction only changes the mapping');
    $check(Model::$rows === $before, 'All alias operations leave the catalog unchanged');
    echo "PASS alias service: existing leaf binding, repeat read, deduplication, correction, tenant/leaf/status guards; no catalog writes\n";
}
