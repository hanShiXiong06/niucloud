<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** 商城上架状态与责任人待办的唯一映射。 */
final class ErpListingWorkflow
{
    public const TASK_PHOTO = 'erp_listing_photo';
    public const TASK_PRICE = 'erp_listing_price';
    public const TASK_MEDIA_PRICE = 'erp_listing_media_price';
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

    /**
     * 设备进入商城前的统一状态机。
     *
     * 仓库规则决定“能不能上商城”，这里决定“当前该谁接手”。商城商品无论由
     * ERP 直上还是交给商城运营，都必须先具备可售图片和销售价格；分类、规格
     * 则可以根据站点配置在 ERP 或商城端完成。
     */
    public static function statusFromAsset(array $asset, array $policy): string
    {
        if ((int)($policy['can_prepare_mall'] ?? 0) !== 1) return 'none';
        if (!self::hasImages($asset['image_urls'] ?? '')) return 'need_photo';
        if ((float)($asset['retail_price'] ?? 0) <= 0) return 'need_price';
        if ((int)($policy['can_list_mall'] ?? 0) === 1) return 'ready';
        return 'need_material';
    }

    /** 商城运营接单前置条件：拍图和定价已经分别闭环。 */
    public static function canHandoffToShop(array $asset, array $policy): bool
    {
        return (int)($policy['can_prepare_mall'] ?? 0) === 1
            && self::hasImages($asset['image_urls'] ?? '')
            && (float)($asset['retail_price'] ?? 0) > 0;
    }

    public static function taskStage(array $asset, string $mode = 'split'): string
    {
        if ((string)($asset['status'] ?? '') !== 'in_stock'
            || (string)($asset['sale_target'] ?? '') !== 'mall'
            || in_array((string)($asset['refurbish_status'] ?? 'none'), ['pending', 'processing', 'failed'], true)) {
            return '';
        }
        $status = (string)($asset['listing_status'] ?? 'none');
        if ($mode === 'one_stop' && in_array($status, ['need_photo', 'need_price', 'need_material', 'ready', 'pending_shop'], true)) {
            return self::TASK_PUBLISH;
        }
        if ($mode === 'photo_price' && in_array($status, ['need_photo', 'need_price'], true)) {
            return self::TASK_MEDIA_PRICE;
        }
        return match ($status) {
            'need_photo' => self::TASK_PHOTO,
            'need_price' => self::TASK_PRICE,
            'need_material', 'ready', 'pending_shop' => self::TASK_PUBLISH,
            default => '',
        };
    }

    public static function taskName(string $stage): string
    {
        return match ($stage) {
            self::TASK_PHOTO => '待商品拍摄',
            self::TASK_PRICE => '待销售定价',
            self::TASK_MEDIA_PRICE => '待拍摄与销售定价',
            self::TASK_PUBLISH => '待商城资料整理',
            default => '待处理',
        };
    }

    public static function listStatus(string $stage): string
    {
        return match ($stage) {
            self::TASK_PHOTO => 'need_photo',
            self::TASK_PRICE => 'need_price',
            self::TASK_MEDIA_PRICE => 'incomplete',
            self::TASK_PUBLISH => 'publish',
            default => '',
        };
    }

    private static function hasImages(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, static fn($item): bool => trim((string)$item) !== '')) > 0;
        }
        $text = trim((string)$value);
        if ($text === '') return false;
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return count(array_filter($decoded, static fn($item): bool => trim((string)$item) !== '')) > 0;
        }
        return count(array_filter(array_map('trim', explode(',', $text)))) > 0;
    }
}
