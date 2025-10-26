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

namespace addon\home_service\app\model\account;


use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\model\store\Store;
use app\model\member\Member;
use core\base\BaseModel;
use think\db\Query;
use think\model\relation\HasOne;

/**
 * 门店模型
 * Class Technician
 * @package app\model\o2o_technician
 */
class StoreAccount extends BaseModel
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
    protected $name = 'home_service_store_account';


    /**
     * 搜索器
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != 'all') {
            $query->where("status", '=', $value);
        }
    }

    /**
     * 搜索器
     * @param $value
     * @param $data
     */
    public function searchFromTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("from_type", '=', $value);
        }
    }

    /**
     * 搜索器
     * @param $value
     * @param $data
     */
    public function searchStoreIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("store_id", '=', $value);
        }
    }

    /**
     * 搜索器
     * @param $value
     * @param $data
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('related_id', 'like', '%' . $value . '%');
        }
    }




    /**
     * 创建时间搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchJoinCreateTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('store_account.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['store_account.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['store_account.create_time', '<=', $end_time]]);
        }
    }


    /**
     * 创建时间搜索器
     * @param $value
     */
    public function searchCreateTimeAttr(Query $query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['create_time', '<=', $end_time]]);
        }
    }


    /**
     * 结算状态
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getStatusNameAttr($value, $data)
    {
        return AccountDict::getStatus()[$data['status']] ?? '';
    }


    /**
     * 类型
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getFromTypeNameAttr($value, $data)
    {
        return AccountDict::getType()[$data['from_type']] ?? '';
    }






    /**
     * 会员关联
     * @return HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id')->withField('member_id, member_no, username, mobile, nickname, headimg')->joinType('inner');
    }


    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }

}
