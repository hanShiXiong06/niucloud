<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\api;

use addon\hsx_ai\app\model\AiConversation;
use addon\hsx_ai\app\model\AiMessage;
use addon\hsx_ai\app\service\core\AiActionService;
use core\base\BaseApiService;
use core\exception\CommonException;

final class AiConversationService extends BaseApiService
{
    public function available(): bool
    {
        return (int)$this->member_id > 0;
    }

    public function getPage(array $where = []): array
    {
        $this->assertMember();
        $query = $this->ownedConversationQuery();
        if (!empty($where['status'])) $query->where('status', '=', (string)$where['status']);
        if (!empty($where['scene_key'])) $query->where('scene_key', '=', (string)$where['scene_key']);
        if (!empty($where['keyword'])) {
            $keyword = mb_substr(trim((string)$where['keyword']), 0, 60);
            $query->whereLike('title|summary|last_intent', '%' . $keyword . '%');
        }
        $page = $query->order('last_message_at desc,id desc')->paginate([
            'list_rows' => max(1, min(50, (int)($where['limit'] ?? 20))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        foreach ($page['data'] as &$row) $row = $this->conversationView($row);
        unset($row);
        return $page;
    }

    public function messages(int $conversationId, array $where = []): array
    {
        $conversation = $this->findOwned($conversationId);
        $page = AiMessage::where([
            ['site_id', '=', (int)$this->site_id],
            ['conversation_id', '=', (int)$conversation->id],
        ])->whereIn('role', ['user', 'assistant'])
            ->order('id desc')->paginate([
                'list_rows' => max(10, min(100, (int)($where['limit'] ?? 60))),
                'page' => max(1, (int)($where['page'] ?? 1)),
            ])->toArray();
        $rows = array_reverse((array)$page['data']);
        return [
            'conversation' => $this->conversationView($conversation->toArray()),
            'messages' => array_map(fn(array $row): array => $this->messageView($row), $rows),
            'pagination' => [
                'total' => (int)($page['total'] ?? 0),
                'current_page' => (int)($page['current_page'] ?? 1),
                'last_page' => (int)($page['last_page'] ?? 1),
                'has_more' => (int)($page['current_page'] ?? 1) < (int)($page['last_page'] ?? 1),
            ],
        ];
    }

    public function archive(int $conversationId): void
    {
        $conversation = $this->findOwned($conversationId);
        $conversation->save(['status' => 'archived', 'update_at' => time()]);
    }

    public function resolve(int $conversationId, string $prompt, string $sceneKey, string $entryPlugin): ?AiConversation
    {
        if (!$this->available()) return null;
        if ($conversationId > 0) {
            $conversation = $this->findOwned($conversationId);
            if ((string)$conversation->status !== 'active') throw new CommonException('该会话已归档，请新建会话后继续');
            return $conversation;
        }
        $now = time();
        return AiConversation::create([
            'site_id' => (int)$this->site_id,
            'conversation_no' => $this->makeConversationNo(),
            'actor_type' => 'member',
            'actor_id' => (int)$this->member_id,
            'entry_plugin' => mb_substr(trim($entryPlugin), 0, 60),
            'scene_key' => mb_substr(trim($sceneKey), 0, 100),
            'agent_key' => mb_substr(trim($sceneKey), 0, 100),
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

    public function history(?AiConversation $conversation, int $limit = 12): array
    {
        if (!$conversation) return [];
        $rows = AiMessage::where([
            ['site_id', '=', (int)$this->site_id],
            ['conversation_id', '=', (int)$conversation->id],
            ['status', '=', 'success'],
        ])->whereIn('role', ['user', 'assistant'])
            ->order('id desc')->limit(max(1, min(30, $limit)))->select()->toArray();
        $rows = array_reverse($rows);
        return array_values(array_map(static fn(array $row): array => [
            'role' => (string)$row['role'],
            'content' => mb_substr(trim((string)($row['content'] ?? '')), 0, 4000),
        ], array_filter($rows, static fn(array $row): bool => trim((string)($row['content'] ?? '')) !== '')));
    }

    public function saveUser(?AiConversation $conversation, string $requestId, string $content): ?AiMessage
    {
        return $this->saveMessage($conversation, [
            'request_id' => $requestId,
            'role' => 'user',
            'content_format' => 'text',
            'content' => mb_substr(trim($content), 0, 4000),
            'status' => 'success',
        ]);
    }

    public function saveAssistant(?AiConversation $conversation, string $requestId, array $result, array $resources = []): ?AiMessage
    {
        return $this->saveMessage($conversation, [
            'request_id' => $requestId,
            'role' => 'assistant',
            'content_format' => 'markdown',
            'content' => mb_substr(trim((string)($result['content'] ?? '')), 0, 20000),
            'blocks_json' => array_values((array)($result['blocks'] ?? [])),
            'resources_json' => array_values($resources),
            'actions_json' => (new AiActionService())->sanitize((int)$this->site_id, (array)($result['actions'] ?? [])),
            'provider_id' => mb_substr((string)($result['provider_id'] ?? ''), 0, 60),
            'model' => mb_substr((string)($result['model'] ?? ''), 0, 120),
            'status' => 'success',
            'prompt_tokens' => (int)($result['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($result['usage']['completion_tokens'] ?? 0),
            'total_tokens' => (int)($result['usage']['total_tokens'] ?? 0),
            'latency_ms' => (int)($result['latency_ms'] ?? 0),
        ]);
    }

    public function saveFailure(?AiConversation $conversation, string $requestId, string $message): void
    {
        $this->saveMessage($conversation, [
            'request_id' => $requestId,
            'role' => 'assistant',
            'content_format' => 'text',
            'content' => '',
            'status' => 'failed',
            'error_message' => mb_substr(trim($message), 0, 1000),
        ]);
    }

    public function rememberIntent(?AiConversation $conversation, string $intent): void
    {
        if (!$conversation) return;
        $intent = mb_substr(trim($intent), 0, 80);
        if ($intent === '' || (string)$conversation->last_intent === $intent) return;
        $conversation->save(['last_intent' => $intent, 'update_at' => time()]);
    }

    public function requestId(string $requestId = ''): string
    {
        $requestId = preg_replace('/[^A-Za-z0-9:_\-.]/', '', trim($requestId)) ?: '';
        return $requestId !== ''
            ? mb_substr($requestId, 0, 100)
            : 'AI' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
    }

    private function saveMessage(?AiConversation $conversation, array $data): ?AiMessage
    {
        if (!$conversation) return null;
        $requestId = (string)($data['request_id'] ?? '');
        $role = (string)($data['role'] ?? '');
        $existing = AiMessage::where([
            ['conversation_id', '=', (int)$conversation->id],
            ['request_id', '=', $requestId],
            ['role', '=', $role],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) {
            if ((string)$existing->status === 'failed' && (string)($data['status'] ?? '') === 'success') {
                $existing->save(array_merge($data, ['update_at' => time()]));
            }
            return $existing;
        }
        $now = time();
        $message = AiMessage::create(array_merge([
            'site_id' => (int)$this->site_id,
            'conversation_id' => (int)$conversation->id,
            'request_id' => $requestId,
            'role' => $role,
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
        $this->assertMember();
        $conversation = $this->ownedConversationQuery()->where('id', '=', $conversationId)->findOrEmpty();
        if ($conversation->isEmpty()) throw new CommonException('会话不存在或无权访问');
        return $conversation;
    }

    private function ownedConversationQuery()
    {
        return AiConversation::where([
            ['site_id', '=', (int)$this->site_id],
            ['actor_type', '=', 'member'],
            ['actor_id', '=', (int)$this->member_id],
        ]);
    }

    private function assertMember(): void
    {
        if (!$this->available()) throw new CommonException('请先登录后查看历史会话');
    }

    private function conversationView(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'conversation_no' => (string)($row['conversation_no'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'scene_key' => (string)($row['scene_key'] ?? ''),
            'message_count' => (int)($row['message_count'] ?? 0),
            'last_message_at' => (int)($row['last_message_at'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
        ];
    }

    private function messageView(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'request_id' => (string)($row['request_id'] ?? ''),
            'role' => (string)($row['role'] ?? ''),
            'content_format' => (string)($row['content_format'] ?? 'markdown'),
            'content' => (string)($row['content'] ?? ''),
            'blocks' => array_values((array)($row['blocks_json'] ?? [])),
            'resources' => array_values((array)($row['resources_json'] ?? [])),
            'actions' => array_values((array)($row['actions_json'] ?? [])),
            'status' => (string)($row['status'] ?? ''),
            'create_at' => (int)($row['create_at'] ?? 0),
        ];
    }

    private function title(string $prompt): string
    {
        $title = preg_replace('/\s+/u', ' ', trim($prompt)) ?: '新的咨询';
        return mb_substr($title, 0, 28) . (mb_strlen($title) > 28 ? '…' : '');
    }

    private function makeConversationNo(): string
    {
        return 'CV' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    }
}
