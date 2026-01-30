<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Goods;
use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 商品跨站点同步服务
 * 用于SAAS多站点环境下的商品上下架状态同步
 *
 * 业务场景：
 * - 100005站点作为主站，当其商品上下架时，同步到其他站点
 * - 通过goods_no字段关联不同站点的相同商品
 * - 各站点的goods_id不同，但goods_no相同
 */
class CoreGoodsSyncService extends BaseCoreService
{
    /**
     * 主站点ID (100005站点作为主站)
     */
    const MASTER_SITE_ID = 100005;

    /**
     * 同步商品上架状态到其他站点
     *
     * @param string $goods_no 商品编号
     * @param int $site_id 当前站点ID
     * @return bool
     */
    public function syncGoodsOnline(string $goods_no, int $site_id): bool
    {
        try {
            // 只有主站点(100005)的商品状态变化才触发同步
            if ($site_id != self::MASTER_SITE_ID) {
                return true;
            }

            if (empty($goods_no)) {
                return true;
            }

            // 查找其他站点具有相同goods_no的商品（排除主站点）
            $otherGoods = Goods::where('goods_no', $goods_no)
                ->where('site_id', '<>', self::MASTER_SITE_ID)
                ->where('status', '<>', '1')  // 只更新当前不是上架状态的商品
                ->select();

            if ($otherGoods->isEmpty()) {
                Log::write("商品同步-上架: 没有找到需要同步的商品 goods_no={$goods_no}");
                return true;
            }

            // 批量更新其他站点的商品状态为上架
            $syncCount = 0;
            foreach ($otherGoods as $item) {
                $item->status = '1';
                $item->save();
                $syncCount++;
                Log::write("商品同步-上架: goods_id={$item->goods_id}, site_id={$item->site_id}, goods_no={$goods_no}, goods_name={$item->goods_name}");
            }

            Log::write("商品同步-上架完成: goods_no={$goods_no}, 同步数量={$syncCount}");
            return true;

        } catch (\Exception $e) {
            Log::write("商品同步-上架失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 同步商品下架状态到其他站点
     *
     * @param string $goods_no 商品编号
     * @param int $site_id 当前站点ID
     * @return bool
     */
    public function syncGoodsOffline(string $goods_no, int $site_id): bool
    {
        try {
            // 只有主站点(100005)的商品状态变化才触发同步
            if ($site_id != self::MASTER_SITE_ID) {
                return true;
            }

            if (empty($goods_no)) {
                return true;
            }

            // 查找其他站点具有相同goods_no的商品（排除主站点）
            $otherGoods = Goods::where('goods_no', $goods_no)
                ->where('site_id', '<>', self::MASTER_SITE_ID)
                ->where('status', '=', '1')  // 只更新当前是上架状态的商品
                ->select();

            if ($otherGoods->isEmpty()) {
                Log::write("商品同步-下架: 没有找到需要同步的商品 goods_no={$goods_no}");
                return true;
            }

            // 批量更新其他站点的商品状态为下架
            $syncCount = 0;
            foreach ($otherGoods as $item) {
                $item->status = '0';
                $item->save();
                $syncCount++;
                Log::write("商品同步-下架: goods_id={$item->goods_id}, site_id={$item->site_id}, goods_no={$goods_no}, goods_name={$item->goods_name}");
            }

            Log::write("商品同步-下架完成: goods_no={$goods_no}, 同步数量={$syncCount}");
            return true;

        } catch (\Exception $e) {
            Log::write("商品同步-下架失败: " . $e->getMessage());
            return false;
        }
    }
}
