<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\printer;

use addon\hsx_recycle\app\service\admin\printer\RecyclePrintLogService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * 回收打印日志控制器
 * Class PrintLog
 * @package addon\hsx_recycle\app\adminapi\controller\printer
 */
class PrintLog extends BaseAdminController
{
    /**
     * @var RecyclePrintLogService
     */
    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecyclePrintLogService();
    }

    /**
     * 打印日志列表
     * @return Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['scene_key', ''],
            ['status', ''],
            ['keyword', ''],
        ]);

        return success($this->service->getPage($data));
    }
}
