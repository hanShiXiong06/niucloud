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

namespace addon\home_service\app\model\card;

use addon\home_service\app\model\order\Order;
use core\base\BaseModel;
/**
 * 会员次卡使用记录模型
 * Class CardUseRecords
 * @package app\model\card_use_records
 */
class CardUseRecords extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'record_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_card_use_records';


    /**
     * 订单
     * @return \think\model\relation\HasMany
     */
    public function order()
    {
        return $this->hasOne(Order::class, 'order_id', 'order_id');
    }
}
