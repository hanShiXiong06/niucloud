<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\admin;

use addon\hsx_ai\app\model\AiConversation;
use addon\hsx_ai\app\model\AiMessage;
use addon\hsx_ai\app\service\core\AiToolCallProtocolService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 管理端经营助手会话记忆，只允许当前管理员访问自己的会话。 */
final class AiAdminAssistantConversationService extends BaseAdminService
{
    public const SCENE = 'business.admin_assistant';

    public function prepare(array $data): array
    {
        $prompt = mb_substr(trim((string)($data['prompt'] ?? '')), 0, 2000);
        if ($prompt === '') throw new CommonException('请输入要查询的经营问题');
        $requestId = $this->requestId((string)($data['request_id'] ?? ''));
        $agentKey = mb_substr(trim((string)($data['agent_key'] ?? '')), 0, 100);
        if ($agentKey === '') throw new CommonException('请选择经营助手');
        $conversation = $this->resolve((int)($data['conversation_id'] ?? 0), $prompt, $agentKey);
        $userMessage = $this->saveMessage($conversation, [
            'request_id' => $requestId,
            'role' => 'user',
            'content_format' => 'text',
            'content' => $prompt,
            'status' => 'success',
        ]);
        $data['request_id'] = $requestId;
        $data['prompt'] = '';
        $data['messages'] = $this->history($conversation);
        return [
            'request' => $data,
            'prompt' => $prompt,
            'request_id' => $requestId,
            'conversation' => $conversation,
            'message_id' => (int)$userMessage->id,
        ];
    }

    public function saveAssistant(array $prepared, array $result): AiMessage
    {
        $content = (new AiToolCallProtocolService())->sanitize((string)($result['content'] ?? ''));
        $blocks = array_values((array)($result['blocks'] ?? []));
        foreach ((array)($result['tool_results'] ?? []) as $toolResult) {
            if (!is_array($toolResult)) continue;
            $blocks[] = ['type' => 'tool_result', 'data' => $toolResult];
        }
        return $this->saveMessage($prepared['conversation'], [
            'request_id' => (string)$prepared['request_id'],
            'role' => 'assistant',
            'content_format' => 'markdown',
            'content' => mb_substr(trim($content), 0, 20000),
            'blocks_json' => $blocks,
            'provider_id' => mb_substr((string)($result['provider_id'] ?? ''), 0, 60),
            'model' => mb_substr((string)($result['model'] ?? ''), 0, 120),
            'status' => 'success',
            'prompt_tokens' => (int)($result['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($result['usage']['completion_tokens'] ?? 0),
            'total_tokens' => (int)($result['usage']['total_tokens'] ?? 0),
            'latency_ms' => (int)($result['latency_ms'] ?? 0),
        ]);
    }

    public function saveFailure(array $prepared, string $error): void
    {
        $this->saveMessage($prepared['conversation'], [
            'request_id' => (string)$prepared['request_id'],
            'role' => 'assistant',
            'content_format' => 'text',
            'content' => '',
            'status' => 'failed',
            'error_message' => mb_substr(trim($error), 0, 1000),
        ]);
    }

    public function resultMeta(array $prepared, int $messageId = 0): array
    {
        return [
            'conversation_id' => (int)$prepared['conversation']->id,
            'message_id' => $messageId,
            'conversation_title' => (string)$prepared['conversation']->title,
            'agent_key' => (string)$prepared['conversation']->agent_key,
        ];
    }

    public function detail(int $conversationId): array
    {
        $conversation = $this->findOwned($conversationId);
        $messages = AiMessage::where([
            ['site_id', '=', (int)$this->site_id],
            ['conversation_id', '=', (int)$conversation->id],
        ])->whereIn('role', ['user', 'assistant'])->order('id asc')->limit(100)->select()->toArray();
        return [
            'conversation' => [
                'id' => (int)$conversation->id,
                'title' => (string)$conversation->title,
                'agent_key' => (string)$conversation->agent_key,
                'message_count' => (int)$conversation->message_count,
                'last_message_at' => (int)$conversation->last_message_at,
            ],
            'messages' => array_map(fn(array $row): array => $this->messageView($row), $messages),
        ];
    }

    /** 当前管理员在指定智能体下最近使用的会话。 */
    public function recent(string $agentKey, int $limit = 20): array
    {
        $agentKey = mb_substr(trim($agentKey), 0, 100);
        if ($agentKey === '') return [];
        $rows = AiConversation::where([
            ['site_id', '=', (int)$this->site_id],
            ['actor_type', '=', 'admin'],
            ['actor_id', '=', (int)$this->uid],
            ['scene_key', '=', self::SCENE],
            ['agent_key', '=', $agentKey],
            ['status', '=', 'active'],
        ])->field('id,title,agent_key,message_count,last_message_at,status,create_at,update_at')
            ->order('last_message_at desc,id desc')
            ->limit(max(1, min(50, $limit)))
            ->select()->toArray();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['message_count'] = (int)$row['message_count'];
            $row['last_message_at'] = (int)$row['last_message_at'];
            $row['create_at'] = (int)$row['create_at'];
            $row['update_at'] = (int)$row['update_at'];
        }
        unset($row);
        return $rows;
    }

    /** 删除当前管理员自己的会话正文；调用和工具审计日志按合规要求保留。 */
    public function delete(int $conversationId): bool
    {
        $conversation = $this->findOwned($conversationId);
        Db::transaction(function () use ($conversation): void {
            AiMessage::where([
                ['site_id', '=', (int)$this->site_id],
                ['conversation_id', '=', (int)$conversation->id],
            ])->delete();
            $conversation->delete();
        });
        return true;
    }

    private function resolve(int $conversationId, string $prompt, string $agentKey): AiConversation
    {
        if ($conversationId > 0) {
            $conversation = $this->findOwned($conversationId);
            if ((string)$conversation->status !== 'active') throw new CommonException('该会话已结束，请新建对话');
            if ((string)$conversation->agent_key !== $agentKey) throw new CommonException('切换智能体后请新建对话');
            return $conversation;
        }
        $now = time();
        return AiConversation::create([
            'site_id' => (int)$this->site_id,
            'conversation_no' => 'AC' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12)),
            'actor_type' => 'admin',
            'actor_id' => (int)$this->uid,
            'entry_plugin' => 'hsx_ai',
            'scene_key' => self::SCENE,
            'agent_key' => $agentKey,
            'title' => $this->title($prompt),
            'summary' => '',
            'last_intent' => '',
            'message_count' => 0,
            'last_message_at' => $now,
            'risk_level' => 0,
            'status' => 'active',
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function history(AiConversation $conversation): array
    {
        $rows = AiMessage::where([
            ['site_id', '=', (int)$this->site_id],
            ['conversation_id', '=', (int)$conversation->id],
            ['status', '=', 'success'],
        ])->whereIn('role', ['user', 'assistant'])->order('id desc')->limit(12)->select()->toArray();
        $messages = [];
        foreach (array_reverse($rows) as $row) {
            $content = mb_substr(trim((string)($row['content'] ?? '')), 0, 6000);
            if ((string)$row['role'] === 'assistant') {
                $content = (new AiToolCallProtocolService())->sanitize($content);
            }
            if ((string)$row['role'] === 'assistant') {
                $snapshots = [];
                foreach ((array)($row['blocks_json'] ?? []) as $block) {
                    if (is_array($block) && (string)($block['type'] ?? '') === 'tool_result') {
                        $snapshots[] = (array)($block['data'] ?? []);
                    }
                }
                if ($snapshots !== []) {
                    $json = json_encode($snapshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (is_string($json)) $content .= "\n\n[上一轮已授权业务查询快照]\n" . mb_substr($json, 0, 12000);
                }
            }
            if ($content !== '') $messages[] = ['role' => (string)$row['role'], 'content' => $content];
        }
        return $messages;
    }

    private function saveMessage(AiConversation $conversation, array $data): AiMessage
    {
        $existing = AiMessage::where([
            ['conversation_id', '=', (int)$conversation->id],
            ['request_id', '=', (string)($data['request_id'] ?? '')],
            ['role', '=', (string)($data['role'] ?? '')],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) return $existing;
        $now = time();
        $message = AiMessage::create(array_merge([
            'site_id' => (int)$this->site_id,
            'conversation_id' => (int)$conversation->id,
            'request_id' => '',
            'role' => '',
            'content_format' => 'markdown',
            'content' => '',
            'blocks_json' => [],
            'resources_json' => [],
            'actions_json' => [],
            'provider_id' => '',
            'model' => '',
            'status' => 'success',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'latency_ms' => 0,
            'error_message' => '',
            'create_at' => $now,
            'update_at' => $now,
        ], $data));
        $conversation->save([
            'message_count' => (int)$conversation->message_count + 1,
            'last_message_at' => $now,
            'update_at' => $now,
        ]);
        return $message;
    }

    private function findOwned(int $conversationId): AiConversation
    {
        $conversation = AiConversation::where([
            ['site_id', '=', (int)$this->site_id],
            ['actor_type', '=', 'admin'],
            ['actor_id', '=', (int)$this->uid],
            ['scene_key', '=', self::SCENE],
            ['id', '=', $conversationId],
        ])->findOrEmpty();
        if ($conversation->isEmpty()) throw new CommonException('会话不存在或无权访问');
        return $conversation;
    }

    private function messageView(array $row): array
    {
        $content = (string)($row['content'] ?? '');
        if ((string)($row['role'] ?? '') === 'assistant') {
            $content = (new AiToolCallProtocolService())->sanitize($content);
        }
        $toolResults = [];
        $blocks = [];
        foreach ((array)($row['blocks_json'] ?? []) as $block) {
            if (is_array($block) && (string)($block['type'] ?? '') === 'tool_result') {
                $toolResults[] = (array)($block['data'] ?? []);
            } elseif (is_array($block)) {
                $blocks[] = $block;
            }
        }
        return [
            'id' => (int)($row['id'] ?? 0),
            'request_id' => (string)($row['request_id'] ?? ''),
            'role' => (string)($row['role'] ?? ''),
            'content' => $content,
            'tool_results' => $toolResults,
            'blocks' => $blocks,
            'provider_id' => (string)($row['provider_id'] ?? ''),
            'model' => (string)($row['model'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'error' => (string)($row['error_message'] ?? ''),
            'latency_ms' => (int)($row['latency_ms'] ?? 0),
            'total_tokens' => (int)($row['total_tokens'] ?? 0),
            'create_at' => (int)($row['create_at'] ?? 0),
        ];
    }

    private function requestId(string $requestId): string
    {
        $requestId = preg_replace('/[^A-Za-z0-9:_\-.]/', '', trim($requestId)) ?: '';
        return $requestId !== '' ? mb_substr($requestId, 0, 100)
            : 'AIA' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
    }

    private function title(string $prompt): string
    {
        $title = preg_replace('/\s+/u', ' ', trim($prompt)) ?: '经营查询';
        return mb_substr($title, 0, 28) . (mb_strlen($title) > 28 ? '…' : '');
    }
}
