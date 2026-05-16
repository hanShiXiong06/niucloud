<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\third_party;

use addon\recycle\app\service\admin\third_party\ThirdPartyServiceService;
use core\base\BaseAdminController;

/**
 * 第三方服务配置控制器
 * Class ThirdPartyService
 * @package addon\recycle\app\adminapi\controller\third_party
 */
class ThirdPartyService extends BaseAdminController
{
    /**
     * 获取第三方服务列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['service_type', ''],
            ['provider_name', ''],
            ['status', ''],
        ]);

        return success((new ThirdPartyServiceService())->getPage($data));
    }

    /**
     * 第三方服务详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new ThirdPartyServiceService())->getInfo($id));
    }

    /**
     * 添加第三方服务
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['service_type', ''],
            ['provider_name', ''],
            ['priority', 1],
            ['config', []],
            ['status', 1],
            ['balance', 0],
            ['min_balance_alert', 100],
        ]);

        $id = (new ThirdPartyServiceService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑第三方服务
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['service_type', ''],
            ['provider_name', ''],
            ['priority', 1],
            ['config', []],
            ['status', 1],
            ['balance', 0],
            ['min_balance_alert', 100],
        ]);

        (new ThirdPartyServiceService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除第三方服务
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new ThirdPartyServiceService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 修改状态
     * @param int $id
     * @return \think\Response
     */
    public function modifyStatus(int $id)
    {
        $data = $this->request->params([
            ['status', 1],
        ]);
        (new ThirdPartyServiceService())->modifyStatus($id, $data['status']);
        return success('MODIFY_SUCCESS');
    }
}
