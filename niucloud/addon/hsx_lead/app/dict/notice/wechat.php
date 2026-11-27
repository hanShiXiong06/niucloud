<?php
declare(strict_types=1);
use addon\hsx_lead\app\service\NoticeConfigService;
return NoticeConfigService::wechatForSite((int) request()->siteId())
    + \addon\hsx_lead\app\service\notice\AccountNoticePolicyService::mapping((int) request()->siteId(), 'wechat');
