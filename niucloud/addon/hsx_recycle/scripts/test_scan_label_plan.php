<?php
declare(strict_types=1);

// Actual print plan/controller, in-memory dependencies. Never prints or connects to DB.
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace ScanLabelPlanFixture {
    final class Store {
        public static int $status = 5;
        public static bool $deviceAvailable = true, $templateAvailable = true, $printerAvailable = true;
        public static string $signTime = '';
        public static array $forwarded = [];
    }
    final class Request {
        public array $data;
        public function __construct(array $data) { $this->data = $data; }
        public function params(array $defaults): array { $result = []; foreach ($defaults as [$key, $value]) $result[$key] = $this->data[$key] ?? $value; return $result; }
    }
    final class Row { public function toArray(): array { return Store::$deviceAvailable ? ['id' => 3036, 'status' => Store::$status, 'confirm_status' => 1, 'pay_status' => 1] : []; } }
    final class TemplateService {
        public function getDevicePrintData(int $id): array {
            if (!Store::$deviceAvailable || $id !== 3036) throw new \core\exception\AdminException('设备不存在');
            return ['device_id' => 3036, 'order_id' => 2527, 'order_no' => 'R2527', 'model' => '苹果 iPhone 14 Pro', 'imei' => '353938205192374', 'color' => '黑色', 'status_name' => '已回收', 'sign_time' => Store::$signTime];
        }
        public function getInfo(int $id): array { return ['template_id' => $id, 'status' => Store::$templateAvailable ? 1 : 0, 'template_name' => '测试标签', 'template_type' => 'device_label', 'instruction_content' => '{{model}} {{imei}} {{color}}', 'width' => 50, 'printer_id' => 3]; }
        public function getDefaultPrinter(int $id): array { return Store::$printerAvailable ? ['printer_id' => $id, 'printer_name' => '测试打印机', 'sn' => 'TEST', 'brand' => 'test', 'type' => 'label'] : []; }
    }
}
namespace core\base { class BaseAdminService { protected $site_id = 100005; } class BaseAdminController { public $request; } }
namespace core\exception { class AdminException extends \RuntimeException {} }
namespace addon\hsx_recycle\app\model\printer { class RecyclePrintScene { public static function getSceneName($key): string { return (string)$key; } } }
namespace addon\hsx_recycle\app\model\order {
    class RecycleDevice {
        public function where(array $conditions): self { return $this; }
        public function field(string $field): self { return $this; }
        public function findOrEmpty(): \ScanLabelPlanFixture\Row { return new \ScanLabelPlanFixture\Row(); }
    }
}
namespace addon\hsx_recycle\app\service\admin\template { class RecycleTemplateBindingService { public function resolveForDevice(array $device, string $scene): array { return []; } } }
namespace {
    use ScanLabelPlanFixture\Store;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
    use addon\hsx_recycle\app\adminapi\controller\printer\PrintScene;

    function success($data = null): array { return ['code' => 1, 'data' => $data]; }
    function fail($message, $data = null): array { return ['code' => 0, 'msg' => $message, 'data' => $data]; }
    if (!function_exists('mb_check_encoding')) { function mb_check_encoding($value, $encoding): bool { return preg_match('//u', $value) === 1; } }
    require dirname(__DIR__) . '/app/service/admin/printer/RecyclePrintSceneService.php';
    require dirname(__DIR__) . '/app/service/admin/printer/template/VariableReplaceService.php';
    require dirname(__DIR__) . '/app/adminapi/controller/printer/PrintScene.php';
    final class FixtureService extends RecyclePrintSceneService {
        public array $scene;
        public function __construct() {
            $this->templateService = new \ScanLabelPlanFixture\TemplateService();
            $this->scene = ['scene_key' => 'label-test', 'scene_name' => '测试标签', 'status' => 1, 'auto_print' => 0, 'biz_type' => 'device', 'template_type' => 'device_label', 'template_id' => 8, 'printer_id' => 3, 'copies' => 1,
                'button_config' => ['enabled' => 1, 'text' => '打印', 'visible_device_status' => [1, 2], 'visible_confirm_status' => [0], 'visible_pay_status' => [0], 'label_edit' => ['enabled' => 0]]];
        }
        public function getInfo(string $sceneKey): array { return $this->scene; }
        public function printScene(string $sceneKey, array $payload = []): array { Store::$forwarded = $payload; return ['success' => true]; }
    }
    $count = 0;
    $same = static function ($actual, $expected, string $label) use (&$count): void { if ($actual !== $expected) throw new \RuntimeException($label . ' actual=' . json_encode($actual, JSON_UNESCAPED_UNICODE)); $count++; };
    $service = new FixtureService();
    $payload = ['device_id' => 3036, 'order_id' => 2527, 'biz_id' => 3036];
    $same($service->resolvePlanBySceneKey('label-test', $payload)['can_print'], false, 'ordinary manual print preserves status filter');
    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $status) {
        Store::$status = $status;
        $plan = $service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label']);
        $same($plan['can_print'], true, 'scan label allowed at business status ' . $status);
        $same($plan['label_edit']['enabled'], 1, 'scan entry explicitly enables temporary label editing');
        $same($plan['device']['device_id'], 3036, 'actual device identity retained');
        $same($plan['device']['order_id'], 2527, 'actual device order retained');
    }
    $plan = $service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label', 'print_data_override' => ['color' => '银色', 'device_id' => '999', 'order_id' => '999']]);
    $same($plan['print_data']['color'], '银色', 'temporary edits apply even when normal scene editor was disabled');
    $same($plan['print_data']['device_id'], 3036, 'readonly device ID cannot be changed by label text');
    $same($plan['print_data']['order_id'], 2527, 'readonly order ID cannot be changed by label text');
    $same(Store::$status, 9, 'plan and overrides do not change workflow status');
    foreach (['status', 'button'] as $disabled) {
        if ($disabled === 'status') $service->scene['status'] = 0; else $service->scene['button_config']['enabled'] = 0;
        $same($service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label'])['can_print'], false, 'disabled ' . $disabled . ' still blocks');
        $service->scene['status'] = 1; $service->scene['button_config']['enabled'] = 1;
    }
    $service->scene['template_type'] = 'device_order';
    $same($service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label'])['can_print'], false, 'scan entry cannot bypass non-label scene state filter');
    $service->scene['template_type'] = 'device_label';
    Store::$deviceAvailable = false;
    $same($service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label'])['can_print'], false, 'device scope lookup still rejects unavailable/cross-site device');
    Store::$deviceAvailable = true; Store::$templateAvailable = false;
    $same($service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label'])['can_print'], false, 'disabled template still blocks');
    Store::$templateAvailable = true; Store::$printerAvailable = false;
    $same($service->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label'])['can_print'], false, 'missing printer still blocks');
    Store::$printerAvailable = true;
    $controller = (new \ReflectionClass(PrintScene::class))->newInstanceWithoutConstructor();
    $property = new \ReflectionProperty(PrintScene::class, 'service'); $property->setAccessible(true); $property->setValue($controller, $service);
    $controller->request = new \ScanLabelPlanFixture\Request($payload + ['entry' => 'scan_label']);
    $same($controller->plan('label-test')['code'], 1, 'actual plan controller forwards scoped entry');
    $controller->print('label-test');
    $same(Store::$forwarded['entry'], 'scan_label', 'actual print controller forwards scoped entry');

    // PC 逐台保存先于整单签收，手机整单提交后已有 sign_time；两种路径必须都有标签日期。
    $signScene = new FixtureService();
    $signScene->scene['trigger_key'] = 'device.sign.saved';
    $signScene->scene['auto_print'] = 1;
    Store::$signTime = '';
    $startedAt = time();
    $plan = $signScene->resolvePlanBySceneKey('label-test', $payload, true);
    $finishedAt = time();
    $same($plan['can_print'], true, 'PC save produces an automatic label plan');
    $signedAt = strtotime((string)($plan['print_data']['sign_time'] ?? ''));
    $same($signedAt !== false && $signedAt >= $startedAt && $signedAt <= $finishedAt, true, 'PC label uses this device-save event time before whole-order signing');
    $same($plan['device_data']['sign_time'], $plan['print_data']['sign_time'], 'task and print variable snapshots agree');
    $replace = new \addon\hsx_recycle\app\service\admin\printer\template\VariableReplaceService();
    $same($replace->replaceVariables('签收日期：{{sign_time}}', $plan['print_data']), '签收日期：' . $plan['print_data']['sign_time'], 'actual template substitution includes receipt date');
    $same(Store::$signTime, '', 'printing does not write an early order signature');

    Store::$signTime = '2026-11-20 09:10:11';
    $plan = $signScene->resolvePlanBySceneKey('label-test', $payload, true);
    $same($plan['print_data']['sign_time'], Store::$signTime, 'mobile/previously signed order preserves actual receipt time, not print time');

    Store::$signTime = '';
    $signScene->scene['trigger_key'] = 'device.check.saved';
    $plan = $signScene->resolvePlanBySceneKey('label-test', $payload, true);
    $same($plan['print_data']['sign_time'], '', 'other automatic events must not invent a signature time');
    $signScene->scene['condition_config'] = ['trigger' => ['key' => 'device.sign.saved']];
    $plan = $signScene->resolvePlanBySceneKey('label-test', $payload, true);
    $same(trim($plan['print_data']['sign_time']) !== '', true, 'configured trigger takes precedence over legacy scene trigger');
    $plan = $signScene->resolvePlanBySceneKey('label-test', $payload + ['entry' => 'scan_label']);
    $same($plan['print_data']['sign_time'], '', 'manual unsigned label keeps time empty rather than pretending today was receipt day');
    $signScene->scene['auto_print'] = 0;
    $same($signScene->resolvePlanBySceneKey('label-test', $payload, true)['can_print'], false, 'date completion does not bypass automatic print switch');
    echo '[通过] ' . $count . " 项扫码打标与签收日期后端隔离回归；实际计划、变量替换与控制器，不连接数据库、不实际打印。\n";
}
