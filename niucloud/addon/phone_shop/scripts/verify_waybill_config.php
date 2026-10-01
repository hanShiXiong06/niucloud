<?php
declare(strict_types=1);
// Configuration-only offline test. No framework boot, database, credentials or HTTP.
namespace core\base { class BaseCoreService {} }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $rows = [];
        public function getConfig($site, $key): array { return self::$rows[$site][$key] ?? []; }
        public function setConfig($site, $key, $value): bool { self::$rows[$site][$key] = ['value' => $value]; return true; }
    }
}
namespace addon\phone_shop\app\service\core\delivery\electronic_sheet {
    class ElectronicSheetProviderRegistry {
        public function options(int $site): array { return [['key' => 'kdbird'], ['key' => 'hsx_express_sf_direct']]; }
    }
}
namespace {
    require __DIR__ . '/../app/service/core/delivery/CoreElectronicSheetService.php';
    require __DIR__ . '/../app/service/core/delivery/CoreConfigService.php';
    use app\service\core\sys\CoreConfigService as Store;
    use addon\phone_shop\app\service\core\delivery\CoreElectronicSheetService as Config;
    use addon\phone_shop\app\service\core\delivery\CoreConfigService as LegacyLoaderConfig;
    $checks = 0;
    function check($value, string $message): void { global $checks; if (!$value) throw new \RuntimeException($message); $checks++; }
    $legacy = ['interface_type' => 'kdbird', 'kdniao_id' => 'fixture-only'];
    Store::$rows[1]['ELECTRONIC_SHEET_CONFIG'] = ['value' => $legacy];
    $config = new Config();
    check($config->getElectronicSheetConfig(1)['interface_type'] === 'kdbird', 'Existing sites retain their original KDBird selection');
    check((new LegacyLoaderConfig())->getDeliveryElectronSheeticConfig(1) === $legacy, 'Original KDBird loader reads legacy setting until explicit save');
    $config->setElectronicSheetConfig(1, ['interface_type' => 'hsx_express_sf_direct']);
    check(Store::$rows[1]['ELECTRONIC_SHEET_CONFIG']['value'] === $legacy, 'Saving phone_shop does not overwrite shared shop configuration');
    check($config->getElectronicSheetConfig(1)['interface_type'] === 'hsx_express_sf_direct', 'phone_shop reads its isolated selection');
    check((new LegacyLoaderConfig())->getDeliveryElectronSheeticConfig(1)['interface_type'] === 'hsx_express_sf_direct', 'Both phone_shop config readers agree');
    check($config->getElectronicSheetConfig(2)['interface_type'] === 'kdbird', 'Another site remains unchanged');
    $config->setElectronicSheetConfig(1, ['interface_type' => 'kdbird', 'kdniao_id' => 'new-fixture']);
    check((new LegacyLoaderConfig())->getDeliveryElectronSheeticConfig(1)['kdniao_id'] === 'new-fixture', 'Switching back to KDBird uses explicit plugin config');
    check(Store::$rows[1]['ELECTRONIC_SHEET_CONFIG']['value'] === $legacy, 'No legacy data migration or rewrite');
    echo "PASS: {$checks} config isolation checks; no database or HTTP.\n";
}
