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

namespace addon\home_service\app\model;

use core\base\BaseModel;
use  app\model\sys\SysArea;

/**
 * 城市策略表
 */
class CityStrategy extends BaseModel
{

    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_city_strategy';


    public function province()
    {
        return $this->hasOne(SysArea::class, 'id', 'province_id');
    }

    public function city()
    {
        return $this->hasOne(SysArea::class, 'id', 'city_id');
    }


}
