<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\export;

/**
 * 订单导出类型监听器
 */
class OrderExportTypeListener
{
    public function handle($data)
    {
        return [
            'sd_xiaoyuan_order' => '校园帮订单',
            'sd_xiaoyuan_task' => '校园帮任务',
        ];
    }
}
