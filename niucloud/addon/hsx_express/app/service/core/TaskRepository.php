<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use core\exception\CommonException;
use think\facade\Db;

class TaskRepository
{
    private const TABLE = 'hsx_express_task';
    public function find(int $siteId, int $id): array { return Db::name(self::TABLE)->where('site_id', $siteId)->where('id', $id)->find() ?: []; }
    public function findBusiness(int $siteId, string $type, string $id): array { return Db::name(self::TABLE)->where('site_id', $siteId)->where('business_type', $type)->where('business_id', $id)->order('id', 'desc')->find() ?: []; }
    public function activeForOrder(int $siteId, string $type, int $orderId): array
    {
        return Db::name(self::TABLE)->where('site_id', $siteId)->where('business_type', $type)->where('business_order_id', $orderId)
            ->whereNotIn('state', ['cancelled', 'failed'])->select()->toArray();
    }
    public function findNumber(int $siteId, string $number): array { return Db::name(self::TABLE)->where('site_id', $siteId)->where('task_no', $number)->find() ?: []; }
    public function create(array $data): array
    {
        try { $id = (int)Db::name(self::TABLE)->insertGetId($data); }
        catch (\Throwable $e) { throw new CommonException('无法保存物流任务，请确认已执行 hsx_express 安装 SQL；未保存前不会请求快递100'); }
        return $this->find((int)$data['site_id'], $id);
    }
    public function update(array $task, array $changes, string $operation = ''): array
    {
        $changes['update_at'] = time();
        if ($operation !== '') {
            $logs = json_decode((string)($task['logs_json'] ?? '[]'), true) ?: [];
            $operator = ['uid' => 0, 'name' => '服务回调'];
            try {
                if (function_exists('request') && request()->uid()) $operator = ['uid' => (int)request()->uid(), 'name' => mb_substr((string)request()->username(), 0, 60)];
            } catch (\Throwable $e) { /* 非登录回调没有操作者身份。 */ }
            $logs[] = ['operation' => $operation, 'state' => $changes['state'] ?? $task['state'], 'message' => $changes['message'] ?? '', 'at' => time(), 'operator' => $operator];
            $changes['logs_json'] = json_encode(array_slice($logs, -100), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        Db::name(self::TABLE)->where('site_id', $task['site_id'])->where('id', $task['id'])->update($changes);
        return array_replace($task, $changes);
    }
    public function page(int $siteId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1)); $limit = max(1, min(100, (int)($filters['limit'] ?? 15)));
        $query = Db::name(self::TABLE)->where('site_id', $siteId);
        if (!empty($filters['state'])) $query->where('state', (string)$filters['state']);
        if (!empty($filters['keyword'])) $query->whereLike('task_no|business_id|waybill_no|business_refs_json', '%' . addcslashes(mb_substr((string)$filters['keyword'], 0, 100), '%_') . '%');
        $total = (clone $query)->count();
        $rows = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return ['data' => $rows, 'total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => (int)ceil($total / $limit)];
    }
}
