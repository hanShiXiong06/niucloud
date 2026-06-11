<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\printer;

/**
 * 打印场景字典
 */
class PrinterSceneDict
{
    /**
     * 获取内置打印场景列表
     * @return array
     */
    public static function getSceneList(): array
    {
        return [
            'manual_device_label' => [
                'scene_key' => 'manual_device_label',
                'trigger_key' => 'device.check.saved',
                'scene_name' => '设备标签打印',
                'biz_type' => 'device',
                'template_type' => 'device_label',
                'trigger_name' => '质检保存后',
                'auto_print' => 0,
                'idempotency_scope' => 'site_scene_device',
                'description' => '用于设备标签打印。可在设备列表显示手动按钮，也可绑定质检保存后自动打印。',
                'trigger_options' => [
                    [
                        'key' => 'device.check.saved',
                        'name' => '质检保存后',
                        'description' => '管理端暂存或完成设备质检后触发。',
                    ],
                ],
                'condition_config' => [
                    'button' => [
                        'enabled' => 1,
                        'text' => '打印设备标签',
                        'position' => 'device_actions',
                        'visible_device_status' => [2, 3, 4, 5],
                        'confirm_required' => 1,
                    ],
                    'trigger' => [
                        'key' => 'device.check.saved',
                        'name' => '质检保存后',
                    ],
                ],
            ],
            'refurbishment_label' => [
                'scene_key' => 'refurbishment_label',
                'trigger_key' => 'device.recycled.refurbishment_required',
                'scene_name' => '整备标签打印',
                'biz_type' => 'device',
                'template_type' => 'device_label',
                'trigger_name' => '设备确认回收且需整备后',
                'auto_print' => 0,
                'idempotency_scope' => 'site_scene_device',
                'description' => '用于需要整备的设备标签打印。设备确认回收后，如已在回收定价中标记需整备，可自动打印整备标签。',
                'trigger_options' => [
                    [
                        'key' => 'device.recycled.refurbishment_required',
                        'name' => '设备确认回收且需整备后',
                        'description' => '客户确认出售，后台将设备确认回收后触发。',
                    ],
                ],
                'condition_config' => [
                    'button' => [
                        'enabled' => 1,
                        'text' => '打印整备标签',
                        'position' => 'device_actions',
                        'visible_device_status' => [4, 5],
                        'confirm_required' => 1,
                    ],
                    'trigger' => [
                        'key' => 'device.recycled.refurbishment_required',
                        'name' => '设备确认回收且需整备后',
                    ],
                ],
            ],
            'consignment_receipt' => [
                'scene_key' => 'consignment_receipt',
                'trigger_key' => 'consignment.created',
                'scene_name' => '代卖凭证打印',
                'biz_type' => 'consignment',
                'template_type' => 'consignment_receipt',
                'trigger_name' => '转入代卖后',
                'auto_print' => 0,
                'idempotency_scope' => 'site_scene_biz',
                'description' => '用于代卖订单凭证打印。可在代卖订单操作区显示手动按钮，也可绑定转入代卖、上架、成交、结算等节点自动打印。',
                'trigger_options' => [
                    ['key' => 'consignment.created', 'name' => '转入代卖后', 'description' => '设备从回收订单转入独立代卖订单后触发。'],
                    ['key' => 'consignment.listed', 'name' => '代卖上架后', 'description' => '后台设置或更新挂牌价后触发。'],
                    ['key' => 'consignment.sold', 'name' => '代卖成交后', 'description' => '后台登记成交价和客户结算金额后触发。'],
                    ['key' => 'consignment.settled', 'name' => '代卖结算后', 'description' => '后台完成客户结算后触发。'],
                    ['key' => 'consignment.cancelled', 'name' => '取消代卖后', 'description' => '后台取消代卖订单后触发。'],
                    ['key' => 'consignment.returned', 'name' => '代卖退回后', 'description' => '后台将代卖设备退回客户后触发。'],
                ],
                'condition_config' => [
                    'button' => [
                        'enabled' => 1,
                        'text' => '打印代卖凭证',
                        'position' => 'consignment_order_actions',
                        'visible_device_status' => [0, 1, 2, 3, 4],
                        'confirm_required' => 1,
                    ],
                    'trigger' => ['key' => 'consignment.created', 'name' => '转入代卖后'],
                ],
            ],
            'return_label' => [
                'scene_key' => 'return_label',
                'trigger_key' => 'return.created',
                'scene_name' => '退货标签打印',
                'biz_type' => 'return',
                'template_type' => 'return_label',
                'trigger_name' => '创建退货单后',
                'auto_print' => 0,
                'idempotency_scope' => 'site_scene_biz',
                'description' => '用于退货设备标签打印。可在退货订单操作区显示手动按钮，也可绑定创建退货单、确认退货、完成退货等节点自动打印。',
                'trigger_options' => [
                    ['key' => 'return.created', 'name' => '创建退货单后', 'description' => '设备退回流程创建退货单后触发。'],
                    ['key' => 'return.confirmed', 'name' => '确认退货后', 'description' => '后台确认退货信息后触发。'],
                    ['key' => 'return.shipped', 'name' => '退货发货后', 'description' => '退货设备已寄出后触发。'],
                    ['key' => 'return.completed', 'name' => '退货完成后', 'description' => '客户签收退货设备后触发。'],
                ],
                'condition_config' => [
                    'button' => [
                        'enabled' => 1,
                        'text' => '打印退货标签',
                        'position' => 'return_order_actions',
                        'visible_device_status' => [0, 1, 2, 3],
                        'confirm_required' => 1,
                    ],
                    'trigger' => ['key' => 'return.created', 'name' => '创建退货单后'],
                ],
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
        $scenes = self::getSceneList();
        return $scenes[$sceneKey]['scene_name'] ?? $sceneKey;
    }
}
