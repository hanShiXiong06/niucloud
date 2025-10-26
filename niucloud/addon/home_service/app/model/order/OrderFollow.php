<?php

namespace addon\home_service\app\model\order;

use core\base\BaseModel;
use app\model\sys\SysUser;
use addon\home_service\app\dict\order\OrderFollowDict;


/**
 * 订单回访
 */
class OrderFollow extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'follow_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_order_follow';

    //类型
    protected $type = [

    ];


    public function sysUser()
    {
        return $this->hasOne(SysUser::class, 'uid', 'follow_staff');
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
            $query->whereBetweenTime('order_follow.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['order_follow.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['order_follow.create_time', '<=', $end_time]]);
        }
    }


    /**
     * @param $value
     * @param $data
     * @return mixed|void
     * @throws \Exception
     */
    public function getFeeSituationNameAttr($value, $data)
    {
        if (isset($data['fee_situation'])) {
            return OrderFollowDict::getFeeSituation()[$data['fee_situation']] ?? '';
        }
    }


    /**
     * @param $value
     * @param $data
     * @return mixed|void
     * @throws \Exception
     */
    public function getResultFeedbackNameAttr($value, $data)
    {
        if (isset($data['result_feedback'])) {
            return OrderFollowDict::getFollowResult()[$data['result_feedback']] ?? '';
        }
    }

    /**
     * @param $value
     * @param $data
     * @return mixed|void
     * @throws \Exception
     */
    public function getFollowTimeAttr($value, $data)
    {
        if (isset($data['follow_time'])) {
            return date('Y-m-d H:i:s',$data['follow_time']);
        }
    }


}

