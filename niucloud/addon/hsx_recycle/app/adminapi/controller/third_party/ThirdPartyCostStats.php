<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\third_party;

use addon\hsx_recycle\app\service\admin\third_party\ThirdPartyCostStatsService;
use core\base\BaseAdminController;

/**
 * 第三方服务费用统计控制器
 * Class ThirdPartyCostStats
 * @package addon\hsx_recycle\app\adminapi\controller\third_party
 */
class ThirdPartyCostStats extends BaseAdminController
{
    /**
     * 获取费用统计列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['service_type', ''],
            ['provider_name', ''],
            ['start_date', ''],
            ['end_date', ''],
        ]);

        return success((new ThirdPartyCostStatsService())->getPage($data));
    }

    /**
     * 从API日志回填统计数据
     * @return \think\Response
     */
    public function rebuild()
    {
        $result = (new ThirdPartyCostStatsService())->rebuildStats();

        if ($result['success']) {
            return success($result, $result['message']);
        } else {
            return fail($result['message']);
        }
    }
}
