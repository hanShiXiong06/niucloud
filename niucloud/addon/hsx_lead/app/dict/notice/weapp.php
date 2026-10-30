<?php
declare(strict_types=1);
use addon\hsx_lead\app\service\NoticeConfigService;
// Do not expose a fake "retrieve template" button before a verified site mapping exists.
return NoticeConfigService::weappForSite((int)request()->siteId());

