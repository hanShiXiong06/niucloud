<?php
declare(strict_types=1);
// 插件配置回归，仅使用内存替身，不连接数据库或外部服务。
namespace core\base { class BaseCoreService { public function __construct() {} } }
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
namespace think\facade { class Log { public static function warning(...$args) {} public static function error(...$args) {} } }
namespace addon\hsx_recycle\app\service\core\third_party\provider\express_order {
    class ProviderYisu { public array $config; public function __construct($config, $site) { $this->config = $config; } public function healthCheck() { return true; } }
}
namespace {
    require __DIR__ . '/../app/dict/config/RecycleConfigKeyDict.php';
    require __DIR__ . '/../app/dict/third_party/ThirdPartyDict.php';
    require __DIR__ . '/../app/service/core/express/ExpressSubmissionException.php';
    require __DIR__ . '/../app/service/core/express/provider/Kuaidi100Protocol.php';
    require __DIR__ . '/../app/service/core/third_party/RecycleThirdPartyConfigService.php';
    require __DIR__ . '/../app/service/core/third_party/CoreThirdPartyService.php';
    $checks = 0;
    $check = static function (bool $ok, string $label) use (&$checks) { if (!$ok) throw new \RuntimeException('FAIL ' . $label); $checks++; };
    $service = new \addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService();
    $config = ['api_key' => 'site-one-key', 'secret' => 'site-one-secret', 'callback_url' => 'https://example.test/api/recycle/express/kuaidi100_push', 'service_type' => '顺丰标快'];
    $service->setConfig(1, ['express_order' => ['provider' => 'kuaidi100', 'kuaidi100' => $config]]);
    $raw = $service->getConfig(1, false);
    $masked = $service->getConfig(1, true);
    $check($masked['express_order']['kuaidi100']['api_key'] === '******', 'key masked');
    $check($masked['express_order']['kuaidi100']['secret'] === '******', 'secret masked');
    $check($masked['express_order']['kuaidi100']['callback_salt'] === '******', 'callback salt masked');
    $check(strlen($raw['express_order']['kuaidi100']['callback_salt']) === 48, 'salt generated server side');
    $service->setConfig(1, ['express_order' => $masked['express_order']]);
    $check($service->getConfig(1, false)['express_order']['kuaidi100']['api_key'] === 'site-one-key', 'masked save preserves key');
    $service->setConfig(1, ['address_parse' => ['enabled' => 0]]);
    $check($service->getConfig(1, false)['express_order']['provider'] === 'kuaidi100', 'unrelated section save preserves provider');
    $service->setConfig(2, ['express_order' => ['provider' => 'kuaidi100', 'kuaidi100' => array_replace($config, ['api_key' => 'site-two-key'])]]);
    $check($service->getProviderConfig(1, 'express_order', 'kuaidi100')['api_key'] !== $service->getProviderConfig(2, 'express_order', 'kuaidi100')['api_key'], 'site credential isolation');
    try { $service->setConfig(1, ['express_order' => ['kuaidi100' => ['payment' => 'CONSIGNEE']]]); throw new \RuntimeException('did not reject'); }
    catch (\addon\hsx_recycle\app\service\core\express\ExpressSubmissionException $e) { $check($e->outcome() === 'rejected', 'invalid payment not saved'); }
    $check($service->getProviderConfig(1, 'express_order', 'kuaidi100')['payment'] === 'SHIPPER', 'failed save preserves previous config');
    $service->setConfig(1, ['express_order' => ['enabled' => 0]]);
    $check($service->getProviderConfig(1, 'express_order', 'kuaidi100') === [], 'disabled blocks new calls');
    $check($service->getProviderConfig(1, 'express_order', 'kuaidi100', true)['api_key'] === 'site-one-key', 'old orders keep credentials after disabling new booking');
    $core = new \addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService();
    $method = new \ReflectionMethod($core, 'getProvider');
    $method->setAccessible(true);
    $oldProvider = $method->invoke($core, 'express_order', 1, '', 'yisu', true);
    $check($oldProvider instanceof \addon\hsx_recycle\app\service\core\third_party\provider\express_order\ProviderYisu, 'explicit old Yisu never changes to active Kuaidi100');
    try { $method->invoke($core, 'express_order', 1, '', 'yisu', false); throw new \RuntimeException('new creation allowed'); }
    catch (\core\exception\CommonException $e) { $check(strpos($e->getMessage(), '禁用') !== false, 'new explicit provider cannot bypass disabled service'); }
    echo "PASS {$checks} configuration checks (mock storage, no DB/network)\n";
}
