<?php
declare(strict_types=1);

namespace addon\recycle\app\api\controller\recycle;

use addon\recycle\app\service\api\DeviceQueryService;
use core\base\BaseApiController;

/**
 * 设备查询API接口清单控制器
 * Class DeviceQueryApiController
 * @package addon\recycle\app\adminapi\controller
 */
class DeviceQueryApiController extends BaseApiController
{
    // 查询 快递的物流信息
      public function getExpress()
      {
        $data = $this->request->params([
          ['express_code', ''],
          ['mobile', ''],
        ]);
        $result = (new DeviceQueryService())->getExpress($data['express_code'], $data['mobile']);
        return success($result);
      }
    
} 