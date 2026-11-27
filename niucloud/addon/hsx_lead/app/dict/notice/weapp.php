<?php
declare(strict_types=1);
use addon\hsx_lead\app\service\NoticeConfigService;
// Discovery includes pending scenes; delivery still requires a real site-owned mapping.
// The generic framework pending guard must reject retrieval before deleting any template.
return NoticeConfigService::weappDefinitionsForSite((int) request()->siteId())
    + \addon\hsx_lead\app\service\notice\AccountNoticePolicyService::mapping((int) request()->siteId(), 'weapp', true);
