<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\api\controller;

use addon\hsx_member_card\app\service\api\MemberCardPortalService;
use core\base\BaseApiController;

final class MemberPortal extends BaseApiController
{
    public function overview()
    {
        return success((new MemberCardPortalService())->overview());
    }

    public function cards()
    {
        return success((new MemberCardPortalService())->cards($this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ])));
    }

    public function orders()
    {
        return success((new MemberCardPortalService())->orders($this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ])));
    }

    public function redemptions()
    {
        return success((new MemberCardPortalService())->redemptions($this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ])));
    }

    public function cardInfo(int $id)
    {
        return success((new MemberCardPortalService())->cardInfo($id));
    }
}
