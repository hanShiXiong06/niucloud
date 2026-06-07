<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\api\controller\device;

use addon\hsx_recycle\app\service\core\device\CoreRecycleDeviceModelDictService;
use core\base\BaseApiController;
use think\App;

/**
 * 用户端设备型号字典。
 */
class RecycleDeviceModelDict extends BaseApiController
{
    protected CoreRecycleDeviceModelDictService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new CoreRecycleDeviceModelDictService();
    }

    public function options()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['limit', 30],
        ]);

        return success($this->service->searchLeafOptions(
            (int)$this->request->siteId(),
            (string)$data['keyword'],
            (int)$data['limit']
        ));
    }
}
