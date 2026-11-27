<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\listener;

use app\service\core\addon\CoreAddonService;
use app\service\core\site\CoreSiteService;

/** Plugin defaults only; CoreDiyConfigService owns each site's saved navigation. */
final class BottomNavigationListener
{
    public function handle($params = []): ?array
    {
        $params = is_array($params) ? $params : [];
        if (isset($params['key']) && $params['key'] !== '' && $params['key'] !== 'hsx_lead') return null;
        $site = (int) request()->siteId();
        if ($site <= 0 || !in_array('hsx_lead', (new CoreSiteService())->getAddonKeysBySiteId($site), true)) return null;
        return ['key' => 'hsx_lead', 'info' => (new CoreAddonService())->getAddonConfig('hsx_lead'), 'value' => self::defaults()];
    }

    public static function defaults(): array
    {
        $links = require dirname(__DIR__).'/dict/diy/links.php';
        $items = [
            'HSX_LEAD_MERCHANT_HOME' => ['首页', 'home'],
            'HSX_LEAD_MERCHANT_WORKBENCH' => ['找客户', 'deliveries'],
            'HSX_LEAD_MERCHANT_STORES' => ['找门店', 'stores'],
            'HSX_LEAD_MERCHANT_WALLET' => ['收益', 'wallet'],
            'HSX_LEAD_MERCHANT_PROFILE' => ['我的', 'profile'],
        ];
        $list = [];
        foreach ($links['HSX_LEAD_MERCHANT_LINK']['child_list'] as $link) {
            if (!isset($items[$link['name']])) continue;
            [$text,$icon] = $items[$link['name']];
            $list[] = ['text' => $text, 'link' => [
                'parent' => 'HSX_LEAD_MERCHANT_LINK', 'name' => $link['name'], 'title' => $link['title'], 'url' => $link['url'],
            ], 'iconPath' => 'addon/hsx_lead/diy/tabbar/'.$icon.'.png', 'iconSelectPath' => 'addon/hsx_lead/diy/tabbar/'.$icon.'-selected.png'];
        }
        return ['backgroundColor' => '#ffffff', 'textColor' => '#737e8c', 'textHoverColor' => '#3478cc', 'type' => '1', 'list' => $list];
    }
}
