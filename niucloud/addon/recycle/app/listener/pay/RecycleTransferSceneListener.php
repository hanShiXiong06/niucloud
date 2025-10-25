<?php
declare(strict_types=1);

namespace addon\recycle\app\listener\pay;

/**
 * 回收业务转账场景监听器
 * Class RecycleTransferSceneListener
 * @package addon\recycle\app\listener\pay
 */
class RecycleTransferSceneListener
{
    /**
     * 获取微信转账业务场景
     * @param array $data
     * @return array
     */
    public function handle(array $data = [])
    {
        return [
            'recycle_payment' => [
                'scene' => 'eshs',  // 使用框架预定义的二手回收场景
                'name' => '回收订单打款',        // 场景名称
                'desc' => '二手设备回收订单完成后的打款转账', // 场景描述
                'infos' => [
                    '回收商品名称' => '手机、电脑等二手电子设备'
                ],
                'perception' => '二手回收货款',  // 收款感知文案
            ]
        ];
    }
} 