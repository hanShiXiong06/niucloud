<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\admin;

use addon\hsx_ai\app\service\core\AiActorContextService;
use addon\hsx_ai\app\service\core\AiToolService;
use app\model\sys\SysRole;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** PC 经营助手目录：可见性来自管理员真实权限，模型不能指定身份或扩大工具范围。 */
final class AiAdminAgentService extends BaseAdminService
{
    public function info(): array
    {
        $profile = $this->profile();
        $agents = $this->visibleAgents($profile);
        return [
            'agents' => $agents,
            'default_agent_key' => (string)($agents[0]['key'] ?? ''),
            'actor' => [
                'name' => (string)$this->username,
                'roles' => $profile['role_names'],
                'is_site_admin' => $profile['is_site_admin'],
            ],
            'operation_policy' => [
                'read' => '仅返回当前账号有权查看的站点数据',
                'write' => '写操作必须具备原业务权限并经过二次确认',
            ],
        ];
    }

    public function resolve(string $agentKey): array
    {
        $agents = $this->visibleAgents($this->profile());
        foreach ($agents as $agent) {
            if ((string)$agent['key'] === $agentKey) return $agent;
        }
        throw new CommonException('该智能体不存在或当前账号无权使用');
    }

    public function context(
        array $agent,
        int $conversationId = 0,
        int $messageId = 0,
        array $approvedCalls = [],
        string $requestedDefaultToolKey = '',
        string $currentPrompt = ''
    ): array
    {
        $profile = $this->profile();
        $defaultToolKey = trim($requestedDefaultToolKey) ?: trim((string)($agent['default_tool_key'] ?? ''));
        if (!in_array($defaultToolKey, (array)$agent['tool_keys'], true)) $defaultToolKey = '';
        return [
            'scene' => AiAdminAssistantConversationService::SCENE,
            'agent_key' => (string)$agent['key'],
            'agent_name' => (string)$agent['name'],
            'agent_prompt' => (string)$agent['system_prompt'],
            'allowed_tool_keys' => (array)$agent['tool_keys'],
            'default_tool_key' => $defaultToolKey,
            'current_prompt' => mb_substr(trim($currentPrompt), 0, 2000),
            'actor' => $profile['actor'],
            'allow_model_override' => true,
            'allow_disabled' => true,
            'approved_tool_calls' => array_values(array_filter(array_map('strval', $approvedCalls))),
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ];
    }

    private function visibleAgents(array $profile): array
    {
        $toolContext = [
            'site_id' => (int)$this->site_id,
            'scene' => AiAdminAssistantConversationService::SCENE,
            'actor' => $profile['actor'],
        ];
        $available = array_column((new AiToolService())->definitions($toolContext), null, 'key');
        $result = [];
        foreach ($this->definitions($profile) as $definition) {
            $configured = array_values(array_filter(array_map('strval', (array)$definition['tool_keys'])));
            $tools = array_values(array_intersect($configured, array_keys($available)));
            $owner = (string)$definition['key'] === 'business.owner';
            if (($owner && !$profile['is_site_admin']) || $tools === []) continue;
            $quickPrompts = [];
            $quickActions = [];
            foreach ((array)($definition['quick_prompts'] ?? []) as $quickPrompt) {
                if (is_string($quickPrompt) && trim($quickPrompt) !== '') {
                    $text = trim($quickPrompt);
                    $quickPrompts[] = $text;
                    $quickActions[] = ['text' => $text, 'tool_key' => ''];
                    continue;
                }
                if (!is_array($quickPrompt)) continue;
                $requiredTools = array_values(array_filter(array_map('strval', (array)($quickPrompt['tool_keys'] ?? []))));
                $text = trim((string)($quickPrompt['text'] ?? ''));
                if ($text !== '' && array_diff($requiredTools, $tools) === []) {
                    $quickPrompts[] = $text;
                    $quickActions[] = [
                        'text' => $text,
                        'tool_key' => count($requiredTools) === 1 ? (string)$requiredTools[0] : '',
                    ];
                }
            }
            $result[] = array_merge($definition, [
                'tool_keys' => $tools,
                'quick_prompts' => array_slice($quickPrompts, 0, 6),
                'quick_actions' => array_slice($quickActions, 0, 6),
                'capability_count' => count($tools),
                'read_only' => count(array_filter($tools, static fn(string $key): bool => empty($available[$key]['read_only']))) === 0,
            ]);
        }
        return $result;
    }

    private function definitions(array $profile): array
    {
        $definitions = [];
        foreach ((array)event('HsxAiAdminAgentRegistryRequested', [
            'site_id' => (int)$this->site_id,
            'scene' => AiAdminAssistantConversationService::SCENE,
            'actor' => (array)$profile['actor'],
        ]) as $response) {
            foreach ($this->rows($response) as $row) {
                if (!is_array($row)) continue;
                $key = trim((string)($row['key'] ?? ''));
                if ($key === '' || $key === 'business.owner' || isset($definitions[$key])) continue;
                $definitions[$key] = $row;
            }
        }
        if ($definitions === []) return [];

        $ownerTools = [];
        foreach ($definitions as $definition) {
            $ownerTools = array_merge($ownerTools, array_values(array_filter(array_map('strval', (array)($definition['tool_keys'] ?? [])))));
        }
        $owner = [
            'key' => 'business.owner',
            'name' => '老板经营助手',
            'short_name' => '经营总览',
            'description' => '汇总当前已安装并已授权业务插件的实时经营事实。',
            'icon' => 'element DataAnalysis',
            'tone' => 'primary',
            'tool_keys' => array_values(array_unique($ownerTools)),
            'quick_prompts' => [
                ['text' => '今天经营情况怎么样？', 'tool_keys' => []],
                ['text' => '汇总我现在能查看的经营数据。', 'tool_keys' => []],
            ],
            'system_prompt' => '你是老板经营助手。能力只来自当前站点已安装、已开启且当前账号有权访问的业务插件。先给结论和异常，再给关键指标及可追溯明细；跨业务问题应组合调用多个工具，不得用单一数据代替全店口径。',
        ];
        return array_merge([$owner], array_values($definitions));
    }

    private function profile(): array
    {
        $auth = new AuthService();
        $permissions = [];
        foreach ((array)$auth->getAuthMenuList(0) as $menu) {
            if (is_array($menu) && trim((string)($menu['menu_key'] ?? '')) !== '') $permissions[] = (string)$menu['menu_key'];
        }
        foreach ((array)$auth->getAuthApiList() as $method => $paths) {
            foreach ((array)$paths as $path) {
                if (trim((string)$path) !== '') $permissions[] = strtolower((string)$method) . ':' . trim((string)$path);
            }
        }
        $roleInfo = (array)$auth->getAuthRole((int)$this->site_id);
        $roleIds = array_values(array_filter(array_map('intval', (array)($roleInfo['role_ids'] ?? []))));
        $roleNames = $roleIds === [] ? [] : SysRole::where('site_id', '=', (int)$this->site_id)
            ->whereIn('role_id', $roleIds)->where('status', '=', 1)->column('role_name');
        $isSiteAdmin = AuthService::isSuperAdmin() || !empty($roleInfo['is_admin']);
        $actor = (new AiActorContextService())->admin(
            (int)$this->site_id,
            (int)$this->uid,
            (string)$this->username,
            array_values(array_unique($permissions)),
            array_values(array_filter(array_map('strval', $roleNames))),
            $isSiteAdmin ? 'site' : 'permission'
        );
        $actor['attributes']['is_site_admin'] = $isSiteAdmin;
        return ['permissions' => $permissions, 'role_names' => $roleNames, 'is_site_admin' => $isSiteAdmin, 'actor' => $actor];
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
