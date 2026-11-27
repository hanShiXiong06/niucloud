<?php
declare(strict_types=1);
// 官方参考目录及保存边界回归；使用内存替身，不连接数据库或外部渠道。
namespace core\base { class BaseCoreService { public function __construct() {} } class BaseAdminController {} }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $rows = [];
        public function getConfigValue($site, $key) { return self::$rows[$site][$key] ?? []; }
        public function getConfig($site, $key) { return self::$rows[$site][$key] ?? []; }
        public function setConfig($site, $key, $value) { self::$rows[$site][$key] = $value; }
    }
}
namespace addon\hsx_recycle\app\service\core\device_query {
    class DeviceQueryConfigService {
        public function getConfig(...$args) { return $this->defaultConfig(); }
        public function defaultConfig() { return ['services' => [], 'channels' => [], 'mappings' => []]; }
        public function sanitizeConfig($value) { return $value; }
        public function setConfig(...$args) {}
    }
}
namespace {
    require __DIR__ . '/../app/dict/config/RecycleConfigKeyDict.php';
    require __DIR__ . '/../app/dict/third_party/ThirdPartyDict.php';
    require __DIR__ . '/../app/service/core/express/ExpressSubmissionException.php';
    require __DIR__ . '/../app/service/core/express/provider/Kuaidi100Protocol.php';
    require __DIR__ . '/../app/service/core/express/provider/Kuaidi100ProductCatalog.php';
    require __DIR__ . '/../app/service/core/third_party/RecycleThirdPartyConfigService.php';
    require __DIR__ . '/../app/adminapi/controller/third_party/ThirdPartyConfig.php';

    use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
    use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
    use addon\hsx_recycle\app\service\core\express\provider\Kuaidi100ProductCatalog;
    use addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService;

    function success($data) { return ['code' => 1, 'data' => $data]; }
    $checks = 0;
    $check = static function (bool $ok, string $label) use (&$checks) {
        if (!$ok) throw new \RuntimeException('FAIL ' . $label);
        $checks++;
    };
    $reject = static function (callable $operation, string $label) use ($check) {
        try { $operation(); } catch (ExpressSubmissionException | \InvalidArgumentException $e) {
            $check($e->getMessage() !== '', $label);
            return;
        }
        throw new \RuntimeException('FAIL no rejection: ' . $label);
    };

    $guide = Kuaidi100ProductCatalog::guide();
    $check($guide['verified_at'] === '2026-09-29', 'catalog has verification date');
    $check($guide['source_type'] === 'official_reference_catalog', 'reference is not account entitlement');
    $check(strpos($guide['notice'], '不是本站账号已开通') !== false, 'account entitlement boundary visible');
    $modes = array_column($guide['modes'], null, 'value');
    $check(array_keys($modes) === ['online', 'offline'], 'separate payment mode catalogs');
    $online = array_values(array_filter($modes['online']['carriers'], static fn(array $row): bool => empty($row['disabled'])));
    $check(count($online) === 9, 'nine supported online carriers');
    $check(array_sum(array_map(static fn(array $row): int => count($row['products']), $online)) === 13, 'thirteen supported online products');
    $disabled = array_values(array_filter($modes['online']['carriers'], static fn(array $row): bool => !empty($row['disabled'])));
    $check(count($disabled) === 3, 'unsupported official carriers remain visible');
    foreach ($disabled as $carrier) {
        $check($carrier['reason'] !== '', $carrier['code'] . ' has unsupported explanation');
        $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave([
            'mode' => 'online', 'carrier_code' => $carrier['code'], 'service_type' => '标准快递',
        ], true), $carrier['code'] . ' cannot enable by forged API');
    }
    foreach ($online as $carrier) {
        foreach ($carrier['products'] as $product) {
            $normalized = Kuaidi100ProductCatalog::normalizeForSave([
                'mode' => 'online', 'carrier_code' => $carrier['code'], 'carrier_name' => '伪造名称', 'service_type' => $product['value'],
            ], true);
            $check($normalized['carrier_name'] === $carrier['name'], $carrier['code'] . ':' . $product['value'] . ' valid with canonical name');
        }
    }
    $check(count($modes['offline']['carriers']) === 9, 'nine offline carriers');
    foreach ($modes['offline']['carriers'] as $carrier) {
        $check(array_column($carrier['products'], 'value') === ['标准快递'], $carrier['code'] . ' offline only documented default');
        $check(strpos($carrier['products'][0]['note'], '未提供逐承运商') !== false, $carrier['code'] . ' offline qualification visible');
        $normalized = Kuaidi100ProductCatalog::normalizeForSave([
            'mode' => 'offline', 'carrier_code' => $carrier['code'], 'service_type' => '标准快递',
        ], true);
        $check($normalized['carrier_name'] === $carrier['name'], $carrier['code'] . ' offline selected name canonical');
    }
    foreach ($guide['sources'] as $source) {
        $check(parse_url($source['url'], PHP_URL_HOST) === 'api.kuaidi100.com', 'source uses official domain');
    }
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'auto'], true), 'unsupported mode rejected');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'online', 'carrier_code' => 'kuayue', 'service_type' => '标准快递'], true), 'offline-only carrier rejected online');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'offline', 'carrier_code' => 'jtexpress', 'service_type' => '标准快递'], true), 'online-only carrier rejected offline');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'offline', 'carrier_code' => 'shunfeng', 'service_type' => '顺丰标快'], true), 'online product not copied into offline');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'online', 'carrier_code' => 'jd', 'service_type' => '顺丰标快'], true), 'product cannot cross carrier');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'online', 'carrier_code' => 'shunfeng', 'service_type' => '商务自填产品'], true), 'freeform product rejected');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'online', 'carrier_code' => 'shunfeng', 'service_type' => ''], true), 'enabled requires explicit product selection');
    $reject(static fn() => Kuaidi100ProductCatalog::normalizeForSave(['mode' => 'online', 'carrier_code' => ['shunfeng'], 'service_type' => '顺丰标快'], true), 'non-scalar carrier rejected');
    $draft = ['mode' => 'unfinished', 'carrier_code' => '', 'service_type' => '待确认'];
    $check(Kuaidi100ProductCatalog::normalizeForSave($draft, false) === $draft, 'draft does not require complete selection');

    $service = new RecycleThirdPartyConfigService();
    $reject(static fn() => $service->setConfig(1, ['express_order' => null]), 'malformed section cannot bypass enabled catalog validation');
    $reject(static fn() => $service->setConfig(1, ['express_order' => ['kuaidi100' => 'invalid']]), 'malformed provider config rejected before save');
    $valid = ['api_key' => 'site-key', 'secret' => 'site-secret', 'carrier_name' => '恶意公司名',
        'callback_url' => 'https://example.test/api/recycle/express/kuaidi100_push', 'service_type' => '顺丰标快'];
    $service->setConfig(1, ['express_order' => ['enabled' => 1, 'provider' => 'kuaidi100', 'kuaidi100' => $valid]]);
    $check($service->getConfig(1)['express_order']['kuaidi100']['carrier_name'] === '顺丰速运', 'actual save canonicalizes name');
    $before = $service->getConfig(1);
    $reject(static fn() => $service->setConfig(1, ['express_order' => ['kuaidi100' => ['service_type' => '任意字符串']]]), 'enabled saved config rejects unlisted product');
    $check($service->getConfig(1) === $before, 'rejected save leaves old configuration intact');
    $reject(static fn() => $service->setConfig(1, ['express_order' => ['kuaidi100' => ['payment' => 'CONSIGNEE']]]), 'existing online payment rule retained');
    $service->setConfig(2, ['express_order' => ['enabled' => 0, 'provider' => 'kuaidi100', 'kuaidi100' => $draft]]);
    $check($service->getConfig(2)['express_order']['kuaidi100']['service_type'] === '待确认', 'disabled incomplete draft saved');
    $reject(static fn() => $service->setConfig(2, ['express_order' => ['enabled' => 1]]), 'enabling an invalid draft rejected');
    $service->setConfig(3, ['express_order' => ['enabled' => 1, 'provider' => 'kuaidi100', 'kuaidi100' => array_replace($valid, [
        'mode' => 'offline', 'carrier_code' => 'shunfeng', 'service_type' => '标准快递', 'payment' => 'CONSIGNEE',
    ])]]);
    $check($service->getConfig(3)['express_order']['kuaidi100']['payment'] === 'CONSIGNEE', 'supported offline consignee preserved');
    $reject(static fn() => $service->setConfig(3, ['express_order' => ['kuaidi100' => ['carrier_code' => 'yuantong']]]), 'offline YTO consignee still rejected');

    // 模拟已有库里的旧商务产品。读取、其他配置保存及停用不应用新的参考目录约束。
    $legacy = $before;
    $legacy['express_order']['kuaidi100']['service_type'] = '历史商务产品';
    \app\service\core\sys\CoreConfigService::$rows[4][RecycleConfigKeyDict::THIRD_PARTY] = $legacy;
    $check($service->getProviderConfig(4, 'express_order', 'kuaidi100', true)['service_type'] === '历史商务产品', 'historical credential read not blocked');
    $service->setConfig(4, ['address_parse' => ['enabled' => 0]]);
    $check($service->getProviderConfig(4, 'express_order', 'kuaidi100', true)['service_type'] === '历史商务产品', 'unrelated save leaves historical product unchanged');
    $service->setConfig(4, ['express_order' => ['enabled' => 0]]);
    $check($service->getProviderConfig(4, 'express_order', 'kuaidi100', true)['service_type'] === '历史商务产品', 'disable legacy config without losing historical product');
    $check($service->getProviderConfig(4, 'express_order', 'kuaidi100') === [], 'disabled legacy new bookings blocked');
    $reject(static fn() => $service->setConfig(4, ['express_order' => ['enabled' => 1]]), 're-enabling legacy arbitrary product requires supported selection');

    $controller = new \addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyConfig();
    $rowsBeforeGuide = \app\service\core\sys\CoreConfigService::$rows;
    $check($controller->kuaidi100Guide()['data'] === $guide, 'controller exposes exact local guide without account request');
    $check(\app\service\core\sys\CoreConfigService::$rows === $rowsBeforeGuide, 'guide makes no configuration mutation');
    $routes = file_get_contents(__DIR__ . '/../app/adminapi/route/route.php');
    $check(strpos($routes, "Route::get('third_party_config/kuaidi100_guide'") !== false, 'read-only GET route registered');
    echo "PASS {$checks} catalog checks (mock storage, no DB/network)\n";
}
