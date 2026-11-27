<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 跨站商品上下架联动
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\agent;

use addon\phone_shop\app\model\agent\PhoneShopAgent;
use addon\phone_shop\app\model\goods\Goods;
use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 商品跨站上下架联动
 * 仅主站(可配置)的商品状态变化才触发；联动所有启用站点关系的从站副本。
 * 单向：主站 -> 子站。
 * Class GoodsSyncService
 * @package addon\phone_shop\app\service\core\agent
 */
class GoodsSyncService extends BaseCoreService
{
    /**
     * 兼容旧调用：按 goods_no 找到主站候选后，再逐个按 goods_id 精确联动。
     * @param string $goodsNo 商品跨站公共键
     * @param int $siteId 触发站点
     * @param int $status 目标状态 1上架 0下架
     * @return bool
     */
    public function sync(string $goodsNo, int $siteId, int $status): bool
    {
        try {
            $masterSiteId = (new AgentConfigService())->getMasterSiteId();
            // 只有主站的状态变化才向下联动
            if ($siteId != $masterSiteId) {
                return true;
            }
            if ($goodsNo === '' || $goodsNo === '0') {
                return true;
            }
            $status = $status ? 1 : 0;

            $masterGoodsIds = (new Goods())->where([
                ['site_id', '=', $masterSiteId],
                ['goods_no', '=', $goodsNo],
            ])->column('goods_id');
            foreach ($masterGoodsIds as $masterGoodsId) {
                $this->syncGoods((int)$masterGoodsId, $siteId, $status);
            }
            return true;
        } catch (\Throwable $e) {
            Log::write('[phone_shop 联动] 失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 按主站商品ID精确联动，避免历史重复 goods_no 导致串商品。
     */
    public function syncGoods(int $masterGoodsId, int $siteId, int $status): bool
    {
        try {
            $masterSiteId = (new AgentConfigService())->getMasterSiteId();
            if ($siteId !== $masterSiteId || $masterGoodsId <= 0) return true;
            $masterGoods = (new Goods())->where([
                ['site_id', '=', $masterSiteId],
                ['goods_id', '=', $masterGoodsId],
            ])->field('goods_id,goods_no,goods_name,source')->findOrEmpty();
            if (!$masterGoods->isEmpty()) {
                $source = trim((string)$masterGoods->source);
                if ($source !== '' && $source !== (string)$masterSiteId) return true;
            } elseif ($status) {
                // 商品已经删除时只能执行下架，不能重新创建副本。
                return true;
            }

            $agents = (new PhoneShopAgent())->where([
                ['master_site_id', '=', $masterSiteId],
                ['status', '=', 1],
            ])->field('agent_site_id,markup_value')->select()->toArray();
            $n = 0;
            foreach ($agents as $agent) {
                try {
                    $agentSiteId = (int)$agent['agent_site_id'];
                    if ($status) {
                        (new GoodsDistributionService())->distributeToAgent(
                            $masterGoodsId,
                            $agentSiteId,
                            (float)$agent['markup_value'],
                            (string)$masterGoods->goods_no
                        );
                        $n++;
                        continue;
                    }

                    $copyQuery = (new Goods())->where([
                        ['site_id', '=', $agentSiteId],
                        ['source', '=', (string)$masterSiteId],
                        ['source_goods_id', '=', $masterGoodsId],
                    ]);
                    $changed = $copyQuery->where('status', '<>', 0)->update([
                        'status' => 0,
                        'update_time' => time(),
                    ]);
                    if ($changed === 0 && !$masterGoods->isEmpty()) {
                        // 仅用于尚未写入 source_goods_id 的历史副本；只有唯一匹配时才更新。
                        $candidateIds = (new Goods())->where([
                            ['site_id', '=', $agentSiteId],
                            ['source', '=', (string)$masterSiteId],
                            ['goods_no', '=', (string)$masterGoods->goods_no],
                            ['goods_name', '=', (string)$masterGoods->goods_name],
                        ])->where(function ($query) {
                            $query->whereNull('source_goods_id')->whereOr('source_goods_id', '=', 0);
                        })->limit(2)->column('goods_id');
                        if (count($candidateIds) === 1) {
                            $changed = (new Goods())->where([
                                ['site_id', '=', $agentSiteId],
                                ['goods_id', '=', (int)$candidateIds[0]],
                            ])->update([
                                'source_goods_id' => $masterGoodsId,
                                'status' => 0,
                                'update_time' => time(),
                            ]);
                        }
                    }
                    $n += (int)$changed;
                } catch (\Throwable $e) {
                    Log::write('[phone_shop 联动] 商品' . $masterGoodsId . '从站' . (int)$agent['agent_site_id'] . '失败: ' . $e->getMessage());
                }
            }
            Log::write("[phone_shop 联动] goods_id={$masterGoodsId} status=" . ($status ? 1 : 0) . " 同步={$n}");
            return true;
        } catch (\Throwable $e) {
            Log::write('[phone_shop 联动] 商品' . $masterGoodsId . '失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 上架联动
     */
    public function syncOnline(string $goodsNo, int $siteId): bool
    {
        return $this->sync($goodsNo, $siteId, 1);
    }

    /**
     * 下架联动（卖出锁定 / 手动下架共用）
     */
    public function syncOffline(string $goodsNo, int $siteId): bool
    {
        return $this->sync($goodsNo, $siteId, 0);
    }
}
