<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

/** Read-only sync projections. A cached UI or a stale pointer is not a running job. */
final class SyncStatus
{
    public static function progress(array $run, bool $published = false): array
    {
        $status = self::status($run['status'] ?? null);
        $count = max(0, (int) ($run['record_count'] ?? 0));
        $cursor = Data::decode($run['cursor_json'] ?? null);
        $raw = $cursor['source_total'] ?? null;
        $total = (is_int($raw) || is_string($raw)) && preg_match('/^(?:0|[1-9][0-9]{0,7})$/D', (string) $raw) ? (int) $raw : null;
        $complete = $status === 'completed';
        // Stored adapter diagnostics stay in the task record, never in a user-facing summary.
        $reason = ProviderPublicMessage::safeReason($run['error_code'] ?? null);
        $code = $status === 'failed' ? ($reason ?: 'sync_failed') : ($status === 'cancelled' ? $reason : '');
        $message = match ($status) {
            'pending' => '已开始全量同步，正在准备读取库存',
            'running' => '已读取 '.$count.' 条库存，正在继续同步'.($total !== null ? '（共 '.$total.' 条）' : ''),
            'completed' => '完整同步已完成，共读取 '.$count.' 条库存；可售情况请向门店核对',
            'cancelled' => $reason === 'generation_expired' ? ProviderPublicMessage::forReason($reason) : '本次同步已取消，未替换已有完整快照',
            'failed' => ProviderPublicMessage::forReason($reason !== '' ? $reason : 'sync_failed'),
            default => '同步状态待核对，请刷新或重新同步',
        };
        return ['run_id' => (int) ($run['id'] ?? 0), 'status' => $status, 'page_count' => (int) ($run['page_count'] ?? 0),
            'record_count' => $count, 'total_records' => $total, 'progress_known' => $complete || $total !== null,
            'error_code' => $code, 'sync_error_code' => $code,
            'progress_percent' => $complete ? 100 : ($total !== null && $total > 0 ? min(99, (int) floor($count / $total * 100)) : null),
            'complete' => $complete, 'published' => $published, 'message' => $message];
    }

    /** Never mutates historical records. Joins are scoped to the exact connection and merchant. */
    public static function connection(int $site, array $connection): array
    {
        $status = self::status($connection['last_sync_status'] ?? 'idle');
        $result = ['last_sync_status' => $status, 'last_sync_message' => ProviderPublicMessage::syncStatus($status),
            'active_run_id' => (int) ($connection['active_run_id'] ?? 0), 'sync_progress' => null, 'requires_sync_restart' => false, 'sync_error_code' => ''];
        if ($site <= 0 || (int) ($connection['id'] ?? 0) <= 0 || (int) ($connection['merchant_id'] ?? 0) <= 0) return $result;
        $query = static fn () => Data::query('sync_run', $site)->where('connection_id', (int) $connection['id'])->where('merchant_id', (int) $connection['merchant_id']);
        $activeId = $result['active_run_id'];
        $revision = (int) ($connection['config_version'] ?? 0);
        $run = null;
        $busy = in_array($status, ['pending', 'running'], true);
        if ($activeId > 0 && $busy) $run = $query()->where('id', $activeId)->where('config_version', $revision)->find();
        if (!$run && $busy) $run = $query()->where('config_version', $revision)->order('id desc')->find();
        if (!$run && $status === 'completed' && (int) ($connection['published_run_id'] ?? 0) > 0) {
            $run = $query()->where('id', (int) $connection['published_run_id'])->where('config_version', $revision)->where('status', 'completed')->find();
        }
        if (!$run && in_array($status, ['failed', 'cancelled'], true)) {
            // Latest CURRENT run wins, even if the connection's cached status
            // still says failed. Do not select an older failure by its status.
            $run = $query()->where('config_version', $revision)->order('id desc')->find();
        }
        return self::connectionFromRun($connection, $run ?: null);
    }

    /** Resolve only the active/published/latest fallback rows for a page, never history lists. */
    public static function connections(int $site, array $connections): array
    {
        if (!$connections) return [];
        $ids = [];
        foreach ($connections as $connection) foreach (['active_run_id', 'published_run_id'] as $key) {
            if ((int) ($connection[$key] ?? 0) > 0) $ids[] = (int) $connection[$key];
        }
        $runs = [];
        if ($ids) foreach (Data::query('sync_run', $site)->whereIn('id', array_unique($ids))->select()->toArray() as $run) $runs[(int) $run['id']] = $run;
        $chosen = [];
        $fallback = [];
        foreach ($connections as $connection) {
            $id = (int) $connection['id'];
            $active = $runs[(int) ($connection['active_run_id'] ?? 0)] ?? null;
            $status = self::status($connection['last_sync_status'] ?? 'idle');
            if (in_array($status, ['pending', 'running'], true) && self::runMatches($active, $connection, true)) {
                $chosen[$id] = $active;
                continue;
            }
            if (in_array($status, ['pending', 'running', 'failed', 'cancelled'], true)) $fallback[$id] = $connection;
            elseif ($status === 'completed') {
                $published = $runs[(int) ($connection['published_run_id'] ?? 0)] ?? null;
                if (self::runMatches($published, $connection, true) && $published['status'] === 'completed') $chosen[$id] = $published;
            }
        }
        if ($fallback) {
            $latest = Data::query('sync_run', $site)->where(function ($query) use ($fallback): void {
                foreach ($fallback as $connection) $query->whereOr(function ($query) use ($connection): void {
                    $query->where('connection_id', (int) $connection['id'])->where('merchant_id', (int) $connection['merchant_id'])
                        ->where('config_version', (int) $connection['config_version']);
                });
            })->field('MAX(id) AS id')->group('connection_id,merchant_id')->select()->toArray();
            $latest = array_column($latest, 'id');
            if ($latest) foreach (Data::query('sync_run', $site)->whereIn('id', $latest)->select()->toArray() as $run) $chosen[(int) $run['connection_id']] = $run;
        }
        $result = [];
        foreach ($connections as $connection) $result[(int) $connection['id']] = self::connectionFromRun($connection, $chosen[(int) $connection['id']] ?? null);
        return $result;
    }

    private static function runMatches(?array $run, array $connection, bool $revision): bool
    {
        return $run !== null && (int) $run['connection_id'] === (int) $connection['id']
            && (int) $run['merchant_id'] === (int) $connection['merchant_id']
            && (!$revision || (int) $run['config_version'] === (int) $connection['config_version']);
    }

    private static function connectionFromRun(array $connection, ?array $run): array
    {
        $status = self::status($connection['last_sync_status'] ?? 'idle');
        $result = ['last_sync_status' => $status, 'last_sync_message' => ProviderPublicMessage::syncStatus($status),
            'active_run_id' => (int) ($connection['active_run_id'] ?? 0), 'sync_progress' => null, 'requires_sync_restart' => false, 'sync_error_code' => ''];
        $activeId = $result['active_run_id'];
        if (!$run) {
            if ($activeId > 0 || in_array($status, ['pending', 'running'], true) || $status === 'completed') {
                $result['active_run_id'] = 0;
                $result['last_sync_status'] = 'interrupted';
                $result['requires_sync_restart'] = true;
                $result['last_sync_message'] = '未找到可继续的同步任务，请重新同步；已有完整快照仍保留';
            } elseif ($status === 'failed') $result['sync_error_code'] = 'sync_failed';
            return $result;
        }
        $progress = self::progress($run, (int) ($connection['published_run_id'] ?? 0) === (int) $run['id']);
        $result['sync_progress'] = $progress;
        $result['sync_error_code'] = $progress['sync_error_code'];
        if (in_array($run['status'], ['pending', 'running'], true)) {
            if ($activeId !== (int) $run['id']) {
                $result['active_run_id'] = 0;
                $result['last_sync_status'] = 'interrupted';
                $result['requires_sync_restart'] = true;
                $result['last_sync_message'] = '本次同步已中断，请重新同步；尚未发布部分库存';
            } else {
                $result['last_sync_status'] = $progress['status'];
                $result['last_sync_message'] = $progress['message'];
            }
        } else {
            $result['active_run_id'] = 0;
            $result['last_sync_status'] = $progress['status'];
            $result['last_sync_message'] = $progress['message'];
            if ($run['status'] === 'completed' && !$progress['published']) {
                $result['last_sync_status'] = 'interrupted';
                $result['requires_sync_restart'] = true;
                $result['last_sync_message'] = '本次读取已结束但未发布，请重新同步；已有完整快照仍保留';
            }
        }
        return $result;
    }

    private static function status(mixed $value): string
    {
        return is_string($value) && in_array($value, ['idle', 'pending', 'running', 'completed', 'failed', 'cancelled', 'interrupted', 'paused', 'unknown'], true) ? $value : 'unknown';
    }
}
