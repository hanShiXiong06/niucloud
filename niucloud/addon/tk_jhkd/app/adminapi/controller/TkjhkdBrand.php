<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\adminapi\controller;

use core\base\BaseAdminController;
use addon\tk_jhkd\app\service\core\TkjhkdBrandService;

/**
 * 聚合快递品牌列表控制器
 * Class TkjhkdBrand
 * @package app\adminapi\controller\tkjhkd_brand
 */
class TkjhkdBrand extends BaseAdminController
{
   /**
    * 获取聚合快递品牌列表列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["site_id",""],
             ["delivery_type",""],
             ["name",""],
             ["logo",""],
             ["status",""],
             ["create_time",""],
             ["update_time",""],
             ["addon",""]
        ]);
        return success((new TkjhkdBrandService())->getPage($data));
    }
}
