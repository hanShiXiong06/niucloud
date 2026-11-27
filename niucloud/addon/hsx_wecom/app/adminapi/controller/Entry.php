<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\adminapi\controller;

use addon\hsx_wecom\app\service\admin\WecomEntryAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Entry extends BaseAdminController
{
    public function resolve(): Response
    {
        $ticket = trim((string)$this->request->param('ticket', ''));
        return success((new WecomEntryAdminService())->resolve($ticket));
    }
}
