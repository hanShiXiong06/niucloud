<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\api\controller;

use addon\hsx_ai\app\service\api\AiMallAssistantService;
use addon\hsx_ai\app\service\api\AiConversationService;
use addon\hsx_ai\app\service\core\AiSpeechService;
use addon\hsx_ai\app\support\AiStreamResponse;
use core\base\BaseApiController;
use core\exception\CommonException;
use think\Response;
use think\facade\Cache;

final class Assistant extends BaseApiController
{
    public function capability(): Response
    {
        return success((new AiMallAssistantService())->capability());
    }

    public function chat(): Response
    {
        return success((new AiMallAssistantService())->chat($this->chatData()));
    }

    public function conversations(): Response
    {
        $where = $this->request->params([
            ['keyword', ''],
            ['status', 'active'],
            ['scene_key', AiMallAssistantService::SCENE],
            ['page', 1],
            ['limit', 20],
        ]);
        return success((new AiConversationService())->getPage($where));
    }

    public function messages(int $id): Response
    {
        $where = $this->request->params([['page', 1], ['limit', 60]]);
        return success((new AiConversationService())->messages($id, $where));
    }

    public function archive(int $id): Response
    {
        (new AiConversationService())->archive($id);
        return success('会话已归档');
    }

    public function stream(): Response
    {
        $data = $this->chatData();
        return new AiStreamResponse(function () use ($data): void {
            $emit = static function (array $event): void {
                $type = preg_replace('/[^a-z_]/', '', (string)($event['type'] ?? 'message')) ?: 'message';
                echo 'event: ' . $type . "\n";
                echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                @ob_flush();
                flush();
            };
            echo ": connected\n";
            echo ':' . str_repeat(' ', 2048) . "\n\n";
            @ob_flush();
            flush();
            try {
                $result = (new AiMallAssistantService())->stream($data, $emit);
                unset($result['content'], $result['reasoning_content']);
                $emit(array_merge(['type' => 'done'], $result));
            } catch (\Throwable $e) {
                $emit(['type' => 'error', 'message' => $e->getMessage()]);
            }
        });
    }

    public function speechToText(): Response
    {
        $this->assertSpeechCapability('stt');
        $this->throttleSpeech();
        $file = $this->request->file('audio');
        if (!$file) throw new CommonException('请上传语音文件');
        return success((new AiSpeechService())->speechToText((int)$this->request->siteId(), $file));
    }

    public function textToSpeech(): Response
    {
        $this->assertSpeechCapability('tts');
        $this->throttleSpeech();
        return success((new AiSpeechService())->textToSpeech(
            (int)$this->request->siteId(),
            trim((string)$this->request->param('text', ''))
        ));
    }

    private function chatData(): array
    {
        return $this->request->params([
            ['request_id', ''],
            ['conversation_id', 0],
            ['prompt', ''],
            ['messages', []],
        ]);
    }

    private function assertSpeechCapability(string $capability): void
    {
        $assistant = (new AiMallAssistantService())->capability();
        if (empty($assistant['available']) || empty($assistant['voice'][$capability])) {
            throw new CommonException('本站暂未启用该语音能力');
        }
    }

    private function throttleSpeech(): void
    {
        $memberId = (int)$this->request->memberId();
        $identity = $memberId > 0 ? 'member_' . $memberId : 'ip_' . sha1((string)$this->request->ip());
        $key = 'hsx_ai:speech:' . (int)$this->request->siteId() . ':' . $identity . ':' . date('YmdHi');
        $count = (int)Cache::get($key, 0);
        if ($count >= 20) throw new CommonException('语音请求有点频繁，请稍后再试');
        Cache::set($key, $count + 1, 70);
    }
}
