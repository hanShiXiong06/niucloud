<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\service\core\WecomEntryService;
use app\service\core\site\CoreSiteService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class WecomEntryAdminService extends BaseAdminService
{
    public function resolve(string $ticket): array
    {
        if ((int)$this->uid <= 0) throw new CommonException('请先登录后台管理端');

        $payload = (new WecomEntryService())->verifyTicket($ticket);
        $siteId = (int)$payload['site_id'];
        if ($siteId !== (int)$this->site_id) {
            throw new CommonException('通知所属站点与当前站点不一致，请重新打开通知');
        }

        // AdminCheckToken 已在进入服务前校验当前员工对请求头站点的管理权限；
        // 此处再次确认站点真实存在，避免返回一个不可落地的目标。
        $site = (new CoreSiteService())->getSiteCache($siteId);
        if (empty($site)) throw new CommonException('通知所属站点不存在或已失效');

        return [
            'site_id' => $siteId,
            'site_name' => (string)($site['site_name'] ?? ''),
            'miniapp_path' => (string)$payload['miniapp_path'],
            'route_key' => (string)$payload['route_key'],
            'expires_at' => (int)$payload['exp'],
        ];
    }
}
