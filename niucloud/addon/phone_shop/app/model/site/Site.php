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

namespace addon\phone_shop\app\model\site;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 站点(二手)管理模型
 * Class Site
 * @package addon\phone_shop\app\model\site
 */
class Site extends BaseModel
{

    

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'phone_shop_site';

    

    

    /**
     * 搜索器:站点(二手)管理
     * @param $value
     * @param $data
     */
    public function searchIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("id", $value);
        }
    }
    
    /**
     * 搜索器:站点(二手)管理商户名
     * @param $value
     * @param $data
     */
    public function searchSiteNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("site_name", $value);
        }
    }
    
    /**
     * 搜索器:站点(二手)管理身份
     * @param $value
     * @param $data
     */
    public function searchClientAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("client", $value);
        }
    }
    
    

    

    
}
