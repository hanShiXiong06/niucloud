<?php

namespace addon\phone_shop\app\listener\goods;

use addon\phone_shop\app\job\goods\GoodsSubscriptionMatch;
use addon\phone_shop\app\model\goods\Goods;
use think\facade\Log;

/**
 * 商品新增、编辑、上下架、库存或价格变化时生成订阅匹配任务。
 */
class GoodsSubscriptionChanged
{
    public function handle($data): void
    {
        try {
            foreach ($this->normalizeItems($data) as $item) {
                GoodsSubscriptionMatch::dispatch([
                    'site_id' => $item['site_id'],
                    'goods_id' => $item['goods_id'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::write('[phone_shop] 商品订阅任务派发失败：' . $e->getMessage());
        }
    }

    private function normalizeItems($data): array
    {
        $data = is_array($data) ? $data : [];
        $goods_ids = $data['goods_ids'] ?? ($data['goods_id'] ?? []);
        $goods_ids = array_values(array_unique(array_filter(array_map('intval', (array)$goods_ids))));
        if (empty($goods_ids)) return [];

        $site_id = (int)($data['site_id'] ?? ($data['goods_data']['site_id'] ?? 0));
        if ($site_id > 0) {
            return array_map(static fn($goods_id) => [ 'site_id' => $site_id, 'goods_id' => $goods_id ], $goods_ids);
        }

        $rows = (new Goods())->where('goods_id', 'in', $goods_ids)->field('goods_id,site_id')->select()->toArray();
        return array_map(static fn($row) => [
            'site_id' => (int)$row['site_id'],
            'goods_id' => (int)$row['goods_id'],
        ], $rows);
    }
}
