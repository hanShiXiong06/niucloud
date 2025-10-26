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

namespace addon\home_service\app\model\technician;

use core\base\BaseModel;

/**
 * 师傅休息模型
 * Class Technician
 * @package app\model\home_service_technician_rest
 */
class TechnicianRest extends BaseModel
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
    protected $name = 'home_service_technician_rest';


    /**
     * 搜索器
     * @param $value
     */
    public function searchDateAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('date', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器
     * @param $value
     */
    public function searchStoreIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('store_id', 'like', '%' . $value . '%');
        }
    }


}
