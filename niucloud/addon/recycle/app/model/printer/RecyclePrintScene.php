<?php
declare(strict_types=1);

namespace addon\recycle\app\model\printer;

use core\base\BaseModel;

/**
 * 回收打印场景模型
 * Class RecyclePrintScene
 * @package addon\recycle\app\model\printer
 */
class RecyclePrintScene extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'scene_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_print_scene';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 获取场景清单
     * @return array
     */
    public static function sceneList(): array
    {
        return [
            'manual_device_label' => [
                'scene_key' => 'manual_device_label',
                'scene_name' => '手动打印设备标签',
                'biz_type' => 'device',
                'template_type' => 'device_label',
                'trigger_name' => '订单设备操作中点击打印设备标签',
                'auto_print' => 0,
                'description' => '用于订单列表中人工确认后打印设备标签。'
            ],
            'device_check_complete' => [
                'scene_key' => 'device_check_complete',
                'scene_name' => '质检完成后打印设备标签',
                'biz_type' => 'device',
                'template_type' => 'device_label',
                'trigger_name' => '管理端完成设备质检后自动触发',
                'auto_print' => 0,
                'description' => '适合质检台完成质检后自动打设备标签，默认关闭，开启后按配置份数打印。'
            ],
        ];
    }

    /**
     * 获取场景名称
     * @param string $sceneKey
     * @return string
     */
    public static function getSceneName(string $sceneKey): string
    {
        $scenes = self::sceneList();
        return $scenes[$sceneKey]['scene_name'] ?? $sceneKey;
    }
}
