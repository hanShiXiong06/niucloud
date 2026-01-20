<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\service\core\delivery\DeliveryLoader;
use app\service\core\sys\CoreConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;
use Exception;

class CoreDeliveryService extends BaseApiService
{
    public function __construct($site_id = '')
    {
        parent::__construct();
        if ($site_id != '') {
            $this->site_id = $site_id;
        }
        $this->config = $this->getConfig();
        if (!$this->config) throw new CommonException('接口配置未完成，请先完成接口配置');
    }

    public function preOrder($data)
    {
        return (new DeliveryLoader($this->config['delivery_type'], $this->config))->preOrder($data);
    }

    public function sendOrder($data)
    {
        return (new DeliveryLoader($this->config['delivery_type'], $this->config))->sendOrder($data);
    }

    public function deliveryTrance($params)
    {
        $delivery_info=(new OrderDelivery())->where(['delivery_id'=>$params['delivery_id']])->findOrEmpty();
        if($delivery_info->isEmpty()) throw new CommonException('订单不存在');
        if($delivery_info['platform']=='') return [];
        if($delivery_info['delivery_id']=='') return [];
        $config = (new CommonService())->getSiteAllDriver($this->site_id,$delivery_info['platform'])['params'];
        return (new DeliveryLoader($delivery_info['platform'], $config))->deliveryTrance($delivery_info);
    }

    public function getBalance()
    {
        return (new DeliveryLoader($this->config['delivery_type'], $this->config))->getBalance();
    }

    public function cancelOrder($data)
    {
        return (new DeliveryLoader($this->config['delivery_type'], $this->config))->cancelOrder($data);
    }

    public function getConfig()
    {

        $info = (new CoreConfigService())->getConfig($this->site_id, 'JHKD_DELIVERY')['value'] ?? [];
        if (empty($info)) throw new Exception('DELIVERY_NOT_OPEN');
        $delivery_type = $info['default'] ?? '';
        $config = array(
            'delivery_type' => $delivery_type,
        );
        return array_merge($config, $info[$delivery_type] ?? []);
    }

}