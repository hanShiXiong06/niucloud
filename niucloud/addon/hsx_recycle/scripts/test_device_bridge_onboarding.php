<?php
declare(strict_types=1);

namespace app\service\core\sys {
    class CoreConfigService {
        public static array $memory = [];
        public function getConfigValue(int $siteId, string $key): array {
            return self::$memory[$siteId][$key] ?? [];
        }
        public function setConfig(int $siteId, string $key, array $value): bool {
            self::$memory[$siteId][$key] = $value;
            return true;
        }
    }
}

namespace core\base {
    class BaseAdminService {
        public static array $context = [0, 'admin'];
        protected $site_id;
        protected $app_type;
        public function __construct() {
            [$this->site_id, $this->app_type] = self::$context;
        }
    }
}

namespace {
    $root = dirname(__DIR__);
    require dirname($root, 2) . '/core/exception/CommonException.php';
    require $root . '/app/dict/config/RecycleConfigKeyDict.php';
    require $root . '/app/service/core/device/DeviceBridgeDownloads.php';
    require dirname($root, 2) . '/app/dict/sys/AppTypeDict.php';
    require $root . '/app/service/admin/device/DeviceBridgeConfigService.php';

    use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
    use addon\hsx_recycle\app\service\core\device\DeviceBridgeDownloads;
    use addon\hsx_recycle\app\service\admin\device\DeviceBridgeConfigService;
    use app\service\core\sys\CoreConfigService;
    use core\base\BaseAdminService;

    $checks = 0;
    $check = static function (bool $condition, string $message) use (&$checks): void {
        if (!$condition) throw new \RuntimeException('FAIL ' . $message);
        ++$checks;
        echo "PASS {$message}\n";
    };
    $defaults = DeviceBridgeDownloads::defaults();
    $check(count($defaults) === 5 && count(array_filter($defaults)) === 0, '默认不编造安装包或版本');
    $valid = DeviceBridgeDownloads::sanitize([
        'windows_url' => ' https://downloads.example.com/bridge.exe?version=0.2.0 ',
        'windows_version' => '0.2.0',
        'macos_arm64_url' => 'https://downloads.example.com/bridge.pkg',
        'macos_arm64_version' => '0.1.10',
        'tutorial_url' => 'https://docs.example.com/bridge',
        'webhook_url' => 'must-not-leak',
    ], true);
    $check($valid['macos_arm64_url'] === 'https://downloads.example.com/bridge.pkg', '平台地址不随站点域名变化');
    $check($valid['windows_url'] === 'https://downloads.example.com/bridge.exe?version=0.2.0', '保留下载查询参数并去除首尾空格');
    $check(!isset($valid['webhook_url']), '输出仅包含安装包元信息');
    foreach (['javascript:alert(1)', 'data:text/html,test', 'file:///tmp/test.exe', '//evil.example/file', '/upload/bridge.pkg', '/\\evil.example/file', 'http:evil.example', 'https://user:password@example.com/file', "https://example.com/\nfile"] as $url) {
        $check(DeviceBridgeDownloads::sanitize(['windows_url' => $url])['windows_url'] === '', '读取时过滤危险地址 ' . json_encode($url));
        $rejected = false;
        try { DeviceBridgeDownloads::sanitize(['windows_url' => $url], true); }
        catch (\core\exception\CommonException $exception) { $rejected = true; }
        $check($rejected, '保存时拒绝危险地址');
    }
    foreach (['latest', '0.2.0-beta', 'v0.2.0', '1.2.3.4.5', '1.9999999'] as $version) {
        $check(DeviceBridgeDownloads::sanitize(['windows_version' => $version])['windows_version'] === '', '拒绝无法比较的版本 ' . $version);
    }
    CoreConfigService::$memory = [
        11 => [RecycleConfigKeyDict::ORDER_SUBMIT => ['device_bridge' => $valid, 'work_wechat' => ['secret' => 'private']]],
        22 => [RecycleConfigKeyDict::ORDER_SUBMIT => ['device_bridge' => ['windows_url' => '/other.exe']]],
    ];
    $check(DeviceBridgeDownloads::getConfig() === $defaults, '未发布时返回空值，不继承任意站点旧地址');
    BaseAdminService::$context = [0, 'admin'];
    $platform = new DeviceBridgeConfigService();
    $check($platform->save($valid + ['site_id' => 22, 'secret' => 'private']), '平台保存成功');
    $check(CoreConfigService::$memory[0][RecycleConfigKeyDict::DEVICE_BRIDGE_DOWNLOADS] === $valid, '保存固定写入平台零站点并过滤额外字段');
    $check($platform->info() === $valid, '平台配置可以回读');
    $before = CoreConfigService::$memory;
    foreach ([[11, 'site'], [22, 'site'], [0, 'site'], [11, 'admin']] as $context) {
        BaseAdminService::$context = $context;
        $check(DeviceBridgeDownloads::getConfig() === $valid, '各站点均消费同一份平台下载配置');
        $service = new DeviceBridgeConfigService();
        foreach (['info', 'save'] as $method) {
            $rejected = false;
            try { $method === 'save' ? $service->save(['windows_url' => 'https://evil.example/test.exe']) : $service->info(); }
            catch (\core\exception\CommonException $exception) { $rejected = true; }
            $check($rejected, '非平台管理员不可执行平台管理接口 ' . $method);
        }
    }
    $check(CoreConfigService::$memory === $before, '越权请求不修改全局配置或站点配置');
    BaseAdminService::$context = [0, 'admin'];
    $platform = new DeviceBridgeConfigService();
    $rejected = false;
    try { $platform->save(['windows_url' => '/upload/bridge.exe']); }
    catch (\core\exception\CommonException $exception) { $rejected = true; }
    $check($rejected && CoreConfigService::$memory === $before, '无效地址保存失败且保留原配置');
    $check($platform->save([]) && DeviceBridgeDownloads::getConfig() === $defaults, '平台可撤下下载，不退回站点旧地址');
    echo "{$checks} checks passed; no database, download or device operations.\n";
}
