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

use addon\home_service\app\dict\technician\TechnicianDict;
use app\model\member\Member;
use core\base\BaseModel;
use think\db\Query;
use addon\home_service\app\model\goods\GoodsCategory;
use addon\home_service\app\model\store\Store;
use app\model\sys\SysUser;
use  app\model\sys\SysArea;


/**
 * 师傅入驻模型
 * Class Technician
 * @package app\model\o2o_technician
 */
class TechnicianApplication extends BaseModel
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
    protected $name = 'home_service_technician_application';


    /**
     * 搜索器:真实姓名
     * @param $value
     * @param $data
     */
    public function searchRealNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("real_name", "like", "%$value%");
        }
    }


    /**
     * 搜索器: 审核状态  audit_status
     * @param $value
     * @param $data
     */
    public function searchAuditStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("audit_status", $value);
        }
    }


    /**
     * 创建时间搜索器
     * @param Query $query
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr(Query $query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('home_service_technician_application.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['home_service_technician_application.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['home_service_technician_application.create_time', '<=', $end_time]]);
        }
    }


    /**
     * 入驻状态
     * @param $value
     * @param $data
     */
    public function getAuditStatusNameAttr($value, $data)
    {
        return TechnicianDict::getTechnicianApplicationStatus()[$data['audit_status']] ?? '';
    }


    /**
     * 获取类目数据
     * @param $value
     * @param $data
     * @return array
     */
    public function getCategoryNameAttr($value, $data)
    {
        if (!isset($data['category_id']) || empty($data['category_id'])) return [];
        return (new GoodsCategory())->where([['category_id', 'in', $data['category_id']]])->field('category_name,category_id')->select()->toArray() ?? [];
    }


    /**
     * 关联会员
     * @return \think\model\relation\HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }


    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }

    public function sysUser()
    {
        return $this->hasOne(SysUser::class, 'uid', 'audit_user_id');
    }


    public function province()
    {
        return $this->hasOne(SysArea::class, 'id', 'province_id');
    }

    public function city()
    {
        return $this->hasOne(SysArea::class, 'id', 'city_id');
    }

    /**
     * 通过分类ID获取数量
     * @param $category_id
     * @return int
     * @throws \think\db\exception\DbException
     */
    public function getCountByCategoryID($category_id)
    {
        /**获取商品表中未删除且使用该分类的数据条数**/
        return $this->whereRaw("FIND_IN_SET(?, category_id)", [$category_id])->count();
    }
}
