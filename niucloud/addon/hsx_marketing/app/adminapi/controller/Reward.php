<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\adminapi\controller;

use addon\hsx_marketing\app\service\admin\MarketingRewardAdminService;
use core\base\BaseAdminController;

final class Reward extends BaseAdminController
{
    public function lists() { return success((new MarketingRewardAdminService())->page($this->request->params([['status', ''], ['keyword', ''], ['page', 1], ['limit', 15]]))); }
    public function retry(int $id) { return success('已重新发放', (new MarketingRewardAdminService())->retry($id)); }
}
