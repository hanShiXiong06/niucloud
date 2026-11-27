<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\order;

/**
 * 回收整备字典
 */
class RecycleRefurbishmentDict
{
    public static function getItemOptions(): array
    {
        return [
            ['key' => 'battery', 'name' => '更换电池', 'type' => 'part'],
            ['key' => 'screen', 'name' => '更换屏幕', 'type' => 'part'],
            ['key' => 'housing', 'name' => '更换外壳', 'type' => 'part'],
            ['key' => 'clean', 'name' => '清洁消毒', 'type' => 'labor'],
            ['key' => 'repair', 'name' => '功能维修', 'type' => 'labor'],
            ['key' => 'external', 'name' => '外部维修', 'type' => 'external'],
            ['key' => 'inspection', 'name' => '复检', 'type' => 'inspection'],
        ];
    }
}
