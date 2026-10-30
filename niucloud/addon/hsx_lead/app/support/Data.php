<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

use core\exception\CommonException;
use think\facade\Db;

final class Data
{
    public static function query(string $table, int $site)
    {
        if (!in_array($table, ['merchant','connection','inventory','sync_run','lead','task','commission','followup','audit'], true) || $site <= 0) {
            throw new CommonException('无效的站点或数据范围');
        }
        return Db::name('hsx_lead_'.$table)->where('site_id', $site);
    }

    public static function row(string $table, int $site, int $id, bool $lock = false): array
    {
        $query = self::query($table, $site)->where('id', $id);
        if ($lock) $query->lock(true);
        $row = $query->find();
        if (!$row) throw new CommonException('记录不存在，或不属于当前站点');
        return $row;
    }

    public static function text(#[\SensitiveParameter] array $input, string $key, int $limit = 200, bool $required = false): string
    {
        $raw = $input[$key] ?? '';
        if (!is_string($raw) && !is_numeric($raw)) throw new CommonException('字段格式错误：'.$key);
        $value = trim((string)$raw);
        if (($required && $value === '') || mb_strlen($value) > $limit) throw new CommonException('请检查字段内容和长度：'.$key);
        return $value;
    }

    public static function amount($value, bool $nullable = false): ?string
    {
        if ($nullable && ($value === null || $value === '')) return null;
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', (string)$value)) {
            throw new CommonException('金额必须为非负数字，最多两位小数');
        }
        return bcadd((string)$value, '0', 2);
    }

    public static function json(#[\SensitiveParameter] array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function decode(#[\SensitiveParameter] ?string $value): array
    {
        if (!$value) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function page($query, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $size = max(1, min(100, (int)($params['limit'] ?? $params['page_size'] ?? 20)));
        return ['data' => (clone $query)->order('id desc')->page($page, $size)->select()->toArray(),
            'total' => (int)(clone $query)->count(), 'per_page' => $size, 'current_page' => $page];
    }

    public static function region(#[\SensitiveParameter] array $input): array
    {
        $result = []; $parent = 0;
        foreach (['province_id','city_id','district_id'] as $index => $field) {
            $id = (int)($input[$field] ?? 0);
            $area = Db::name('sys_area')->where('id', $id)->field('id,pid,level')->find();
            if (!$area || (int)$area['level'] !== $index + 1 || (int)$area['pid'] !== $parent) {
                throw new CommonException('请选择有效且对应的省、市、区县');
            }
            $result[$field] = $id; $parent = $id;
        }
        return $result;
    }

    public static function regionNames(array $row): array
    {
        $names = [];
        foreach (['province','city','district'] as $field) {
            $name = (string)(Db::name('sys_area')->where('id', (int)($row[$field.'_id'] ?? 0))->value('name') ?? '');
            $row[$field.'_name'] = $name;
            if ($name !== '') $names[] = $name;
        }
        $row['region_text'] = implode(' / ', $names);
        return $row;
    }

    /** Explicit province/city/district coverage; omitted trailing levels mean all descendants. */
    public static function serviceArea(#[\SensitiveParameter] array $input): array
    {
        $result=['province_id'=>0,'city_id'=>0,'district_id'=>0]; $parent=0; $ended=false;
        foreach(array_keys($result) as $index=>$field) {
            $id=(int)($input[$field]??0);
            if ($id===0 && $index>0) { $ended=true; continue; }
            if ($ended || $id<=0) throw new CommonException('服务地区需要先选择省份，再逐级选择城市和区县');
            $area=Db::name('sys_area')->where('id',$id)->field('id,pid,level')->find();
            if (!$area || (int)$area['level']!==$index+1 || (int)$area['pid']!==$parent) throw new CommonException('服务地区上下级不对应');
            $result[$field]=$id; $parent=$id;
        }
        return $result;
    }

    /** Passwords are opaque strings; trimming can make a valid remote password unusable. */
    public static function secret(#[\SensitiveParameter] array $input,string $key,int $maxBytes): string
    {
        $value=$input[$key]??'';
        if (!is_string($value) || strlen($value)>$maxBytes) throw new CommonException('请检查凭据格式及长度');
        return $value;
    }

    public static function audit(int $site, int $uid, int $memberId, string $entity, int $id, string $action, array $safe = []): void
    {
        // Callers construct this whitelist; never pass an HTTP request or provider response.
        self::query('audit', $site)->insert(['site_id'=>$site, 'entity_type'=>$entity, 'entity_id'=>$id,
            'action'=>$action, 'operator_uid'=>$uid, 'operator_member_id'=>$memberId,
            'detail_json'=>self::json($safe), 'create_time'=>time(), 'update_time'=>time()]);
    }

    public static function number(string $prefix): string
    {
        return $prefix.date('YmdHis').bin2hex(random_bytes(6));
    }
}
