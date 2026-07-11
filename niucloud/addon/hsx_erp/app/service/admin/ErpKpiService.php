<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use app\model\sys\SysUser;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpKpiService extends BaseAdminService
{
    private const METRICS = [
        'sale_amount' => ['name' => '销售额', 'unit' => '元', 'target' => 50000, 'weight' => 35],
        'sale_profit' => ['name' => '销售毛利', 'unit' => '元', 'target' => 5000, 'weight' => 25],
        'purchase_count' => ['name' => '采购台次', 'unit' => '台', 'target' => 50, 'weight' => 15],
        'inspection_count' => ['name' => '质检台次', 'unit' => '台', 'target' => 80, 'weight' => 15],
        'settlement_amount' => ['name' => '财务结算额', 'unit' => '元', 'target' => 50000, 'weight' => 10],
    ];

    public function rules(): array
    {
        $rows = Db::name('erp_kpi_rule')->where('site_id', '=', $this->site_id)->order('sort asc,id asc')->select()->toArray();
        if ($rows !== []) return $rows;
        $now = time();
        $sort = 1;
        foreach (self::METRICS as $key => $meta) {
            Db::name('erp_kpi_rule')->insert(['site_id' => $this->site_id, 'metric_key' => $key, 'metric_name' => $meta['name'], 'unit' => $meta['unit'], 'target_value' => $meta['target'], 'weight' => $meta['weight'], 'enabled' => 1, 'sort' => $sort++, 'create_at' => $now, 'update_at' => $now]);
        }
        return Db::name('erp_kpi_rule')->where('site_id', '=', $this->site_id)->order('sort asc,id asc')->select()->toArray();
    }

    public function saveRules(array $rows): array
    {
        if ($rows === []) throw new CommonException('至少保留一项考核指标');
        $normalized = [];
        foreach ($rows as $index => $row) {
            $key = trim((string)($row['metric_key'] ?? ''));
            if (!isset(self::METRICS[$key])) throw new CommonException('存在不支持的绩效指标');
            $target = round((float)($row['target_value'] ?? 0), 2);
            $weight = round((float)($row['weight'] ?? 0), 2);
            if ($target <= 0 || $weight <= 0) throw new CommonException('目标值和权重必须大于0');
            $meta = self::METRICS[$key];
            $normalized[] = ['site_id' => $this->site_id, 'metric_key' => $key, 'metric_name' => $meta['name'], 'unit' => $meta['unit'], 'target_value' => $target, 'weight' => $weight, 'enabled' => (int)($row['enabled'] ?? 1) === 1 ? 1 : 0, 'sort' => $index + 1, 'create_at' => time(), 'update_at' => time()];
        }
        Db::transaction(function () use ($normalized) {
            Db::name('erp_kpi_rule')->where('site_id', '=', $this->site_id)->delete();
            Db::name('erp_kpi_rule')->insertAll($normalized);
        });
        return $this->rules();
    }

    public function dashboard(array $where): array
    {
        [$start, $end] = $this->range($where);
        $rules = array_values(array_filter($this->rules(), static fn(array $row): bool => (int)$row['enabled'] === 1));
        $facts = [];
        $add = static function (array &$target, int $uid, string $key, float $value): void { if ($uid > 0) $target[$uid][$key] = round(($target[$uid][$key] ?? 0) + $value, 2); };

        $sales = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['status', '<>', 'void']])->whereBetweenTime('sale_at', $start, $end)->field('salesman_uid,total_amount,profit')->select()->toArray();
        foreach ($sales as $row) { $add($facts, (int)$row['salesman_uid'], 'sale_amount', (float)$row['total_amount']); $add($facts, (int)$row['salesman_uid'], 'sale_profit', (float)$row['profit']); }
        $purchases = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['status', '<>', 'void']])->whereBetweenTime('purchase_at', $start, $end)->field('purchaser_uid')->select()->toArray();
        foreach ($purchases as $row) $add($facts, (int)$row['purchaser_uid'], 'purchase_count', 1);
        $assets = ErpAsset::where('site_id', '=', $this->site_id)->whereBetweenTime('stock_in_at', $start, $end)->field('inspector_uid')->select()->toArray();
        foreach ($assets as $row) $add($facts, (int)$row['inspector_uid'], 'inspection_count', 1);
        $settlements = ErpSettlement::where([['site_id', '=', $this->site_id], ['status', '=', 'confirmed']])->whereBetweenTime('confirmed_at', $start, $end)->field('operator_uid,amount')->select()->toArray();
        foreach ($settlements as $row) $add($facts, (int)$row['operator_uid'], 'settlement_amount', (float)$row['amount']);

        $users = $facts === [] ? [] : SysUser::whereIn('uid', array_keys($facts))->field('uid,username,real_name')->select()->toArray();
        $names = [];
        foreach ($users as $user) $names[(int)$user['uid']] = (string)($user['real_name'] ?: $user['username'] ?: ('员工#' . $user['uid']));
        $staff = [];
        foreach ($facts as $uid => $values) {
            $details = []; $score = 0; $weightTotal = 0;
            foreach ($rules as $rule) {
                $actual = (float)($values[$rule['metric_key']] ?? 0); $target = (float)$rule['target_value']; $weight = (float)$rule['weight'];
                $metricScore = min($weight * 1.2, $target > 0 ? $actual / $target * $weight : 0);
                $score += $metricScore; $weightTotal += $weight;
                $details[] = ['metric_key' => $rule['metric_key'], 'metric_name' => $rule['metric_name'], 'unit' => $rule['unit'], 'actual' => round($actual, 2), 'target' => $target, 'completion_rate' => round($target > 0 ? $actual / $target * 100 : 0, 1), 'score' => round($metricScore, 1)];
            }
            $staff[] = ['uid' => (int)$uid, 'name' => $names[(int)$uid] ?? ('员工#' . $uid), 'score' => round($weightTotal > 0 ? $score / $weightTotal * 100 : 0, 1), 'details' => $details];
        }
        usort($staff, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return ['range' => ['start_at' => $start, 'end_at' => $end], 'rules' => $rules, 'staff' => $staff];
    }

    private function range(array $where): array
    {
        $start = (int)($where['start_at'] ?? 0); $end = (int)($where['end_at'] ?? 0);
        if ($start > 0 && $end > 0) return [$start, $end];
        $period = (string)($where['period'] ?? 'month');
        return match ($period) {
            'today' => [strtotime('today 00:00:00'), strtotime('today 23:59:59')],
            'yesterday' => [strtotime('yesterday 00:00:00'), strtotime('yesterday 23:59:59')],
            'last7' => [strtotime('-6 days 00:00:00'), strtotime('today 23:59:59')],
            'last_month' => [strtotime('first day of last month 00:00:00'), strtotime('last day of last month 23:59:59')],
            'all' => [1, time()],
            default => [strtotime(date('Y-m-01 00:00:00')), strtotime(date('Y-m-t 23:59:59'))],
        };
    }
}
