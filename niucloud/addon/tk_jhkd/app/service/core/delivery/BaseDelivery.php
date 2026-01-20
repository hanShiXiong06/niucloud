<?php

namespace addon\tk_jhkd\app\service\core\delivery;

use core\loader\Storage;

/**
 * Class BaseChat
 */
abstract class BaseDelivery extends Storage
{
    /**
     * @var array
     */
    private $config;

    /**
     * 初始化
     * @param array $config
     * @return void
     */
    protected function initialize(array $config = [])
    {
        $this->config = $config;
    }
    //发送订单
    abstract protected function sendOrder($params);
    //预下单
    abstract protected function preOrder($params);
    //取消订单
    abstract protected function cancelOrder($params);
    abstract protected function callbackOrder($data);
    //获取余额
    abstract protected function getBalance();
    //查询物流轨迹
    abstract protected function deliveryTrance($params);
}
