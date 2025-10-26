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

namespace addon\home_service\app\adminapi\controller\account;


use core\base\BaseAdminController;
use addon\home_service\app\service\admin\account\StoreAccountService;


/**
 * 门店账户
 * Class O2oGoodsCategory
 * @description  商品分类
 * @package app\adminapi\controller\o2o_goods_category
 */
class StoreAccount extends BaseAdminController
{
    /**
     * @description 获取分页列表
     * @return \think\Response
     */
    public function lists(int $store_id)
    {
        $data = $this->request->params([
            ["join_create_time", []]
        ]);
        $data['store_id'] = $store_id;
        return success((new StoreAccountService())->getPage($data));
    }


}
