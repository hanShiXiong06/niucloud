<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\stat;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;

/**
 * 回收工单「环节」字典
 *
 * 环节是任务系统的基本单位：每个环节对应一个角色的待办、一段设备状态、一组统计指标。
 * 这里集中定义：① 有哪些环节（配置/看板用）② 设备状态 → 环节 的映射（埋点用）。
 */
class RecycleStageDict
{
    const STAGE_SIGN     = 'sign';      // 待签收（订单级：到件包裹签收）
    const STAGE_PICKUP   = 'pickup';    // 待取货（订单级：物流车到点后取回门店）
    const STAGE_CHECK    = 'check';     // 质检（待质检/质检中，且订单已签收）
    const STAGE_PRICE    = 'price';     // 定价（已质检待定价/已定价）
    const STAGE_CONFIRM  = 'confirm';   // 报价确认（待确认）
    const STAGE_PAY      = 'pay';       // 打款（已回收待打款）→ 打款后入库 ERP，回收系统生命周期结束
    const STAGE_ABNORMAL = 'abnormal';  // 异常（退回）

    /** 订单级环节（工单单位是订单而非设备）。其余环节均为设备级。 */
    public static function isOrderStage(string $stageKey): bool
    {
        return in_array($stageKey, [self::STAGE_PICKUP, self::STAGE_SIGN], true);
    }

    /**
     * 环节定义（环节↔角色配置页 / 看板分区 用）
     * 回收系统边界：签收→质检→定价→报价确认→打款（入库ERP后结束）；处置/销售由其他插件负责。
     */
    public static function getStages(): array
    {
        return [
            ['stage_key' => self::STAGE_PICKUP,   'name' => '待取货',   'sort' => 1],
            ['stage_key' => self::STAGE_SIGN,     'name' => '待签收',   'sort' => 2],
            ['stage_key' => self::STAGE_CHECK,    'name' => '质检',     'sort' => 3],
            ['stage_key' => self::STAGE_PRICE,    'name' => '定价',     'sort' => 4],
            ['stage_key' => self::STAGE_CONFIRM,  'name' => '报价确认', 'sort' => 5],
            ['stage_key' => self::STAGE_PAY,      'name' => '打款',     'sort' => 6],
            ['stage_key' => self::STAGE_ABNORMAL, 'name' => '异常处理', 'sort' => 7],
        ];
    }

    /**
     * 环节 → 该环节动作的权限key（任务分发：有此权限的角色即负责该环节）
     * 角色↔权限统一交给 ERP/核心管理，这里只声明"哪个环节对应哪个动作权限"。
     */
    public static function getStagePermissions(): array
    {
        return [
            self::STAGE_PICKUP   => ['recycle_order_pickup'],
            // 待签收 = 实际的两个动作权限：代下单(recycle_order_add) / 签收订单(recycle_order_edit，PUT action=order_sign)
            self::STAGE_SIGN     => ['recycle_order_add', 'recycle_order_edit'],
            self::STAGE_CHECK    => ['recycle_device_check'],
            self::STAGE_PRICE    => ['recycle_device_confirm_price', 'recycle_device_re_confirm_price'],
            self::STAGE_CONFIRM  => ['recycle_device_batch_recycle', 'recycle_device_batch_return'],
            // ERP 接管财务时，允许拥有 ERP 应付权限的财务人员成为待打款责任人。
            self::STAGE_PAY      => ['recycle_order_payment_confirm', 'hsx_erp_payable', 'hsx_erp_confirm_payment'],
            self::STAGE_ABNORMAL => ['recycle_device_batch_return'],
        ];
    }

    /**
     * 环节 → 设备状态集合（任务队列查询用，stageOfStatus 的反向）
     */
    public static function getStageStatuses(): array
    {
        // 仅设备级环节。待签收(sign)是订单级，不在此映射，由 TaskService 单独按订单状态查询。
        return [
            self::STAGE_CHECK    => [RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK, RecycleOrderDict::DEVICE_STATUS_CHECKING],
            self::STAGE_PRICE    => [RecycleOrderDict::DEVICE_STATUS_CHECKED, RecycleOrderDict::DEVICE_STATUS_PRICED, RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE],
            self::STAGE_CONFIRM  => [RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM],
            self::STAGE_PAY      => [RecycleOrderDict::DEVICE_STATUS_RECYCLED],
            self::STAGE_ABNORMAL => [RecycleOrderDict::DEVICE_STATUS_RETURNED],
        ];
    }

    /**
     * 一组环节 → 对应的全部设备状态（去重）
     */
    public static function statusesOfStages(array $stageKeys): array
    {
        $map = self::getStageStatuses();
        $out = [];
        foreach ($stageKeys as $key) {
            if (isset($map[$key])) {
                $out = array_merge($out, $map[$key]);
            }
        }
        return array_values(array_unique($out));
    }

    /** 已回收状态值（待打款/已打款待处置 共用此状态，靠 pay_status 细分） */
    public static function statusRecycled(): int
    {
        return RecycleOrderDict::DEVICE_STATUS_RECYCLED;
    }

    /**
     * 设备状态(+打款状态) → 环节key。
     * status=5(已回收)：未打款→打款环节(pay)；已打款→已入库 ERP，回收系统生命周期结束，离场('')。
     */
    public static function stageOf(int $status, int $payStatus = 0): string
    {
        if ($status === RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
            return $payStatus > 0 ? '' : self::STAGE_PAY;
        }
        return self::stageOfStatus($status);
    }

    /**
     * 设备状态 → 环节key（埋点映射）
     * 返回 '' 表示离场（新建未入场 / 已移除），不计入任何环节。
     * 注意：打款后的「处置销售」环节由 pay_status 决定，不在设备状态里，
     *       需用 stageOf($status,$payStatus) 才能区分 打款/处置。
     */
    public static function stageOfStatus(int $status): string
    {
        switch ($status) {
            case RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK:   // 1 待质检
            case RecycleOrderDict::DEVICE_STATUS_CHECKING:        // 2 质检中
                return self::STAGE_CHECK;
            case RecycleOrderDict::DEVICE_STATUS_CHECKED:         // 3 已质检 = 待定价
            case RecycleOrderDict::DEVICE_STATUS_PRICED:          // 7 已定价
            case RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE:  // 8 重新定价
                return self::STAGE_PRICE;
            case RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM: // 4 待确认
                return self::STAGE_CONFIRM;
            case RecycleOrderDict::DEVICE_STATUS_RECYCLED:        // 5 已回收 = 待打款（已打款由 stageOf 判离场）
                return self::STAGE_PAY;
            case RecycleOrderDict::DEVICE_STATUS_RETURNED:        // 6 已退回
                return self::STAGE_ABNORMAL;
            case RecycleOrderDict::DEVICE_STATUS_CONSIGNED:       // 9 已转代卖 → 出回收系统范围
            default:
                return '';
        }
    }
}
