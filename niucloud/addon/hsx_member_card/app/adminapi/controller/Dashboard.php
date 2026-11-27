<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardDashboardService;
use core\base\BaseAdminController;
final class Dashboard extends BaseAdminController
{
    public function index() { return success((new MemberCardDashboardService())->overview($this->request->params([['start_at', 0], ['end_at', 0]]))); }
}
