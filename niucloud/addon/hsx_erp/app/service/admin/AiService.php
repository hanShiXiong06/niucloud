<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\service\core\ai\AiConfigService;
use addon\hsx_erp\app\service\core\ai\AiSceneService;
use addon\hsx_erp\app\service\core\ai\AiToolService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * AI 后台服务（编排层）
 *
 * 当前阶段：提供「配置管理 + 简单聊天测试」能力，用于打通云雾接口。
 * 后续业务场景（验机/总结/财务/财报）在此基础上扩展 run(scene, payload)。
 */
class AiService extends BaseAdminService
{
    protected AiConfigService $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = new AiConfigService();
    }

    /**
     * 读取站点 AI 配置（脱敏）
     */
    public function getConfig(): array
    {
        return $this->config->get($this->site_id);
    }

    /**
     * 保存站点 AI 配置
     */
    public function saveConfig(array $data): bool
    {
        return $this->config->save($this->site_id, $data);
    }

    /**
     * 拉取可用模型列表（来自云雾 /v1/models）
     */
    public function models(): array
    {
        return $this->config->channel($this->site_id)->models();
    }

    /**
     * 连通性测试
     */
    public function ping(string $model = ''): array
    {
        $raw = $this->config->getRaw($this->site_id);
        $model = $model ?: $raw['default_model'];
        return $this->config->channel($this->site_id)->ping($model);
    }

    /**
     * 简单聊天（测试用）
     *
     * @param array  $messages 完整 messages 数组；为空时用 prompt 包一条 user 消息
     * @param string $prompt   单条用户输入（messages 为空时使用）
     * @param string $model    指定模型，留空用默认
     */
    public function chat(array $messages = [], string $prompt = '', string $model = ''): array
    {
        $raw = $this->config->getRaw($this->site_id);
        if ((int)$raw['enabled'] !== 1) {
            throw new CommonException('AI 功能未开启，请先在「AI 配置」中开启');
        }

        $model = $model ?: $raw['default_model'];

        if (empty($messages)) {
            if (trim($prompt) === '') {
                throw new CommonException('请输入内容');
            }
            $messages = [['role' => 'user', 'content' => $prompt]];
        }

        $result = $this->config->channel($this->site_id)->chat($messages, $model, [
            'temperature' => 0.7,
        ]);

        return [
            'content' => $result['content'],
            'model'   => $result['model'],
            'usage'   => $result['usage'],
        ];
    }

    /**
     * 场景清单（给通用组件用，不含系统提示词）
     */
    public function scenes(): array
    {
        return AiSceneService::options();
    }

    /**
     * 按场景执行（业务页通用入口）
     *
     * @param string $scene   场景 key
     * @param array  $payload { context: mixed, question: string, model: string }
     *   - context：本页业务数据（数组或文本），后端拼进 Prompt
     *   - question：用户的具体提问
     *   - model：可选，覆盖默认模型
     */
    public function run(string $scene, array $payload): array
    {
        $raw = $this->config->getRaw($this->site_id);
        if ((int)$raw['enabled'] !== 1) {
            throw new CommonException('AI 功能未开启，请先在「AI 配置」中开启');
        }

        $def = AiSceneService::get($scene);
        if (empty($def)) {
            throw new CommonException('未知的 AI 场景：' . $scene);
        }

        $model = trim((string)($payload['model'] ?? '')) ?: $raw['default_model'];

        $question = trim((string)($payload['question'] ?? ''));
        $context  = $payload['context'] ?? '';
        if (is_array($context)) {
            $context = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $context = trim((string)$context);

        $parts = [];
        if ($context !== '') {
            $parts[] = "【上下文数据】\n" . $context;
        }
        if ($question !== '') {
            $parts[] = "【问题】\n" . $question;
        }
        if (empty($parts)) {
            throw new CommonException('请输入问题或提供数据');
        }

        $messages = [['role' => 'system', 'content' => $def['system']]];
        // 多轮上下文：把历史对话接进去（只取 role/content）
        $history = $payload['history'] ?? [];
        if (is_array($history)) {
            foreach ($history as $h) {
                $role = (($h['role'] ?? '') === 'assistant') ? 'assistant' : 'user';
                $content = trim((string)($h['content'] ?? ''));
                if ($content !== '') {
                    $messages[] = ['role' => $role, 'content' => $content];
                }
            }
        }
        $messages[] = ['role' => 'user', 'content' => implode("\n\n", $parts)];

        $channel = $this->config->channel($this->site_id);
        $options = ['temperature' => $def['temperature'] ?? 0.7];
        $allowTools = $def['tools'] ?? [];

        if (!empty($allowTools)) {
            // 场景启用了 Function Calling：让 AI 自主调用只读查询工具（site_id 已锁定）
            $toolService = new AiToolService();
            $tools = $toolService->definitions($allowTools);
            $executor = fn(string $name, array $args) => $toolService->execute($name, $args, $allowTools);
            $result = $channel->chatWithTools($messages, $model, $tools, $executor, $options);
            return [
                'scene'      => $scene,
                'content'    => $result['content'],
                'model'      => $result['model'],
                'usage'      => $result['usage'],
                'references' => $this->extractReferences($result['tool_results'] ?? []),
            ];
        }

        $result = $channel->chat($messages, $model, $options);
        return [
            'scene'      => $scene,
            'content'    => $result['content'],
            'model'      => $result['model'],
            'usage'      => $result['usage'],
            'references' => [],
        ];
    }

    /**
     * 流式执行（SSE）。通过 $emit('status'|'delta'|'done', data) 推送过程与结果。
     */
    public function runStream(string $scene, array $payload, callable $emit): void
    {
        $raw = $this->config->getRaw($this->site_id);
        if ((int)$raw['enabled'] !== 1) {
            $emit('error', ['message' => 'AI 功能未开启，请先在「AI 配置」中开启']);
            return;
        }
        $def = AiSceneService::get($scene);
        if (empty($def)) {
            $emit('error', ['message' => '未知的 AI 场景：' . $scene]);
            return;
        }

        $model = trim((string)($payload['model'] ?? '')) ?: $raw['default_model'];

        $question = trim((string)($payload['question'] ?? ''));
        $context  = $payload['context'] ?? '';
        if (is_array($context)) {
            $context = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $context = trim((string)$context);

        $parts = [];
        if ($context !== '') {
            $parts[] = "【上下文数据】\n" . $context;
        }
        if ($question !== '') {
            $parts[] = "【问题】\n" . $question;
        }
        if (empty($parts)) {
            $emit('error', ['message' => '请输入问题或提供数据']);
            return;
        }

        $messages = [['role' => 'system', 'content' => $def['system']]];
        $history = $payload['history'] ?? [];
        if (is_array($history)) {
            foreach ($history as $h) {
                $role = (($h['role'] ?? '') === 'assistant') ? 'assistant' : 'user';
                $content = trim((string)($h['content'] ?? ''));
                if ($content !== '') {
                    $messages[] = ['role' => $role, 'content' => $content];
                }
            }
        }
        $messages[] = ['role' => 'user', 'content' => implode("\n\n", $parts)];

        $channel = $this->config->channel($this->site_id);
        $options = ['temperature' => $def['temperature'] ?? 0.7];
        $allowTools = $def['tools'] ?? [];

        try {
            if (!empty($allowTools)) {
                $toolService = new AiToolService();
                $tools = $toolService->definitions($allowTools);
                $executor = function (string $name, array $args) use ($toolService, $allowTools, $emit) {
                    $emit('status', ['text' => $toolService->statusBefore($name, $args)]);
                    $r = $toolService->execute($name, $args, $allowTools);
                    $emit('status', ['text' => $toolService->statusAfter($name, $r)]);
                    return $r;
                };
                $res = $channel->streamWithTools($messages, $model, $tools, $executor, $emit, $options);
                $references = $this->extractReferences($res['tool_results'] ?? []);
            } else {
                $res = $channel->streamWithTools($messages, $model, [], fn() => [], $emit, $options);
                $references = [];
            }

            $emit('done', [
                'content'    => $res['content'],
                'usage'      => $res['usage'],
                'references' => $references,
                'model'      => $model,
            ]);
        } catch (\Throwable $e) {
            $emit('error', ['message' => $e->getMessage()]);
        }
    }

    /** 引用按钮数量上限（防爆：流水/设备很多时不至于撑爆界面与上下文） */
    protected const MAX_REFERENCES = 30;

    /**
     * 从工具结果里提取「可跳转引用」（当前：设备）。
     * 标签 = 设备型号 · 串号后6位（无串号则只型号；都没有再退回 设备 #id）。
     * 结构：[{ type:'device', id:123, label:'iPhone 15 Pro · 123456' }]
     */
    protected function extractReferences(array $toolResults): array
    {
        $devices = [];
        foreach ($toolResults as $tr) {
            $records = $tr['result']['records'] ?? [];
            if (!is_array($records)) {
                continue;
            }
            foreach ($records as $r) {
                $did = (int)($r['device_id'] ?? 0);
                if ($did > 0) {
                    $devices[$did] = true;
                }
            }
        }
        $ids = array_keys($devices);
        if (empty($ids)) {
            return [];
        }
        // 防爆上限：最多展示前 N 个引用
        $ids = array_slice($ids, 0, self::MAX_REFERENCES);
        $labelMap = $this->resolveDeviceLabels($ids);

        $refs = [];
        foreach ($ids as $did) {
            $info = $labelMap[$did] ?? null;
            $model = trim((string)($info['model'] ?? ''));
            $serial = trim((string)($info['serial'] ?? ''));
            $tail = $serial !== '' ? mb_substr($serial, -6) : '';
            if ($model !== '' && $tail !== '') {
                $label = $model . ' · ' . $tail;
            } elseif ($model !== '') {
                $label = $model;
            } elseif ($tail !== '') {
                $label = '设备 ' . $tail;
            } else {
                $label = '设备 #' . $did;
            }
            $refs[] = ['type' => 'device', 'id' => $did, 'label' => $label];
        }
        return $refs;
    }

    /**
     * 批量解析设备 device_id(=ErpAsset.source_device_id) → 型号 + 串号(imei 优先, 否则 sn)。
     * 一次查询, 查不到的设备留空(由调用方回退为 设备 #id)。
     * @param int[] $deviceIds
     * @return array<int, array{model:string, serial:string}>
     */
    protected function resolveDeviceLabels(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            return [];
        }
        $rows = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('source_device_id', $deviceIds)
            ->field('source_device_id, model, imei, sn')
            ->select()->toArray();
        $map = [];
        foreach ($rows as $r) {
            $sid = (int)$r['source_device_id'];
            if ($sid > 0 && !isset($map[$sid])) {
                $map[$sid] = [
                    'model'  => (string)($r['model'] ?? ''),
                    'serial' => (string)($r['imei'] ?? '') ?: (string)($r['sn'] ?? ''),
                ];
            }
        }
        return $map;
    }
}
