<?php
declare(strict_types=1);

/** 下单设置顺丰跟随回归；配置、产品和主题均为内存替身，不连接数据库或快递。 */
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $data = [];
        public static array $writes = [];
        public function getConfigValue($site, $key) { return self::$data[$site][$key] ?? []; }
        public function setConfig($site, $key, $data) {
            self::$writes[] = [$site, $key]; self::$data[$site][$key] = $data; return true;
        }
    }
}
namespace app\model\diy {
    class DiyTheme {
        public function where($value) { return $this; }
        public function field($value) { return $this; }
        public function findOrEmpty() { return $this; }
        public function toArray() { return []; }
    }
}
namespace addon\hsx_recycle\app\service\core\third_party {
    class RecycleThirdPartyConfigService {
        public static array $active = [1 => 'sf_direct', 2 => 'yisu'];
        public function getActiveProvider($site, $type) { return self::$active[$site] ?? 'yisu'; }
    }
}
namespace addon\hsx_recycle\app\model\express {
    class ExpressProviderConfig {
        public static int $reads = 0;
        public static bool $empty = false;
        public static function getEnabledProviders($site) {
            self::$reads++;
            return self::$empty ? [] : [['provider' => 'yisu', 'provider_name' => '亿速物流', 'is_default' => 1]];
        }
        public static function initSiteConfig($site) {}
    }
}
namespace addon\hsx_recycle\app\service\core\express {
    class ExpressGatewayService {
        public static array $products = [];
        public static bool $fail = false;
        public function products($site) {
            if (self::$fail) throw new \RuntimeException('disabled or missing provider');
            return self::$products[$site] ?? [];
        }
    }
    class ExpressProductCatalogService {
        public static int $reads = 0;
        public function getEnabledProducts($site, $provider) {
            self::$reads++;
            return [['product_code' => 'JD', 'product_name' => '京东快递']];
        }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    function event($name, $data) { return []; }
    $root = dirname(__DIR__);
    foreach (['dict/config/RecycleConfigKeyDict', 'dict/order/RecycleOrderDict', 'dict/express/ExpressProviderDict',
        'dict/third_party/ThirdPartyDict', 'service/core/express/PickupAppointmentPolicy', 'service/core/order/OrderSubmitConfigService',
        'service/core/express/RecycleExpressService'] as $file) {
        require $root . '/app/' . $file . '.php';
    }
    use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict as Keys;
    use addon\hsx_recycle\app\model\express\ExpressProviderConfig as LegacyProviders;
    use addon\hsx_recycle\app\service\core\express\ExpressGatewayService as Gateway;
    use addon\hsx_recycle\app\service\core\express\ExpressProductCatalogService as LegacyProducts;
    use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService as Service;
    use app\service\core\sys\CoreConfigService as Storage;

    $checks = 0;
    function check(bool $ok, string $name): void {
        global $checks;
        if (!$ok) throw new \RuntimeException('FAIL ' . $name);
        $checks++;
    }
    $service = new Service();
    $old = $service->defaultConfig();
    $old['platform_delivery'] = ['provider' => 'yisu', 'provider_name' => '亿速物流', 'product_code' => 'JD',
        'product_name' => '京东快递', 'display_name' => '原京东包邮', 'free_shipping_min_count' => 3];
    $old['notice'] = ['enabled' => 1, 'title' => '原通知', 'content' => '保留内容'];
    Storage::$data[1][Keys::ORDER_SUBMIT] = $old;
    Storage::$data[2][Keys::ORDER_SUBMIT] = $old;
    $sfProduct = ['provider' => 'sf_direct', 'enabled' => 1, 'product_code' => 'sf_pickup_2', 'product_name' => '上门取件 · 顺丰标快'];
    Gateway::$products[1] = [$sfProduct];

    $config = $service->getConfig(1);
    $delivery = $config['platform_delivery'];
    check($delivery['provider'] === 'sf_direct' && $delivery['provider_name'] === '顺丰直连', 'active channel overrides stale provider identity');
    check(count($delivery['provider_options']) === 1 && $delivery['provider_options'][0]['provider'] === 'sf_direct', 'no competing Yisu channel in SF selection');
    check($delivery['product_code'] === 'sf_pickup_2' && $delivery['product_name'] === $sfProduct['product_name'], 'product comes from actual pickup gateway');
    check($delivery['display_name'] === '顺丰速运', 'customer name is not stale JD label');
    check($delivery['free_shipping_min_count'] === 3, 'existing minimum count kept');
    check($config['notice']['content'] === '保留内容', 'unrelated settings preserved');
    check(LegacyProviders::$reads === 0 && LegacyProducts::$reads === 0, 'SF does not read or initialize Yisu configuration');
    check(Storage::$writes === [] && Storage::$data[1][Keys::ORDER_SUBMIT] === $old, 'read does not migrate stored data');

    $config['platform_delivery'] = array_replace($delivery, ['provider' => 'yisu', 'provider_name' => 'fake',
        'product_code' => 'JD', 'product_name' => 'fake product', 'display_name' => 'fake label', 'free_shipping_min_count' => 5]);
    check($service->setConfig(1, $config), 'settings save succeeds');
    $saved = Storage::$data[1][Keys::ORDER_SUBMIT]['platform_delivery'];
    check($saved['provider'] === 'sf_direct' && $saved['provider_name'] === '顺丰直连', 'client cannot override central provider');
    check($saved['product_code'] === 'sf_pickup_2' && $saved['product_name'] === $sfProduct['product_name'] && $saved['display_name'] === '顺丰速运', 'save ignores stale or forged names and product');
    check($saved['free_shipping_min_count'] === 5, 'business minimum count still editable');
    check(Storage::$writes === [[1, Keys::ORDER_SUBMIT]], 'save does not change logistics credentials or other sites');
    check(Storage::$data[2][Keys::ORDER_SUBMIT] === $old, 'other tenant untouched');

    Gateway::$products[1] = [array_replace($sfProduct, ['product_code' => 'sf_pickup_1', 'product_name' => '上门取件 · 顺丰特快'])];
    $delivery = $service->getConfig(1)['platform_delivery'];
    check($delivery['product_code'] === 'sf_pickup_1' && $delivery['product_name'] === '上门取件 · 顺丰特快', 'central product changes reflected without resaving submit settings');

    foreach (['empty', 'failure', 'disabled_product', 'wrong_provider'] as $case) {
        Gateway::$fail = $case === 'failure';
        Gateway::$products[1] = $case === 'disabled_product' ? [array_replace($sfProduct, ['enabled' => 0])]
            : ($case === 'wrong_provider' ? [array_replace($sfProduct, ['provider' => 'yisu'])] : []);
        $config = $service->getConfig(1);
        $delivery = $config['platform_delivery'];
        check($delivery['provider'] === 'sf_direct', $case . ' keeps original configured channel');
        check($delivery['product_options'] === [] && $delivery['product_code'] === '' && $delivery['product_name'] === '', $case . ' exposes no fake product');
        check($delivery['display_name'] === '顺丰速运', $case . ' never falls back to JD name');
        check($service->setConfig(1, $config), $case . ' does not block saving unrelated settings');
    }
    Gateway::$fail = false;
    $delivery = $service->getConfig(2)['platform_delivery'];
    check($delivery['provider'] === 'yisu' && $delivery['product_code'] === 'JD' && $delivery['display_name'] === '原京东包邮', 'legacy site keeps existing route');
    LegacyProviders::$empty = true;
    $delivery = $service->getConfig(2)['platform_delivery'];
    check(count($delivery['provider_options']) === 1 && $delivery['provider_options'][0]['provider'] === 'yisu', 'empty legacy directory does not advertise unselected SF');
    check($service->canUsePlatformDelivery(1, 5) && !$service->canUsePlatformDelivery(1, 4), 'existing count eligibility unchanged');
    $config = $service->getConfig(1);
    check($config['platform_delivery']['payment_tips'] === '', 'no invented merchant freight promise');
    check($config['platform_delivery']['pickup_schedule'] === ['start' => '09:00', 'end' => '18:00', 'cutoff' => '16:00'], 'old settings get explicit daily defaults');
    $config['platform_delivery']['payment_tips'] = '  运费由商家承担，您无需支付。  ';
    $config['platform_delivery']['pickup_schedule'] = ['start' => '10:00', 'end' => '17:00', 'cutoff' => '15:00'];
    $service->setConfig(1, $config);
    $readback = $service->getConfig(1)['platform_delivery'];
    check($readback['payment_tips'] === '运费由商家承担，您无需支付。', 'editable customer explanation round trips');
    check($readback['pickup_schedule'] === $config['platform_delivery']['pickup_schedule'], 'schedule round trips in existing config');
    check($service->getConfig(2)['platform_delivery']['payment_tips'] === '', 'freight explanation is site scoped');
    $config['platform_delivery']['payment_tips'] = str_repeat('字', 130);
    $service->setConfig(1, $config);
    check(mb_strlen($service->getConfig(1)['platform_delivery']['payment_tips']) === 120, 'server limits explanation length');
    $config['platform_delivery']['pickup_schedule']['end'] = '09:00';
    $writes = count(Storage::$writes);
    try { $service->setConfig(1, $config); throw new \RuntimeException('invalid schedule saved'); }
    catch (\core\exception\CommonException $e) { check(count(Storage::$writes) === $writes, 'invalid schedule rejected before persistence'); }
    Gateway::$products[1] = [array_replace($sfProduct, ['pickup_time_supported' => true, 'pickup_time_required' => true,
        'payment_tips' => '供应商技术结算说明'])];
    $config['platform_delivery']['pickup_schedule'] = ['start' => '10:00', 'end' => '17:00', 'cutoff' => '15:00'];
    $config['platform_delivery']['payment_tips'] = '运费由商家承担，您无需支付。';
    $service->setConfig(1, $config);
    $pickup = new class extends \addon\hsx_recycle\app\service\core\express\RecycleExpressService {
        public bool $available = true;
        public function isExpressEnabled(int $siteId): bool { return $this->available; }
        public function getShopAddress(int $siteId): ?array { return ['name' => '测试门店']; }
    };
    $policy = $pickup->pickupPolicy(1);
    check($policy['pickup_enabled'] === true && $policy['pickup_time_required'] === true, 'actual service exposes usable appointment policy');
    check($policy['payment_tips'] === $config['platform_delivery']['payment_tips'], 'actual service uses customer wording not provider billing text');
    check(str_ends_with($policy['pickup_time'], '-17:00') && $policy['pickup_time_text'] !== '', 'actual service generates complete maintained window');
    check($pickup->validatePickupTime(1, $policy['pickup_time']) === $policy['pickup_time'], 'displayed policy validates unchanged before creating recycle order');
    $writes = count(Storage::$writes);
    foreach (['', " \t\n"] as $missing) {
        $generated = $pickup->validatePickupTime(1, $missing);
        check(str_ends_with($generated, '-17:00') && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}-/', $generated), 'missing client time becomes full configured appointment');
        check($pickup->validatePickupTime(1, $generated) === $generated, 'fallback is valid for downstream submission');
    }
    foreach (['2026-01-01 09:00-18:00', '09:00-18:00'] as $invalid) {
        try { $pickup->validatePickupTime(1, $invalid); throw new \RuntimeException('invalid client time accepted'); }
        catch (\core\exception\CommonException $e) { check(str_contains($e->getMessage(), '尚未提交订单'), 'invalid client appointment has actionable error'); }
    }
    check(count(Storage::$writes) === $writes, 'policy read and validation do not write business data');
    $pickup->available = false;
    check($pickup->pickupPolicy(1)['pickup_enabled'] === false, 'disabled pickup does not advertise a booking');
    try { $pickup->validatePickupTime(1, $policy['pickup_time']); throw new \RuntimeException('disabled pickup accepted'); }
    catch (\core\exception\CommonException $e) { check(str_contains($e->getMessage(), '暂未开通'), 'disabled service rejected before order'); }
    try { $pickup->validatePickupTime(1, ''); throw new \RuntimeException('fallback enabled disabled pickup'); }
    catch (\core\exception\CommonException $e) { check(str_contains($e->getMessage(), '暂未开通'), 'default time cannot bypass disabled service'); }
    $controller = file_get_contents($root . '/app/api/controller/recycle_order/RecycleOrder.php');
    check(strpos($controller, '->validatePickupTime(') < strpos($controller, '$this->service->add($data)'), 'controller validates time before creating the recycle order');
    echo "PASS {$checks} submit/SF configuration checks (memory only; no DB/network)\n";
}
