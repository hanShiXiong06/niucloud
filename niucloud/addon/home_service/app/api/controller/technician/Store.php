<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\api\controller\technician;

use core\base\BaseAdminController;
use addon\home_service\app\service\api\technician\StoreService;


/**
 * 门店
 * Class Reserve
 * @package app\adminapi\controller
 */
class Store extends BaseAdminController
{
    /**
     * 门店列表详情
     * @param int $id
     * @return \think\Response
     */
    public function getStoreList()
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["lng", ""],
            ["lat", ""],
        ]);
        return success((new StoreService())->getStoreList($data));
    }

    /**
     * 我的门店数据
     * @param int $id
     * @return \think\Response
     */
    public function getMyStore()
    {
        return success((new StoreService())->getMyStore());
    }


}
