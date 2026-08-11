<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\api\controller;

use addon\hsx_marketing\app\service\api\MarketingPortalService;
use core\base\BaseApiController;

final class Portal extends BaseApiController
{
    public function overview() { return success((new MarketingPortalService())->overview()); }
    public function tasks() { return success((new MarketingPortalService())->tasks($this->request->params([['page', 1], ['limit', 10]]))); }
    public function claimTask(int $id) { return success('任务领取成功', (new MarketingPortalService())->claimTask($id)); }
    public function rewards() { return success((new MarketingPortalService())->rewards($this->request->params([['status', ''], ['page', 1], ['limit', 10]]))); }
    public function claimReward(int $id) { return success('奖励领取成功', (new MarketingPortalService())->claimReward($id)); }
}
