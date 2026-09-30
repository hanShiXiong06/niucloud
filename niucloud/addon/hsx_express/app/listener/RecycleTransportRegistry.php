<?php
declare(strict_types=1);

namespace addon\hsx_express\app\listener;

use addon\hsx_express\app\integration\RecycleKuaidi100Transport;
use app\service\core\site\CoreSiteService;

/** 只登记公共传输能力，不加载回收业务类，也不发起网络请求。 */
final class RecycleTransportRegistry
{
    public function handle(array $context = []): array
    {
        if ((int)($context['site_id'] ?? 0) <= 0 || ($context['environment'] ?? '') !== 'production') {
            return [];
        }
        if (!in_array('hsx_express', (new CoreSiteService())->getAddonKeysBySiteId((int)$context['site_id']), true)) {
            return [];
        }
        return ['transports' => ['kuaidi100' => RecycleKuaidi100Transport::class]];
    }
}
