<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\adminapi\controller;

use addon\hsx_ai\app\service\admin\AiConfigAdminService;
use addon\hsx_ai\app\support\AiStreamResponse;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\Response;

final class Config extends BaseAdminController
{
    public function modelInfo(): Response
    {
        return success((new AiConfigAdminService())->section('model'));
    }

    public function saveModel(): Response
    {
        return success((new AiConfigAdminService())->saveSection('model', $this->request->params([
            ['enabled', 0], ['default_provider_id', ''], ['default_model', ''],
            ['redact_sensitive', 1], ['log_content', 0], ['providers', []], ['scenes', []],
        ])));
    }

    public function integrationInfo(): Response
    {
        return success((new AiConfigAdminService())->section('integration'));
    }

    public function saveIntegration(): Response
    {
        return success((new AiConfigAdminService())->saveSection('integration', $this->request->params([
            ['integrations', []],
        ])));
    }

    public function speechInfo(): Response
    {
        return success((new AiConfigAdminService())->section('speech'));
    }

    public function saveSpeech(): Response
    {
        return success((new AiConfigAdminService())->saveSection('speech', $this->request->params([
            ['speech', []],
        ])));
    }

    public function playgroundInfo(): Response
    {
        return success((new AiConfigAdminService())->section('playground'));
    }

    public function testSpeech(): Response
    {
        return success((new AiConfigAdminService())->testSpeech((array)$this->request->param('speech', [])));
    }

    public function playgroundSpeechToText(): Response
    {
        $file = $this->request->file('audio');
        if (!$file) throw new CommonException('请录制或上传语音');
        return success((new AiConfigAdminService())->speechToText($file));
    }

    public function playgroundTextToSpeech(): Response
    {
        return success((new AiConfigAdminService())->textToSpeech(trim((string)$this->request->param('text', ''))));
    }

    public function testProvider(): Response
    {
        return success((new AiConfigAdminService())->testProvider((array)$this->request->param('provider', [])));
    }

    public function syncModels(): Response
    {
        return success((new AiConfigAdminService())->syncModels((array)$this->request->param('provider', [])));
    }

    public function execute(): Response
    {
        $data = $this->request->params([
            ['request_id', ''],
            ['scene_key', 'general'],
            ['provider_id', ''],
            ['model', ''],
            ['prompt', ''],
            ['response_mode', 'text'],
            ['temperature', 0.2],
            ['max_tokens', 0],
        ]);
        return success((new AiConfigAdminService())->execute($data));
    }

    public function stream(): Response
    {
        $data = $this->request->params([
            ['request_id', ''],
            ['scene_key', 'general'],
            ['provider_id', ''],
            ['model', ''],
            ['prompt', ''],
            ['response_mode', 'text'],
            ['temperature', 0.2],
            ['max_tokens', 0],
        ]);
        return new AiStreamResponse(function () use ($data): void {
            $emit = static function (array $event): void {
                $type = preg_replace('/[^a-z_]/', '', (string)($event['type'] ?? 'message')) ?: 'message';
                echo 'event: ' . $type . "\n";
                echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                @ob_flush();
                flush();
            };
            echo ": connected\n\n";
            flush();
            try {
                $result = (new AiConfigAdminService())->stream($data, $emit);
                unset($result['content'], $result['reasoning_content']);
                $emit(array_merge(['type' => 'done'], $result));
            } catch (\Throwable $e) {
                $emit(['type' => 'error', 'message' => $e->getMessage()]);
            }
        });
    }
}
