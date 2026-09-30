<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

class PhoneShopElectronicSheetProviders
{
    public function handle($data = null): array
    {
        $siteId = (int) ($data['site_id'] ?? 0);
        if ($siteId <= 0 || !in_array('hsx_express', (new \app\service\core\site\CoreSiteService())->getAddonKeysBySiteId($siteId), true)) return ['providers' => []];
        $config = (new \addon\hsx_express\app\service\core\ConfigService())->get($siteId);
        return ['providers' => [[
            'key' => 'hsx_express_kuaidi100',
            'label' => '快递100（物流服务）',
            'handler' => PhoneShopElectronicSheetProvider::class,
            'config_url' => '/hsx_express/config',
            'tasks_url' => '/hsx_express/tasks',
            'carrier_code' => (string) $config['carrier'],
            'description' => '先取号、打印面单，实际交件后再确认发货；账号与模板统一在物流服务配置。',
        ]]];
    }
}
