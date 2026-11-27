<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\upgrade;

use RuntimeException;
use think\facade\Db;

/** Only indexes used by the public detail request; no business-row updates or deduplication. */
final class GoodsDetailIndexes
{
    public const INDEXES = [
        'phone_shop_goods_stat' => ['idx_goods_stat_product_day', ['goods_id', 'date_time']],
        'phone_shop_goods_sku'  => ['idx_goods_sku_product', ['goods_id']],
    ];

    public static function table(string $logical): string
    {
        if (!isset(self::INDEXES[$logical])) throw new RuntimeException('未知目标表');
        $name = Db::name($logical)->getTable();
        if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $name)) throw new RuntimeException('目标表前缀无效');
        return $name;
    }

    /** Return an equivalent usable BTREE, irrespective of name. All daily predicates are equalities. */
    public static function existing(string $logical, array $rows): ?string
    {
        [$target] = self::INDEXES[$logical];
        $indexes = [];
        $conflict = false;
        foreach ($rows as $row) {
            $name = $row['Key_name'];
            $conflict = $conflict || strcasecmp($name, $target) === 0;
            if (strcasecmp($row['Index_type'], 'BTREE') !== 0 || strtoupper($row['Visible'] ?? 'YES') === 'NO'
                || strtoupper($row['Ignored'] ?? 'NO') === 'YES') continue;
            $indexes[$name][(int)$row['Seq_in_index']] = $row['Sub_part'] === null ? strtolower((string)$row['Column_name']) : '';
        }
        foreach ($indexes as $name => $ordered) {
            ksort($ordered);
            $columns = array_values($ordered);
            if ($logical === 'phone_shop_goods_sku') {
                // skuList filters only by goods_id, so (goods_id, is_default, ...) already covers it.
                if ($columns[0] === 'goods_id') return $name;
            } else {
                $prefix = [];
                foreach ($columns as $column) {
                    if (!in_array($column, ['goods_id', 'date', 'date_time', 'site_id'], true)) break;
                    $prefix[$column] = true;
                    if (isset($prefix['goods_id']) && (isset($prefix['date_time']) || isset($prefix['date']))) return $name;
                }
            }
        }
        if ($conflict) throw new RuntimeException("{$logical}: 同名索引 {$target} 不能覆盖查询，不覆盖或删除，请 DBA 核对");
        return null;
    }

    public function plan(): array
    {
        $plan = [];
        foreach (self::INDEXES as $logical => [$index, $columns]) {
            $table = self::table($logical);
            $existing = self::existing($logical, Db::query("SHOW INDEX FROM `{$table}`", [], true));
            $columnSql = '`' . implode('`,`', $columns) . '`';
            $plan[$table] = [$existing, "ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnSql}), ALGORITHM=INPLACE, LOCK=NONE"];
        }
        return $plan;
    }

    public function handle(): void
    {
        $conn = Db::connect();
        $key = 'phone_detail_' . sha1($conn->getConfig('database') . ':' . self::table('phone_shop_goods_stat'));
        if ((int)($conn->query('SELECT GET_LOCK(?, 0) AS acquired', [$key], true)[0]['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('另一进程正在升级索引，请稍后重新检查');
        }
        try {
            $plan = $this->plan();
            $timeout = (int)$conn->query('SELECT @@SESSION.lock_wait_timeout AS timeout_value', [], true)[0]['timeout_value'];
            $conn->execute('SET SESSION lock_wait_timeout = 5');
            try {
                foreach ($plan as [$existing, $sql]) {
                    // Do not fall back to a blocking table copy if this server does not support online DDL.
                    if ($existing === null) $conn->execute($sql);
                }
                foreach ($this->plan() as $table => [$existing]) {
                    if ($existing === null) throw new RuntimeException("{$table}: 索引复查未通过");
                }
            } finally {
                $conn->execute('SET SESSION lock_wait_timeout = ' . $timeout);
            }
        } finally {
            $conn->query('SELECT RELEASE_LOCK(?)', [$key], true);
        }
    }
}
