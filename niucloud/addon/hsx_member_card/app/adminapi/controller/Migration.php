<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\adminapi\controller;

use addon\hsx_member_card\app\service\admin\MemberCardMigrationService;
use core\base\BaseAdminController;

final class Migration extends BaseAdminController
{
    public function start()
    {
        return success((new MemberCardMigrationService())->start($this->request->params([
            ['phone', ''],
            ['password', ''],
        ])));
    }

    public function lists()
    {
        return success((new MemberCardMigrationService())->lists($this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 15],
        ])));
    }

    public function info(int $id)
    {
        return success((new MemberCardMigrationService())->info($id));
    }

    public function items(int $id)
    {
        return success((new MemberCardMigrationService())->items($id, $this->request->params([
            ['entity_type', ''],
            ['status', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 20],
        ])));
    }
}
