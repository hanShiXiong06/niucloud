<?php
declare(strict_types=1);

// 执行真实业务服务/监听器；ORM、事务和外部能力用隔离内存替身，不连接任何真实数据库。
namespace PhotoTest {
    class Store { public static array $tables = []; public static array $transactions = []; }
    class Row {
        public function __construct(public string $table, public array $data = []) {}
        public function __get($key) { return $this->data[$key] ?? null; }
        public function __set($key, $value) { $this->data[$key] = $value; }
        public function isEmpty(): bool { return !$this->data; }
        public function toArray(): array { return $this->data; }
        public function save(array $values): bool {
            $this->data = array_replace($this->data, $values);
            Store::$tables[$this->table][$this->data['id']] = $this->data;
            return true;
        }
    }
    class Rows {
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }
    class Query {
        private array $conditions = [];
        private string $sort = '';
        private int $max = PHP_INT_MAX;
        public function __construct(private string $model) {}
        private function condition($field, $operator = null, $value = null): callable {
            if ($field instanceof \Closure) {
                $query = new self($this->model); $field($query);
                return fn($row) => $query->matches($row);
            }
            if (is_array($field)) {
                $parts = array_map(fn($item) => $this->condition(...$item), $field);
                return fn($row) => !in_array(false, array_map(fn($p) => $p($row), $parts), true);
            }
            if (func_num_args() < 3) { $value = $operator; $operator = '='; }
            return static fn($row) => match ($operator) {
                '=' => ($row[$field] ?? null) == $value,
                '<>', '!=' => ($row[$field] ?? null) != $value,
                '>' => ($row[$field] ?? 0) > $value,
                'in' => in_array($row[$field] ?? null, $value),
                default => throw new \RuntimeException('测试未实现条件 ' . $operator),
            };
        }
        public function where(...$args): self { $this->conditions[] = ['and', $this->condition(...$args)]; return $this; }
        public function whereOr(...$args): self { $this->conditions[] = ['or', $this->condition(...$args)]; return $this; }
        public function whereIn($field, $values): self { return $this->where($field, 'in', $values); }
        public function lock($value): self { return $this; }
        public function order($sort): self { $this->sort = $sort; return $this; }
        public function limit($value): self { $this->max = $value; return $this; }
        public function matches($row): bool {
            $result = null;
            foreach ($this->conditions as [$op, $test]) $result = $result === null ? $test($row) : ($op === 'or' ? $result || $test($row) : $result && $test($row));
            return $result ?? true;
        }
        private function rows(): array {
            $rows = array_values(array_filter(Store::$tables[$this->model] ?? [], fn($row) => $this->matches($row)));
            if ($this->sort) usort($rows, function ($a, $b) {
                foreach (explode(',', $this->sort) as $sort) {
                    [$field, $direction] = array_pad(explode(' ', trim($sort)), 2, 'asc');
                    $cmp = ($a[$field] ?? 0) <=> ($b[$field] ?? 0);
                    if ($cmp) return $direction === 'desc' ? -$cmp : $cmp;
                } return 0;
            });
            return array_slice($rows, 0, $this->max);
        }
        public function select(): Rows { return new Rows($this->rows()); }
        public function findOrEmpty(): Row { return new $this->model($this->model, $this->rows()[0] ?? []); }
        public function find(): ?Row { $row = $this->findOrEmpty(); return $row->isEmpty() ? null : $row; }
        public function count(): int { return count($this->rows()); }
        public function value($field) { return $this->rows()[0][$field] ?? null; }
        public function update($values): int { $rows = $this->rows(); foreach ($rows as $row) (new $this->model($this->model, $row))->save($values); return count($rows); }
    }
    class Model extends Row {
        public function __construct($table = null, array $data = []) { parent::__construct($table ?? static::class, $data); }
        public static function where(...$args): Query { return (new Query(static::class))->where(...$args); }
        public static function create(array $data): static {
            $id = $data['id'] ?? (max(array_keys(Store::$tables[static::class] ?? [0 => []])) + 1);
            $row = new static(static::class, array_replace($data, ['id' => $id])); $row->save([]); return $row;
        }
    }
}
namespace think\facade {
    class Db {
        public static function startTrans(): void { \PhotoTest\Store::$transactions[] = \PhotoTest\Store::$tables; }
        public static function commit(): void { array_pop(\PhotoTest\Store::$transactions); }
        public static function rollback(): void { \PhotoTest\Store::$tables = array_pop(\PhotoTest\Store::$transactions); }
        public static function transaction($fn) { self::startTrans(); try { $result = $fn(); self::commit(); return $result; } catch (\Throwable $e) { self::rollback(); throw $e; } }
    }
}
namespace core\base { class BaseAdminService { public int $site_id = 100; public int $uid = 21; public string $username = '测试拍照员'; } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace addon\hsx_device_asset\app\model {
    class DeviceAssetItem extends \PhotoTest\Model {}
    class DeviceAssetMedia extends \PhotoTest\Model {}
    class DeviceAssetPhotoTask extends \PhotoTest\Model {}
    class DeviceAssetPriceOrder extends \PhotoTest\Model {}
}
namespace addon\hsx_erp\app\model {
    class ErpAsset extends \PhotoTest\Model {}
    class ErpWarehouse extends \PhotoTest\Model {}
}
namespace addon\hsx_erp\app\service\admin {
    class ErpLedgerService {
        public static array $entries = [];
        public static function forSite(...$args): self { return new self(); }
        public function asset($data): void { self::$entries[] = $data; }
    }
    class ErpListingTaskService {
        public static bool $fail = false;
        public static function forSite(...$args): self { return new self(); }
        public function sync($id): bool { if (self::$fail) throw new \RuntimeException('模拟任务分配失败'); return true; }
    }
    class ErpWarehousePolicyService {
        public static function forSite(...$args): self { return new self(); }
        public function evaluate(...$args): array { return ['can_prepare_mall' => 1, 'can_list_mall' => 0, 'missing_fields' => ['retail_price']]; }
    }
}
namespace {
    $root = dirname(__DIR__) . '/niucloud/addon/';
    require $root . 'hsx_erp/app/dict/ErpDict.php';
    require $root . 'hsx_erp/app/support/ErpListingWorkflow.php';
    require $root . 'hsx_device_asset/app/dict/DeviceAssetDict.php';
    require $root . 'hsx_device_asset/app/support/DeviceAssetPhotoEntry.php';
    require $root . 'hsx_device_asset/app/service/admin/DeviceAssetService.php';
    require $root . 'hsx_erp/app/listener/DeviceAssetPhotosCompleted.php';
    require $root . 'hsx_erp/app/listener/DeviceAssetPhotoLookupRequested.php';
    use addon\hsx_device_asset\app\model\{DeviceAssetItem as Item, DeviceAssetMedia as Media, DeviceAssetPhotoTask as Task};
    use addon\hsx_erp\app\model\ErpAsset;
    use addon\hsx_erp\app\service\admin\{ErpLedgerService, ErpListingTaskService};
    use addon\hsx_erp\app\listener\DeviceAssetPhotosCompleted;
    $eventsEnabled = true;
    function event($name, $payload): array {
        if ($name !== 'DeviceAssetPhotosCompleted') throw new \RuntimeException('意外事件：' . $name);
        return $GLOBALS['eventsEnabled'] ? [(new DeviceAssetPhotosCompleted())->handle($payload)] : [];
    }
    class Service extends \addon\hsx_device_asset\app\service\admin\DeviceAssetService {
        public int $priceOrders = 0;
        public function __construct() {}
        public function getInfo(int $assetId): array { return $this->getAsset($assetId)->toArray(); }
        protected function writeLog(int $assetId, int $deviceId, string $action, array $payload = []): void {}
        protected function ensurePriceOrder(Item $asset): void { $this->priceOrders++; }
    }
    $checks = 0;
    function check($condition, $message): void { if (!$condition) throw new \RuntimeException('FAIL ' . $message); $GLOBALS['checks']++; echo 'PASS ' . $message . PHP_EOL; }
    function denied(callable $operation, $message, $needle = ''): void { try { $operation(); } catch (\Throwable $e) { check($needle === '' || str_contains($e->getMessage(), $needle), $message . '：' . $e->getMessage()); return; } check(false, $message); }
    $service = new Service();
    check(\addon\hsx_device_asset\app\support\DeviceAssetPhotoEntry::mobileUrl('https://erp.test/', 100, 7) === 'https://erp.test/adminapp/100/addon/hsx_device_asset/pages/photo/capture?id=7', '手机兜底进入管理端且保留站点和设备');
    Item::create(['id' => 1, 'site_id' => 100, 'device_id' => 7, 'status' => 'wait_photo', 'photo_status' => 'wait_photo', 'ext_json' => ['erp_asset_id' => 10, 'asset_no' => 'ERP-TEST']]);
    Item::create(['id' => 2, 'site_id' => 200, 'device_id' => 8, 'ext_json' => []]);
    ErpAsset::create(['id' => 10, 'site_id' => 100, 'asset_no' => 'ERP-TEST', 'status' => 'in_stock', 'sale_target' => 'mall', 'listing_status' => 'pending_photo', 'warehouse_id' => 1, 'image_urls' => '', 'purchase_price' => 4500, 'total_cost' => 4600, 'retail_price' => 0, 'sale_price' => 0]);
    denied(fn() => $service->createPhotoTask(2), '站点隔离');
    $task = $service->createPhotoTask(1, ['source' => 'auto', 'station_id' => 'station-a']);
    $again = $service->createPhotoTask(1, ['source' => 'mobile']);
    check($again['id'] === $task['id'], '自动与手机共用一个有效拍摄任务');
    denied(fn() => $service->createPhotoTask(1, ['source' => 'auto', 'station_id' => 'station-b']), '不同工位不能争抢同一设备');
    $data = ['task_id' => $task['id'], 'media' => [['url' => 'upload/test/front.jpg', 'source' => 'auto', 'client_key' => 'station-a:job-1:photo-1', 'sort' => 1]]];
    denied(fn() => $service->saveMedia(1, $data + ['simulated' => true]), '模拟照片禁止进入业务');
    $result = $service->saveMedia(1, $data);
    $firstId = $result['saved_media'][0]['id'];
    $data['media'][0]['url'] = 'upload/test/duplicate-upload.jpg';
    $repeat = $service->saveMedia(1, $data);
    check($repeat['saved_media'][0]['id'] === $firstId && Media::where('asset_id', 1)->count() === 1, '丢失响应后重复上传同一照片不重复绑定');
    $second = $service->saveMedia(1, ['task_id' => $task['id'], 'media' => [['url' => 'upload/test/back.jpg', 'source' => 'mobile', 'client_key' => 'mobile:phone-photo-1', 'sort' => 2]]]);
    $secondId = $second['saved_media'][0]['id'];
    $selection = ['task_id' => $task['id'], 'media_ids' => [$secondId, $firstId]];
    denied(fn() => $service->confirmPhotos(1, ['media_ids' => [999]]), '不能确认无效或其他设备图片');
    $eventsEnabled = false;
    denied(fn() => $service->confirmPhotos(1, $selection), 'ERP 无回执不得显示成功', 'ERP 尚未接收');
    check(Item::where('id', 1)->find()->photo_status === 'review', '交接失败保留待确认状态');
    check(Media::where('asset_id', 1)->count() === 2, '交接失败不丢已上传图片');
    $eventsEnabled = true;
    ErpListingTaskService::$fail = true;
    denied(fn() => $service->confirmPhotos(1, $selection), 'ERP 后续处理失败向上抛出', '模拟任务分配失败');
    check(ErpAsset::where('id', 10)->find()->image_urls === '', 'ERP 中途失败回滚图片回写');
    ErpListingTaskService::$fail = false;
    ErpLedgerService::$entries = [];
    $receipt = $service->confirmPhotos(1, $selection);
    $erp = ErpAsset::where('id', 10)->find();
    check($receipt['status'] === 'erp_handoff' && $receipt['photo_handoff']['erp_asset_id'] === 10, 'ERP 明确回执后中台进入已交接');
    check(json_decode($erp->image_urls, true) === ['upload/test/back.jpg', 'upload/test/front.jpg'], '只用选图且尊重首图排序');
    check($erp->purchase_price === 4500 && $erp->total_cost === 4600 && $erp->retail_price === 0, '不修改成本或定价');
    check($erp->listing_status === 'need_price', '照片齐全但未定价时进入待定价，不自动上架');
    check($service->priceOrders === 0, 'ERP 设备不再创建中台定价工单');
    $repeat = $service->confirmPhotos(1, $selection);
    check($repeat['photo_handoff']['status'] === 'duplicate' && count(ErpLedgerService::$entries) === 1, '重复确认不重复写业务流水');
    denied(fn() => $service->confirmPhotos(1, ['task_id' => $task['id'], 'media_ids' => [$firstId]]), '另一窗口不能覆盖已经确认的选图', '照片已由其他窗口确认');
    denied(fn() => $service->completePrice(1, ['sale_price' => 5000]), 'ERP 定价责任唯一');
    denied(fn() => $service->saveMedia(1, ['task_id' => $task['id'], 'media' => [['url' => 'upload/test/new.jpg', 'source' => 'auto']]]), '已交接旧任务不能继续加入新图');
    $newTask = $service->createPhotoTask(1, ['source' => 'auto', 'station_id' => 'station-b']);
    check($newTask['id'] !== $task['id'], '完成后允许新的拍摄任务');
    denied(fn() => $service->confirmPhotos(1, $selection), '旧拍摄窗口不能覆盖新任务');
    $erp->save(['status' => 'sold']);
    denied(fn() => $service->confirmPhotos(1, ['task_id' => $newTask['id'], 'media_ids' => [$firstId]]), '已离库的设备不能接收销售图片');
    $erp->save(['status' => 'in_stock', 'listing_status' => 'listed']);
    denied(fn() => $service->confirmPhotos(1, ['task_id' => $newTask['id'], 'media_ids' => [$firstId]]), '已上架设备变更图片需先撤回');
    Item::create(['id' => 3, 'site_id' => 100, 'device_id' => 9, 'status' => 'photo_review', 'photo_status' => 'review', 'ext_json' => []]);
    Media::create(['site_id' => 100, 'asset_id' => 3, 'media_type' => 'image', 'status' => 'approved', 'url' => 'upload/test/standalone.jpg', 'sort' => 1]);
    $standalone = $service->confirmPhotos(3);
    check($standalone['status'] === 'wait_price' && $service->priceOrders === 1, '独立中台仍可完成自己的定价流程');
    $lookup = new \addon\hsx_erp\app\listener\DeviceAssetPhotoLookupRequested();
    $erp->save(['status' => 'in_stock', 'sn' => 'PHOTO-SCAN-SN']);
    check($lookup->handle(['site_id' => 100, 'keyword' => 'PHOTO-SCAN-SN'])['asset']['id'] === 10, '扫码完整串号定位唯一在库设备');
    check($lookup->handle(['site_id' => 200, 'keyword' => 'PHOTO-SCAN-SN']) === [], '扫码不会跨站取得设备');
    check($lookup->handle(['site_id' => 100, 'keyword' => 'PHOTO-SCAN']) === [], '扫码不以模糊串号误绑定');
    ErpAsset::create(['id' => 11, 'site_id' => 100, 'status' => 'in_stock', 'sn' => 'PHOTO-SCAN-SN']);
    denied(fn() => $lookup->handle(['site_id' => 100, 'keyword' => 'PHOTO-SCAN-SN']), '重复串号提示人工核对，不自动挑第一台', '多台在库设备');
    echo "{$checks} checks passed. No real database or hardware used.\n";
}
