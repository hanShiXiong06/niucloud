<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use core\base\BaseAdminService;
use think\facade\Db;

/**
 * 库存周转统一口径。
 *
 * 库龄和预警等级只在后端计算，PC、移动端和工作台共享同一结果，
 * 避免不同终端各自写死 30/90 天后产生口径差异。
 */
class ErpTurnoverService extends BaseAdminService
{
    public function rules(): array
    {
        return (array)((new ErpConfigService())->getRules()['turnover'] ?? []);
    }

    public function decorate(array $rows): array
    {
        $rules = $this->rules();
        foreach ($rows as &$row) {
            $row = array_merge($row, $this->meta(
                (int)($row['stock_in_at'] ?? 0) ?: (int)($row['create_at'] ?? 0),
                (string)($row['status'] ?? ''),
                $rules
            ));
        }
        unset($row);
        return $rows;
    }

    public function meta(int $stockInAt, string $status, ?array $rules = null): array
    {
        $rules ??= $this->rules();
        $days = $stockInAt > 0 ? max(0, (int)floor((time() - $stockInAt) / 86400)) : 0;
        if ($status !== ErpDict::ASSET_IN_STOCK) {
            return [
                'stock_age_days' => $days,
                'turnover_level' => 'inactive',
                'turnover_label' => '已退出库存',
                'turnover_type' => 'info',
                'turnover_overdue_days' => 0,
                'is_turnover_warning' => 0,
                'turnover_action' => '',
            ];
        }

        $attention = (int)($rules['attention_days'] ?? 7);
        $warning = (int)($rules['warning_days'] ?? 15);
        $critical = (int)($rules['critical_days'] ?? 30);
        if ($days > $critical) {
            [$level, $label, $type, $action] = ['critical', '严重滞销', 'danger', '建议转同行、调仓或专项清理'];
        } elseif ($days > $warning) {
            [$level, $label, $type, $action] = ['warning', '周转预警', 'warning', '优先检查售价、图片与销售渠道'];
        } elseif ($days > $attention) {
            [$level, $label, $type, $action] = ['attention', '需要关注', 'primary', '关注询价和上架完整度'];
        } else {
            [$level, $label, $type, $action] = ['healthy', '周转正常', 'success', '保持当前销售节奏'];
        }
        return [
            'stock_age_days' => $days,
            'turnover_level' => $level,
            'turnover_label' => $label,
            'turnover_type' => $type,
            'turnover_overdue_days' => max(0, $days - $warning),
            'is_turnover_warning' => in_array($level, ['warning', 'critical'], true) ? 1 : 0,
            'turnover_action' => $action,
        ];
    }

    public function summary(): array
    {
        $rules = $this->rules();
        $attention = (int)($rules['attention_days'] ?? 7);
        $warning = (int)($rules['warning_days'] ?? 15);
        $critical = (int)($rules['critical_days'] ?? 30);
        $now = time();
        // meta() 以 floor 后的完整天数判断“> 阈值”，因此 SQL 截点也使用 threshold + 1 天。
        $attentionCutoff = $now - ($attention + 1) * 86400;
        $warningCutoff = $now - ($warning + 1) * 86400;
        $criticalCutoff = $now - ($critical + 1) * 86400;
        $timeExpr = 'IF(stock_in_at > 0, stock_in_at, create_at)';
        $table = (new ErpAsset())->getTable();
        $siteId = (int)$this->site_id;
        $status = ErpDict::ASSET_IN_STOCK;
        $sql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(total_cost),0) AS total_cost,
            COALESCE(AVG(GREATEST(0,FLOOR(({$now} - {$timeExpr})/86400))),0) AS average_age_days,
            SUM(CASE WHEN {$timeExpr} >= {$attentionCutoff} THEN 1 ELSE 0 END) AS healthy_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} >= {$attentionCutoff} THEN total_cost ELSE 0 END),0) AS healthy_cost,
            SUM(CASE WHEN {$timeExpr} < {$attentionCutoff} AND {$timeExpr} >= {$warningCutoff} THEN 1 ELSE 0 END) AS attention_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} < {$attentionCutoff} AND {$timeExpr} >= {$warningCutoff} THEN total_cost ELSE 0 END),0) AS attention_cost,
            SUM(CASE WHEN {$timeExpr} < {$warningCutoff} AND {$timeExpr} >= {$criticalCutoff} THEN 1 ELSE 0 END) AS warning_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} < {$warningCutoff} AND {$timeExpr} >= {$criticalCutoff} THEN total_cost ELSE 0 END),0) AS warning_cost,
            SUM(CASE WHEN {$timeExpr} < {$criticalCutoff} THEN 1 ELSE 0 END) AS critical_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} < {$criticalCutoff} THEN total_cost ELSE 0 END),0) AS critical_cost
            FROM `{$table}` WHERE site_id = {$siteId} AND status = '{$status}'";
        $aggregate = (array)(Db::query($sql)[0] ?? []);
        $summary = array_merge([
            'total_count' => 0, 'total_cost' => 0.0, 'average_age_days' => 0.0,
            'healthy_count' => 0, 'healthy_cost' => 0.0, 'attention_count' => 0, 'attention_cost' => 0.0,
            'warning_count' => 0, 'warning_cost' => 0.0, 'critical_count' => 0, 'critical_cost' => 0.0,
        ], $aggregate, [
            'warning_total_count' => 0, 'warning_total_cost' => 0.0,
            'thresholds' => ['attention_days' => $attention, 'warning_days' => $warning, 'critical_days' => $critical],
        ]);
        foreach (['total_count', 'healthy_count', 'attention_count', 'warning_count', 'critical_count'] as $key) $summary[$key] = (int)$summary[$key];
        $summary['average_age_days'] = round((float)$summary['average_age_days'], 1);
        $summary['warning_total_count'] = $summary['warning_count'] + $summary['critical_count'];
        $summary['warning_total_cost'] = round($summary['warning_cost'] + $summary['critical_cost'], 2);
        foreach (['total_cost', 'healthy_cost', 'attention_cost', 'warning_cost', 'critical_cost', 'warning_total_cost'] as $key) {
            $summary[$key] = round((float)$summary[$key], 2);
        }
        $summary['reminder_visible'] = (int)($rules['reminder_enabled'] ?? 1) === 1
            && (string)($rules['reminder_dismiss_date'] ?? '') !== date('Y-m-d')
            && $summary['warning_total_count'] >= max(1, (int)($rules['reminder_count_threshold'] ?? 1));
        return $summary;
    }
}
