<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\admin;

use addon\hsx_ai\app\service\core\AiAgentService;
use addon\hsx_ai\app\model\AiCallLog;
use addon\hsx_ai\app\service\core\AiConfigService;
use addon\hsx_ai\app\service\core\AiGatewayService;
use addon\hsx_ai\app\service\core\AiIntegrationService;
use addon\hsx_ai\app\service\core\AiProviderRegistry;
use addon\hsx_ai\app\service\core\AiSpeechService;
use core\base\BaseAdminService;

final class AiConfigAdminService extends BaseAdminService
{
    public function section(string $section): array
    {
        $config = (new AiConfigService())->get($this->site_id, true);
        if ($section === 'integration') {
            $config['integrations'] = (new AiIntegrationService())->definitions($this->site_id, (array)$config['integrations']);
            return array_intersect_key($config, array_flip(['enabled', 'integrations']));
        }
        if ($section === 'speech') return array_intersect_key($config, array_flip(['enabled', 'speech']));
        if ($section === 'playground') {
            $result = array_intersect_key($config, array_flip(['enabled', 'default_provider_id', 'default_model', 'providers', 'scenes']));
            $result['speech_capability'] = $this->speechCapability((array)($config['speech'] ?? []));
            return $result;
        }
        return array_intersect_key($config, array_flip([
            'enabled', 'default_provider_id', 'default_model', 'redact_sensitive', 'log_content', 'providers', 'scenes',
        ]));
    }

    public function saveSection(string $section, array $data): array
    {
        $allowed = $section === 'integration'
            ? ['integrations']
            : ($section === 'speech' ? ['speech'] : [
                'enabled', 'default_provider_id', 'default_model', 'redact_sensitive', 'log_content', 'providers', 'scenes',
            ]);
        (new AiConfigService())->save($this->site_id, array_intersect_key($data, array_flip($allowed)));
        return $this->section($section);
    }

    public function assistantInfo(): array
    {
        return array_merge($this->section('playground'), (new AiAdminAgentService())->info());
    }

    public function testSpeech(array $input): array
    {
        return (new AiSpeechService())->test($this->site_id, $input);
    }

    public function speechToText($file): array
    {
        return (new AiSpeechService())->speechToText($this->site_id, $file);
    }

    public function textToSpeech(string $text): array
    {
        return (new AiSpeechService())->textToSpeech($this->site_id, $text);
    }

    public function testProvider(array $input): array
    {
        $provider = (new AiConfigService())->resolveProviderInput($this->site_id, $input);
        return array_merge(
            ['provider_id' => (string)$provider['id'], 'provider_name' => (string)$provider['name']],
            (new AiProviderRegistry())->resolve((string)$provider['driver'])->test($provider)
        );
    }

    public function syncModels(array $input): array
    {
        $provider = (new AiConfigService())->resolveProviderInput($this->site_id, $input);
        $models = (new AiProviderRegistry())->resolve((string)$provider['driver'])->models($provider);
        return [
            'provider_id' => (string)$provider['id'],
            'models' => array_map(static fn(array $model): array => array_merge($model, ['enabled' => 1]), $models),
            'total' => count($models),
        ];
    }

    public function execute(array $data): array
    {
        $adminAssistant = (string)($data['scene_key'] ?? '') === 'business.admin_assistant';
        $data['operator'] = [
            'id' => (int)$this->uid,
            'name' => (string)$this->username,
        ];
        $data['source'] = [
            'plugin' => 'hsx_ai',
            'type' => $adminAssistant ? 'admin_assistant' : 'admin_test',
            'id' => '',
        ];
        if ($adminAssistant) {
            $agentService = new AiAdminAgentService();
            $agent = $agentService->resolve(trim((string)($data['agent_key'] ?? '')));
            $data['agent_key'] = (string)$agent['key'];
            $memory = new AiAdminAssistantConversationService();
            $prepared = $memory->prepare($data);
            try {
                $result = (new AiAgentService())->execute(
                    $this->site_id,
                    $prepared['request'],
                    $agentService->context(
                        $agent,
                        (int)$prepared['conversation']->id,
                        (int)$prepared['message_id'],
                        (array)($data['approved_tool_calls'] ?? []),
                        trim((string)($data['default_tool_key'] ?? '')),
                        (string)$prepared['prompt']
                    )
                );
                $message = $memory->saveAssistant($prepared, $result);
                return array_merge($result, $memory->resultMeta($prepared, (int)$message->id));
            } catch (\Throwable $e) {
                $memory->saveFailure($prepared, $e->getMessage());
                throw $e;
            }
        }
        return (new AiGatewayService())->execute($this->site_id, $data, true, true);
    }

    public function stream(array $data, callable $emit): array
    {
        $adminAssistant = (string)($data['scene_key'] ?? '') === 'business.admin_assistant';
        $data['operator'] = [
            'id' => (int)$this->uid,
            'name' => (string)$this->username,
        ];
        $data['source'] = [
            'plugin' => 'hsx_ai',
            'type' => $adminAssistant ? 'admin_assistant' : 'admin_test',
            'id' => '',
        ];
        if ($adminAssistant) {
            $agentService = new AiAdminAgentService();
            $agent = $agentService->resolve(trim((string)($data['agent_key'] ?? '')));
            $data['agent_key'] = (string)$agent['key'];
            $memory = new AiAdminAssistantConversationService();
            $prepared = $memory->prepare($data);
            $emit(array_merge(['type' => 'conversation'], $memory->resultMeta($prepared)));
            try {
                $result = (new AiAgentService())->stream(
                    $this->site_id,
                    $prepared['request'],
                    $agentService->context(
                        $agent,
                        (int)$prepared['conversation']->id,
                        (int)$prepared['message_id'],
                        (array)($data['approved_tool_calls'] ?? []),
                        trim((string)($data['default_tool_key'] ?? '')),
                        (string)$prepared['prompt']
                    ),
                    $emit
                );
                $message = $memory->saveAssistant($prepared, $result);
                return array_merge($result, $memory->resultMeta($prepared, (int)$message->id));
            } catch (\Throwable $e) {
                $memory->saveFailure($prepared, $e->getMessage());
                throw $e;
            }
        }
        return (new AiGatewayService())->stream($this->site_id, $data, $emit, true, true);
    }

    public function logs(array $where): array
    {
        $query = AiCallLog::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['status'])) $query->where('status', '=', (string)$where['status']);
        if (!empty($where['scene_key'])) $query->where('scene_key', '=', (string)$where['scene_key']);
        if (!empty($where['provider_id'])) $query->where('provider_id', '=', (string)$where['provider_id']);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('request_id|scene_key|source_plugin|source_type|operator_name|provider_id|model|error_message', '%' . $keyword . '%');
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $row['request_meta'] = json_decode((string)($row['request_meta_json'] ?? ''), true) ?: [];
            $row['response_meta'] = json_decode((string)($row['response_meta_json'] ?? ''), true) ?: [];
            unset($row['request_meta_json'], $row['response_meta_json'], $row['prompt_hash']);
        }
        unset($row);
        return $page;
    }

    private function speechCapability(array $speech): array
    {
        $provider = (string)($speech['provider'] ?? 'baidu');
        $credentialsReady = $provider === 'tencent'
            ? !empty($speech['secret_id_configured']) && !empty($speech['secret_key_configured'])
            : !empty($speech['api_key_configured']) && (
                (string)($speech['baidu_auth_mode'] ?? '') === 'api_key'
                || !empty($speech['secret_key_configured'])
            );
        $enabled = !empty($speech['enabled']) && $credentialsReady;
        return [
            'enabled' => $enabled,
            'provider' => $provider,
            'provider_name' => $provider === 'tencent' ? '腾讯云' : '百度智能云',
            'stt' => $enabled && !empty($speech['stt_enabled']),
            'tts' => $enabled && !empty($speech['tts_enabled']),
        ];
    }

    public function assistantConversation(int $conversationId): array
    {
        return (new AiAdminAssistantConversationService())->detail($conversationId);
    }

    public function assistantConversations(array $where): array
    {
        $agent = (new AiAdminAgentService())->resolve(trim((string)($where['agent_key'] ?? '')));
        return (new AiAdminAssistantConversationService())->recent(
            (string)$agent['key'],
            (int)($where['limit'] ?? 20)
        );
    }

    public function deleteAssistantConversation(int $conversationId): bool
    {
        return (new AiAdminAssistantConversationService())->delete($conversationId);
    }

}
