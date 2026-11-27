<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\order;

/**
 * 下游流转阶段字典（回收设备的下游生命周期镜像）
 *
 * 回收侧在打款/回收完成后把设备交给 ERP（入库）和数据中台（拍照定价），
 * 此后设备的去向由下游事件回流到回收设备的 downstream_stage 字段。
 * 阶段值采用 10 的步进，预留中间态扩展空间；只前进、不回退（幂等）。
 *
 * Class RecycleDownstreamDict
 * @package addon\hsx_recycle\app\dict\order
 */
class RecycleDownstreamDict
{
    /** 未流转（默认） */
    public const STAGE_NONE = 0;
    /** 已入库（ERP erp.asset.stocked.v1） */
    public const STAGE_STOCKED = 10;
    /** 转中台·待拍照定价（ERP erp.asset.ready_for_photo.v1） */
    public const STAGE_READY_FOR_PHOTO = 20;
    /** 已定价·可售（数据中台 device_asset.price.completed.v1） */
    public const STAGE_PRICED = 30;
    /** 已售/已下架（预留，待 shop 插件接入行情/销售） */
    public const STAGE_SOLD = 40;
    /** ERP 已完成采购退货，设备已经退回客户 */
    public const STAGE_PURCHASE_RETURN_PENDING = 50;
    /** ERP 采购退货款已经实际到账 */
    public const STAGE_PURCHASE_RETURN_SETTLED = 60;

    /**
     * 阶段名称映射
     * @return array<int,string>
     */
    public static function stageMap(): array
    {
        return [
            self::STAGE_NONE => '未流转',
            self::STAGE_STOCKED => '已入库',
            self::STAGE_READY_FOR_PHOTO => '转中台·待拍照',
            self::STAGE_PRICED => '已定价·可售',
            self::STAGE_SOLD => '已售/下架',
            self::STAGE_PURCHASE_RETURN_PENDING => 'ERP采退·已退回',
            self::STAGE_PURCHASE_RETURN_SETTLED => 'ERP采退·已到账',
        ];
    }

    /**
     * 阶段名称
     * @param int $stage
     * @return string
     */
    public static function stageName(int $stage): string
    {
        return self::stageMap()[$stage] ?? '';
    }
}
