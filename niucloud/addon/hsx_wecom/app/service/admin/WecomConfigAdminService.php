<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\service\core\WecomClient;
use addon\hsx_wecom\app\service\core\WecomConfigService;
use core\base\BaseAdminService;

final class WecomConfigAdminService extends BaseAdminService
{
    public function getConfig(): array
    {
        return (new WecomConfigService())->get($this->site_id, true);
    }

    public function save(array $data): array
    {
        return (new WecomConfigService())->save($this->site_id, $data);
    }

    public function test(): array
    {
        return (new WecomClient())->test((new WecomConfigService())->get($this->site_id));
    }
}
