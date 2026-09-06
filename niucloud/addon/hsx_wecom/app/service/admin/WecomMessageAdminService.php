<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\model\WecomMessageLog;
use addon\hsx_wecom\app\service\core\WecomNotificationService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class WecomMessageAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = WecomMessageLog::where('site_id', '=', $this->site_id);
        if (!empty($where['status'])) $query->where('status', '=', (string)$where['status']);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('title|receiver_name|wecom_userid|error_message', '%' . $kw . '%');
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, (int)($where['limit'] ?? 15)),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) $payload = [];
            $deliveryTarget = is_array($payload['wecom_target'] ?? null) ? $payload['wecom_target'] : [];
            $businessTarget = is_array($payload['target'] ?? null) ? $payload['target'] : [];
            $row['target_plugin'] = trim((string)($deliveryTarget['plugin'] ?? $businessTarget['plugin'] ?? ''));
            $row['target_route_key'] = trim((string)($deliveryTarget['route_key'] ?? $businessTarget['route_key'] ?? ''));
            $row['target_label'] = $this->targetLabel($row['target_plugin'], $row['target_route_key']);
            $row['web_target_configured'] = trim((string)($deliveryTarget['web_url'] ?? $businessTarget['web_path'] ?? $row['target_url'] ?? '')) !== '' ? 1 : 0;
            $row['miniapp_target_configured'] = trim((string)($deliveryTarget['miniapp_path'] ?? $businessTarget['miniapp_path'] ?? '')) !== '' ? 1 : 0;
        }
        unset($row);
        return $page;
    }

    private function targetLabel(string $plugin, string $routeKey): string
    {
        if (str_starts_with($routeKey, 'hsx_erp.payable.')) return 'ERP 应付款';
        if (str_starts_with($routeKey, 'hsx_erp.receivable.')) return 'ERP 应收款';
        if (str_starts_with($routeKey, 'hsx_recycle.task.')) return '回收待办';
        if (str_starts_with($routeKey, 'hsx_recycle.order.')) return '回收订单';
        if (str_starts_with($routeKey, 'hsx_performance.report.')) return '经营报告';
        return match ($plugin) {
            'hsx_erp' => '二手机 ERP',
            'hsx_recycle' => '回收业务',
            'hsx_performance' => '经营报告',
            default => $routeKey !== '' ? $routeKey : '未标识',
        };
    }

    public function retry(int $id): bool
    {
        $log = WecomMessageLog::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($log->isEmpty()) throw new CommonException('消息记录不存在');
        $log->save(['status' => 'pending', 'retry_count' => 0, 'next_retry_at' => time(), 'update_at' => time()]);
        return (new WecomNotificationService())->dispatch($id);
    }

    public function test(int $receiverUid = 0): array
    {
        $receiverUid = $receiverUid > 0 ? $receiverUid : (int)$this->uid;
        if ($receiverUid <= 0) throw new CommonException('请选择接收测试通知的员工');
        return (new WecomNotificationService())->sendTest((int)$this->site_id, $receiverUid);
    }
}
