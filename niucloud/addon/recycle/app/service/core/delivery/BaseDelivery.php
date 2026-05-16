<?php

namespace addon\recycle\app\service\core\delivery;

/**
 * 快递渠道基类
 * Class BaseDelivery
 */
abstract class BaseDelivery
{
    /**
     * @var array
     */
    protected $config;

    /**
     * 初始化
     * @param array $config
     * @return void
     */
    protected function initialize(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 发送订单（寄件人模式）
     * @param $params
     * @return array
     */
    abstract public function sendOrder($params);

    /**
     * 发送订单（收件人模式）
     * @param $params
     * @return array
     */
    abstract public function sendOrderByRecipient($params);

    /**
     * 预下单
     * @param $params
     * @return array
     */
    abstract public function preOrder($params);

    /**
     * 取消订单
     * @param $params
     * @return array
     */
    abstract public function cancelOrder($params);

    /**
     * 订单回调
     * @param $data
     * @return void
     */
    abstract public function callbackOrder($data);

    /**
     * 获取余额
     * @return string
     */
    abstract public function getBalance();

    /**
     * 查询物流轨迹
     * @param $params
     * @return array
     */
    abstract public function deliveryTrance($params);
}
