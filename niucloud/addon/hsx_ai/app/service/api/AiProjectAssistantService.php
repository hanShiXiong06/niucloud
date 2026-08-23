<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\api;

use addon\hsx_ai\app\service\core\AiAgentService;
use addon\hsx_ai\app\service\core\AiBusinessContextService;
use addon\hsx_ai\app\service\core\AiConfigService;
use addon\hsx_ai\app\service\core\AiIntegrationService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 面向客户的项目咨询助手。
 * hsx_ai 只负责模型、流式、会话和语音；项目事实通过插件事件按 project_id 获取。
 */
final class AiProjectAssistantService extends BaseApiService
{
    public const SCENE = 'hsx_project_center.customer_assistant';
    public const INTEGRATION = 'hsx_project_center';

    public function capability(int $projectId, string $groupNo = ''): array
    {
        $config = (new AiConfigService())->get((int)$this->site_id);
        $sceneEnabled = false;
        foreach ((array)($config['scenes'] ?? []) as $scene) {
            if ((string)($scene['key'] ?? '') === self::SCENE && !empty($scene['enabled'])) $sceneEnabled = true;
        }
        $available = !empty($config['enabled']) && $sceneEnabled
            && (new AiIntegrationService())->isEnabled((int)$this->site_id, self::INTEGRATION);
        $business = $available ? $this->business($projectId, '项目介绍', $groupNo) : [];
        $available = $available && !empty($business['handled']) && !empty($business['allowed']);
        $speech = $this->speechCapability((array)($config['speech'] ?? []));
        $context = $this->decodedContext((array)($business['contexts'] ?? []));
        $assistantConfig = (array)($context['assistant_config'] ?? []);
        if (array_key_exists('voice_enabled', $assistantConfig) && empty($assistantConfig['voice_enabled'])) {
            $speech = ['stt' => false, 'tts' => false, 'auto_read_default' => false, 'max_seconds' => 60];
        } elseif (array_key_exists('auto_read', $assistantConfig)) {
            $speech['auto_read_default'] = !empty($speech['tts']) && !empty($assistantConfig['auto_read']);
        }
        $title = trim((string)($context['project_title'] ?? ''));
        $configuredTitle = trim((string)($assistantConfig['title'] ?? ''));
        $configuredWelcome = trim((string)($assistantConfig['welcome'] ?? ''));
        return [
            'available' => $available,
            'scene' => self::SCENE,
            'title' => $configuredTitle !== '' ? $configuredTitle : ($title !== '' ? $title . ' · AI 顾问' : 'AI 项目顾问'),
            'welcome' => $configuredWelcome !== '' ? $configuredWelcome : '你可以直接问项目费用、准备资料、办理流程、审核和退款规则。我只依据当前项目资料回答。',
            'suggestions' => array_slice((array)($business['suggestions'] ?? []), 0, 8),
            'voice' => $speech,
            'conversation' => ['persistent' => (new AiConversationService())->available()],
            'human_handoff' => [
                'enabled' => true,
                'group_no' => mb_substr(trim($groupNo), 0, 30),
                'label' => '联系群内工作人员',
            ],
        ];
    }

    public function chat(int $projectId, array $data): array
    {
        $prepared = $this->prepare($projectId, $data);
        if (!$prepared['allowed']) return $this->refusal($prepared);
        $conversationService = new AiConversationService();
        try {
            $result = (new AiAgentService())->execute(
                (int)$this->site_id,
                $prepared['request'],
                $prepared['agent_context']
            );
            $message = $conversationService->saveAssistant($prepared['conversation'], $prepared['request_id'], $result);
            return array_merge($result, $this->meta($prepared, $message ? (int)$message->id : 0), $this->handoffMeta((string)($result['content'] ?? '')));
        } catch (\Throwable $e) {
            $conversationService->saveFailure($prepared['conversation'], $prepared['request_id'], $e->getMessage());
            throw $e;
        }
    }

    public function stream(int $projectId, array $data, callable $emit): array
    {
        $prepared = $this->prepare($projectId, $data);
        $conversationService = new AiConversationService();
        $emit(array_merge(['type' => 'conversation'], $this->meta($prepared)));
        if (!$prepared['allowed']) {
            $result = $this->refusal($prepared);
            $emit(['type' => 'content', 'delta' => $result['content']]);
            return $result;
        }
        try {
            $result = (new AiAgentService())->stream(
                (int)$this->site_id,
                $prepared['request'],
                $prepared['agent_context'],
                $emit
            );
            $message = $conversationService->saveAssistant($prepared['conversation'], $prepared['request_id'], $result);
            return array_merge($result, $this->meta($prepared, $message ? (int)$message->id : 0), $this->handoffMeta((string)($result['content'] ?? '')));
        } catch (\Throwable $e) {
            $conversationService->saveFailure($prepared['conversation'], $prepared['request_id'], $e->getMessage());
            throw $e;
        }
    }

    private function prepare(int $projectId, array $data): array
    {
        if ($projectId <= 0) throw new CommonException('项目参数不正确');
        $groupNo = mb_substr(trim((string)($data['group_no'] ?? '')), 0, 30);
        $capability = $this->capability($projectId, $groupNo);
        if (empty($capability['available'])) throw new CommonException('当前项目暂未启用 AI 顾问');

        $risk = new AiRiskService();
        $risk->throttle(10);
        $clientMessages = [];
        foreach (array_slice((array)($data['messages'] ?? []), -10) as $message) {
            if (!is_array($message)) continue;
            $role = (string)($message['role'] ?? '');
            $content = trim((string)($message['content'] ?? ''));
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $clientMessages[] = ['role' => $role, 'content' => mb_substr($content, 0, 1500)];
            }
        }
        $prompt = mb_substr(trim((string)($data['prompt'] ?? '')), 0, 1000);
        if ($prompt === '') throw new CommonException('请输入想咨询的问题');
        $risk->inspectPrompt($prompt);

        $conversationService = new AiConversationService();
        $requestId = $conversationService->requestId((string)($data['request_id'] ?? ''));
        $conversation = $conversationService->resolve(
            (int)($data['conversation_id'] ?? 0),
            $prompt,
            self::SCENE,
            self::INTEGRATION . ':' . $projectId
        );
        if ($conversation && ((string)$conversation->scene_key !== self::SCENE || (string)$conversation->entry_plugin !== self::INTEGRATION . ':' . $projectId)) {
            throw new CommonException('当前会话不属于该项目，请重新发起咨询');
        }
        $userMessage = $conversationService->saveUser($conversation, $requestId, $prompt);
        $messages = $conversation ? $conversationService->history($conversation, 10) : $clientMessages;
        $hasPrompt = false;
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if (($messages[$index]['role'] ?? '') !== 'user') continue;
            $hasPrompt = (string)$messages[$index]['content'] === $prompt;
            break;
        }
        if (!$hasPrompt) $messages[] = ['role' => 'user', 'content' => $prompt];

        $business = $this->business($projectId, $prompt, $groupNo);
        if (empty($business['handled']) || empty($business['allowed'])) {
            return [
                'allowed' => false,
                'suggestions' => $capability['suggestions'],
                'conversation' => $conversation,
                'request_id' => $requestId,
                'group_no' => $groupNo,
            ];
        }
        array_unshift($messages, [
            'role' => 'system',
            'content' => "以下内容是服务端按当前站点和当前项目生成的唯一可信知识。只能依据这些内容回答。"
                . "如果知识没有明确答案，直接说明需要群内工作人员确认，不得根据常识、其他项目或营销经验猜测。"
                . "回答尽量控制在300字内，优先用客户听得懂的表达；涉及步骤时使用短列表。\n\n"
                . implode("\n\n", (array)$business['contexts']),
        ]);

        return [
            'allowed' => true,
            'suggestions' => (array)$business['suggestions'],
            'conversation' => $conversation,
            'request_id' => $requestId,
            'group_no' => $groupNo,
            'agent_context' => [
                'scene' => self::SCENE,
                'actor' => [
                    'type' => (int)$this->member_id > 0 ? 'member' : 'guest',
                    'id' => (int)$this->member_id,
                    'channel' => (string)$this->channel,
                    'data_scope' => 'public',
                ],
                'conversation_id' => $conversation ? (int)$conversation->id : 0,
                'message_id' => $userMessage ? (int)$userMessage->id : 0,
            ],
            'request' => [
                'request_id' => $requestId,
                'scene_key' => self::SCENE,
                'messages' => $messages,
                'prompt' => '',
                'source' => ['plugin' => self::INTEGRATION, 'type' => 'project_customer_assistant', 'id' => (string)$projectId],
                'operator' => ['id' => (int)$this->member_id, 'name' => (int)$this->member_id > 0 ? '项目客户' : '访客'],
            ],
        ];
    }

    private function business(int $projectId, string $prompt, string $groupNo): array
    {
        return (new AiBusinessContextService())->resolve([
            'site_id' => (int)$this->site_id,
            'integration_key' => self::INTEGRATION,
            'scene' => self::SCENE,
            'project_id' => $projectId,
            'subject_id' => $projectId,
            'group_no' => $groupNo,
            'actor' => ['type' => (int)$this->member_id > 0 ? 'member' : 'guest', 'id' => (int)$this->member_id],
            'prompt' => $prompt,
            'current_prompt' => $prompt,
        ]);
    }

    private function refusal(array $prepared): array
    {
        $content = '这个问题当前项目资料没有明确说明，需要群内工作人员确认。';
        if (trim((string)($prepared['group_no'] ?? '')) !== '') {
            $content .= '请返回客户群“' . trim((string)$prepared['group_no']) . '”咨询。';
        }
        return array_merge([
            'request_id' => (string)($prepared['request_id'] ?? ''),
            'scene_key' => self::SCENE,
            'content' => $content,
            'suggestions' => (array)($prepared['suggestions'] ?? []),
            'status' => 'human_required',
        ], $this->meta($prepared), ['human_handoff' => true]);
    }

    private function meta(array $prepared, int $messageId = 0): array
    {
        $conversation = $prepared['conversation'] ?? null;
        return [
            'conversation_id' => $conversation ? (int)$conversation->id : 0,
            'message_id' => $messageId,
            'persistent' => $conversation !== null,
            'suggestions' => (array)($prepared['suggestions'] ?? []),
        ];
    }

    private function handoffMeta(string $content): array
    {
        return ['human_handoff' => (bool)preg_match('/群内工作人员|人工确认|没有明确说明|无法确认/u', $content)];
    }

    private function decodedContext(array $contexts): array
    {
        foreach ($contexts as $context) {
            $decoded = json_decode((string)$context, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    private function speechCapability(array $speech): array
    {
        $provider = (string)($speech['provider'] ?? 'baidu');
        $apiKey = (string)($speech['api_key'] ?? '');
        $ready = !empty($speech['enabled']) && ($provider === 'tencent'
            ? trim((string)($speech['secret_id'] ?? '')) !== '' && trim((string)($speech['secret_key'] ?? '')) !== ''
            : $apiKey !== '' && (AiConfigService::isBaiduDirectApiKey($apiKey) || trim((string)($speech['secret_key'] ?? '')) !== ''));
        return [
            'stt' => $ready && !empty($speech['stt_enabled']),
            'tts' => $ready && !empty($speech['tts_enabled']),
            'auto_read_default' => $ready && !empty($speech['tts_enabled']) && !empty($speech['auto_read_default']),
            'max_seconds' => 60,
        ];
    }
}
