<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\printer;

use core\base\BaseModel;

/**
 * 回收打印场景模型
 * Class RecyclePrintScene
 * @package addon\hsx_recycle\app\model\printer
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
                'trigger_key' => 'device.label.manual',
                'scene_name' => '手动打印设备标签',
                'biz_type' => 'device',
                'template_type' => 'device_label',
                'trigger_name' => '订单设备操作中点击打印设备标签',
                'auto_print' => 0,
                'idempotency_scope' => 'none',
                'description' => '用于订单列表中人工确认后打印设备标签。'
            ],
            'device_check_complete' => [
                'scene_key' => 'device_check_complete',
                'trigger_key' => 'device.check.saved',
                'scene_name' => '质检后打印设备标签',
                'biz_type' => 'device',
                'template_type' => 'device_label',
                'trigger_name' => '管理端暂存或完成设备质检后自动触发',
                'auto_print' => 0,
                'idempotency_scope' => 'site_scene_device',
                'description' => '适合质检台暂存或完成质检后自动打设备标签，默认关闭，开启后同一设备同一场景只自动成功打印一次。'
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
