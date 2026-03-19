<?php

namespace addon\sd_xiaoyuan\app\model\order;

use core\base\BaseModel;

class Order extends BaseModel
{
    protected $name = 'xiaoyuan_order';
    protected $pk = 'id';

    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            0 => '待支付',
            10 => '待接单',
            20 => '已接单',
            30 => '取货中',
            40 => '配送中',
            50 => '已完成',
            90 => '已取消',
            91 => '已退款'
        ];
        return $statusMap[$data['status']] ?? '未知';
    }

    public function getTaskTypeTextAttr($value, $data)
    {
        $typeMap = [
            'EXPRESS' => '代取快递',
            'BUY' => '代买',
            'ERRAND' => '跑腿',
            'QUEUE' => '代排队',
            'PRINT' => '代打印',
            'SEAT' => '代占座',
            'CLEAN' => '代清洁',
            'TRASH' => '扔垃圾',
            'CARRY' => '帮搬运',
            'HELP' => '帮帮忙'
        ];
        return $typeMap[$data['task_type']] ?? '未知';
    }
}
