<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\AiService;
use addon\hsx_erp\app\service\admin\AiConversationService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * AI 助手接口
 * Class Ai
 * @package addon\hsx_erp\app\adminapi\controller
 */
class Ai extends BaseAdminController
{
    protected AiService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new AiService();
    }

    /**
     * 读取站点 AI 配置（密钥脱敏）
     */
    public function config(): Response
    {
        return success($this->service->getConfig());
    }

    /**
     * 保存站点 AI 配置
     */
    public function saveConfig(): Response
    {
        $data = $this->request->params([
            ['enabled', 0],
            ['base_url', ''],
            ['api_key', ''],
            ['default_model', ''],
            ['available_models', []],
            ['timeout', 60],
            ['daily_token_limit', 0],
        ]);
        $this->service->saveConfig($data);
        return success();
    }

    /**
     * 拉取可用模型（云雾 /v1/models）
     */
    public function models(): Response
    {
        return success($this->service->models());
    }

    /**
     * 连通性测试
     */
    public function ping(): Response
    {
        $model = (string)$this->request->param('model', '');
        return success($this->service->ping($model));
    }

    /**
     * 聊天测试
     */
    public function chat(): Response
    {
        $data = $this->request->params([
            ['messages', []],
            ['prompt', ''],
            ['model', ''],
        ]);
        return success($this->service->chat(
            is_array($data['messages']) ? $data['messages'] : [],
            (string)$data['prompt'],
            (string)$data['model']
        ));
    }

    /**
     * 场景清单
     */
    public function scenes(): Response
    {
        return success($this->service->scenes());
    }

    /**
     * 按场景执行（业务页通用入口）
     */
    public function run(): Response
    {
        $data = $this->request->params([
            ['scene', 'general'],
            ['context', ''],
            ['question', ''],
            ['model', ''],
            ['history', []],
        ]);
        return success($this->service->run((string)$data['scene'], [
            'context'  => $data['context'],
            'question' => (string)$data['question'],
            'model'    => (string)$data['model'],
            'history'  => is_array($data['history']) ? $data['history'] : [],
        ]));
    }

    /**
     * 按场景执行（SSE 流式：进度状态 + 逐字答案）
     */
    public function stream()
    {
        $data = $this->request->params([
            ['scene', 'general'],
            ['context', ''],
            ['question', ''],
            ['model', ''],
            ['history', []],
        ]);

        // 关闭输出缓冲，准备 SSE
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ignore_user_abort(true);
        @set_time_limit(0);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $emit = function (string $event, $payload) {
            echo 'event: ' . $event . "\n";
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();
        };

        $this->service->runStream((string)$data['scene'], [
            'context'  => $data['context'],
            'question' => (string)$data['question'],
            'model'    => (string)$data['model'],
            'history'  => is_array($data['history']) ? $data['history'] : [],
        ], $emit);

        $emit('end', ['ok' => true]);
        exit;
    }

    /**
     * 对话历史列表
     */
    public function conversations(): Response
    {
        $scene = (string)$this->request->param('scene', '');
        return success((new AiConversationService())->lists($scene));
    }

    /**
     * 对话详情
     */
    public function conversationDetail(int $id): Response
    {
        return success((new AiConversationService())->detail($id));
    }

    /**
     * 保存对话（新建/更新）
     */
    public function conversationSave(): Response
    {
        $data = $this->request->params([
            ['id', 0],
            ['scene', 'general'],
            ['title', ''],
            ['messages', []],
            ['tokens', 0],
        ]);
        $id = (new AiConversationService())->save([
            'id'       => (int)$data['id'],
            'scene'    => (string)$data['scene'],
            'title'    => (string)$data['title'],
            'messages' => is_array($data['messages']) ? $data['messages'] : [],
            'tokens'   => (int)$data['tokens'],
        ]);
        return success(['id' => $id]);
    }

    /**
     * 删除对话
     */
    public function conversationDelete(int $id): Response
    {
        (new AiConversationService())->remove($id);
        return success();
    }
}
