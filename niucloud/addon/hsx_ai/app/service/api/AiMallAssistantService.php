<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\api;

use addon\hsx_ai\app\service\core\AiBusinessContextService;
use addon\hsx_ai\app\service\core\AiConfigService;
use addon\hsx_ai\app\service\core\AiGatewayService;
use addon\hsx_ai\app\service\core\AiIntegrationService;
use addon\hsx_ai\app\service\core\AiSkillService;
use core\base\BaseApiService;
use core\exception\CommonException;

final class AiMallAssistantService extends BaseApiService
{
    public const SCENE = 'phone_shop.customer_assistant';
    public const INTEGRATION = 'phone_shop';

    public function capability(): array
    {
        $config = (new AiConfigService())->get((int)$this->site_id);
        $sceneEnabled = false;
        foreach ($config['scenes'] as $scene) {
            if ((string)$scene['key'] === self::SCENE && !empty($scene['enabled'])) $sceneEnabled = true;
        }
        $speech = (array)($config['speech'] ?? []);
        $speechProvider = (string)($speech['provider'] ?? 'baidu');
        $speechApiKey = (string)($speech['api_key'] ?? '');
        $speechReady = !empty($speech['enabled']) && ($speechProvider === 'tencent'
            ? (string)($speech['secret_id'] ?? '') !== '' && (string)($speech['secret_key'] ?? '') !== ''
            : $speechApiKey !== '' && (AiConfigService::isBaiduDirectApiKey($speechApiKey) || (string)($speech['secret_key'] ?? '') !== ''));
        return [
            'available' => !empty($config['enabled']) && $sceneEnabled
                && (new AiIntegrationService())->isEnabled((int)$this->site_id, self::INTEGRATION),
            'scene' => self::SCENE,
            'title' => 'AI 选机助手',
            'welcome' => '告诉我预算、品牌、内存或成色要求，我会按本站实时在售商品帮你挑选。',
            'suggestions' => ['预算 3000 元推荐什么手机', '想看苹果 256G 的机器', '推荐一台性价比高的备用机'],
            'quick_actions' => (new AiSkillService())->quickActions(
                (int)$this->site_id,
                self::SCENE,
                ['type' => 'member', 'id' => (int)$this->member_id]
            ),
            'conversation' => [
                'persistent' => (new AiConversationService())->available(),
                'history' => (new AiConversationService())->available(),
            ],
            'voice' => [
                'stt' => $speechReady && !empty($speech['stt_enabled']),
                'tts' => $speechReady && !empty($speech['tts_enabled']),
                'auto_read_default' => $speechReady && !empty($speech['tts_enabled']) && !empty($speech['auto_read_default']),
                'max_seconds' => 60,
            ],
        ];
    }

    public function chat(array $data): array
    {
        $prepared = $this->prepare($data);
        $conversationService = new AiConversationService();
        if (!$prepared['allowed']) {
            $result = $this->refusal($prepared);
            $message = $conversationService->saveAssistant($prepared['conversation'], $prepared['request_id'], $result);
            return array_merge($result, $this->conversationMeta($prepared, $message ? (int)$message->id : 0));
        }
        try {
            $result = array_merge(
                (new AiGatewayService())->execute((int)$this->site_id, $prepared['request']),
                ['resources' => $prepared['resources'], 'blocks' => $prepared['blocks']]
            );
            $message = $conversationService->saveAssistant(
                $prepared['conversation'],
                $prepared['request_id'],
                $result,
                $prepared['resources']
            );
            return array_merge($result, $this->conversationMeta($prepared, $message ? (int)$message->id : 0));
        } catch (\Throwable $e) {
            $conversationService->saveFailure($prepared['conversation'], $prepared['request_id'], $e->getMessage());
            throw $e;
        }
    }

    public function stream(array $data, callable $emit): array
    {
        $prepared = $this->prepare($data);
        $conversationService = new AiConversationService();
        $emit(array_merge(['type' => 'conversation'], $this->conversationMeta($prepared)));
        if (!$prepared['allowed']) {
            $result = $this->refusal($prepared);
            $emit(['type' => 'content', 'delta' => $result['content']]);
            $message = $conversationService->saveAssistant($prepared['conversation'], $prepared['request_id'], $result);
            return array_merge($result, $this->conversationMeta($prepared, $message ? (int)$message->id : 0));
        }
        if ($prepared['resources'] !== []) $emit(['type' => 'resources', 'items' => $prepared['resources']]);
        if ($prepared['blocks'] !== []) $emit(['type' => 'blocks', 'items' => $prepared['blocks']]);
        try {
            $result = (new AiGatewayService())->stream((int)$this->site_id, $prepared['request'], $emit);
            $result['blocks'] = $prepared['blocks'];
            $message = $conversationService->saveAssistant(
                $prepared['conversation'],
                $prepared['request_id'],
                $result,
                $prepared['resources']
            );
            return array_merge($result, $this->conversationMeta($prepared, $message ? (int)$message->id : 0));
        } catch (\Throwable $e) {
            $conversationService->saveFailure($prepared['conversation'], $prepared['request_id'], $e->getMessage());
            throw $e;
        }
    }

    private function prepare(array $data): array
    {
        $capability = $this->capability();
        if (empty($capability['available'])) throw new CommonException('本站暂未启用 AI 选机助手');
        (new AiRiskService())->throttle();

        $clientMessages = [];
        foreach (array_slice((array)($data['messages'] ?? []), -12) as $message) {
            if (!is_array($message)) continue;
            $role = (string)($message['role'] ?? '');
            $content = trim((string)($message['content'] ?? ''));
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $clientMessages[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
            }
        }
        $prompt = trim((string)($data['prompt'] ?? ''));
        if ($prompt === '' && $clientMessages !== []) {
            for ($index = count($clientMessages) - 1; $index >= 0; $index--) {
                if ($clientMessages[$index]['role'] === 'user') { $prompt = $clientMessages[$index]['content']; break; }
            }
        }
        if ($prompt === '') throw new CommonException('请先告诉我你的选机需求');
        $prompt = mb_substr($prompt, 0, 2000);
        $conversationService = new AiConversationService();
        $requestId = $conversationService->requestId((string)($data['request_id'] ?? ''));
        (new AiRiskService())->inspectPrompt($prompt);
        $conversation = $conversationService->resolve(
            (int)($data['conversation_id'] ?? 0),
            $prompt,
            self::SCENE,
            self::INTEGRATION
        );
        $userMessage = $conversationService->saveUser($conversation, $requestId, $prompt);
        (new AiDemandService())->capture($conversation, $userMessage, $prompt);
        $messages = $conversation
            ? $conversationService->history($conversation, 12)
            : $clientMessages;
        $hasCurrentPrompt = false;
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if ($messages[$index]['role'] !== 'user') continue;
            $hasCurrentPrompt = $messages[$index]['content'] === $prompt;
            break;
        }
        if (!$hasCurrentPrompt) $messages[] = ['role' => 'user', 'content' => $prompt];

        $recentUserPrompts = array_values(array_map(
            static fn(array $message): string => (string)$message['content'],
            array_slice(array_values(array_filter(
                $messages,
                static fn(array $message): bool => $message['role'] === 'user'
            )), -3)
        ));
        $business = (new AiBusinessContextService())->resolve([
            'site_id' => (int)$this->site_id,
            'integration_key' => self::INTEGRATION,
            'scene' => self::SCENE,
            'actor' => ['type' => 'member', 'id' => (int)$this->member_id],
            'prompt' => implode('；', $recentUserPrompts),
            'current_prompt' => $prompt,
            'active_intent' => $conversation ? (string)$conversation->last_intent : '',
        ]);
        if (empty($business['handled']) || empty($business['allowed'])) {
            return [
                'allowed' => false,
                'suggestions' => $business['suggestions'] ?: $capability['suggestions'],
                'conversation' => $conversation,
                'request_id' => $requestId,
            ];
        }

        $conversationService->rememberIntent($conversation, (string)($business['consumers'][0] ?? ''));

        array_unshift($messages, [
            'role' => 'system',
            'content' => "以下是系统按当前站点、当前会员权限生成的实时业务上下文。它可能是商城商品、回收报价事实，也可能是需要澄清的业务意图。用户询问回收价、卖机价、量级、成色等级或报价行情时，身份是卖方，禁止推荐其购买本站商品。若 intent_state=needs_clarification，先结合入口推测用户需求，再只追问一个关键条件，不得输出固定拒答话术。若已提供商品事实，请充分使用商品说明、公开参数、服务和质检结果，先给清晰结论，再解释价格、成色、异常项和适合人群。价格必须严格按业务上下文的字段定义表达，优先说明当前会员身份和实际可购价，禁止把划线参考价当成正常售价、市场成交价或最终价。若是回收报价，必须逐张报价单整理 datasets，不得只回答第一张，不得把不同报价单的等级或备注合并为同一口径；必须严格使用型号、内存、等级、日期、扣价说明和快照边界。不得补充不存在的商品、价格、库存或未经提供的技术参数；缺少资料时要明确说明本站暂未维护，不能用模糊话术假装知道：\n"
                . implode("\n\n", $business['contexts']),
        ]);
        return [
            'allowed' => true,
            'resources' => array_slice((array)$business['resources'], 0, 8),
            'blocks' => array_slice((array)$business['blocks'], 0, 8),
            'conversation' => $conversation,
            'request_id' => $requestId,
            'request' => [
                'request_id' => $requestId,
                'scene_key' => self::SCENE,
                'messages' => $messages,
                'source' => [
                    'plugin' => (string)($business['consumers'][0] ?? 'phone_shop'),
                    'type' => 'customer_assistant',
                    'id' => (string)$this->member_id,
                ],
                'operator' => ['id' => (int)$this->member_id, 'name' => '商城会员'],
            ],
        ];
    }

    private function refusal(array $prepared): array
    {
        return [
            'request_id' => (string)($prepared['request_id'] ?? ''),
            'scene_key' => self::SCENE,
            'content' => '这个问题和当前的选机场景无关，我不会绕开本站数据去猜答案。我可以继续帮你挑在售设备，比如按预算、用途或成色来选。',
            'suggestions' => (array)($prepared['suggestions'] ?? []),
            'status' => 'restricted',
        ];
    }

    private function conversationMeta(array $prepared, int $messageId = 0): array
    {
        $conversation = $prepared['conversation'] ?? null;
        return [
            'conversation_id' => $conversation ? (int)$conversation->id : 0,
            'message_id' => $messageId,
            'persistent' => $conversation !== null,
        ];
    }

}
