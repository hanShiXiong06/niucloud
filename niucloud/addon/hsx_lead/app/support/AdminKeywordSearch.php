<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\support;

use core\exception\CommonException;
use think\facade\Db;

/** Grouped, tenant-scoped keyword predicates for existing unaliased admin queries. */
final class AdminKeywordSearch
{
    public static function members($query, int $site, string $keyword): void
    {
        if ($keyword === '') return;
        $like = self::like($keyword);
        $query->where('site_id', $site)->where(function ($part) use ($keyword, $like): void {
            $part->whereLike('nickname|mobile|member_no', $like);
            if (self::memberId($keyword) !== null) $part->whereOr('member_id', self::memberId($keyword));
        });
    }

    public static function merchants($query, int $site, string $keyword, bool $subjectCode = false): void
    {
        if ($keyword === '') return;
        $merchant = self::table('hsx_lead_merchant');
        $member = self::table('member');
        $fields = 'name|contact_name|mobile'.($subjectCode ? '|subject_code' : '');
        $like = self::like($keyword);
        $sql = "EXISTS (SELECT 1 FROM $member keyword_member WHERE keyword_member.site_id = :keyword_site"
            ." AND keyword_member.member_id = $merchant.member_id AND keyword_member.status = 1"
            ." AND keyword_member.is_del = 0 AND keyword_member.delete_time = 0"
            ." AND (keyword_member.nickname LIKE :keyword_nickname OR keyword_member.mobile LIKE :keyword_mobile"
            ." OR keyword_member.member_no LIKE :keyword_number OR keyword_member.member_id = :keyword_id))";
        $bind = ['keyword_site' => $site, 'keyword_nickname' => $like, 'keyword_mobile' => $like,
            'keyword_number' => $like, 'keyword_id' => self::memberId($keyword) ?? 0];
        $query->where(function ($part) use ($fields, $like, $sql, $bind): void {
            $part->whereLike($fields, $like)->whereOrRaw($sql, $bind);
        });
    }

    public static function leads($query, int $site, string $keyword): void
    {
        if ($keyword === '') return;
        $lead = self::table('hsx_lead_collaboration');
        $merchant = self::table('hsx_lead_merchant');
        $like = self::like($keyword);
        $sql = "EXISTS (SELECT 1 FROM $merchant keyword_shop WHERE keyword_shop.site_id = :keyword_shop_site"
            ." AND keyword_shop.id IN ($lead.publisher_id, $lead.receiver_id)"
            ." AND (keyword_shop.name LIKE :keyword_shop_name OR keyword_shop.contact_name LIKE :keyword_shop_contact"
            ." OR keyword_shop.mobile LIKE :keyword_shop_mobile))";
        $bind = ['keyword_shop_site' => $site, 'keyword_shop_name' => $like,
            'keyword_shop_contact' => $like, 'keyword_shop_mobile' => $like];
        $query->where(function ($part) use ($like, $sql, $bind): void {
            $part->whereLike('customer_name|mobile|models_json|lead_no', $like)->whereOrRaw($sql, $bind);
        });
    }

    public static function orders($query, int $site, string $keyword): void
    {
        if ($keyword === '') return;
        $order = self::table('hsx_lead_coop_order');
        $merchant = self::table('hsx_lead_merchant');
        $member = self::table('member');
        $like = self::like($keyword);
        $shopSql = "EXISTS (SELECT 1 FROM $merchant payment_shop WHERE payment_shop.site_id = :payment_shop_site"
            ." AND payment_shop.id = $order.merchant_id"
            ." AND (payment_shop.name LIKE :payment_shop_name OR payment_shop.contact_name LIKE :payment_shop_contact))";
        // Financial history belongs to the original payer, not today's store
        // owner. A later account suspension does not erase its payment history.
        $payerSql = "EXISTS (SELECT 1 FROM $member payment_member WHERE payment_member.site_id = :payment_member_site"
            ." AND payment_member.member_id = $order.member_id"
            ." AND (payment_member.nickname LIKE :payment_member_name OR payment_member.mobile LIKE :payment_member_mobile"
            ." OR payment_member.member_no LIKE :payment_member_number OR payment_member.member_id = :payment_member_id))";
        $query->where(function ($part) use ($site, $keyword, $like, $shopSql, $payerSql): void {
            $part->whereLike('order_no|out_trade_no', $like)
                ->whereOrRaw($shopSql, ['payment_shop_site' => $site, 'payment_shop_name' => $like, 'payment_shop_contact' => $like])
                ->whereOrRaw($payerSql, ['payment_member_site' => $site, 'payment_member_name' => $like,
                    'payment_member_mobile' => $like, 'payment_member_number' => $like, 'payment_member_id' => self::memberId($keyword) ?? 0]);
        });
    }

    /** Exact phone lookup is separate from fuzzy keyword search and never widens its scope. */
    public static function orderMobile($query, int $site, $mobile, $scope = 'payer'): array
    {
        if (!is_string($scope) || !in_array($scope, ['payer', 'customer', 'store', 'all'], true)) {
            throw new CommonException('请选择有效的手机号查询范围', 400);
        }
        $numbers = self::phoneVariants($mobile);
        if (!$numbers) return [];
        $order = self::table('hsx_lead_coop_order');
        $sources = [
            'payer' => [self::table('member'), 'member_id', 'member_id'],
            'customer' => [self::table('hsx_lead_collaboration'), 'id', 'lead_id'],
            'store' => [self::table('hsx_lead_merchant'), 'id', 'merchant_id'],
        ];
        $conditions = [];
        $bind = [];
        foreach ($sources as $source => [$table, $primary, $foreign]) {
            if ($scope !== 'all' && $source !== $scope) continue;
            $alias = 'phone_'.$source;
            $placeholders = [];
            foreach ($numbers as $index => $number) {
                $key = $alias.'_'.$index;
                $placeholders[] = ':'.$key;
                $bind[$key] = $number;
            }
            $bind[$alias.'_site'] = $site;
            // Stored country-code and display separators are normalized as well;
            // no LIKE prefix/suffix matching or accidental cross-tenant join.
            $phone = "REPLACE(REPLACE(REPLACE($alias.mobile, ' ', ''), '-', ''), '+', '')";
            $conditions[] = "EXISTS (SELECT 1 FROM $table $alias WHERE $alias.site_id = :{$alias}_site"
                ." AND $alias.$primary = $order.$foreign AND $phone IN (".implode(',', $placeholders).'))';
        }
        $query->whereRaw('('.implode(' OR ', $conditions).')', $bind);
        return $numbers;
    }

    public static function phoneVariants($value): array
    {
        if (!is_string($value) && !is_int($value)) throw new CommonException('请输入完整手机号，不支持尾号查询', 400);
        $text = (string) $value;
        if (strlen($text) > 64 || !preg_match('/^ *\+?[0-9][0-9 -]* *$/D', $text)) {
            if ($text === '') return [];
            throw new CommonException('手机号仅支持数字、开头的 +、空格或连字符', 400);
        }
        $digits = str_replace([' ', '-', '+'], '', $text);
        if (strlen($digits) < 7 || strlen($digits) > 20) throw new CommonException('请输入7至20位的完整手机号，不支持尾号查询', 400);
        if (preg_match('/^(?:86)?(1[3-9][0-9]{9})$/D', $digits, $matches)) {
            return [$matches[1], '86'.$matches[1]];
        }
        return [$digits];
    }

    /** One batch per identity table; names and phones always belong to the order's original links. */
    public static function orderContacts(array $rows, int $site, array $numbers = [], string $scope = 'payer'): array
    {
        if (!$rows) return [];
        $read = static function (string $table, string $key, array $ids, string $fields) use ($site): array {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
            if (!$ids) return [];
            $result = [];
            foreach (Db::name($table)->where('site_id', $site)->whereIn($key, $ids)->field($fields)->select()->toArray() as $item) {
                $result[(int) $item[$key]] = $item;
            }
            return $result;
        };
        $members = $read('member', 'member_id', array_column($rows, 'member_id'), 'member_id,nickname,member_no,mobile');
        $shops = $read('hsx_lead_merchant', 'id', array_column($rows, 'merchant_id'), 'id,name,contact_name,mobile');
        $leads = $read('hsx_lead_collaboration', 'id', array_column($rows, 'lead_id'), 'id,lead_no,customer_name,mobile');
        foreach ($rows as &$row) {
            $payer = $members[(int) ($row['member_id'] ?? 0)] ?? [];
            $shop = $shops[(int) ($row['merchant_id'] ?? 0)] ?? [];
            $lead = $leads[(int) ($row['lead_id'] ?? 0)] ?? [];
            $row['payer_name'] = (string) ($payer['nickname'] ?? '');
            $row['payer_member_no'] = (string) ($payer['member_no'] ?? '');
            $row['payer_mobile'] = (string) ($payer['mobile'] ?? '');
            $row['merchant_name'] = (string) ($shop['name'] ?? '');
            $row['merchant_contact_name'] = (string) ($shop['contact_name'] ?? '');
            $row['merchant_mobile'] = (string) ($shop['mobile'] ?? '');
            $row['lead_no'] = (string) ($lead['lead_no'] ?? '');
            $row['customer_name'] = (string) ($lead['customer_name'] ?? '');
            $row['customer_mobile'] = (string) ($lead['mobile'] ?? '');
            $row['mobile_match_sources'] = [];
            foreach (['payer' => 'payer_mobile', 'customer' => 'customer_mobile', 'store' => 'merchant_mobile'] as $source => $field) {
                if ($scope !== 'all' && $scope !== $source) continue;
                $digits = str_replace([' ', '-', '+'], '', $row[$field]);
                if ($numbers && in_array($digits, $numbers, true)) $row['mobile_match_sources'][] = $source;
            }
        }
        unset($row);
        return $rows;
    }

    private static function like(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }
    public static function memberId(string $value): ?int
    {
        return preg_match('/^[1-9][0-9]{0,9}$/D', $value) && (float) $value <= 4294967295 ? (int) $value : null;
    }
    private static function table(string $name): string
    {
        return '`'.str_replace('`', '``', Db::name($name)->getTable()).'`';
    }
}
