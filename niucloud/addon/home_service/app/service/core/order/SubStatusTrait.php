<?php


namespace addon\home_service\app\service\core\order;


use addon\home_service\app\dict\order\TechnicianOrderDict;


trait SubStatusTrait
{


    /**
     * 根据商品配置获取子状态
     * @param $orderItem 订单商品模型（需包含 is_force_departure、is_force_clock_in 字段）
     * @return string 子状态常量
     */
    public static function getSubStatus($orderItem)
    {
        // 优先判断是否需要出发
        if (isset($orderItem->is_force_departure) && !empty($orderItem->is_force_departure) && $orderItem->is_force_departure == 1) {
            return TechnicianOrderDict::SUB_STATUS_DEPART;
        }
        // 再判断是否需要拍照
        if (isset($orderItem->is_force_clock_in) && !empty($orderItem->is_force_clock_in) && $orderItem->is_force_clock_in == 1) {
            return TechnicianOrderDict::SUB_STATUS_PHOTO_TAKEN;
        }
        // 默认：直接开始服务
        return TechnicianOrderDict::SUB_STATUS_ACTION_START;
    }


}