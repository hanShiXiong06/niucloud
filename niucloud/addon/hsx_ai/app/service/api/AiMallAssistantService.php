<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\api;

use addon\hsx_ai\app\service\core\AiBusinessContextService;
use addon\hsx_ai\app\service\core\AiConfigService;
use addon\hsx_ai\app\service\core\AiGatewayService;
use addon\hsx_ai\app\service\core\AiIntegrationService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Cache;

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
            'voice' => [
                'stt' => $speechReady && !empty($speech['stt_enabled']),
                'tts' => $speechReady && !empty($speech['tts_enabled']),
                'max_seconds' => 60,
            ],
        ];
    }

    public function chat(array $data): array
    {
        $prepared = $this->prepare($data);
        if (!$prepared['allowed']) return $this->refusal($prepared);
        return array_merge(
            (new AiGatewayService())->execute((int)$this->site_id, $prepared['request']),
            ['resources' => $prepared['resources']]
        );
    }

    public function stream(array $data, callable $emit): array
    {
        $prepared = $this->prepare($data);
        if (!$prepared['allowed']) {
            $result = $this->refusal($prepared);
            $emit(['type' => 'content', 'delta' => $result['content']]);
            return $result;
        }
        if ($prepared['resources'] !== []) $emit(['type' => 'resources', 'items' => $prepared['resources']]);
        return (new AiGatewayService())->stream((int)$this->site_id, $prepared['request'], $emit);
    }

    private function prepare(array $data): array
    {
        $capability = $this->capability();
        if (empty($capability['available'])) throw new CommonException('本站暂未启用 AI 选机助手');
        $this->throttle();

        $messages = [];
        foreach (array_slice((array)($data['messages'] ?? []), -12) as $message) {
            if (!is_array($message)) continue;
            $role = (string)($message['role'] ?? '');
            $content = trim((string)($message['content'] ?? ''));
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
            }
        }
        $prompt = trim((string)($data['prompt'] ?? ''));
        if ($prompt === '' && $messages !== []) {
            for ($index = count($messages) - 1; $index >= 0; $index--) {
                if ($messages[$index]['role'] === 'user') { $prompt = $messages[$index]['content']; break; }
            }
        }
        if ($prompt === '') throw new CommonException('请先告诉我你的选机需求');
        $hasCurrentPrompt = false;
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if ($messages[$index]['role'] !== 'user') continue;
            $hasCurrentPrompt = $messages[$index]['content'] === $prompt;
            break;
        }
        if (!$hasCurrentPrompt) $messages[] = ['role' => 'user', 'content' => mb_substr($prompt, 0, 2000)];

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
        ]);
        if (empty($business['handled']) || empty($business['allowed'])) {
            return ['allowed' => false, 'suggestions' => $business['suggestions'] ?: $capability['suggestions']];
        }

        array_unshift($messages, [
            'role' => 'system',
            'content' => "以下是系统按当前站点、当前会员权限查询出的实时业务事实。只能依据这些事实回答，不得补充不存在的商品、价格或库存：\n"
                . implode("\n\n", $business['contexts']),
        ]);
        return [
            'allowed' => true,
            'resources' => array_slice((array)$business['resources'], 0, 8),
            'request' => [
                'request_id' => trim((string)($data['request_id'] ?? '')),
                'scene_key' => self::SCENE,
                'messages' => $messages,
                'source' => ['plugin' => 'phone_shop', 'type' => 'customer_assistant', 'id' => (string)$this->member_id],
                'operator' => ['id' => (int)$this->member_id, 'name' => '商城会员'],
            ],
        ];
    }

    private function refusal(array $prepared): array
    {
        return [
            'request_id' => '',
            'scene_key' => self::SCENE,
            'content' => '我只负责本站商品挑选和购买咨询。你可以告诉我预算、品牌、内存或成色要求，我来帮你找在售商品。',
            'suggestions' => (array)($prepared['suggestions'] ?? []),
            'status' => 'restricted',
        ];
    }

    private function throttle(): void
    {
        $identity = (int)$this->member_id > 0 ? 'member_' . (int)$this->member_id : 'ip_' . sha1((string)request()->ip());
        $key = 'hsx_ai:mall:' . (int)$this->site_id . ':' . $identity . ':' . date('YmdHi');
        $count = (int)Cache::get($key, 0);
        if ($count >= 12) throw new CommonException('提问有点频繁，请稍后再试');
        Cache::set($key, $count + 1, 70);
    }
}
