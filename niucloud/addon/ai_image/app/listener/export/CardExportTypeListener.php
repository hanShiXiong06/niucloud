<?php
// +---------------------------------------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\ai_image\app\listener\export;

/**
 * 卡密导出数据类型查询
 */
class CardExportTypeListener
{

    public function handle()
    {
        return [
            'ai_image_card' => [
                'name' => 'AI设计卡密导出',
                'column' => [
                    'card_num' => ['name' => '卡密'],
                    'point' => ['name' => '积分'],
                    'is_use' => ['name' => '使用状态'],
                    'pid_name' => ['name' => '所属用户'],
                    'create_time' => ['name' => '创建时间'],
                ]
            ]
        ];
    }
}