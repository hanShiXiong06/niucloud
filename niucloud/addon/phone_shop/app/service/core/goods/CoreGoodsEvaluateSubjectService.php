<?php
// +----------------------------------------------------------------------
// | 二手商城评价归属解析
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\goods;

use core\base\BaseCoreService;
use think\facade\Db;

/**
 * 一机一物商品的评价不能只跟随已经售出的 goods_id。
 *
 * 评价事实仍绑定订单和具体商品，展示归属则沉淀到商品的末级分类。
 * 这样同型号的新设备重新上架后，可以复用历史真实成交评价。
 */
class CoreGoodsEvaluateSubjectService extends BaseCoreService
{
    /**
     * 解析评价展示归属及购买快照。
     */
    public function resolve(int $siteId, int $goodsId, array $orderGoods = []): array
    {
        $goods = Db::name('phone_shop_goods')
            ->where([['site_id', '=', $siteId], ['goods_id', '=', $goodsId]])
            ->field('goods_id,goods_name,goods_cover,goods_category')
            ->find();

        $categoryIds = $this->decodeIds($goods['goods_category'] ?? []);
        $categoryId = (int) (end($categoryIds) ?: 0);
        $categoryName = '';
        $categoryPath = '';

        if ($categoryId > 0) {
            $category = Db::name('phone_shop_goods_category')
                ->where([['site_id', '=', $siteId], ['category_id', '=', $categoryId]])
                ->field('category_name,category_full_name')
                ->find();
            $categoryName = trim((string) ($category['category_name'] ?? ''));
            $categoryPath = trim((string) ($category['category_full_name'] ?? ''));
        }

        $goodsName = trim((string) ($orderGoods['goods_name'] ?? ($goods['goods_name'] ?? '')));
        $goodsImage = trim((string) ($orderGoods['goods_image'] ?? ($goods['goods_cover'] ?? '')));

        return [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'category_path' => $categoryPath !== '' ? $categoryPath : $categoryName,
            'goods_name' => $goodsName,
            'sku_name' => trim((string) ($orderGoods['sku_name'] ?? '')),
            'goods_image' => $goodsImage,
        ];
    }

    /**
     * 当前商品详情页应读取的评价归属。
     */
    public function resolveDisplaySubject(int $siteId, int $goodsId): array
    {
        $subject = $this->resolve($siteId, $goodsId);
        $subject['type'] = $subject['category_id'] > 0 ? 'category' : 'goods';
        $subject['key'] = $subject['category_id'] > 0
            ? 'category:' . $subject['category_id']
            : 'goods:' . $goodsId;
        return $subject;
    }

    private function decodeIds($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) return [];
        return array_values(array_filter(array_map('intval', $value)));
    }
}
