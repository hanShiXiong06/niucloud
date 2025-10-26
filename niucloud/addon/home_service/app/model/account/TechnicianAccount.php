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


use addon\home_service\app\model\order\Order;
use app\dict\sys\FileDict;
use core\base\BaseModel;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\dict\account\AccountDict;
use think\db\Query;

/**
 * 师傅账单模型
 * Class Technician
 * @package app\model\o2o_technician
 */
class TechnicianAccount extends BaseModel
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
    protected $name = 'home_service_technician_account';


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
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != 'all') {
            $query->where("status", '=', $value);
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
            $query->whereBetweenTime('technician_account.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['technician_account.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['technician_account.create_time', '<=', $end_time]]);
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
     * 搜索器
     * @param $value
     * @param $data
     */
    public function searchTechnicianIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("technician_id", '=', $value);
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


    public function technician()
    {
        return $this->hasOne(Technician::class, 'id', 'technician_id');
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'order_no', 'related_id');
    }

    /**
     * 头像
     * @param $value
     * @param $data
     */
    public function getHeadimgMidAttr($value, $data)
    {
        $thumb = '';
        if ($data['headimg'] != '') {
            $thumb = get_thumb_images($data['site_id'], $data['headimg'], FileDict::SMALL);
        }
        return $thumb;
    }

}

