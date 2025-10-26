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


use addon\home_service\app\service\admin\store\StoreService;
use addon\home_service\app\service\admin\member\MemberService;
use core\base\BaseAdminController;


/**
 * 门店控制器
 * @description 师傅
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class Store extends BaseAdminController
{

    /**
     * 门店分页列表
     * @description 门店分页列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["contact_name", ""],
            ["nickname", ""],
            ["mobile", ""],
            ["create_time", ""],
        ]);
        return success('SUCCESS', (new StoreService())->getPage($data));
    }


    public function getList()
    {
        $data = $this->request->params([
            ["store_name", ""],
        ]);
        return success((new StoreService())->getList($data));
    }


    /**
     * 门店详情
     * @description 门店详情
     * @param int $store_id
     * @return \think\Response
     */
    public function info(int $store_id)
    {
        return success((new StoreService())->getInfo($store_id));
    }


    public function getSelsecMembers()
    {
        $data = $this->request->params([
            ["keyword", ""],
        ]);
        return success((new MemberService())->getPage($data));
    }


    /**
     * 添加门店
     * @description 添加门店
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["contact_name", ""],
            ["mobile", ""],
            ["member_id", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ["license_img", ""],
            ["province_id", ""],
            ["city_id", ""],
            ["district_id", ""],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
            ["service_ratio", "0.00"],
            ["headimg", "0.00"],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\store\Store.add');
        return success('SUCCESS', (new StoreService())->add($data));
    }


    /**
     * 门店编辑
     * @description 师傅编辑
     * @param $id  师傅id
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["contact_name", ""],
            ["mobile", ""],
            ["member_id", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ["license_img", ""],
            ["province_id", ""],
            ["city_id", ""],
            ["district_id", ""],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
            ["service_ratio", "0.00"],
            ["headimg", "0.00"],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\store\Store.edit');
        return success('EDIT_SUCCESS', (new StoreService())->edit($id, $data));
    }


}
