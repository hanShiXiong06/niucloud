<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\admin;

use addon\hsx_ai\app\model\AiConversation;
use addon\hsx_ai\app\model\AiDemand;
use addon\hsx_ai\app\model\AiMessage;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class AiConversationAdminService extends BaseAdminService
{
    public function getPage(array $where): array
    {
        $memberTable = (new Member())->getTable();
        $query = AiConversation::alias('c')
            ->leftJoin($memberTable . ' m', "c.actor_type = 'member' AND m.member_id = c.actor_id AND m.site_id = c.site_id")
            ->where('c.site_id', '=', $this->site_id);
        if (!empty($where['status'])) $query->where('c.status', '=', (string)$where['status']);
        if (!empty($where['scene_key'])) $query->where('c.scene_key', '=', (string)$where['scene_key']);
        if (!empty($where['risk_level'])) $query->where('c.risk_level', '>=', (int)$where['risk_level']);
        if (!empty($where['keyword'])) {
            $keyword = mb_substr(trim((string)$where['keyword']), 0, 60);
            $query->whereLike('c.conversation_no|c.title|c.summary|c.last_intent|m.nickname|m.mobile', '%' . $keyword . '%');
        }
        return $query->field([
            'c.id', 'c.conversation_no', 'c.actor_type', 'c.actor_id', 'c.entry_plugin', 'c.scene_key',
            'c.agent_key', 'c.title', 'c.summary', 'c.last_intent', 'c.message_count', 'c.last_message_at',
            'c.risk_level', 'c.status', 'c.create_at', 'c.update_at', 'm.nickname', 'm.mobile', 'm.headimg',
        ])->order('c.last_message_at desc,c.id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }

    public function detail(int $id): array
    {
        $conversation = AiConversation::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($conversation->isEmpty()) throw new CommonException('会话不存在');
        $messages = AiMessage::where([
            ['site_id', '=', $this->site_id],
            ['conversation_id', '=', $id],
        ])->whereIn('role', ['user', 'assistant'])->order('id asc')->select()->toArray();
        $demands = AiDemand::where([
            ['site_id', '=', $this->site_id],
            ['conversation_id', '=', $id],
        ])->order('id desc')->select()->toArray();
        foreach ($messages as &$message) {
            $message['blocks'] = (array)($message['blocks_json'] ?? []);
            $message['resources'] = (array)($message['resources_json'] ?? []);
            $message['actions'] = (array)($message['actions_json'] ?? []);
            unset($message['blocks_json'], $message['resources_json'], $message['actions_json']);
        }
        unset($message);
        return ['conversation' => $conversation->toArray(), 'messages' => $messages, 'demands' => $demands];
    }
}
