<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** 商城上架状态与责任人待办的唯一映射。 */
final class ErpListingWorkflow
{
    public const TASK_PHOTO = 'erp_listing_photo';
    public const TASK_PRICE = 'erp_listing_price';
    public const TASK_PUBLISH = 'erp_listing_publish';

    public static function statusFromPolicy(array $policy): string
    {
        if ((int)($policy['can_prepare_mall'] ?? 0) !== 1) return 'none';
        if ((int)($policy['can_list_mall'] ?? 0) === 1) return 'ready';
        $missing = array_values((array)($policy['missing_fields'] ?? []));
        if (in_array('image', $missing, true)) return 'need_photo';
        if (in_array('retail_price', $missing, true)) return 'need_price';
        return 'need_material';
    }

    public static function taskStage(array $asset): string
    {
        if ((string)($asset['status'] ?? '') !== 'in_stock'
            || (string)($asset['sale_target'] ?? '') !== 'mall'
            || in_array((string)($asset['refurbish_status'] ?? 'none'), ['pending', 'processing', 'failed'], true)) {
            return '';
        }
        return match ((string)($asset['listing_status'] ?? 'none')) {
            'need_photo' => self::TASK_PHOTO,
            'need_price' => self::TASK_PRICE,
            'need_material', 'ready', 'pending_shop' => self::TASK_PUBLISH,
            default => '',
        };
    }

    public static function taskName(string $stage): string
    {
        return match ($stage) {
            self::TASK_PHOTO => '待拍照',
            self::TASK_PRICE => '待商城定价',
            self::TASK_PUBLISH => '待完善资料并上架',
            default => '待处理',
        };
    }

    public static function listStatus(string $stage): string
    {
        return match ($stage) {
            self::TASK_PHOTO => 'need_photo',
            self::TASK_PRICE => 'need_price',
            self::TASK_PUBLISH => 'publish',
            default => '',
        };
    }
}
