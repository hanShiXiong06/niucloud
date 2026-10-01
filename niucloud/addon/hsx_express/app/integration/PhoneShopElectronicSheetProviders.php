<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

class PhoneShopElectronicSheetProviders
{
    public function handle($data = null): array
    {
        $siteId = (int) ($data['site_id'] ?? 0);
        if ($siteId <= 0 || !in_array('hsx_express', (new \app\service\core\site\CoreSiteService())->getAddonKeysBySiteId($siteId), true)) return ['providers' => []];
        // Registration needs only the carrier code, not credentials. A damaged account in one
        // channel must not prevent listing or operating another independent channel.
        $config = (new \app\service\core\sys\CoreConfigService())->getConfigValue($siteId, \addon\hsx_express\app\service\core\ConfigService::KEY);
        return ['providers' => [[
            'key' => 'hsx_express_kuaidi100',
            'label' => '快递100（物流服务）',
            'handler' => PhoneShopElectronicSheetProvider::class,
            'config_url' => '/hsx_express/config',
            'tasks_url' => '/hsx_express/tasks',
            'carrier_code' => is_array($config) && is_scalar($config['carrier'] ?? null) ? (string)$config['carrier'] : '',
            'description' => '先取号、打印面单，实际交件后再确认发货；账号与模板统一在物流服务配置。',
        ], [
            'key' => 'hsx_express_sf_direct',
            'label' => '顺丰直连（PDF 本地打印）',
            'handler' => PhoneShopSfElectronicSheetProvider::class,
            'config_url' => '/hsx_express/config?provider=sf_direct&scene=waybill',
            'tasks_url' => '/hsx_express/tasks',
            'carrier_code' => 'shunfeng',
            'carrier_mapping' => ['field' => 'express_no', 'code' => 'SF'],
            'description' => '使用本站独立顺丰账号取号、下载 PDF；实际交件后再确认发货，不替换已有物流渠道。',
        ]]];
    }
}
