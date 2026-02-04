<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\third_party;

use addon\recycle\app\service\admin\third_party\ThirdPartyApiLogService;
use core\base\BaseAdminController;

/**
 * 第三方API调用日志控制器
 * Class ThirdPartyApiLog
 * @package addon\recycle\app\adminapi\controller\third_party
 */
class ThirdPartyApiLog extends BaseAdminController
{
    /**
     * 获取API调用日志列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['service_type', ''],
            ['provider_name', ''],
            ['status', ''],
            ['start_time', ''],
            ['end_time', ''],
        ]);

        return success((new ThirdPartyApiLogService())->getPage($data));
    }

    /**
     * API调用日志详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new ThirdPartyApiLogService())->getInfo($id));
    }

    /**
     * 清理日志
     * @return \think\Response
     */
    public function clean()
    {
        $data = $this->request->params([
            ['days', 30],
        ]);

        (new ThirdPartyApiLogService())->clean($data['days']);
        return success('CLEAN_SUCCESS');
    }
}
