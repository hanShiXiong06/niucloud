<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\service\core\goods\CoreListingMediaService;
use core\exception\CommonException;
use addon\phone_shop\app\support\ListingMediaContract;

/** ERP 只消费分类选择契约，不读写商城分类表，更不会自动创建分类。 */
final class ErpListingCatalog
{
    public function handle(array $event = []): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        if ($siteId <= 0) return [];
        $result = ['provider' => 'phone_shop', 'connected' => 1];
        if (($event['action'] ?? 'describe') === 'describe') return $result;
        if (($event['action'] ?? '') === 'resolve_brand') {
            $context = is_array($event['context'] ?? null) ? $event['context'] : [];
            $brands = (new CoreListingMediaService())->brands($siteId);
            if (array_key_exists('brand_id', $context)) return $result + ['brand' => ListingMediaContract::brand($context['brand_id'], $brands)];
            $name = trim((string)($context['brand_name'] ?? ''));
            $matches = array_values(array_filter($brands, static fn(array $brand): bool => $name !== '' && trim((string)$brand['brand_name']) === $name));
            return $result + ['brand' => count($matches) === 1 ? ['id' => (int)$matches[0]['brand_id'], 'name' => (string)$matches[0]['brand_name']] : ['id' => 0, 'name' => '']];
        }
        if (($event['action'] ?? '') === 'unlock_media') {
            $context = is_array($event['context'] ?? null) ? $event['context'] : [];
            $lockKey = $context['lock_key'] ?? '';
            if (!is_string($lockKey)) throw new CommonException('标准图库锁租约无效');
            (new CoreListingMediaService())->unlockMedia($siteId, $lockKey);
            return $result + ['unlocked' => true];
        }
        $rows = (new Category())->where('site_id', $siteId)
            ->field('category_id,pid,category_name,is_show')->select()->toArray();
        $options = self::options($rows);
        $action = (string)($event['action'] ?? '');
        $id = (int)($event['category_id'] ?? 0);
        if ($action === 'options' && $id <= 0) {
            $media = new CoreListingMediaService();
            return $result + ['options' => $options, 'brands' => $media->brands($siteId), 'labels' => $media->labels($siteId)];
        }
        foreach ($options as $option) {
            if ($option['value'] !== $id) continue;
            $context = is_array($event['context'] ?? null) ? $event['context'] : [];
            if ($action === 'options') return $result + ['options' => $options, 'selection' => $option]
                + (new CoreListingMediaService())->describe($siteId, $option, $context);
            if ($action === 'resolve_media') return $result + ['selection' => $option]
                + (new CoreListingMediaService())->resolveMedia($siteId, $option, $context);
            if ($action === 'lock_media') return $result + ['selection' => $option]
                + (new CoreListingMediaService())->lockMedia($siteId, $option, $context);
            return $result + ['selection' => $option];
        }
        throw new CommonException('所选商城分类已停用、删除或不属于本站，请重新选择末级分类');
    }

    /** 只提供完整、可见的末级分类；名称相同也绝不猜测对应。 */
    public static function options(array $rows): array
    {
        $map = array_column($rows, null, 'category_id');
        $parents = [];
        foreach ($rows as $row) $parents[(int)$row['pid']] = true;
        $options = [];
        foreach ($rows as $row) {
            $id = (int)$row['category_id'];
            if (isset($parents[$id])) continue;
            $path = $names = $visited = [];
            $cursor = $id;
            while ($cursor > 0) {
                if (isset($visited[$cursor]) || !isset($map[$cursor]) || (int)$map[$cursor]['is_show'] !== 1) {
                    $path = [];
                    break;
                }
                $visited[$cursor] = true;
                array_unshift($path, $cursor);
                array_unshift($names, (string)$map[$cursor]['category_name']);
                $cursor = (int)$map[$cursor]['pid'];
            }
            if ($path !== []) $options[] = ['value' => $id, 'label' => implode(' / ', $names), 'path' => $path, 'names' => $names];
        }
        return $options;
    }
}
