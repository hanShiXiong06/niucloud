<?php

namespace addon\tk_jhkd\app\model;

use core\base\BaseModel;


/**
 * 聚合快递通知列表模型
 * Class TkjhkdNotice
 * @package app\model\tkjhkd_notice
 */
class OrderDeliveryReal extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'order_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tkjhkd_order_delivery_real';


}
