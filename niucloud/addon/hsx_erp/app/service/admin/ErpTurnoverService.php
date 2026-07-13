<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
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
        $rows = (new ErpWarehousePolicyService())->decorate($rows);
        $rules = $this->rules();
        foreach ($rows as &$row) {
            $meta = $this->meta(
                (int)($row['stock_in_at'] ?? 0) ?: (int)($row['create_at'] ?? 0),
                (string)($row['status'] ?? ''),
                $rules
            );
            $row = array_merge($row, $meta, $this->resolveAction($row, $meta));
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
                'turnover_action' => '设备已退出库存',
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

    private function resolveAction(array $row, array $meta): array
    {
        $policy = (array)($row['warehouse_policy'] ?? []);
        $policyAction = (string)($policy['primary_action'] ?? 'view');
        $policyLabel = (string)($policy['primary_action_label'] ?? '查看档案');
        $policyReason = (string)($policy['primary_action_reason'] ?? '查看设备当前状态');
        $level = (string)($meta['turnover_level'] ?? 'inactive');

        if (!in_array($policyAction, ['direct_sale', 'view'], true)) {
            return [
                'turnover_action_key' => $policyAction,
                'turnover_action_label' => $policyLabel,
                'turnover_action' => $policyReason,
            ];
        }
        if ($policyAction === 'view' || $level === 'inactive') {
            return [
                'turnover_action_key' => 'view',
                'turnover_action_label' => '查看档案',
                'turnover_action' => $policyReason,
            ];
        }
        if (in_array($level, ['attention', 'warning', 'critical'], true)) {
            if ((float)($row['retail_price'] ?? 0) <= 0) {
                return [
                    'turnover_action_key' => 'set_retail_price',
                    'turnover_action_label' => '设置零售价',
                    'turnover_action' => '当前没有零售价，无法有效判断市场反馈',
                ];
            }
            if ($level === 'critical' && (int)($policy['can_transfer'] ?? 0) === 1) {
                return [
                    'turnover_action_key' => 'transfer',
                    'turnover_action_label' => '调拨或清理',
                    'turnover_action' => '设备严重滞销，建议复核零售价后调拨至更合适的销售仓',
                ];
            }
            return [
                'turnover_action_key' => 'adjust_retail_price',
                'turnover_action_label' => '复核零售价',
                'turnover_action' => $level === 'attention' ? '开始关注询价和当前零售价' : '优先复核零售价与销售渠道',
            ];
        }
        return [
            'turnover_action_key' => 'direct_sale',
            'turnover_action_label' => '销售出库',
            'turnover_action' => '当前设备资料和仓库状态允许直接销售',
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
        $timeExpr = 'IF(a.stock_in_at > 0, a.stock_in_at, a.create_at)';
        $table = (new ErpAsset())->getTable();
        $warehouseTable = (new ErpWarehouse())->getTable();
        $siteId = (int)$this->site_id;
        $status = ErpDict::ASSET_IN_STOCK;
        $imagePresentExpr = "LOWER(TRIM(COALESCE(a.image_urls,''))) NOT IN ('', '[]', '{}', 'null')";
        $listingReadyExpr = "w.default_sale_target = 'mall' AND a.refurbish_status NOT IN ('pending','processing','failed')
            AND a.category_id > 0 AND TRIM(COALESCE(a.spec,'')) <> ''
            AND (w.need_photo = 0 OR {$imagePresentExpr})
            AND (w.need_pricing = 0 OR a.retail_price > 0)";
        $listingIncompleteExpr = "w.default_sale_target = 'mall' AND a.refurbish_status NOT IN ('pending','processing','failed') AND NOT ({$listingReadyExpr})";
        $sql = "SELECT COUNT(*) AS total_count, COALESCE(SUM(a.total_cost),0) AS total_cost,
            COALESCE(AVG(GREATEST(0,FLOOR(({$now} - {$timeExpr})/86400))),0) AS average_age_days,
            SUM(CASE WHEN {$timeExpr} > {$attentionCutoff} THEN 1 ELSE 0 END) AS healthy_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} > {$attentionCutoff} THEN a.total_cost ELSE 0 END),0) AS healthy_cost,
            SUM(CASE WHEN {$timeExpr} <= {$attentionCutoff} AND {$timeExpr} > {$warningCutoff} THEN 1 ELSE 0 END) AS attention_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} <= {$attentionCutoff} AND {$timeExpr} > {$warningCutoff} THEN a.total_cost ELSE 0 END),0) AS attention_cost,
            SUM(CASE WHEN {$timeExpr} <= {$warningCutoff} AND {$timeExpr} > {$criticalCutoff} THEN 1 ELSE 0 END) AS warning_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} <= {$warningCutoff} AND {$timeExpr} > {$criticalCutoff} THEN a.total_cost ELSE 0 END),0) AS warning_cost,
            SUM(CASE WHEN {$timeExpr} <= {$criticalCutoff} THEN 1 ELSE 0 END) AS critical_count,
            COALESCE(SUM(CASE WHEN {$timeExpr} <= {$criticalCutoff} THEN a.total_cost ELSE 0 END),0) AS critical_cost,
            SUM(CASE WHEN w.allow_direct_sale = 1 AND a.refurbish_status NOT IN ('pending','processing','failed') THEN 1 ELSE 0 END) AS saleable_count,
            SUM(CASE WHEN w.allow_transfer = 1 AND a.refurbish_status NOT IN ('pending','processing','failed') THEN 1 ELSE 0 END) AS transferable_count,
            SUM(CASE WHEN a.refurbish_status IN ('pending','processing','failed') THEN 1 ELSE 0 END) AS refurbish_blocked_count,
            SUM(CASE WHEN w.default_sale_target = 'mall' AND a.category_id <= 0 THEN 1 ELSE 0 END) AS missing_category_count,
            SUM(CASE WHEN w.default_sale_target = 'mall' AND TRIM(COALESCE(a.spec,'')) = '' THEN 1 ELSE 0 END) AS missing_spec_count,
            SUM(CASE WHEN w.default_sale_target = 'mall' AND w.need_photo = 1 AND NOT ({$imagePresentExpr}) THEN 1 ELSE 0 END) AS missing_image_count,
            SUM(CASE WHEN w.default_sale_target = 'mall' AND w.need_pricing = 1 AND a.retail_price <= 0 THEN 1 ELSE 0 END) AS missing_price_count,
            SUM(CASE WHEN {$listingIncompleteExpr} THEN 1 ELSE 0 END) AS listing_incomplete_count,
            SUM(CASE WHEN {$listingReadyExpr} AND a.listing_status <> 'listed' THEN 1 ELSE 0 END) AS ready_to_list_count,
            SUM(CASE WHEN w.allow_direct_sale <> 1 AND w.allow_transfer <> 1 AND w.default_sale_target <> 'mall' THEN 1 ELSE 0 END) AS blocked_count
            FROM `{$table}` a LEFT JOIN `{$warehouseTable}` w ON w.id = a.warehouse_id AND w.site_id = a.site_id
            WHERE a.site_id = {$siteId} AND a.status = '{$status}'";
        $aggregate = (array)(Db::query($sql)[0] ?? []);
        $summary = array_merge([
            'total_count' => 0, 'total_cost' => 0.0, 'average_age_days' => 0.0,
            'healthy_count' => 0, 'healthy_cost' => 0.0, 'attention_count' => 0, 'attention_cost' => 0.0,
            'warning_count' => 0, 'warning_cost' => 0.0, 'critical_count' => 0, 'critical_cost' => 0.0,
            'saleable_count' => 0, 'transferable_count' => 0, 'refurbish_blocked_count' => 0,
            'missing_category_count' => 0, 'missing_spec_count' => 0, 'missing_image_count' => 0, 'missing_price_count' => 0,
            'listing_incomplete_count' => 0, 'ready_to_list_count' => 0, 'blocked_count' => 0,
        ], $aggregate, [
            'warning_total_count' => 0, 'warning_total_cost' => 0.0,
            'thresholds' => ['attention_days' => $attention, 'warning_days' => $warning, 'critical_days' => $critical],
        ]);
        foreach (['total_count', 'healthy_count', 'attention_count', 'warning_count', 'critical_count', 'saleable_count', 'transferable_count',
            'refurbish_blocked_count', 'missing_category_count', 'missing_spec_count', 'missing_image_count', 'missing_price_count',
            'listing_incomplete_count', 'ready_to_list_count', 'blocked_count'] as $key) $summary[$key] = (int)$summary[$key];
        $summary['average_age_days'] = round((float)$summary['average_age_days'], 1);
        $summary['warning_total_count'] = $summary['warning_count'] + $summary['critical_count'];
        $summary['warning_total_cost'] = round($summary['warning_cost'] + $summary['critical_cost'], 2);
        foreach (['total_cost', 'healthy_cost', 'attention_cost', 'warning_cost', 'critical_cost', 'warning_total_cost'] as $key) {
            $summary[$key] = round((float)$summary[$key], 2);
        }
        $summary['warning_rate'] = $summary['total_count'] > 0
            ? round($summary['warning_total_count'] / $summary['total_count'] * 100, 2)
            : 0.0;
        $summary['warehouse_risks'] = $this->warehouseRisks($warehouseTable, $table, $siteId, $warningCutoff, $now);
        $summary['actions'] = $this->actionSummary($summary);
        $summary['reminder_visible'] = (int)($rules['reminder_enabled'] ?? 1) === 1
            && (string)($rules['reminder_dismiss_date'] ?? '') !== date('Y-m-d')
            && $summary['warning_total_count'] >= max(1, (int)($rules['reminder_count_threshold'] ?? 1));
        return $summary;
    }

    private function warehouseRisks(string $warehouseTable, string $assetTable, int $siteId, int $warningCutoff, int $now): array
    {
        $timeExpr = 'IF(a.stock_in_at > 0, a.stock_in_at, a.create_at)';
        $rows = Db::query("SELECT a.warehouse_id, COALESCE(w.warehouse_name, a.warehouse_name, '未设置仓库') AS warehouse_name,
            COUNT(*) AS warning_count, COALESCE(SUM(a.total_cost),0) AS warning_cost,
            COALESCE(AVG(GREATEST(0,FLOOR(({$now} - {$timeExpr})/86400))),0) AS average_age_days
            FROM `{$assetTable}` a LEFT JOIN `{$warehouseTable}` w ON w.id = a.warehouse_id AND w.site_id = a.site_id
            WHERE a.site_id = {$siteId} AND a.status = '" . ErpDict::ASSET_IN_STOCK . "' AND {$timeExpr} <= {$warningCutoff}
            GROUP BY a.warehouse_id, warehouse_name ORDER BY warning_cost DESC, warning_count DESC LIMIT 5");
        return array_map(static function (array $row): array {
            return [
                'warehouse_id' => (int)($row['warehouse_id'] ?? 0),
                'warehouse_name' => (string)($row['warehouse_name'] ?? '未设置仓库'),
                'warning_count' => (int)($row['warning_count'] ?? 0),
                'warning_cost' => round((float)($row['warning_cost'] ?? 0), 2),
                'average_age_days' => round((float)($row['average_age_days'] ?? 0), 1),
            ];
        }, $rows);
    }

    private function actionSummary(array $summary): array
    {
        return array_values(array_filter([
            (int)$summary['refurbish_blocked_count'] > 0 ? [
                'key' => 'refurbish', 'label' => '处理整备设备', 'count' => (int)$summary['refurbish_blocked_count'],
                'query' => ['refurbish_status' => 'pending'],
            ] : null,
            (int)$summary['listing_incomplete_count'] > 0 ? [
                'key' => 'complete_listing', 'label' => '完善商城资料', 'count' => (int)$summary['listing_incomplete_count'],
                'query' => ['listing_status' => 'incomplete'],
            ] : null,
            (int)$summary['ready_to_list_count'] > 0 ? [
                'key' => 'ready_to_list', 'label' => '处理待上架', 'count' => (int)$summary['ready_to_list_count'],
                'query' => ['listing_status' => 'ready'],
            ] : null,
            (int)$summary['warning_total_count'] > 0 ? [
                'key' => 'turnover_risk', 'label' => '处理周转预警', 'count' => (int)$summary['warning_total_count'],
                'query' => ['turnover_level' => 'risk'],
            ] : null,
        ]));
    }
}
