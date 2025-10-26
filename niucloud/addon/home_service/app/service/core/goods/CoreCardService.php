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
use addon\home_service\app\model\goods\Card;
use addon\home_service\app\dict\goods\CardDict;


/**
 * 次卡业务
 * Class CoreGoodsCategoryService
 * @package addon\shop\app\service\core\goods
 */
class CoreCardService extends BaseCoreService
{


    public function __construct()
    {
        parent::__construct();
        $this->model = new Card();
    }


    /**
     * 获取有效期类型
     * @description 获取服务项目列表
     * @return \think\Response
     */
    public function getValidType()
    {
        return CardDict::getValidType();
    }


}
