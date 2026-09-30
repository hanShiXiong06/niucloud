<?php

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\GoodsSubscription;
use addon\phone_shop\app\model\goods\GoodsSubscriptionMatch;
use app\model\member\Member;
use think\facade\Db;
use think\facade\Log;

/**
 * 商品可售状态变化后的订阅匹配。
 *
 * 同一订阅、同一商品、同一可售状态指纹只通知一次；价格、库存或筛选属性变化后，
 * 指纹随之变化，可再次触发符合条件的到货提醒。
 */
class CoreGoodsSubscriptionMatchService
{
    private array $categoryParents = [];
    public function match(int $site_id, int $goods_id): int
    {
        if ($site_id <= 0 || $goods_id <= 0) return 0;

        $goods = (new Goods())->where([
            [ 'site_id', '=', $site_id ],
            [ 'goods_id', '=', $goods_id ],
            [ 'status', '=', 1 ],
            [ 'delete_time', '=', 0 ],
            [ 'sale_status', '=', 'available' ],
            [ 'is_online_sellable', '=', 1 ],
            [ 'is_gift', '=', 0 ],
            [ 'stock', '>', 0 ],
        ])->field('goods_id,site_id,goods_name,sub_title,goods_category,label_ids,service_ids,brand_id,memory_group,condition_grade,device_color,battery_health,warranty_expire_time,source,status,stock,update_time')
            ->findOrEmpty()
            ->toArray();
        if (empty($goods)) return 0;

        $sku = (new GoodsSku())->where([
            [ 'site_id', '=', $site_id ],
            [ 'goods_id', '=', $goods_id ],
            [ 'is_default', '=', 1 ],
        ])->field('sku_id,sku_no,price,sale_price,stock')->findOrEmpty()->toArray();
        if (empty($sku) || (int)($sku['stock'] ?? 0) <= 0) return 0;
        // 商品通常只保存末级分类；订阅父节点时需要把完整祖先链加入匹配集合。
        $goods['goods_category_match'] = $this->expandCategoryLineage(
            $site_id,
            $this->toList($goods['goods_category'] ?? [])
        );

        $subscriptions = (new GoodsSubscription())->where([
            [ 'site_id', '=', $site_id ],
            [ 'status', '=', 1 ],
        ])->field('subscription_id,member_id,rule_json')->select()->toArray();
        if (empty($subscriptions)) return 0;

        $fingerprint = $this->fingerprint($goods, $sku);
        $matched = 0;
        $batchMembers = [];
        foreach ($subscriptions as $subscription) {
            $rule = json_decode((string)($subscription['rule_json'] ?? ''), true) ?: [];
            if (!empty($rule['arrival_notice'])) $batchMembers[(int)$subscription['member_id']] = true;
        }
        foreach ($subscriptions as $subscription) {
            $rule = json_decode((string)($subscription['rule_json'] ?? ''), true) ?: [];
            // 全站上新只由管理员批次发送，不因逐台事件消耗订阅额度。
            if (isset($batchMembers[(int)$subscription['member_id']])) continue;
            if (!$this->matches($rule, $goods, $sku, $site_id)) continue;
            if ($this->recordAndNotify($site_id, $goods_id, $sku, $subscription, $fingerprint)) {
                $matched++;
            }
        }
        return $matched;
    }

    public function matches(array $rule, array $goods, array $sku, int $site_id): bool
    {
        $keyword = trim((string)($rule['keyword'] ?? ''));
        if ($keyword !== '') {
            $haystack = mb_strtolower((string)($goods['goods_name'] ?? '') . ' ' . (string)($goods['sub_title'] ?? '') . ' ' . (string)($sku['sku_no'] ?? ''));
            if (mb_strpos($haystack, mb_strtolower($keyword)) === false) return false;
        }

        if (!$this->matchesAny(
            $rule['category_ids'] ?? [],
            $this->toList($goods['goods_category_match'] ?? ($goods['goods_category'] ?? []))
        )) return false;
        if (!$this->matchesAny($rule['label_ids'] ?? [], $this->toList($goods['label_ids'] ?? []))) return false;
        if (!$this->matchesAny($rule['service_ids'] ?? [], $this->toList($goods['service_ids'] ?? []))) return false;
        if (!$this->matchesScalar($rule['memory_group'] ?? [], $goods['memory_group'] ?? '')) return false;
        if (!$this->matchesScalar($rule['condition_grade'] ?? [], $goods['condition_grade'] ?? '')) return false;
        if (!$this->matchesScalar($rule['device_color'] ?? [], $goods['device_color'] ?? '')) return false;
        if (!$this->matchesScalar($rule['brand_ids'] ?? [], $goods['brand_id'] ?? '')) return false;
        $deviceAttributes = new CoreDeviceAttributeService();
        if (!$this->matchesRanges($rule['battery_range'] ?? [], (int)($goods['battery_health'] ?? -1), $deviceAttributes->batteryRanges())) return false;
        if (!$this->matchesRanges($rule['warranty_range'] ?? [], (int)($goods['warranty_expire_time'] ?? 0), $deviceAttributes->warrantyRanges())) return false;

        $price = (float)($sku['price'] ?? 0);
        if (($rule['start_price'] ?? '') !== '' && $price < (float)$rule['start_price']) return false;
        if (($rule['end_price'] ?? '') !== '' && $price > (float)$rule['end_price']) return false;
        if (!empty($rule['in_stock']) && (int)($sku['stock'] ?? 0) <= 0) return false;

        $warehouse = (string)($rule['warehouse'] ?? '');
        if ($warehouse !== '') {
            $master_site_id = (new \addon\phone_shop\app\service\core\agent\AgentConfigService())->getMasterSiteId();
            $is_agent = $site_id !== $master_site_id && (string)($goods['source'] ?? '') === (string)$master_site_id;
            if (($warehouse === 'agent' && !$is_agent) || ($warehouse === 'local' && $is_agent)) return false;
        }
        return true;
    }

    private function matchesAny($expected, array $actual): bool
    {
        $expected = $this->toList($expected);
        if (empty($expected)) return true;
        return !empty(array_intersect($expected, $actual));
    }

    private function matchesScalar($expected, $actual): bool
    {
        $expected = $this->toList($expected);
        return empty($expected) || in_array((string)$actual, $expected, true);
    }

    private function matchesRanges($expected, int $actual, array $ranges): bool
    {
        $expected = $this->toList($expected);
        if (empty($expected)) return true;
        foreach ($ranges as $range) {
            if (!in_array((string)($range['value'] ?? ''), $expected, true)) continue;
            $min = (int)($range['min'] ?? 0);
            $max = $range['max'] ?? null;
            if ($actual >= $min && ($max === null || $actual <= (int)$max)) return true;
        }
        return false;
    }

    private function toList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        return array_values(array_unique(array_filter(array_map(
            static fn($item) => trim((string)$item),
            (array)$value
        ), static fn($item) => $item !== '')));
    }

    private function fingerprint(array $goods, array $sku): string
    {
        $data = [
            'status' => (int)($goods['status'] ?? 0),
            'price' => (string)($sku['price'] ?? ''),
            'stock' => (int)($sku['stock'] ?? 0),
            'category' => $this->toList($goods['goods_category'] ?? []),
            'labels' => $this->toList($goods['label_ids'] ?? []),
            'services' => $this->toList($goods['service_ids'] ?? []),
            'brand' => (string)($goods['brand_id'] ?? ''),
            'memory' => (string)($goods['memory_group'] ?? ''),
            'grade' => (string)($goods['condition_grade'] ?? ''),
            'color' => (string)($goods['device_color'] ?? ''),
            'battery' => (int)($goods['battery_health'] ?? -1),
            'warranty' => (int)($goods['warranty_expire_time'] ?? 0),
        ];
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 将末级分类扩展为“自身 + 全部父节点”，让任意分类节点都可被订阅。
     */
    public function categoryLineage(int $siteId, $value): array
    {
        return $this->expandCategoryLineage($siteId, $this->toList($value));
    }

    private function expandCategoryLineage(int $site_id, array $categoryIds): array
    {
        if (empty($categoryIds)) return [];
        if (!isset($this->categoryParents[$site_id])) {
            $rows = (new Category())->where('site_id', $site_id)->field('category_id,pid')->select()->toArray();
            $parents = [];
            foreach ($rows as $row) $parents[(string)$row['category_id']] = (string)($row['pid'] ?? '0');
            $this->categoryParents[$site_id] = $parents;
        }
        $parents = $this->categoryParents[$site_id];

        $expanded = [];
        foreach ($categoryIds as $categoryId) {
            $current = (string)$categoryId;
            $guard = 0;
            while ($current !== '' && $current !== '0' && $guard < 20) {
                $expanded[$current] = true;
                $current = $parents[$current] ?? '0';
                $guard++;
            }
        }
        return array_keys($expanded);
    }

    private function recordAndNotify(int $site_id, int $goods_id, array $sku, array $subscription, string $fingerprint): bool
    {
        $match_model = new GoodsSubscriptionMatch();
        $unique_where = [
            [ 'site_id', '=', $site_id ],
            [ 'subscription_id', '=', (int)$subscription['subscription_id'] ],
            [ 'goods_id', '=', $goods_id ],
            [ 'goods_fingerprint', '=', $fingerprint ],
        ];
        $rule = json_decode((string)$subscription['rule_json'], true) ?: [];
        $acceptedAt = (int)($rule['_weapp_consent']['accepted_at'] ?? 0);
        $existing = $match_model->where($unique_where)->findOrEmpty();
        if (!$existing->isEmpty() && (in_array((int)$existing->notify_status, [0, 1, 3], true)
            || $acceptedAt <= (int)$existing->notify_time)) return false;
        // 同一会员可能同时订阅父节点、子节点或相邻筛选规则；一次商品变化只提醒一次。
        $memberDuplicate = $match_model->where([
            [ 'site_id', '=', $site_id ],
            [ 'member_id', '=', (int)$subscription['member_id'] ],
            [ 'goods_id', '=', $goods_id ],
            [ 'goods_fingerprint', '=', $fingerprint ],
            [ 'notify_status', 'in', [0, 1, 3] ],
        ])->count();
        if ($memberDuplicate > 0) return false;

        $fields = $match_model->getTableFields();
        $supportsSnapshot = in_array('price_snapshot', $fields, true);
        $previous = $match_model->where([
            [ 'subscription_id', '=', (int)$subscription['subscription_id'] ],
            [ 'goods_id', '=', $goods_id ],
            [ 'notify_status', '=', 1 ],
        ])->order('match_id desc')->findOrEmpty()->toArray();
        $currentPrice = round((float)($sku['price'] ?? 0), 2);
        $previousPrice = $supportsSnapshot && !empty($previous)
            ? round((float)($previous['price_snapshot'] ?? 0), 2)
            : null;
        $changeType = empty($previous) ? 'new_listing' : 'info_updated';
        $changeSummary = empty($previous) ? '有符合订阅条件的新商品上架' : '商品资料已更新';
        if ($previousPrice !== null && abs($previousPrice - $currentPrice) >= 0.01) {
            $changeType = 'price_changed';
            $changeSummary = $previousPrice > 0
                ? sprintf(
                    '价格由 ¥%s 调整为 ¥%s',
                    number_format($previousPrice, 2, '.', ''),
                    number_format($currentPrice, 2, '.', '')
                )
                : sprintf('最新价格调整为 ¥%s', number_format($currentPrice, 2, '.', ''));
        }
        // 已经通知过且价格未变化时，不因图片、库存数量等普通编辑反复打扰用户。
        if (!empty($previous) && $changeType === 'info_updated') return false;

        try {
            $payload = [
                'site_id' => $site_id,
                'subscription_id' => (int)$subscription['subscription_id'],
                'member_id' => (int)$subscription['member_id'],
                'goods_id' => $goods_id,
                'sku_id' => (int)($sku['sku_id'] ?? 0),
                'goods_fingerprint' => $fingerprint,
                'notify_status' => 0,
                'error_message' => '',
                'create_time' => time(),
                'notify_time' => 0,
            ];
            if ($supportsSnapshot) {
                $payload += [
                    'price_snapshot' => number_format($currentPrice, 2, '.', ''),
                    'stock_snapshot' => (int)($sku['stock'] ?? 0),
                    'change_type' => $changeType,
                    'change_summary' => mb_substr($changeSummary, 0, 255),
                ];
            }
            // 只在数据库认领阶段锁会员行；并发的多个分类订阅不能为同一变化各发一条。
            // 调微信在事务外，避免网络请求长时间锁住会员数据。
            $match = Db::transaction(function () use ($site_id, $subscription, $goods_id, $fingerprint, $unique_where, $acceptedAt, $payload) {
                $member = (new Member())->where([['site_id', '=', $site_id], ['member_id', '=', (int)$subscription['member_id']]])->lock(true)->findOrEmpty();
                if ($member->isEmpty()) return null;
                if ((new GoodsSubscriptionMatch())->where([
                    ['site_id', '=', $site_id], ['member_id', '=', (int)$subscription['member_id']],
                    ['goods_id', '=', $goods_id], ['goods_fingerprint', '=', $fingerprint], ['notify_status', 'in', [0, 1, 3]],
                ])->count()) return null;
                $record = (new GoodsSubscriptionMatch())->where($unique_where)->lock(true)->findOrEmpty();
                if ($record->isEmpty()) return (new GoodsSubscriptionMatch())->create($payload);
                if ($acceptedAt <= (int)$record->notify_time) return null;
                unset($payload['create_time']);
                $record->save($payload);
                return $record;
            });
            if (!$match) return false;
        } catch (\Throwable $e) {
            // 唯一索引兜底并发重复。
            Log::error('[phone_shop 订阅认领失败] goods=' . $goods_id . ' ' . $e->getMessage());
            return false;
        }

        $sending = false;
        $responseSaved = false;
        try {
            $notice = new CoreGoodsNoticeService();
            $currentSubscription = (new GoodsSubscription())->where([
                ['site_id', '=', $site_id], ['subscription_id', '=', (int)$subscription['subscription_id']], ['status', '=', 1],
            ])->findOrEmpty();
            if ($currentSubscription->isEmpty()) {
                $match->save(['notify_status' => 4, 'notify_time' => time(), 'error_message' => '客户已取消订阅']);
                return false;
            }
            try { $notice->queueOtherChannels($site_id, (int)$match->match_id); }
            catch (\Throwable $e) { Log::error('[phone_shop 其他订阅渠道] match=' . (int)$match->match_id . ' ' . $e->getMessage()); }
            $rule = json_decode((string)$currentSubscription->rule_json, true) ?: [];
            $capability = $notice->capability($site_id);
            if (!CoreGoodsNoticeService::hasConsent($rule, $capability)) {
                $match->save(['notify_status' => 4, 'notify_time' => time(), 'error_message' => $capability['reason'] ?: '客户尚未允许当前小程序模板通知']);
                return false;
            }
            $data = (new \addon\phone_shop\app\listener\notice_template\GoodsMatch())->handle([
                'key' => CoreGoodsNoticeService::KEY, 'site_id' => $site_id, 'data' => ['match_id' => (int)$match->match_id],
            ]);
            if (empty($data['vars']['__weapp_page'])) throw new \RuntimeException('订阅通知内容生成失败');
            $sending = true;
            $result = $notice->send($site_id, (int)$subscription['member_id'], $data['vars'], $data['vars']['__weapp_page']);
            $match->save(['notify_status' => $result['status'], 'notify_time' => time(), 'error_message' => $result['status'] === 1 ? '' : $result['reason']]);
            $responseSaved = true;
            if ($result['status'] !== 1) return false;
            (new GoodsSubscription())->where('subscription_id', (int)$subscription['subscription_id'])
                ->inc('match_count')
                ->update([ 'last_notify_time' => time(), 'update_time' => time() ]);
            return true;
        } catch (\Throwable $e) {
            if ($responseSaved) {
                Log::error('[phone_shop 订阅统计更新失败] match=' . (int)$match->match_id . ' ' . $e->getMessage());
                return (int)$match->notify_status === 1;
            }
            $match->save([
                'notify_status' => $sending ? 3 : 2,
                'notify_time' => time(),
                'error_message' => $sending ? '发送后本地记录未完成，结果待核实；不自动重发' : mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::write('[phone_shop] 商品订阅通知失败：' . $e->getMessage());
            return false;
        }
    }
}
