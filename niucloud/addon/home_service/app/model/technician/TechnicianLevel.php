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
 * 师傅等级模型
 * Class Technician
 * @package app\model\o2o_technician
 */
class TechnicianLevel extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'level_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_technician_level';



    /**
     * 搜索器
     * @param $value
     */
    public function searchLevelNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('level_name', 'like', '%' . $value . '%');
        }
    }



}
