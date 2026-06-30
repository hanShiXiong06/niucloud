<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\stat;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\stat\RecycleStatCurrent;
use addon\hsx_recycle\app\model\stat\RecycleStatDaily;
use addon\hsx_recycle\app\model\stat\RecycleStatDailyDim;
use core\base\BaseCoreService;

/**
 * 回收任务/统计 汇总引擎（埋点写入处）
 *
 * 设计：状态流转时只调本服务一次，由它幂等增减两张汇总表：
 *  - recycle_stat_current：实时态计数（当前各环节台数），看板/待办直接读
 *  - recycle_stat_daily：按日流水（当天进入/完成/打款台数与金额），趋势/绩效直接读
 * 任何看板查询都读这两张表，不再对订单/设备大表做重复聚合。
 */
class CoreRecycleStatService extends BaseCoreService
{
    /**
     * 实时态计数 增减（幂等 upsert）
     * @param int $siteId
     * @param string $metricKey 指标键，如 stage_check / stage_price ...
     * @param int $uid 维度：0=全站汇总，>0=经手人
     * @param int $delta 增量（可负）
     */
    public function incrCurrent(int $siteId, string $metricKey, int $uid, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $model = new RecycleStatCurrent();
        $where = [
            ['site_id', '=', $siteId],
            ['metric_key', '=', $metricKey],
            ['uid', '=', $uid],
        ];
        $affected = $model->where($where)->inc('value', $delta)->update(['update_time' => time()]);
        if (!$affected) {
            // 行不存在则插入（value 不为负）
            $model->create([
                'site_id'     => $siteId,
                'metric_key'  => $metricKey,
                'uid'         => $uid,
                'value'       => max(0, $delta),
                'update_time' => time(),
            ]);
        }
    }

    /**
     * 按日流水 累计（幂等 upsert，按 站点+日期+指标+经手人）
     */
    public function incrDaily(int $siteId, string $metricKey, int $uid, int $valueDelta, float $amountDelta = 0, ?int $statDate = null): void
    {
        if ($valueDelta === 0 && $amountDelta == 0) {
            return;
        }
        $statDate = $statDate ?? (int)date('Ymd');
        $model = new RecycleStatDaily();
        $where = [
            ['site_id', '=', $siteId],
            ['stat_date', '=', $statDate],
            ['metric_key', '=', $metricKey],
            ['uid', '=', $uid],
        ];
        $row = $model->where($where)->findOrEmpty();
        if ($row->isEmpty()) {
            $model->create([
                'site_id'     => $siteId,
                'stat_date'   => $statDate,
                'metric_key'  => $metricKey,
                'uid'         => $uid,
                'value'       => $valueDelta,
                'amount'      => $amountDelta,
                'update_time' => time(),
            ]);
        } else {
            $model->where($where)->update([
                'value'       => max(0, (int)$row['value'] + $valueDelta),
                'amount'      => (float)$row['amount'] + $amountDelta,
                'update_time' => time(),
            ]);
        }
    }

    /**
     * 记录一次环节流转（埋点统一入口）
     * 旧环节 -1、新环节 +1（实时态）；当天 进入新环节 +1（按日流水）。
     * 全站维度 + 经手人维度各记一份。
     *
     * @param int $siteId
     * @param string $fromStage 旧环节key，空表示新建工单
     * @param string $toStage 新环节key，空表示离场（完成/异常出库）
     * @param int $uid 经手人，0 表示系统/未知
     * @param float $amount 关联金额（如打款金额），用于按日流水累计
     */
    public function recordStageChange(int $siteId, string $fromStage, string $toStage, int $uid = 0, float $amount = 0): void
    {
        // 实时态：旧环节减、新环节加（全站维度）
        if ($fromStage !== '') {
            $this->incrCurrent($siteId, 'stage_' . $fromStage, 0, -1);
        }
        if ($toStage !== '') {
            $this->incrCurrent($siteId, 'stage_' . $toStage, 0, 1);
        }

        // 按日流水：进入新环节计数（全站 + 经手人）
        if ($toStage !== '') {
            $this->incrDaily($siteId, 'enter_' . $toStage, 0, 1, $amount);
            if ($uid > 0) {
                $this->incrDaily($siteId, 'enter_' . $toStage, $uid, 1, $amount);
            }
        }
        // 完成旧环节（谁做完的，计入经手人绩效）
        if ($fromStage !== '' && $uid > 0) {
            $this->incrDaily($siteId, 'done_' . $fromStage, $uid, 1, $amount);
            $this->incrDaily($siteId, 'done_' . $fromStage, 0, 1, $amount);
        }
    }

    /**
     * 读取实时态计数（看板/待办用）
     * @return array metric_key => value
     */
    public function getCurrentMap(int $siteId, int $uid = 0): array
    {
        $rows = (new RecycleStatCurrent())
            ->where([['site_id', '=', $siteId], ['uid', '=', $uid]])
            ->column('value', 'metric_key');
        return $rows ?: [];
    }

    /**
     * 读取按日流水（趋势/绩效用）
     * @param array $metricKeys 指标键集合
     * @return array 原始行 [{stat_date, metric_key, uid, value, amount}]
     */
    public function getDaily(int $siteId, int $dateStart, int $dateEnd, array $metricKeys = [], int $uid = 0): array
    {
        $query = (new RecycleStatDaily())
            ->where([
                ['site_id', '=', $siteId],
                ['uid', '=', $uid],
                ['stat_date', 'between', [$dateStart, $dateEnd]],
            ]);
        if (!empty($metricKeys)) {
            $query->whereIn('metric_key', $metricKeys);
        }
        return $query->field('stat_date,metric_key,uid,value,amount')
            ->order('stat_date asc')
            ->select()->toArray();
    }

    /**
     * 一次性回填：按当前设备状态重算各环节在途台数，覆盖写入 stat_current（全站维度）。
     * 部署后执行一次，让看板从第一天就有"当前在途"的准确底数。
     * 之后由埋点增量维护，不再需要重算。
     *
     * @return array stage_key => 台数
     */
    public function rebuildCurrent(int $siteId): array
    {
        $rows = (new RecycleDevice())
            ->where([['site_id', '=', $siteId]])
            ->field('status, pay_status, count(*) as cnt')
            ->group('status, pay_status')
            ->select()->toArray();

        $stageCount = [];
        foreach ($rows as $r) {
            // 已回收(5) 按打款状态细分：未打款=待打款(pay)，已打款=已入库ERP离场
            $stage = RecycleStageDict::stageOf((int)$r['status'], (int)$r['pay_status']);
            if ($stage === '') {
                continue;
            }
            $stageCount[$stage] = ($stageCount[$stage] ?? 0) + (int)$r['cnt'];
        }
        // 待签收是订单级环节：在途 = 待签收订单数
        $stageCount[RecycleStageDict::STAGE_SIGN] = (new \addon\hsx_recycle\app\model\order\RecycleOrder())
            ->where([['site_id', '=', $siteId], ['status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN]])
            ->count();
        // 质检在途排除"订单未签收"的待质检设备（它们计入待签收，避免与质检任务队列重复）
        if (!empty($stageCount[RecycleStageDict::STAGE_CHECK])) {
            $unsignedCheck = (new RecycleDevice())
                ->where('site_id', '=', $siteId)
                ->where('status', 'in', [RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK, RecycleOrderDict::DEVICE_STATUS_CHECKING])
                ->where('order_id', 'in', function ($sub) use ($siteId) {
                    $sub->name('recycle_order')
                        ->where('site_id', '=', $siteId)
                        ->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN)
                        ->field('id');
                })
                ->count();
            $stageCount[RecycleStageDict::STAGE_CHECK] = max(0, $stageCount[RecycleStageDict::STAGE_CHECK] - (int)$unsignedCheck);
        }

        $now = time();
        foreach (RecycleStageDict::getStages() as $stage) {
            $metricKey = 'stage_' . $stage['stage_key'];
            $value = $stageCount[$stage['stage_key']] ?? 0;
            $model = new RecycleStatCurrent();
            $where = [['site_id', '=', $siteId], ['metric_key', '=', $metricKey], ['uid', '=', 0]];
            if ($model->where($where)->findOrEmpty()->isEmpty()) {
                $model->create([
                    'site_id'     => $siteId,
                    'metric_key'  => $metricKey,
                    'uid'         => 0,
                    'value'       => $value,
                    'update_time' => $now,
                ]);
            } else {
                $model->where($where)->update(['value' => $value, 'update_time' => $now]);
            }
        }
        return $stageCount;
    }

    /**
     * 经营看板数据（只读两张汇总表，零大表聚合）
     * 返回：各环节在途台数 + 今日关键数字 + 近 N 天趋势
     */
    public function getBoard(int $siteId, int $days = 7): array
    {
        // 环节定义统一取自字典（含待签收，已去处置），避免两处漂移
        $current = $this->getCurrentMap($siteId, 0);
        // 待签收是订单级小集合，实时 COUNT 最准（status 有索引），不依赖埋点/回填
        $signCount = (new \addon\hsx_recycle\app\model\order\RecycleOrder())
            ->where([['site_id', '=', $siteId], ['status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN]])
            ->count();
        $stages = [];
        foreach (RecycleStageDict::getStages() as $s) {
            $key = $s['stage_key'];
            $count = $key === RecycleStageDict::STAGE_SIGN
                ? (int)$signCount
                : (int)($current['stage_' . $key] ?? 0);
            $stages[] = ['stage_key' => $key, 'name' => $s['name'], 'count' => $count];
        }

        // 今日关键数字
        $today = (int)date('Ymd');
        $todayMap = [];
        foreach ($this->getDaily($siteId, $today, $today, [], 0) as $r) {
            $todayMap[$r['metric_key']] = ['value' => (int)$r['value'], 'amount' => (float)$r['amount']];
        }
        $todayStat = [
            'enter_check'      => (int)($todayMap['enter_check']['value'] ?? 0),   // 今日新进质检
            'recycled'         => (int)($todayMap['enter_pay']['value'] ?? 0),     // 今日确认回收(进入待打款)
            'paid_count'       => (int)($todayMap['done_pay']['value'] ?? 0),      // 今日打款台数
            'paid_amount'      => round((float)($todayMap['done_pay']['amount'] ?? 0), 2), // 今日打款金额
        ];

        // 近 N 天趋势：打款台数/金额 + 新进质检
        $start = (int)date('Ymd', strtotime('-' . ($days - 1) . ' days'));
        $byDate = [];
        foreach ($this->getDaily($siteId, $start, $today, ['done_pay', 'enter_check'], 0) as $r) {
            $byDate[(int)$r['stat_date']][$r['metric_key']] = ['value' => (int)$r['value'], 'amount' => (float)$r['amount']];
        }
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $ts = strtotime("-$i days");
            $d = (int)date('Ymd', $ts);
            $trend[] = [
                'date'        => $d,
                'label'       => date('m-d', $ts),
                'pay_count'   => (int)($byDate[$d]['done_pay']['value'] ?? 0),
                'pay_amount'  => round((float)($byDate[$d]['done_pay']['amount'] ?? 0), 2),
                'check_count' => (int)($byDate[$d]['enter_check']['value'] ?? 0),
            ];
        }

        return ['stages' => $stages, 'today' => $todayStat, 'trend' => $trend];
    }

    // ======================= 每日维度汇总（抗千万级分析） =======================

    /** Ymd 整数 → [当日起始时间戳, 结束时间戳] */
    private function dayRange(int $dateInt): array
    {
        $s = (string)$dateInt;
        $start = strtotime(substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2) . ' 00:00:00');
        return [$start, $start + 86400 - 1];
    }

    /** 前一天 Ymd */
    private function prevDay(int $dateInt): int
    {
        [$start] = $this->dayRange($dateInt);
        return (int)date('Ymd', $start - 86400);
    }

    /** 区间内每一天 Ymd 列表 */
    private function eachDay(int $startInt, int $endInt): array
    {
        [$cur] = $this->dayRange($startInt);
        [$endTs] = $this->dayRange($endInt);
        $days = [];
        while ($cur <= $endTs) {
            $days[] = (int)date('Ymd', $cur);
            $cur += 86400;
        }
        return $days;
    }

    /**
     * 聚合"某一天"的维度分布（只扫大表的一小片：当天 create_at），返回扁平行。
     * 维度：category(分类) / model(型号) / grade(成色) / source(来源,join 订单)
     * @return array<int,array{dim_type:string,dim_value:string,cnt:int,amount:float}>
     */
    public function aggregateDayDim(int $siteId, int $dateInt): array
    {
        [$start, $end] = $this->dayRange($dateInt);
        $out = [];
        $deviceFields = (new RecycleDevice())->getTableFields();
        // 设备自带维度（仅聚合表中真实存在的列，兼容历史库缺列如 condition_grade）
        foreach (['category' => 'category_id', 'model' => 'model', 'grade' => 'condition_grade'] as $type => $field) {
            if (!in_array($field, $deviceFields)) continue;
            $rows = (new RecycleDevice())
                ->where([['site_id', '=', $siteId], ['create_at', 'between', [$start, $end]]])
                ->field($field . ' as dv, count(*) as cnt, sum(final_price) as amt')
                ->group($field)->select()->toArray();
            foreach ($rows as $r) {
                $dv = trim((string)($r['dv'] ?? ''));
                if ($dv === '') continue;
                $out[] = ['dim_type' => $type, 'dim_value' => $dv, 'cnt' => (int)$r['cnt'], 'amount' => (float)$r['amt']];
            }
        }
        // 来源：join 订单表（同一天小片，cnt 仍是设备数）
        $orderTable = (new RecycleOrder())->getTable();
        $srcRows = (new RecycleDevice())->alias('d')
            ->join($orderTable . ' o', 'd.order_id = o.id')
            ->where([['d.site_id', '=', $siteId], ['d.create_at', 'between', [$start, $end]]])
            ->field('o.order_source as dv, count(*) as cnt, sum(d.final_price) as amt')
            ->group('o.order_source')->select()->toArray();
        foreach ($srcRows as $r) {
            $dv = trim((string)($r['dv'] ?? ''));
            if ($dv === '') continue;
            $out[] = ['dim_type' => 'source', 'dim_value' => $dv, 'cnt' => (int)$r['cnt'], 'amount' => (float)$r['amt']];
        }
        return $out;
    }

    /** 把某一天的维度分布写入汇总表（幂等覆盖），并打"已回填"标记 */
    public function rollupDailyDim(int $siteId, int $dateInt): void
    {
        $now = time();
        $model = new RecycleStatDailyDim();
        foreach ($this->aggregateDayDim($siteId, $dateInt) as $r) {
            $where = [
                ['site_id', '=', $siteId], ['stat_date', '=', $dateInt],
                ['dim_type', '=', $r['dim_type']], ['dim_value', '=', $r['dim_value']],
            ];
            $data = ['cnt' => $r['cnt'], 'amount' => $r['amount'], 'update_time' => $now];
            if ($model->where($where)->findOrEmpty()->isEmpty()) {
                $model->create(array_merge([
                    'site_id' => $siteId, 'stat_date' => $dateInt,
                    'dim_type' => $r['dim_type'], 'dim_value' => $r['dim_value'],
                ], $data));
            } else {
                $model->where($where)->update($data);
            }
        }
        // 标记该天已回填（即使当天 0 台，也不再重复扫）
        $markWhere = [['site_id', '=', $siteId], ['stat_date', '=', $dateInt], ['dim_type', '=', '_done'], ['dim_value', '=', '1']];
        if ((new RecycleStatDailyDim())->where($markWhere)->findOrEmpty()->isEmpty()) {
            (new RecycleStatDailyDim())->create([
                'site_id' => $siteId, 'stat_date' => $dateInt,
                'dim_type' => '_done', 'dim_value' => '1', 'cnt' => 0, 'amount' => 0, 'update_time' => $now,
            ]);
        }
    }

    /**
     * 读某维度在时间段内的 TOP 分布（历史读汇总表 + 缺天懒回填；当天实时算后合并）。
     * 大表只在"补历史某天"和"当天"被扫一小片，永不全表聚合。
     * @return array<int,array{dim_value:string,cnt:int,amount:float}>
     */
    public function getDimBreakdown(int $siteId, string $dimType, int $startDateInt, int $endDateInt, int $limit = 10): array
    {
        $today = (int)date('Ymd');
        $agg = [];
        $merge = function (string $dv, int $cnt, float $amt) use (&$agg) {
            if (!isset($agg[$dv])) $agg[$dv] = ['cnt' => 0, 'amount' => 0.0];
            $agg[$dv]['cnt'] += $cnt;
            $agg[$dv]['amount'] += $amt;
        };

        // 历史天（< 今天）：读汇总表，缺的懒回填
        $pastEnd = min($endDateInt, $this->prevDay($today));
        if ($startDateInt <= $pastEnd) {
            $doneDays = (new RecycleStatDailyDim())
                ->where([['site_id', '=', $siteId], ['dim_type', '=', '_done'], ['stat_date', 'between', [$startDateInt, $pastEnd]]])
                ->column('stat_date');
            $doneSet = array_flip(array_map('intval', $doneDays));
            foreach ($this->eachDay($startDateInt, $pastEnd) as $d) {
                if (!isset($doneSet[$d])) {
                    $this->rollupDailyDim($siteId, $d);
                }
            }
            $rows = (new RecycleStatDailyDim())
                ->where([['site_id', '=', $siteId], ['dim_type', '=', $dimType], ['stat_date', 'between', [$startDateInt, $pastEnd]]])
                ->field('dim_value, sum(cnt) as cnt, sum(amount) as amount')
                ->group('dim_value')->select()->toArray();
            foreach ($rows as $r) {
                $merge((string)$r['dim_value'], (int)$r['cnt'], (float)$r['amount']);
            }
        }

        // 当天：实时算一小片（不写表，避免脏数据）
        if ($endDateInt >= $today) {
            foreach ($this->aggregateDayDim($siteId, $today) as $r) {
                if ($r['dim_type'] !== $dimType) continue;
                $merge($r['dim_value'], $r['cnt'], $r['amount']);
            }
        }

        $list = [];
        foreach ($agg as $dv => $v) {
            $list[] = ['dim_value' => (string)$dv, 'cnt' => (int)$v['cnt'], 'amount' => round((float)$v['amount'], 2)];
        }
        usort($list, fn($a, $b) => $b['cnt'] <=> $a['cnt']);
        return array_slice($list, 0, max(1, $limit));
    }
}
