<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\api\controller;

use addon\hsx_ai\app\service\api\AiProjectAssistantService;
use addon\hsx_ai\app\service\core\AiSpeechService;
use addon\hsx_ai\app\support\AiStreamResponse;
use core\base\BaseApiController;
use core\exception\CommonException;
use think\facade\Cache;
use think\Response;

final class ProjectAssistant extends BaseApiController
{
    public function capability(int $projectId): Response
    {
        return success((new AiProjectAssistantService())->capability($projectId, $this->groupNo()));
    }

    public function chat(int $projectId): Response
    {
        return success((new AiProjectAssistantService())->chat($projectId, $this->chatData()));
    }

    public function stream(int $projectId): Response
    {
        $data = $this->chatData();
        return new AiStreamResponse(function () use ($projectId, $data): void {
            $emit = static function (array $event): void {
                $type = preg_replace('/[^a-z_]/', '', (string)($event['type'] ?? 'message')) ?: 'message';
                echo 'event: ' . $type . "\n";
                echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                @ob_flush();
                flush();
            };
            echo ": connected\n:" . str_repeat(' ', 2048) . "\n\n";
            @ob_flush();
            flush();
            try {
                $result = (new AiProjectAssistantService())->stream($projectId, $data, $emit);
                unset($result['content'], $result['reasoning_content']);
                $emit(array_merge(['type' => 'done'], $result));
            } catch (\Throwable $e) {
                $emit(['type' => 'error', 'message' => $e->getMessage()]);
            }
        });
    }

    public function textToSpeech(int $projectId): Response
    {
        $capability = (new AiProjectAssistantService())->capability($projectId, $this->groupNo());
        if (empty($capability['available']) || empty($capability['voice']['tts'])) {
            throw new CommonException('当前项目暂未启用语音朗读');
        }
        $this->throttleSpeech();
        return success((new AiSpeechService())->textToSpeech(
            (int)$this->request->siteId(),
            trim((string)$this->request->param('text', ''))
        ));
    }

    private function chatData(): array
    {
        return $this->request->params([
            ['request_id', ''], ['conversation_id', 0], ['prompt', ''], ['messages', []], ['group_no', ''],
        ]);
    }

    private function groupNo(): string
    {
        return mb_substr(trim((string)$this->request->param('group_no', '')), 0, 30);
    }

    private function throttleSpeech(): void
    {
        $memberId = (int)$this->request->memberId();
        $identity = $memberId > 0 ? 'member_' . $memberId : 'ip_' . sha1((string)$this->request->ip());
        $key = 'hsx_ai:project_speech:' . (int)$this->request->siteId() . ':' . $identity . ':' . date('YmdHi');
        $count = (int)Cache::get($key, 0);
        if ($count >= 12) throw new CommonException('语音请求有点频繁，请稍后再试');
        Cache::set($key, $count + 1, 70);
    }
}
