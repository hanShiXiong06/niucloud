<?php
declare(strict_types=1);

// 执行真实配置服务和策略监听器；配置存储为内存替身，不连接数据库。
namespace core\base {
    class BaseAdminService
    {
        protected int $site_id = 0;
    }
}

namespace app\service\core\sys {
    class CoreConfigService
    {
        public static array $values = [];

        public function getConfigValue(int $siteId, string $key): mixed
        {
            return self::$values[$siteId][$key] ?? null;
        }

        public function setConfig(int $siteId, string $key, array $value): void
        {
            self::$values[$siteId][$key] = $value;
        }
    }
}

namespace {
    use addon\hsx_erp\app\listener\marketplace\ListingMaterialPolicy;
    use addon\hsx_erp\app\service\admin\ErpConfigService;
    use app\service\core\sys\CoreConfigService;

    require dirname(__DIR__) . '/app/support/ErpListingFormContract.php';
    require dirname(__DIR__) . '/app/service/admin/ErpConfigService.php';
    require dirname(__DIR__) . '/app/listener/marketplace/ListingMaterialPolicy.php';

    set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    $count = 0;
    $assert = static function (bool $condition, string $message) use (&$count): void {
        $count++;
        if (!$condition) throw new \RuntimeException('[FAIL] ' . $message);
    };
    $siteId = 100005;
    $service = ErpConfigService::forSite($siteId);
    $put = static function (mixed $value, int $site = 100005): void {
        CoreConfigService::$values[$site][ErpConfigService::CONFIG_KEY] = $value;
    };
    $expect = static function (array $channel, array $expected, string $scenario) use ($assert): void {
        foreach ($expected as $key => $value) {
            $assert(($channel[$key] ?? null) === $value, $scenario . ': ' . $key);
        }
        $assert($channel['erp_is_master'] === 1 && $channel['channel_can_write_erp_master'] === 0,
            $scenario . ': 商城不能覆盖 ERP 主资料');
    };
    $direct = ['enabled' => 1, 'category_mode' => 'erp', 'spec_mode' => 'erp', 'publish_mode' => 'direct'];
    $manual = ['enabled' => 1, 'category_mode' => 'independent', 'spec_mode' => 'independent', 'publish_mode' => 'manual'];

    // 复现线上未保存配置时读取 recycle_material_owner 的报错。
    foreach ([null, false, '', [], ['marketplace' => []], ['marketplace' => ['recycle_material_owner' => null]]] as $index => $value) {
        $put($value);
        $expect($service->getMarketplaceChannel(), $direct, '无配置或空值 ' . $index);
    }

    foreach (['erp' => $direct, 'phone_shop' => $manual, 'unknown' => $direct] as $owner => $expected) {
        $put(['marketplace' => ['recycle_material_owner' => $owner]]);
        $expect($service->getMarketplaceChannel(), $expected, '已有负责人 ' . $owner);
    }

    $cases = [
        ['channel' => ['enabled' => 0], 'expected' => array_replace($direct, ['enabled' => 0])],
        ['channel' => ['publish_mode' => 'manual'], 'expected' => array_replace($direct, ['publish_mode' => 'manual'])],
        ['channel' => ['publish_mode' => 'basic_first'], 'expected' => array_replace($direct, ['publish_mode' => 'basic_first'])],
        ['channel' => ['category_mode' => 'independent'], 'expected' => array_replace($direct, ['category_mode' => 'independent'])],
        ['channel' => ['spec_mode' => 'independent'], 'expected' => array_replace($direct, ['spec_mode' => 'independent'])],
        ['channel' => ['category_mode' => null, 'spec_mode' => null, 'publish_mode' => null], 'expected' => $direct],
        ['channel' => ['category_mode' => 'bad', 'spec_mode' => 'bad', 'publish_mode' => 'bad'], 'expected' => $direct],
        ['channel' => ['enabled' => '0', 'category_mode' => 'independent', 'spec_mode' => 'erp', 'publish_mode' => 'manual'],
            'expected' => ['enabled' => 0, 'category_mode' => 'independent', 'spec_mode' => 'erp', 'publish_mode' => 'manual']],
    ];
    foreach ($cases as $index => $case) {
        $put(['marketplace' => ['channels' => ['phone_shop' => $case['channel']]]]);
        $beforeRead = CoreConfigService::$values;
        $expect($service->getMarketplaceChannel(), $case['expected'], '部分渠道配置 ' . $index);
        $rulesChannel = $service->getRules()['marketplace']['channels']['phone_shop'];
        foreach ($case['expected'] as $key => $value) {
            $assert($rulesChannel[$key] === $value, '完整规则和只读渠道接口结果一致 ' . $index . ': ' . $key);
        }
        $assert(CoreConfigService::$values === $beforeRead, '读取策略不能自动保存配置');
    }

    $put(['marketplace' => ['recycle_material_owner' => 'phone_shop', 'channels' => ['phone_shop' => $direct]]]);
    $expect($service->getMarketplaceChannel(), $direct, '显式新渠道策略优先于旧负责人');

    $put(['marketplace' => ['channels' => ['phone_shop' => $manual]]], 100006);
    $expect($service->getMarketplaceChannel('phone_shop', 100006), $manual, '显式站点读取');
    $expect($service->getMarketplaceChannel(), $direct, '显式读取别站不改变当前站点');

    $saved = $service->saveRules(['marketplace' => ['recycle_material_owner' => 'phone_shop']]);
    $assert($saved['marketplace']['channels']['phone_shop'] === $manual, '旧入口显式保存仍能切换运营模式');
    $expect($service->getMarketplaceChannel(), $manual, '保存后策略一致');

    $put([]);
    $policy = (new ListingMaterialPolicy())->handle(['site_id' => $siteId]);
    $assert($policy['owner'] === 'erp' && $policy['can_phone_shop_operate'] === 0,
        '无配置时商城资料策略接口仍返回默认策略，不报错或擅自改为商城上架');
    $put(['marketplace' => ['channels' => ['phone_shop' => ['publish_mode' => 'manual']]]]);
    $policy = (new ListingMaterialPolicy())->handle(['site_id' => $siteId]);
    $assert($policy['owner'] === 'phone_shop' && $policy['can_phone_shop_operate'] === 1,
        '手动上架配置允许商城运营处理');
    $put(['marketplace' => ['channels' => ['phone_shop' => ['publish_mode' => 'basic_first']]]]);
    $policy = (new ListingMaterialPolicy())->handle(['site_id' => $siteId]);
    $assert($policy['owner'] === 'phone_shop' && $policy['can_phone_shop_operate'] === 1 && $policy['basic_first'] === 1,
        '基础先上架的细节资料由商城运营承接');
    $put(['marketplace' => ['channels' => ['phone_shop' => ['enabled' => 0, 'publish_mode' => 'basic_first']]]]);
    $policy = (new ListingMaterialPolicy())->handle(['site_id' => $siteId]);
    $assert($policy['can_phone_shop_operate'] === 0 && $policy['enabled'] === 0, '渠道关闭不能继续新建并上架');

    restore_error_handler();
    echo "[PASS] marketplace channel config: {$count} assertions; no database or publication\n";
}
