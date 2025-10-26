<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\core\goods;


use core\base\BaseCoreService;
use addon\home_service\app\model\goods\GoodsCategory;


/**
 * 商品分类
 * Class CoreGoodsCategoryService
 * @package addon\shop\app\service\core\goods
 */
class CoreGoodsCategoryService extends BaseCoreService
{
    //系统配置文件

    public function __construct()
    {
        parent::__construct();
        $this->model = new GoodsCategory();
    }




}
