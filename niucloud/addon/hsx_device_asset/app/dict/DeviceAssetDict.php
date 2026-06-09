<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\dict;

class DeviceAssetDict
{
    public const STATUS_WAIT_PHOTO = 'wait_photo';
    public const STATUS_PHOTOING = 'photoing';
    public const STATUS_PHOTO_REVIEW = 'photo_review';
    public const STATUS_PHOTO_REJECTED = 'photo_rejected';
    public const STATUS_WAIT_PRICE = 'wait_price';
    public const STATUS_PRICED = 'priced';
    public const STATUS_READY_EXPORT = 'ready_export';
    public const STATUS_EXPORTED = 'exported';
    public const STATUS_ARCHIVED = 'archived';

    public const PHOTO_STATUS_WAIT = 'wait_photo';
    public const PHOTO_STATUS_PHOTOING = 'photoing';
    public const PHOTO_STATUS_REVIEW = 'review';
    public const PHOTO_STATUS_REJECTED = 'rejected';
    public const PHOTO_STATUS_APPROVED = 'approved';

    public const PRICE_STATUS_WAIT = 'wait_price';
    public const PRICE_STATUS_PENDING = 'pending';
    public const PRICE_STATUS_PROCESSING = 'processing';
    public const PRICE_STATUS_COMPLETED = 'completed';
    public const PRICE_STATUS_REJECTED = 'rejected';

    public const EXPORT_STATUS_PENDING = 'pending';
    public const EXPORT_STATUS_EXPORTED = 'exported';

    public const MEDIA_STATUS_PENDING = 'pending';
    public const MEDIA_STATUS_APPROVED = 'approved';
    public const MEDIA_STATUS_REJECTED = 'rejected';

    public const TASK_STATUS_PENDING = 'pending';
    public const TASK_STATUS_PROCESSING = 'processing';
    public const TASK_STATUS_REVIEW = 'review';
    public const TASK_STATUS_COMPLETED = 'completed';
    public const TASK_STATUS_CANCELLED = 'cancelled';

    public const ACTION_IMPORT = 'import';
    public const ACTION_PHOTO_TASK_CREATE = 'photo_task_create';
    public const ACTION_MEDIA_SAVE = 'media_save';
    public const ACTION_MEDIA_REVIEW = 'media_review';
    public const ACTION_PHOTO_CONFIRM = 'photo_confirm';
    public const ACTION_PRICE_ORDER_CREATE = 'price_order_create';
    public const ACTION_PRICE_COMPLETE = 'price_complete';
    public const ACTION_EXPORT = 'export';

    public static function statusName(string $status): string
    {
        return [
            self::STATUS_WAIT_PHOTO => '待拍照',
            self::STATUS_PHOTOING => '拍照中',
            self::STATUS_PHOTO_REVIEW => '待复检',
            self::STATUS_PHOTO_REJECTED => '图片退回',
            self::STATUS_WAIT_PRICE => '待定价',
            self::STATUS_PRICED => '已定价',
            self::STATUS_READY_EXPORT => '待导出',
            self::STATUS_EXPORTED => '已导出',
            self::STATUS_ARCHIVED => '已归档',
        ][$status] ?? $status;
    }

    public static function photoStatusName(string $status): string
    {
        return [
            self::PHOTO_STATUS_WAIT => '待拍照',
            self::PHOTO_STATUS_PHOTOING => '拍照中',
            self::PHOTO_STATUS_REVIEW => '待复检',
            self::PHOTO_STATUS_REJECTED => '复检退回',
            self::PHOTO_STATUS_APPROVED => '图片通过',
        ][$status] ?? $status;
    }

    public static function priceStatusName(string $status): string
    {
        return [
            self::PRICE_STATUS_WAIT => '待定价',
            self::PRICE_STATUS_PENDING => '待处理',
            self::PRICE_STATUS_PROCESSING => '定价中',
            self::PRICE_STATUS_COMPLETED => '已完成',
            self::PRICE_STATUS_REJECTED => '已退回',
        ][$status] ?? $status;
    }

    public static function exportStatusName(string $status): string
    {
        return [
            self::EXPORT_STATUS_PENDING => '未导出',
            self::EXPORT_STATUS_EXPORTED => '已导出',
        ][$status] ?? $status;
    }

    public static function mediaStatusName(string $status): string
    {
        return [
            self::MEDIA_STATUS_PENDING => '待复检',
            self::MEDIA_STATUS_APPROVED => '已通过',
            self::MEDIA_STATUS_REJECTED => '已退回',
        ][$status] ?? $status;
    }

    public static function actionName(string $action): string
    {
        return [
            self::ACTION_IMPORT => '导入资产',
            self::ACTION_PHOTO_TASK_CREATE => '创建拍照任务',
            self::ACTION_MEDIA_SAVE => '保存图片/视频',
            self::ACTION_MEDIA_REVIEW => '复检图片/视频',
            self::ACTION_PHOTO_CONFIRM => '确认图片完成',
            self::ACTION_PRICE_ORDER_CREATE => '生成定价工单',
            self::ACTION_PRICE_COMPLETE => '完成定价',
            self::ACTION_EXPORT => '导出资料',
        ][$action] ?? $action;
    }
}
