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

namespace addon\home_service\app\api\controller\store;

use core\base\BaseAdminController;
use addon\home_service\app\service\api\store\StoreService;


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
    public function info()
    {
        return success((new StoreService())->getStoreInfo());
    }

    /**
     * 编辑门店联系人信息
     * @return \think\Response
     */
    public function editContact()
    {
        $data = $this->request->params([
            ['contact_name', ''],
            ['mobile', ''],
        ]);
        return success('EDIT_SUCCESS',(new StoreService())->editContact($data));
    }


    /**
     * 门店列表
     * @param int $id
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["lat", ""],
            ["lng", ""],
        ]);
        return success((new StoreService())->getList($data));
    }

    /**
     * 门店切换
     * @return \think\Response
     */
    public function storeSwitch()
    {
        $data = $this->request->params([
            ['store_id', ''],
        ]);
        (new StoreService())->storeSwitch($data['store_id']);
        return success();
    }


}
