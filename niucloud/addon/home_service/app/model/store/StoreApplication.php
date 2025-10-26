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

use addon\home_service\app\dict\store\StoreDict;
use app\model\member\Member;
use core\base\BaseModel;
use think\db\Query;
use app\model\sys\SysUser;


/**
 * 门店入驻模型
 * Class StoreApplication
 * @package app\model\home_service_store_application
 */
class StoreApplication extends BaseModel
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
    protected $name = 'home_service_store_application';

    /**
     * 搜索器:真实姓名
     * @param $value
     * @param $data
     */
    public function searchContactNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("contact_name", "like", "%$value%");
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
            $query->whereBetweenTime('store_application.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['store_application.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['store_application.create_time', '<=', $end_time]]);
        }
    }

    /**
     * 入驻状态
     * @param $value
     * @param $data
     */
    public function getAuditStatusNameAttr($value, $data)
    {
        return StoreDict::getStoreApplicationStatus()[$data['audit_status']] ?? '';
    }

    /**
     * 关联会员
     * @return \think\model\relation\HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }

    public function sysUser()
    {
        return $this->hasOne(SysUser::class, 'uid', 'audit_user_id');
    }


}
