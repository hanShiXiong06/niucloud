<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model\ai;

use core\base\BaseModel;

/**
 * AI 对话记录
 */
class HsxAiConversation extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'hsx_ai_conversation';
    protected $autoWriteTimestamp = false;

    // messages 以 JSON 存储，读出为数组
    protected $json = ['messages'];
    protected $jsonAssoc = true;
}
