<?php

namespace addon\sd_xiaoyuan\app\dict\order;

class OrderDict
{
    const STATUS_WAIT_PAY = 0;
    const STATUS_WAIT_ACCEPT = 10;
    const STATUS_ACCEPTED = 20;
    const STATUS_PICKING = 30;
    const STATUS_DELIVERING = 40;
    const STATUS_COMPLETED = 50;
    const STATUS_CANCELLED = 90;
    const STATUS_REFUNDED = 91;
    
    const TASK_TYPE_EXPRESS = 'EXPRESS';
    const TASK_TYPE_BUY = 'BUY';
    const TASK_TYPE_SEND = 'SEND';
    const TASK_TYPE_ERRAND = 'ERRAND';
    const TASK_TYPE_QUEUE = 'QUEUE';
    const TASK_TYPE_PRINT = 'PRINT';
    const TASK_TYPE_SEAT = 'SEAT';
    const TASK_TYPE_TRASH = 'TRASH';
    const TASK_TYPE_CARRY = 'CARRY';
    const TASK_TYPE_CLEAN = 'CLEAN';
    const TASK_TYPE_HELP = 'HELP';
    const TASK_TYPE_GAME = 'GAME';
    const TASK_TYPE_GROUP = 'GROUP';
    
    public static function getStatus()
    {
        return [
            self::STATUS_WAIT_PAY => '待支付',
            self::STATUS_WAIT_ACCEPT => '待接单',
            self::STATUS_ACCEPTED => '已接单',
            self::STATUS_PICKING => '取货中',
            self::STATUS_DELIVERING => '配送中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_REFUNDED => '已退款'
        ];
    }
    
    public static function getTaskType()
    {
        return [
            self::TASK_TYPE_EXPRESS => '代取快递',
            self::TASK_TYPE_BUY => '帮我买',
            self::TASK_TYPE_SEND => '帮我送',
            self::TASK_TYPE_ERRAND => '跑腿',
            self::TASK_TYPE_QUEUE => '代排队',
            self::TASK_TYPE_PRINT => '代打印',
            self::TASK_TYPE_SEAT => '代占座',
            self::TASK_TYPE_TRASH => '扔垃圾',
            self::TASK_TYPE_CARRY => '帮搬运',
            self::TASK_TYPE_CLEAN => '代清洁',
            self::TASK_TYPE_HELP => '帮帮忙',
            self::TASK_TYPE_GAME => '游戏陪练',
            self::TASK_TYPE_GROUP => '拼单'
        ];
    }
}
