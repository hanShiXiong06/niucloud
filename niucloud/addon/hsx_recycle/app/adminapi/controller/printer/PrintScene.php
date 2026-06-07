<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\printer;

use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * 回收打印场景控制器
 * Class PrintScene
 * @package addon\hsx_recycle\app\adminapi\controller\printer
 */
class PrintScene extends BaseAdminController
{
    /**
     * @var RecyclePrintSceneService
     */
    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecyclePrintSceneService();
    }

    /**
     * 场景列表
     * @return Response
     */
    public function lists()
    {
        return success($this->service->getList());
    }

    /**
     * 场景详情
     * @param string $sceneKey
     * @return Response
     */
    public function info(string $sceneKey)
    {
        return success($this->service->getInfo($sceneKey));
    }

    /**
     * 保存场景配置
     * @param string $sceneKey
     * @return Response
     */
    public function edit(string $sceneKey)
    {
        $data = $this->request->params([
            ['button', []],
            ['trigger', []],
            ['auto_print', 0],
            ['template_id', 0],
            ['printer_id', 0],
            ['copies', 1],
            ['status', 1],
            ['sort', 0],
            ['idempotency_scope', 'site_scene_biz'],
            ['retry_enabled', 1],
            ['max_attempts', 3],
        ]);

        $this->service->saveScene($sceneKey, $data);
        return success('保存成功');
    }

    /**
     * 添加自定义场景
     * @return Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['scene_name', ''],
            ['biz_type', 'device'],
            ['template_type', 'device_label'],
            ['button', []],
            ['trigger', []],
            ['auto_print', 0],
            ['template_id', 0],
            ['printer_id', 0],
            ['copies', 1],
            ['status', 1],
            ['sort', 0],
            ['idempotency_scope', 'site_scene_device'],
            ['retry_enabled', 1],
            ['max_attempts', 3],
        ]);

        return success('添加成功', $this->service->addScene($data));
    }

    /**
     * 删除自定义场景
     * @param string $sceneKey
     * @return Response
     */
    public function del(string $sceneKey)
    {
        $this->service->deleteScene($sceneKey);
        return success('删除成功');
    }

    /**
     * 修改场景状态
     * @param string $sceneKey
     * @return Response
     */
    public function modifyStatus(string $sceneKey)
    {
        $data = $this->request->params([
            ['status', 1],
        ]);

        $this->service->modifyStatus($sceneKey, (int)$data['status']);
        return success('状态修改成功');
    }

    /**
     * 手动打印动作
     * @return Response
     */
    public function manualActions()
    {
        $data = $this->request->params([
            ['biz_type', 'device'],
        ]);
        return success($this->service->getManualActions((string)$data['biz_type']));
    }

    /**
     * 按场景获取打印计划
     * @param string $sceneKey
     * @return Response
     */
    public function plan(string $sceneKey)
    {
        $data = $this->request->params([
            ['device_id', 0],
            ['order_id', 0],
            ['return_order_id', 0],
            ['consignment_id', 0],
            ['biz_id', 0],
            ['print_data_override', []],
        ]);
        $result = $this->service->resolvePlanBySceneKey($sceneKey, [
            'device_id' => (int)$data['device_id'],
            'order_id' => (int)$data['order_id'],
            'return_order_id' => (int)$data['return_order_id'],
            'consignment_id' => (int)$data['consignment_id'],
            'biz_id' => (int)$data['biz_id'],
            'print_data_override' => is_array($data['print_data_override']) ? $data['print_data_override'] : [],
        ], false);
        if (!empty($result['can_print'])) {
            unset($result['template_info'], $result['printer_info'], $result['device_data'], $result['print_data']);
            return success($result);
        }
        return fail($result['message'] ?? '打印计划不可用', $result);
    }

    /**
     * 按场景打印
     * @param string $sceneKey
     * @return Response
     */
    public function print(string $sceneKey)
    {
        $data = $this->request->params([
            ['device_id', 0],
            ['order_id', 0],
            ['return_order_id', 0],
            ['consignment_id', 0],
            ['biz_id', 0],
            ['print_data_override', []],
        ]);
        $result = $this->service->printScene($sceneKey, [
            'device_id' => (int)$data['device_id'],
            'order_id' => (int)$data['order_id'],
            'return_order_id' => (int)$data['return_order_id'],
            'consignment_id' => (int)$data['consignment_id'],
            'biz_id' => (int)$data['biz_id'],
            'print_data_override' => is_array($data['print_data_override']) ? $data['print_data_override'] : [],
        ]);
        if (!empty($result['success'])) {
            return success($result);
        }
        return fail($result['message'] ?? '打印失败', $result);
    }

    /**
     * 场景选项
     * @return Response
     */
    public function options()
    {
        return success($this->service->getSceneOptions());
    }
}
