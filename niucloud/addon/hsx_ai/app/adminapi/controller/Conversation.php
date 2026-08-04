<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\adminapi\controller;

use addon\hsx_ai\app\service\admin\AiConversationAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Conversation extends BaseAdminController
{
    public function lists(): Response
    {
        $where = $this->request->params([
            ['keyword', ''], ['status', ''], ['scene_key', ''], ['risk_level', 0], ['page', 1], ['limit', 15],
        ]);
        return success((new AiConversationAdminService())->getPage($where));
    }

    public function detail(int $id): Response
    {
        return success((new AiConversationAdminService())->detail($id));
    }
}
