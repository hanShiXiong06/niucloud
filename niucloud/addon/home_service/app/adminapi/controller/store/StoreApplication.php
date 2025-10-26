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

namespace addon\home_service\app\adminapi\controller\store;

use addon\home_service\app\dict\store\StoreDict;
use addon\home_service\app\service\admin\store\StoreApplicationService;
use addon\shop\app\dict\delivery\DeliveryDict;
use core\base\BaseAdminController;


/**
 * 门店入驻控制器
 * @description 门店入驻申请
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class StoreApplication extends BaseAdminController
{
    /**
     * 门店入驻申请分页列表
     * @description 获 门店入驻申请分页列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["nickname", ""],
            ["mobile", ""],
            ["create_time", ""],
            ['real_name', ''],
            ['audit_status', ''],
            ['store_name', ''],
            ['contact_name', ''],
        ]);
        return success((new StoreApplicationService())->getPage($data));
    }


    /**
     * 门店入驻状态
     * @description 获 门店入驻申请分页列表
     * @return \think\Response
     */
    public function getStatus()
    {
        return success(StoreDict::getStoreApplicationStatus());
    }


    /**
     * 门店入驻申请详情
     * @description 门店入驻申请详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new StoreApplicationService())->getInfo($id));
    }


    /**
     * 审核操作
     * @description 添 门店入驻申请
     * @return \think\Response
     */
    public function examine(int $id)
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["contact_name", ""],
            ["headimg", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ['mobile', ''],
            ['license_img', ''],
            ['apply_desc', ''],
            ["province_id", 0],
            ["city_id", 0],
            ["district_id", 0],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
            ["member_id", ''],
            ['audit_status', ''],
            ['audit_remark', ''],
            ['service_ratio', 0],
        ]);
        $id = (new StoreApplicationService())->examine($data, $id);
        return success('ADD_SUCCESS', ['id' => $id]);
    }


}
