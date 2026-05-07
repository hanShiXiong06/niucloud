<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\printer;

use addon\recycle\app\service\admin\printer\RecyclePrintSceneService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * 回收打印场景控制器
 * Class PrintScene
 * @package addon\recycle\app\adminapi\controller\printer
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
            ['auto_print', 0],
            ['template_id', 0],
            ['printer_id', 0],
            ['copies', 1],
            ['status', 1],
            ['sort', 0],
        ]);

        $this->service->saveScene($sceneKey, $data);
        return success('保存成功');
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
     * 场景选项
     * @return Response
     */
    public function options()
    {
        return success($this->service->getSceneOptions());
    }
}
