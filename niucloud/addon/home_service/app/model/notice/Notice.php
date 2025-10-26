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

namespace addon\home_service\app\model\notice;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\model\account\TechnicianAccount;
use addon\home_service\app\model\order\Order;
use core\base\BaseModel;

/**
 * 通知模型
 * Class Notice
 * @package app\model\goods
 */
class Notice extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'notice_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_notice';



    /**
     * 关联订单列表
     * @return \think\model\relation\hasOne
     */
    public function order()
    {
        return $this->hasOne(Order::class, 'order_id', 'order_id');
    }

    /**
     * 获取内容信息
     * @return \think\model\relation\hasOne
     */
    public function getTextAttr($value,$data)
    {
        dump($data);die;
        switch ($data['type']) {
            case NoticeDict::ABOUT_TO_TIMEOUT:
                // 计算剩余时间逻辑
                $remainingTime = '30分钟';
                //您的订单还有{time}超时
                break;
            case NoticeDict::TIMEOUT:
                //您已超时{time}
                break;
            case NoticeDict::GRAB_SUCCESS:
            case NoticeDict::DISPATCH_SUCCESS:
            case NoticeDict::REFUND_SUCCESS:
            case NoticeDict::ITEM_PAY_SUCCESS:
            case NoticeDict::REFUND:
            case NoticeDict::REFUND_FAIL:
            default:
                // 这些类型可能不需要额外参数
                break;
        }
    }

    /**
     * 获取账单信息
     * @return \think\model\relation\hasOne
     */
    public function getAccountAttr($value,$data)
    {
        $order_no = (new Order())->where([['order_id', '=', $data['order_id']]])->value('order_no');
        return (new TechnicianAccount())->field('account_data,create_time,related_id as order_no')->where([['related_id', '=', $order_no]])->findOrEmpty()->toArray();
    }

}
