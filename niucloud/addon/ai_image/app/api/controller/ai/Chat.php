<?php
/**
 * Created by addon888
 */

namespace addon\ai_image\app\api\controller\ai;

use addon\ai_image\app\service\core\ChatStreamService;
use core\base\BaseApiController;

class Chat extends BaseApiController
{
    public function sendText()
    {
        $data = $this->request->params([
            ['msg_id', time()],
            ['role', 'user'],
            ['prompt', '你是谁'],
            ['type', 'scene'],
            ["duration",15],
        ]);
        return success('成功', (new ChatStreamService())->sendText($data));
    }

}