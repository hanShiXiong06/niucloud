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

namespace addon\recycle\app\listener;

use addon\recycle\app\listener\device\DeviceCheckCompleteListener;
use addon\recycle\app\listener\member\MemberLoginListener;
use addon\recycle\app\listener\order\OrderAuditListener;
use addon\recycle\app\listener\order\OrderCompleteListener;
use addon\recycle\app\listener\order\OrderCreateAfterListener;
use addon\recycle\app\listener\order\OrderCreateListener;
use addon\recycle\app\listener\printer\DeviceLabelPrinterListener;
use addon\recycle\app\listener\printer\OrderPrinterListener;

/**
 * 事件监听注册，通过该文件注册的事件，可以实现事件的自动注册，无需再到配置文件去注册
 * Class ListenerConfig
 * @package addon\recycle\app\listener
 */
class ListenerConfig
{
    public static function get()
    {
        return [
            'Order-Create'               => [
                OrderCreateListener::class
            ],
            'Order-CreateAfter'          => [
                OrderCreateAfterListener::class
            ],
            'Order-Complete'             => [
                OrderCompleteListener::class
            ],
            'Order-Audit'                => [
                OrderAuditListener::class
            ],
            'Member-Login'               => [
                MemberLoginListener::class
            ],
            'Device-CheckComplete'       => [
                DeviceCheckCompleteListener::class
            ],
            // 打印监听器注册
            'printer'                    => [
                OrderPrinterListener::class, 
                DeviceLabelPrinterListener::class
            ]
        ];
    }
} 