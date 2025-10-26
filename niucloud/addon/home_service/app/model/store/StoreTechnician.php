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

namespace addon\home_service\app\model\store;


use core\base\BaseModel;
use  addon\home_service\app\model\technician\Technician;

/**
 * 门店师傅表
 */
class StoreTechnician extends BaseModel
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
    protected $name = 'home_service_store_technician';


    /**
     * 搜索器
     * @param $value
     * @param $data
     */
    public function searchStoreIdAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("store_id", '=', $value);
        }
    }


    /**
     * 师傅关联（直接配置 append 和默认返回字段）
     * @return HasOne
     */
    public function technician()
    {
        return $this->hasOne(Technician::class, 'id', 'technician_id');
    }


}
