<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\member;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\MemberEntitlementDisplayService;
use think\facade\Log;

/** Optional official-admin display extension. Never an entitlement grant or public member API. */
final class MemberAdminEntitlementDisplay
{
    public function handle($params = []): array
    {
        if (!is_array($params) || !is_array($params['member_ids'] ?? null))return [];
        $site = (int) ($params['site_id'] ?? 0);
        $uid = (int) request()->uid();
        if ($site <= 0 || $site !== (int) request()->siteId() || $uid <= 0 || !in_array(request()->appType(), ['site', 'admin'], true))return [];
        try {
            return (new MemberEntitlementDisplayService(new Context($site, $uid)))->display($params['member_ids']);
        } catch (\Throwable$error) {
            // Authentication/plugin-installation failure must not expose store
            // details or prevent unrelated member administration from opening.
            Log::warning('会员权益展示扩展未执行：site_id='.$site.'，error='.get_class($error));
            return [];
        }
    }
}
