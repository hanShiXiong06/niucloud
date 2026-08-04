<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\api;

use addon\hsx_ai\app\model\AiRiskEvent;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Cache;

final class AiRiskService extends BaseApiService
{
    public function assertAvailable(): void
    {
        $until = (int)Cache::get($this->freezeKey(), 0);
        if ($until <= time()) return;
        throw new CommonException('AI 咨询已临时暂停，请稍后再试');
    }

    public function inspectPrompt(string $prompt, int $conversationId = 0): void
    {
        $patterns = [
            'override_instruction' => '/(?:ignore|忽略|无视).{0,20}(?:previous|above|之前|上面|系统).{0,12}(?:instruction|prompt|指令|提示)/iu',
            'system_prompt_probe' => '/(?:system\s*prompt|系统提示词|开发者指令|隐藏指令|完整提示词)/iu',
            'credential_probe' => '/(?:api\s*key|secret\s*key|数据库密码|管理员密码|服务器密钥).{0,16}(?:给我|显示|输出|泄露|是什么)/iu',
            'role_escalation' => '/(?:绕过|解除|跳过).{0,16}(?:权限|安全|限制|审核|风控)/iu',
        ];
        foreach ($patterns as $type => $pattern) {
            if (!preg_match($pattern, $prompt)) continue;
            $count = $this->increment('prompt_attack', 3600);
            $freezeSeconds = $count >= 3 ? 900 : 0;
            $this->record($type, $count >= 3 ? 2 : 1, $conversationId, [
                'prompt_hash' => hash('sha256', $prompt),
                'rule' => $type,
                'hour_count' => $count,
            ], $freezeSeconds > 0 ? 'freeze_ai' : 'reject');
            if ($freezeSeconds > 0) Cache::set($this->freezeKey(), time() + $freezeSeconds, $freezeSeconds);
            throw new CommonException($freezeSeconds > 0 ? 'AI 咨询已临时暂停，请稍后再试' : '这项请求不属于本站业务咨询范围');
        }
    }

    public function throttle(int $limit = 12): void
    {
        $this->assertAvailable();
        $key = 'hsx_ai:rate:' . (int)$this->site_id . ':' . $this->identity() . ':' . date('YmdHi');
        $count = (int)Cache::get($key, 0) + 1;
        Cache::set($key, $count, 70);
        if ($count <= $limit) return;
        $freezeSeconds = 300;
        Cache::set($this->freezeKey(), time() + $freezeSeconds, $freezeSeconds);
        $this->record('rate_limit', 2, 0, ['minute_count' => $count, 'limit' => $limit], 'freeze_ai', $freezeSeconds);
        throw new CommonException('提问有点频繁，AI 咨询已暂停 5 分钟');
    }

    private function record(string $type, int $level, int $conversationId, array $evidence, string $action, int $freezeSeconds = 0): void
    {
        try {
            $now = time();
            AiRiskEvent::create([
                'site_id' => (int)$this->site_id,
                'conversation_id' => $conversationId,
                'actor_type' => (int)$this->member_id > 0 ? 'member' : 'guest',
                'actor_id' => max(0, (int)$this->member_id),
                'ip_hash' => hash('sha256', (string)$this->request->ip()),
                'device_hash' => '',
                'risk_type' => $type,
                'risk_level' => $level,
                'risk_score' => $level === 1 ? 35 : 70,
                'evidence_json' => $evidence,
                'action' => $action,
                'status' => 'open',
                'frozen_until' => $freezeSeconds > 0 ? $now + $freezeSeconds : 0,
                'create_at' => $now,
                'update_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // 风险日志故障不能放开已识别的危险请求。
        }
    }

    private function increment(string $bucket, int $ttl): int
    {
        $key = 'hsx_ai:risk:' . (int)$this->site_id . ':' . $this->identity() . ':' . $bucket;
        $count = (int)Cache::get($key, 0) + 1;
        Cache::set($key, $count, $ttl);
        return $count;
    }

    private function freezeKey(): string
    {
        return 'hsx_ai:freeze:' . (int)$this->site_id . ':' . $this->identity();
    }

    private function identity(): string
    {
        return (int)$this->member_id > 0
            ? 'member_' . (int)$this->member_id
            : 'ip_' . hash('sha256', (string)$this->request->ip());
    }
}
