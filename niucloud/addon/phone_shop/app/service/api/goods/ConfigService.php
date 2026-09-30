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

namespace addon\phone_shop\app\service\api\goods;

use addon\phone_shop\app\service\core\goods\CoreGoodsConfigService;
use core\base\BaseApiService;


/**
 * 商品分类服务层
 * Class CategoryService
 * @package addon\phone_shop\app\service\admin\goods
 */
class ConfigService extends BaseApiService
{
    public $core__goods_config_service;
    public function __construct()
    {
        parent::__construct();
        $this->core__goods_config_service = new CoreGoodsConfigService();
    }

    /**
     * 获取商品搜索配置
     * @return array
     */
    public function getSearchConfig()
    {
       return $this->core__goods_config_service->getSearchConfig($this->site_id);
    }

}
