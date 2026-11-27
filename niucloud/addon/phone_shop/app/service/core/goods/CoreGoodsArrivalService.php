<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\job\goods\GoodsArrivalNotice;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\GoodsSubscription;
use addon\phone_shop\app\model\goods\GoodsSubscriptionMatch;
use addon\phone_shop\app\model\goods\GoodsTransferTask;
use app\model\member\Member;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 批次 → 可售商品 → 匹配的有效订阅 → 一人一条微信通知。复用既有任务/命中表。 */
class CoreGoodsArrivalService
{
    public const TASK_TYPE = 'arrival_notice';

    public function importTask(int $siteId, int $id): array
    {
        $task = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id], ['task_type', '=', 'import']])->findOrEmpty()->toArray();
        if (!$task) throw new CommonException('本站导入批次不存在');
        if (!in_array($task['status'], ['completed', 'partial'], true)) throw new CommonException('请等待导入完成后再通知客户');
        if (empty($task['result_json']['imported_goods_ids'])) throw new CommonException('该批次没有可核验的导入商品清单；仅支持更新后新建的导入批次');
        return $task;
    }

    public function batchGoodsIds(int $siteId, int $id): array
    {
        return self::ids($this->importTask($siteId, $id)['result_json']['imported_goods_ids']);
    }

    public function preview(int $siteId, int $importId): array
    {
        $task = $this->importTask($siteId, $importId);
        $ids = self::ids($task['result_json']['imported_goods_ids']);
        $goods = $this->saleableGoods($siteId, $ids);
        $capability = (new CoreGoodsNoticeService())->capability($siteId);
        $audience = $this->audience($siteId, $goods, $capability);
        $noticeId = (int)($task['result_json']['notice_task_id'] ?? 0);
        $notice = $noticeId ? $this->noticeInfo($siteId, $noticeId) : null;
        $supplement = ['member_count' => 0, 'audience_token' => '', 'available' => false];
        if ($notice) {
            $request = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $noticeId]])->value('request_json');
            if (is_string($request)) $request = json_decode($request, true) ?: [];
            $members = self::ids($request['member_ids'] ?? []);
            $additional = $this->additionalAudience($siteId, $noticeId, $audience, $members);
            $supplement = [
                'member_count' => count($additional['member_ids']), 'audience_token' => $additional['audience_token'],
                'available' => (bool)$additional['member_ids'] && in_array($notice['status'], ['completed', 'partial'], true)
                    && (int)$notice['processed_rows'] === count($members),
            ];
        }
        return [
            'imported_count' => count($ids), 'saleable_count' => count($goods), 'excluded_count' => count($ids) - count($goods),
            'member_count' => count($audience), 'capability' => $capability,
            'notice_task' => $notice, 'supplement' => $supplement,
            'tips' => '包含匹配本批商品的分类、筛选及上新订阅；已取消的不发送。历史记录缺少本地授权信息时交由微信核验，每位客户本批至多一条，受理不代表已读。',
        ];
    }

    public function create(int $siteId, int $importId, int $uid, string $username): array
    {
        $preview = $this->preview($siteId, $importId);
        if ($preview['notice_task']) return $preview['notice_task'];
        if (!$preview['capability']['enabled']) throw new CommonException($preview['capability']['reason']);
        if (!$preview['saleable_count']) throw new CommonException('本批没有可售商品，请先上架并检查库存、锁定/已售状态及允许线上销售开关');
        if (!$preview['member_count']) throw new CommonException('暂无匹配本批商品的有效订阅客户，请检查订阅状态、商品分类和筛选条件');
        if (!env('queue.state', false)) throw new CommonException('后台队列未开启，无法批量通知；请先启用队列服务');
        $goodsIds = $this->batchGoodsIds($siteId, $importId);
        $audience = $this->audience($siteId, $this->saleableGoods($siteId, $goodsIds), $preview['capability']);
        if (!$audience) throw new CommonException('商品或订阅名单已变化，请刷新后再发送');
        $created = false;
        $id = Db::transaction(function () use ($siteId, $importId, $uid, $username, $goodsIds, $audience, &$created) {
            $parent = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $importId], ['task_type', '=', 'import']])->lock(true)->findOrEmpty();
            if ($parent->isEmpty() || !in_array($parent->status, ['completed', 'partial'], true)) throw new CommonException('导入状态已变化，请刷新');
            $result = (array)$parent->result_json;
            if (!empty($result['notice_task_id'])) return (int)$result['notice_task_id'];
            // 锁内只写快照，不调用微信；父任务锁保证重复点击不会创建多个群发任务。
            $notice = (new GoodsTransferTask())->create([
                'site_id' => $siteId, 'task_type' => self::TASK_TYPE, 'operator_uid' => $uid, 'operator_name' => $username,
                'status' => 'queued', 'queue_enabled' => 1, 'total_rows' => count($audience),
                'request_json' => ['import_id' => $importId, 'goods_ids' => $goodsIds, 'member_ids' => array_keys($audience)],
                'result_json' => [], 'message' => '上新通知等待队列处理',
            ]);
            $result['notice_task_id'] = (int)$notice->id;
            $parent->save(['result_json' => $result]);
            $created = true;
            return (int)$notice->id;
        });
        if ($created) $this->dispatch($siteId, $id);
        return $this->noticeInfo($siteId, $id);
    }

    public function retry(int $siteId, int $id): array
    {
        if (!env('queue.state', false)) throw new CommonException('请先启用后台队列');
        $changed = $this->withTaskLock($siteId, $id, fn() => Db::transaction(function () use ($siteId, $id) {
            $task = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id], ['task_type', '=', self::TASK_TYPE]])->lock(true)->findOrEmpty();
            if ($task->isEmpty() || !in_array($task->status, ['failed', 'partial', 'processing'], true)) return false;
            $request = (array)$task->request_json;
            $request['cursor'] = 0;
            unset($request['resume_cursor'], $request['resume_stats']);
            return $task->save([
                'request_json' => $request, 'result_json' => [], 'status' => 'queued', 'processed_rows' => 0,
                'success_count' => 0, 'error_count' => 0, 'skipped_count' => 0, 'start_time' => 0, 'finish_time' => 0,
                'message' => '重新核验回执，只重试未发送/明确失败项', 'error_message' => '',
            ]);
        }));
        if ($changed) $this->dispatch($siteId, $id);
        return $this->noticeInfo($siteId, $id);
    }

    /** 显式补充原名单之外的新匹配客户，不重试原名单中的失败或待核实项。 */
    public function supplement(int $siteId, int $id, string $audienceToken): array
    {
        if (!env('queue.state', false)) throw new CommonException('请先启用后台队列');
        $changed = $this->withTaskLock($siteId, $id, fn() => Db::transaction(function () use ($siteId, $id, $audienceToken) {
            $task = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id], ['task_type', '=', self::TASK_TYPE]])->lock(true)->findOrEmpty();
            if ($task->isEmpty()) throw new CommonException('本站通知任务不存在');
            if (!in_array($task->status, ['completed', 'partial'], true)) throw new CommonException('请等待原名单处理完毕，或先恢复中断的任务');
            $request = (array)$task->request_json;
            $members = self::ids($request['member_ids'] ?? []);
            if ((int)$task->processed_rows !== count($members)) throw new CommonException('原名单尚未处理完毕，请先恢复任务');
            $capability = (new CoreGoodsNoticeService())->capability($siteId);
            if (!$capability['enabled']) throw new CommonException($capability['reason']);
            $goods = $this->saleableGoods($siteId, self::ids($request['goods_ids'] ?? []));
            $additional = $this->additionalAudience($siteId, $id, $this->audience($siteId, $goods, $capability), $members);
            if (!$additional['member_ids']) return false;
            if (!hash_equals($additional['audience_token'], $audienceToken)) throw new CommonException('新增匹配客户已变化，请刷新名单后重新确认补发');
            // 中断恢复从本轮新增名单开始，不能顺带重新发送原名单中的失败项。
            $request['resume_cursor'] = $request['cursor'] = count($members);
            $request['resume_stats'] = (array)$task->result_json;
            $request['member_ids'] = array_merge($members, $additional['member_ids']);
            return $task->save([
                'request_json' => $request, 'status' => 'queued', 'total_rows' => count($request['member_ids']),
                'start_time' => 0, 'finish_time' => 0, 'error_message' => '',
                'message' => '等待补发新增匹配客户 ' . count($additional['member_ids']) . ' 人，原名单回执保留',
            ]);
        }));
        if ($changed) $this->dispatch($siteId, $id);
        return $this->noticeInfo($siteId, $id);
    }

    private function additionalAudience(int $siteId, int $taskId, array $audience, array $members): array
    {
        $additional = array_values(array_diff(self::ids(array_keys($audience)), $members));
        sort($additional, SORT_NUMERIC);
        return [
            'member_ids' => $additional,
            'audience_token' => hash('sha256', $siteId . ':' . $taskId . ':' . implode(',', $additional)),
        ];
    }

    private function dispatch(int $siteId, int $id): void
    {
        try {
            if (GoodsArrivalNotice::dispatch(['siteId' => $siteId, 'taskId' => $id]) === false) throw new CommonException('队列提交失败');
        } catch (\Throwable $e) {
            (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id], ['status', '=', 'queued']])
                ->update(['status' => 'failed', 'message' => '通知未进入队列，请检查队列后重试', 'update_time' => time()]);
        }
    }

    public function noticeInfo(int $siteId, int $id): array
    {
        $task = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id], ['task_type', '=', self::TASK_TYPE]])->findOrEmpty()->toArray();
        if (!$task) throw new CommonException('本站通知任务不存在');
        $task['can_resume'] = $task['status'] === 'processing' && (int)$task['update_time'] < time() - 60;
        unset($task['request_json'], $task['source_file'], $task['result_file']);
        return $task;
    }

    public function results(int $siteId, int $id, int $page = 1, int $limit = 15): array
    {
        $this->noticeInfo($siteId, $id);
        $request = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id]])->value('request_json');
        if (is_string($request)) $request = json_decode($request, true) ?: [];
        $members = self::ids($request['member_ids'] ?? []);
        $page = max(1, $page); $limit = max(1, min(50, $limit));
        $current = array_slice($members, ($page - 1) * $limit, $limit);
        if (!$current) return ['total' => count($members), 'data' => []];
        $fingerprints = array_map(static fn($member) => hash('sha256', 'arrival:' . $id . ':' . $member), $current);
        $records = (new GoodsSubscriptionMatch())->where([['site_id', '=', $siteId], ['goods_id', '=', 0]])->whereIn('goods_fingerprint', $fingerprints)->select()->toArray();
        $records = array_column($records, null, 'member_id');
        $names = (new Member())->where('site_id', $siteId)->whereIn('member_id', $current)->column('nickname', 'member_id');
        $rows = [];
        foreach ($current as $member) {
            $record = $records[$member] ?? [];
            $status = (int)($record['notify_status'] ?? 0);
            $rows[] = [
                'member_id' => $member, 'member_name' => $names[$member] ?? ('会员 ' . $member),
                'status' => $status,
                'result' => $status === 1 ? '微信已受理（不代表已阅读）' : (($record['error_message'] ?? '') ?: '等待处理/微信响应尚未确认'),
            ];
        }
        return ['total' => count($members), 'data' => $rows];
    }

    public function run(int $siteId, int $taskId): void
    {
        // 命名锁只串行化此通知任务，不锁业务表；进程退出后数据库自动释放。
        $this->withTaskLock($siteId, $taskId, fn() => $this->runLocked($siteId, $taskId), true);
    }

    private function withTaskLock(int $siteId, int $taskId, callable $action, bool $quiet = false)
    {
        $connection = Db::connect();
        $scope = (string)$connection->getConfig('database') . ':' . (string)$connection->getConfig('prefix');
        $key = 'phone_arrival:' . substr(hash('sha256', $scope . ':' . $siteId . ':' . $taskId), 0, 40);
        $lock = $connection->query('SELECT GET_LOCK(:lock_key, 0) AS acquired', ['lock_key' => $key], true);
        if ((int)($lock[0]['acquired'] ?? 0) !== 1) {
            if ($quiet) return null;
            throw new CommonException('通知任务仍在执行，请稍后刷新；不会重复发送');
        }
        try { return $action(); }
        finally {
            try { $connection->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $key], true); }
            catch (\Throwable $e) { Log::error('[phone_shop 通知任务锁释放失败] task=' . $taskId); }
        }
    }

    private function runLocked(int $siteId, int $taskId): void
    {
        $abandoned = (new GoodsTransferTask())->where([
            ['site_id', '=', $siteId], ['id', '=', $taskId], ['task_type', '=', self::TASK_TYPE], ['status', '=', 'processing'],
        ])->findOrEmpty();
        if (!$abandoned->isEmpty()) {
            // 已取得独占锁，旧消费者不在运行。重算统计，逐条复用回执，绝不重发成功/待核实项。
            $request = (array)$abandoned->request_json;
            $request['cursor'] = (int)($request['resume_cursor'] ?? 0);
            $abandoned->save(['status' => 'queued', 'request_json' => $request, 'result_json' => (array)($request['resume_stats'] ?? [])]);
        }
        // 同一任务只允许一个消费者，重复投递不能重复发送。
        $query = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $taskId], ['task_type', '=', self::TASK_TYPE]]);
        if (!$query->where('status', 'queued')->update(['status' => 'processing', 'start_time' => time(), 'update_time' => time(), 'message' => '正在核验订阅并发送微信通知'])) return;
        $task = (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->findOrEmpty()->toArray();
        $request = (array)$task['request_json'];
        $stats = array_merge(['accepted' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'details' => []], (array)$task['result_json']);
        $members = self::ids($request['member_ids'] ?? []);
        $cursor = max(0, (int)($request['cursor'] ?? 0));
        try {
            // 小批次继续投递，避免一次给几千人发送导致队列进程长时间占用。
            foreach (array_slice($members, $cursor, 5) as $memberId) {
                // 执行时重新核验；管理员排队后下架或客户取消订阅，均不再触达。
                $goods = $this->saleableGoods($siteId, self::ids($request['goods_ids'] ?? []));
                $capability = (new CoreGoodsNoticeService())->capability($siteId);
                $audience = $this->audience($siteId, $goods, $capability, $memberId);
                $result = $this->sendMember($siteId, $taskId, $memberId, (int)$request['import_id'], $audience[$memberId] ?? null, $capability);
                $key = [1 => 'accepted', 2 => 'failed', 3 => 'unknown', 4 => 'skipped'][$result['status']] ?? 'unknown';
                $stats[$key]++;
                if (count($stats['details']) < 100) $stats['details'][] = ['member_id' => $memberId, 'result' => $result['reason']];
                $this->saveProgress($siteId, $taskId, $stats, false);
                $cursor++;
            }
            if ($cursor < count($members)) {
                $request['cursor'] = $cursor;
                (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                    'status' => 'queued', 'request_json' => json_encode($request, JSON_UNESCAPED_UNICODE), 'update_time' => time(),
                ]);
                $this->dispatch($siteId, $taskId);
            } else $this->saveProgress($siteId, $taskId, $stats, true);
        } catch (\Throwable $e) {
            Log::error('[phone_shop 上新通知任务] task=' . $taskId . ' ' . $e->getMessage());
            (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                'status' => 'failed', 'message' => '通知任务中断，可重试未发送/明确失败项；受理成功或结果待核实的不会重复发送',
                'error_message' => mb_substr($e->getMessage(), 0, 1000), 'finish_time' => time(), 'update_time' => time(),
            ]);
        }
    }

    private function sendMember(int $siteId, int $taskId, int $memberId, int $importId, ?array $target, array $capability): array
    {
        $fingerprint = hash('sha256', 'arrival:' . $taskId . ':' . $memberId);
        $where = [['site_id', '=', $siteId], ['member_id', '=', $memberId], ['goods_id', '=', 0], ['goods_fingerprint', '=', $fingerprint]];
        $record = (new GoodsSubscriptionMatch())->where($where)->findOrEmpty();
        if (!$record->isEmpty() && in_array((int)$record->notify_status, [0, 1, 3], true)) {
            if ((int)$record->notify_status === 0) $record->save(['notify_status' => 3, 'error_message' => '上次发送中断，结果待核实，不重复发送']);
            return ['status' => (int)$record->notify_status, 'reason' => (int)$record->notify_status === 1 ? '微信已受理，不重复发送' : '上次结果待核实，不重复发送'];
        }
        $payload = ['notify_status' => 0, 'error_message' => '', 'notify_time' => 0];
        if ($record->isEmpty()) {
            $record = (new GoodsSubscriptionMatch())->create($payload + [
                'site_id' => $siteId, 'member_id' => $memberId, 'subscription_id' => $target['subscription_id'] ?? 0,
                'goods_id' => 0, 'sku_id' => 0, 'goods_fingerprint' => $fingerprint, 'create_time' => time(),
            ]);
        } else $record->save($payload);
        if (!$target) {
            $reason = $capability['reason'] ?: '已取消订阅、订阅模板不匹配或本批已无符合条件的可售商品';
            $record->save(['notify_status' => 4, 'error_message' => $reason, 'notify_time' => time()]);
            return ['status' => 4, 'reason' => $reason];
        }
        $count = count($target['goods']);
        $min = min(array_column($target['goods'], 'notice_price'));
        $result = (new CoreGoodsNoticeService())->send($siteId, $memberId, [
            'goods_name' => '本批上新' . $count . '台设备', 'goods_price' => number_format($min, 2, '.', ''),
            'change_type_name' => '新品上架', 'change_summary' => '标价起，实际售价以商品页为准',
            'notice_remark' => '本批匹配' . $count . '台，点击查看实时报价',
            'subscription_name' => '上新提醒', 'match_time' => date('Y-m-d H:i:s'),
        ] + CoreGoodsNoticeService::arrivalVariables($siteId, $target['goods']), 'addon/phone_shop/pages/goods/list?arrival_batch_id=' . $importId);
        $record->save(['notify_status' => $result['status'], 'error_message' => $result['status'] === 1 ? '' : $result['reason'], 'notify_time' => time()]);
        if ($result['status'] === 1) (new GoodsSubscription())->where([['site_id', '=', $siteId], ['subscription_id', '=', $target['subscription_id']]])->inc('match_count')->update(['last_notify_time' => time()]);
        return $result;
    }

    private function saveProgress(int $siteId, int $id, array $stats, bool $done): void
    {
        $processed = $stats['accepted'] + $stats['failed'] + $stats['unknown'] + $stats['skipped'];
        $data = [
            'processed_rows' => $processed, 'success_count' => $stats['accepted'], 'error_count' => $stats['failed'] + $stats['unknown'], 'skipped_count' => $stats['skipped'],
            'result_json' => json_encode($stats, JSON_UNESCAPED_UNICODE), 'update_time' => time(),
            'message' => sprintf('微信受理 %d · 失败 %d · 待核实 %d · 未发送 %d', $stats['accepted'], $stats['failed'], $stats['unknown'], $stats['skipped']),
        ];
        if ($done) $data += ['status' => ($stats['failed'] || $stats['skipped']) ? 'partial' : 'completed', 'finish_time' => time()];
        (new GoodsTransferTask())->where([['site_id', '=', $siteId], ['id', '=', $id]])->update($data);
    }

    public function saleableGoods(int $siteId, array $ids): array
    {
        if (!$ids) return [];
        $goods = (new Goods())->where([
            ['site_id', '=', $siteId], ['status', '=', 1], ['sale_status', '=', 'available'], ['is_online_sellable', '=', 1],
            ['delete_time', '=', 0], ['stock', '>', 0], ['is_gift', '=', 0],
        ])->whereIn('goods_id', $ids)
            ->field('goods_id,site_id,goods_name,sub_title,goods_category,label_ids,service_ids,brand_id,memory_group,condition_grade,device_color,battery_health,warranty_expire_time,source,create_time')
            ->select()->toArray();
        $skus = (new GoodsSku())->where([['site_id', '=', $siteId], ['is_default', '=', 1], ['stock', '>', 0]])->whereIn('goods_id', $ids)->select()->toArray();
        $skuMap = array_column($skus, null, 'goods_id');
        $result = [];
        foreach ($goods as $item) {
            if (!isset($skuMap[$item['goods_id']])) continue;
            $item['notice_sku'] = $skuMap[$item['goods_id']];
            $item['notice_price'] = (float)$item['notice_sku']['price'];
            $result[] = $item;
        }
        return $result;
    }

    private function audience(int $siteId, array $goods, array $capability, int $memberId = 0): array
    {
        if (!$goods || !$capability['enabled']) return [];
        $query = (new GoodsSubscription())->where([['site_id', '=', $siteId], ['status', '=', 1]]);
        if ($memberId) $query->where('member_id', $memberId);
        $rows = $query->field('subscription_id,member_id,rule_json')->order('subscription_id asc')->select()->toArray();
        $matcher = new CoreGoodsSubscriptionMatchService();
        foreach ($goods as &$item) {
            $item['goods_category_match'] = $matcher->categoryLineage($siteId, $item['goods_category'] ?? []);
        }
        unset($item);
        $result = [];
        foreach ($rows as $subscription) {
            $rule = json_decode((string)$subscription['rule_json'], true);
            if (!is_array($rule) || !array_diff_key($rule, ['_weapp_consent' => true])) continue;
            // 历史有效订阅缺少本地回执时允许试发，不伪造授权；已记录的其他应用/模板授权仍不能混用。
            if (array_key_exists('_weapp_consent', $rule)
                && (!is_array($rule['_weapp_consent']) || !CoreGoodsNoticeService::hasConsent($rule, $capability))) continue;
            $member = (int)$subscription['member_id'];
            if ($member <= 0) continue;
            foreach ($goods as $item) {
                if (!$matcher->matches($rule, $item, $item['notice_sku'], $siteId)) continue;
                $result[$member]['subscription_id'] ??= (int)$subscription['subscription_id'];
                $result[$member]['goods'][$item['goods_id']] = $item;
            }
        }
        return $result;
    }

    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    }
}
