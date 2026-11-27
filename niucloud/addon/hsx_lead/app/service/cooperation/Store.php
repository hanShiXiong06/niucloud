<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** All cooperation queries are tenant scoped; identities never come from payloads. */
final class Store
{
    private const TABLES = ['partner', 'collaboration', 'coop_order', 'review'];
    public static function query(string $table, int $site)
    {
        if ($site <= 0 || !in_array($table, self::TABLES, true)) throw new CommonException('无效的协作数据范围');
        return Db::name('hsx_lead_'.$table)->where('site_id', $site);
    }
    public static function row(string $table, int $site, int $id, bool $lock = false): array
    {
        $q = self::query($table, $site)->where('id', $id);
        if ($lock) $q->lock(true);
        $row = $q->find();
        if (!$row) throw new CommonException('记录不存在，或不属于本站');
        return $row;
    }
    public static function partner(int $site, int $merchantId, bool $lock = false): array
    {
        if (!$lock) {
            $existing = self::query('partner', $site)->where('merchant_id', $merchantId)->find();
            if ($existing) return $existing;
        }
        return Db::transaction(function () use ($site, $merchantId, $lock): array {
            Data::row('merchant', $site, $merchantId, true);
            $q = self::query('partner', $site)->where('merchant_id', $merchantId);
            if ($lock) $q->lock(true);
            $row = $q->find();
            if (!$row) {
                $id = (int) self::query('partner', $site)->insertGetId([
                        'site_id' => $site,
                        'merchant_id' => $merchantId,
                        'create_time' => time(),
                        'update_time' => time()
                    ]);
                $row = self::row('partner', $site, $id, $lock);
            }
            return $row;
        });
    }
    public static function permission(Context $ctx, string $name): void
    {
        $ctx->permission('hsx_lead_cooperation_'.$name);
    }
    /** Shared employee data boundary for current merchant collaborations. */
    public static function scope(Context $ctx): array
    {
        return $ctx->ownership()->scope();
    }
    public static function requireTransfer(Context $ctx): void
    {
        if (!self::scope($ctx)['can_transfer']) throw new CommonException('转交协作归属需要全站业务查看与协作转交权限，请联系管理员');
    }
    public static function adminQuery(Context $ctx, array $params = [])
    {
        $ctx->manager();
        return $ctx->ownership()->leads(self::query('collaboration', $ctx->site), $params);
    }
    public static function adminLead(Context $ctx, int $id, bool $lock = false): array
    {
        $q = self::adminQuery($ctx)->where('id', $id);
        if ($lock) $q->lock(true);
        $row = $q->find();
        if (!$row) throw new CommonException('线索不存在，或不在当前可见范围');
        return $row;
    }
    public static function requestId(array $input): string
    {
        $id = Data::text($input, 'request_id', 64, true);
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/D', $id)) throw new CommonException('请求标识无效，请保留当前内容重新提交');
        return $id;
    }
    public static function attachments($items, Context $ctx): array
    {
        if (!is_array($items) || count($items) > 6) throw new CommonException('最多上传6张图片');
        $out = [];
        foreach ($items as $item) {
            if (!is_string($item) || strlen($item) > 1000 || str_contains($item, '..')) throw new CommonException('请使用本人在本站上传的图片');
            $attachment = Db::name('sys_attachment')->where('site_id', $ctx->site)->where('att_type', 'image')->where(function ($q) use ($item): void {$q->where('path', $item)->whereOr('url', $item);})->find();
            if (!$attachment) throw new CommonException('图片不存在或不属于本站，请重新上传');
            $audit = Data::query('audit', $ctx->site)->where('entity_type', 'cooperation_attachment')->where('entity_id', $attachment['att_id'])->where('action', 'uploaded');
            if ($ctx->uid > 0) $audit->where('operator_uid', $ctx->uid);
            else $audit->where('operator_member_id', $ctx->memberId);
            if (!$audit->count()) throw new CommonException('此图片不是当前账号上传，请重新选择本人图片');
            $out[] = $attachment['path'];
        }
        return array_values(array_unique($out));
    }
    public static function pageArray(array $rows, array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $size = max(1, min(100, (int) ($params['limit'] ?? 20)));
        return ['data' => array_slice($rows, ($page - 1) * $size, $size), 'total' => count($rows), 'per_page' => $size, 'current_page' => $page];
    }
    public static function audit(Context $ctx, int $id, string $action, array $safe = []): void
    {
        $ctx->audit('collaboration', $id, $action, $safe);
    }
}
