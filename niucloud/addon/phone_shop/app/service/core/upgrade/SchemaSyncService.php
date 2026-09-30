<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 幂等表结构同步/迁移
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\upgrade;

use addon\phone_shop\app\service\core\goods\CoreGoodsDescriptionService;
use think\facade\Db;
use think\facade\Log;

/**
 * 把已有(老 phone_shop 或先前版本)的库表结构补齐到当前目标结构。
 *
 * 关键：目标列定义【直接从 install.sql 解析】，不再手工维护第二份，永不与 install.sql 漂移。
 * 行为：对已存在的表，只 ADD install.sql 里有、但库里缺的列；不删不改不重建，可重复执行（幂等）。
 * 表的创建由 install.sql 的 CREATE TABLE IF NOT EXISTS 负责（安装时框架先跑 install.sql 再调本类）。
 */
class SchemaSyncService
{
    protected string $prefix = '';

    public function __construct()
    {
        // 必须跟随当前实际数据库连接。部分部署会在运行期切换/重写默认连接，
        // 此时直接读取 connections.mysql 可能得到空前缀，而 ORM 实际仍在使用 phone_ 等前缀。
        // 优先从已解析完成的连接实例取配置，静态配置仅作为兼容兜底。
        try {
            $connectionPrefix = Db::connect()->getConfig('prefix');
            if ($connectionPrefix !== null) {
                $this->prefix = (string) $connectionPrefix;
                return;
            }
        } catch (\Throwable $e) {
            Log::write('[phone_shop SchemaSync] 读取当前数据库连接前缀失败: ' . $e->getMessage());
        }

        $defaultConnection = (string) config('database.default', 'mysql');
        $this->prefix = (string) config(
            'database.connections.' . $defaultConnection . '.prefix',
            config('database.connections.mysql.prefix', '')
        );
    }

    /**
     * 执行同步。返回每张表的处理结果，便于排查。失败只记日志、不打断主流程。
     */
    public function run(): array
    {
        $report = [];
        $tables = $this->parseInstallSql();
        $raws = null; // 懒加载：install.sql 里每张表的原始 CREATE 语句(用于自动建新表)
        foreach ($tables as $table => $schema) {
            try {
                if (!$this->tableExists($table)) {
                    // 新表(如 phone_shop_agent)：直接用 install.sql 的原始 DDL 创建，
                    // CREATE TABLE IF NOT EXISTS 幂等，且前缀由框架配置统一替换，避免硬编码。
                    if ($raws === null) $raws = $this->rawCreateStatements();
                    if (!empty($raws[$table])) {
                        Db::execute($raws[$table]);
                        $report[$table] = 'created(新表)';
                    } else {
                        $report[$table] = 'skip(表不存在且无DDL)';
                    }
                    continue;
                }
                // 补缺列
                $existingCols = $this->tableColumns($table);
                $addedCols = [];
                foreach ($schema['cols'] as $col => $def) {
                    if (in_array($col, $existingCols, true)) continue;
                    if (stripos($def, 'AUTO_INCREMENT') !== false) continue; // 自增列必为主键,不补
                    Db::execute("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                    $addedCols[] = $col;
                }
                // 补缺索引(快速检索)
                $existingIdx = $this->tableIndexes($table);
                $addedIdx = [];
                foreach ($schema['idx'] as $name => $idx) {
                    if (in_array($name, $existingIdx, true)) continue;
                    $unique = $idx['unique'] ? 'UNIQUE ' : '';
                    Db::execute("ALTER TABLE `{$table}` ADD {$unique}INDEX `{$name}` ({$idx['cols']})");
                    $addedIdx[] = $name;
                }
                $parts = [];
                if ($addedCols) $parts[] = 'cols: ' . implode(',', $addedCols);
                if ($addedIdx) $parts[] = 'idx: ' . implode(',', $addedIdx);
                $report[$table] = $parts ? implode('; ', $parts) : 'ok';
            } catch (\Throwable $e) {
                $report[$table] = 'error: ' . $e->getMessage();
                Log::write("[phone_shop SchemaSync] {$table} 失败: " . $e->getMessage());
            }
        }
        try {
            $report['device_intake_index_migrate'] = $this->migrateDeviceIntakeIndexes();
        } catch (\Throwable $e) {
            $report['device_intake_index_migrate'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] 商城货源站点索引迁移失败: ' . $e->getMessage());
        }
        try {
            // 老版本 attr_ids 是 INT，当前模型把它作为 JSON 数组读取。必须先迁移字段类型，
            // 否则 ORM 会把数据库返回的整数直接交给 json_decode，PHP 8 将抛出 TypeError。
            $report['attr_ids_column_migrate'] = $this->migrateAttrIdsColumn();
        } catch (\Throwable $e) {
            $report['attr_ids_column_migrate'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] attr_ids 字段迁移失败: ' . $e->getMessage());
        }
        try {
            // goods_no 是跨站公共键而非数值。历史数据已有长编号，INT 会溢出且会丢失前导零。
            $report['goods_no_column_migrate'] = $this->migrateGoodsNoColumn();
        } catch (\Throwable $e) {
            $report['goods_no_column_migrate'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] goods_no 字段迁移失败: ' . $e->getMessage());
        }
        try {
            $report['attr_migrate'] = $this->migrateAttr();
        } catch (\Throwable $e) {
            $report['attr_migrate'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] attr 迁移失败: ' . $e->getMessage());
        }
        try {
            $report['evaluate_subject_migrate'] = $this->migrateEvaluateSubject();
        } catch (\Throwable $e) {
            $report['evaluate_subject_migrate'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] 评价归属迁移失败: ' . $e->getMessage());
        }
        try {
            $report['goods_desc_qc_cleanup'] = $this->migrateGoodsDescription();
        } catch (\Throwable $e) {
            $report['goods_desc_qc_cleanup'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] 商品详情质检脏数据清理失败: ' . $e->getMessage());
        }
        try {
            $report['source_goods_id_backfill'] = $this->migrateSourceGoodsId();
        } catch (\Throwable $e) {
            $report['source_goods_id_backfill'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] 代理商品来源ID回填失败: ' . $e->getMessage());
        }
        try {
            $report['source_goods_index_migrate'] = $this->migrateSourceGoodsIndex();
        } catch (\Throwable $e) {
            $report['source_goods_index_migrate'] = 'error: ' . $e->getMessage();
            Log::write('[phone_shop SchemaSync] 代理商品精确索引迁移失败: ' . $e->getMessage());
        }
        return $report;
    }

    /**
     * 为历史代理副本补齐来源商品ID。
     * 只有同一来源站、goods_no、名称唯一匹配时才迁移；歧义数据留给全量同步重新建副本。
     */
    protected function migrateSourceGoodsId(): string
    {
        $goods = $this->prefix . 'phone_shop_goods';
        if (!$this->tableExists($goods)) {
            return 'skip(表不存在)';
        }

        $sql = "UPDATE `{$goods}` AS child_goods
                INNER JOIN (
                    SELECT `site_id`,`goods_no`,`goods_name`,MIN(`goods_id`) AS source_goods_id
                    FROM `{$goods}`
                    WHERE `goods_no` <> ''
                      AND `delete_time` = 0
                      AND (`source` = '' OR CAST(`source` AS UNSIGNED) = `site_id`)
                    GROUP BY `site_id`,`goods_no`,`goods_name`
                    HAVING COUNT(*) = 1
                ) AS master_goods
                    ON master_goods.`site_id` = CAST(child_goods.`source` AS UNSIGNED)
                   AND master_goods.`goods_no` = child_goods.`goods_no`
                   AND master_goods.`goods_name` = child_goods.`goods_name`
                SET child_goods.`source_goods_id` = master_goods.`source_goods_id`,
                    child_goods.`update_time` = UNIX_TIMESTAMP()
                WHERE child_goods.`source` <> ''
                  AND CAST(child_goods.`source` AS UNSIGNED) <> child_goods.`site_id`
                  AND (child_goods.`source_goods_id` IS NULL OR child_goods.`source_goods_id` = 0)
                  AND child_goods.`delete_time` = 0";
        $count = Db::execute($sql);
        return 'mapped:' . (int)$count;
    }

    /**
     * 把过渡版本的 0 默认值迁移为 NULL，并建立“站点 + 来源站 + 来源商品”唯一约束。
     * NULL 允许本站自营商品重复；代理副本的 source_goods_id 非空，因此数据库可阻止
     * 并发任务为同一站点关系重复创建商品副本。
     */
    protected function migrateSourceGoodsIndex(): string
    {
        $table = $this->prefix . 'phone_shop_goods';
        if (!$this->tableExists($table)) return 'skip(表不存在)';
        $columns = $this->tableColumns($table);
        if (!in_array('source_goods_id', $columns, true)) return 'skip(字段不存在)';

        Db::execute("ALTER TABLE `{$table}` MODIFY COLUMN `source_goods_id` int(11) NULL DEFAULT NULL COMMENT '来源站商品ID(仅代理副本使用,系统内部精确联动)'");
        $zeroToNull = Db::execute("UPDATE `{$table}` SET `source_goods_id` = NULL WHERE `source_goods_id` = 0");

        // 若过渡代码已经产生重复映射，保留最早一条，其余降为不可展示的待人工历史数据。
        $deduped = Db::execute("UPDATE `{$table}` AS duplicate_goods
            INNER JOIN (
                SELECT `site_id`,`source`,`source_goods_id`,`delete_time`,MIN(`goods_id`) AS keep_goods_id
                FROM `{$table}`
                WHERE `source_goods_id` IS NOT NULL
                GROUP BY `site_id`,`source`,`source_goods_id`,`delete_time`
                HAVING COUNT(*) > 1
            ) AS duplicated
                ON duplicated.`site_id` = duplicate_goods.`site_id`
               AND duplicated.`source` = duplicate_goods.`source`
               AND duplicated.`source_goods_id` = duplicate_goods.`source_goods_id`
               AND duplicated.`delete_time` = duplicate_goods.`delete_time`
               AND duplicate_goods.`goods_id` <> duplicated.`keep_goods_id`
            SET duplicate_goods.`source_goods_id` = NULL,
                duplicate_goods.`status` = 0,
                duplicate_goods.`update_time` = UNIX_TIMESTAMP()");

        $indexes = $this->tableIndexes($table);
        if (!in_array('uk_goods_source_goods', $indexes, true)) {
            Db::execute("ALTER TABLE `{$table}` ADD UNIQUE INDEX `uk_goods_source_goods` (`site_id`,`source`,`source_goods_id`,`delete_time`)");
        }
        return 'zero_to_null:' . (int)$zeroToNull . ',deduped:' . (int)$deduped;
    }

    /**
     * 只按 install.sql 补建一张指定的插件表。
     *
     * 用于新业务在老站点首次访问时做轻量兼容，避免为了补一张表而执行整套历史迁移。
     * 表结构仍以 install.sql 为唯一来源，不在业务服务里复制第二份 DDL。
     */
    public function ensureTable(string $baseTable): bool
    {
        $baseTable = trim($baseTable);
        if ($baseTable === '') return false;
        $table = str_starts_with($baseTable, $this->prefix) ? $baseTable : $this->prefix . $baseTable;
        if (!$this->tableExists($table)) {
            $statements = $this->rawCreateStatements();
            if (empty($statements[$table])) return false;
            Db::execute($statements[$table]);
        }
        if (!$this->tableExists($table)) return false;

        // 老版本可能已有同名表，但缺少新版映射字段/索引；首次使用时只补这一张表。
        $schemas = $this->parseInstallSql();
        if (empty($schemas[$table])) return true;
        $existingCols = $this->tableColumns($table);
        foreach ($schemas[$table]['cols'] as $col => $def) {
            if (in_array($col, $existingCols, true) || stripos($def, 'AUTO_INCREMENT') !== false) continue;
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
        }
        $existingIdx = $this->tableIndexes($table);
        foreach ($schemas[$table]['idx'] as $name => $idx) {
            if (in_array($name, $existingIdx, true)) continue;
            $unique = $idx['unique'] ? 'UNIQUE ' : '';
            Db::execute("ALTER TABLE `{$table}` ADD {$unique}INDEX `{$name}` ({$idx['cols']})");
        }
        return true;
    }

    /**
     * 老版本把 erp_asset_id 当成全平台唯一键。ERP 重装会复用自增 ID，
     * 不同站点本来也允许拥有相同的资产 ID，因此升级时迁移为站点级唯一键。
     */
    protected function migrateDeviceIntakeIndexes(): string
    {
        $table = $this->prefix . 'phone_shop_device_intake';
        if (!$this->tableExists($table)) return 'skip(表不存在)';

        $indexes = $this->tableIndexes($table);
        $changed = [];
        if (in_array('uk_erp_asset_id', $indexes, true)) {
            Db::execute("ALTER TABLE `{$table}` DROP INDEX `uk_erp_asset_id`");
            $changed[] = 'drop:uk_erp_asset_id';
            $indexes = $this->tableIndexes($table);
        }
        if (!in_array('uk_site_erp_asset', $indexes, true)) {
            Db::execute("ALTER TABLE `{$table}` ADD UNIQUE INDEX `uk_site_erp_asset` (`site_id`,`erp_asset_id`)");
            $changed[] = 'add:uk_site_erp_asset';
        }
        if (in_array('idx_status', $indexes, true)) {
            Db::execute("ALTER TABLE `{$table}` DROP INDEX `idx_status`");
            $changed[] = 'drop:idx_status';
            $indexes = $this->tableIndexes($table);
        }
        if (!in_array('idx_site_status', $indexes, true)) {
            Db::execute("ALTER TABLE `{$table}` ADD INDEX `idx_site_status` (`site_id`,`status`)");
            $changed[] = 'add:idx_site_status';
        }
        return $changed ? implode(',', $changed) : 'ok';
    }

    /**
     * 兼容早期单参数模板字段：attr_ids INT -> TEXT(JSON数组)。
     *
     * 此迁移只处理已确认的历史结构差异，不对其他字段做在线类型对齐；可重复执行。
     */
    protected function migrateAttrIdsColumn(): string
    {
        $table = $this->prefix . 'phone_shop_goods';
        if (!$this->tableExists($table)) return 'skip(表不存在)';

        $column = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'attr_ids'");
        if (empty($column)) return 'skip(字段不存在)';

        $type = strtolower((string)($column[0]['Type'] ?? ''));
        $changed = false;
        if (!str_contains($type, 'text') && !str_contains($type, 'json') && !str_contains($type, 'char')) {
            Db::execute("ALTER TABLE `{$table}` MODIFY COLUMN `attr_ids` TEXT NULL COMMENT '商品参数id，支持多个'");
            $changed = true;
        }

        $rows = Db::table($table)->field('goods_id,attr_ids')->select()->toArray();
        $normalized = 0;
        foreach ($rows as $row) {
            $raw = $row['attr_ids'] ?? null;
            $target = $this->normalizeAttrIds($raw);
            if ((string)$raw === $target) continue;

            Db::table($table)->where('goods_id', '=', (int)$row['goods_id'])
                ->update(['attr_ids' => $target]);
            $normalized++;
        }

        $parts = [];
        if ($changed) $parts[] = 'INT->TEXT';
        if ($normalized > 0) $parts[] = "normalize:{$normalized}";
        return $parts ? implode(',', $parts) : 'ok';
    }

    /**
     * 跨站商品编号必须按字符串保存，兼容长编号与前导零。
     */
    protected function migrateGoodsNoColumn(): string
    {
        $table = $this->prefix . 'phone_shop_goods';
        if (!$this->tableExists($table)) return 'skip(表不存在)';

        $column = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'goods_no'");
        if (empty($column)) return 'skip(字段不存在)';
        $type = strtolower((string)($column[0]['Type'] ?? ''));
        if (str_contains($type, 'char') || str_contains($type, 'text')) return 'ok';

        Db::execute("ALTER TABLE `{$table}` MODIFY COLUMN `goods_no` varchar(64) NOT NULL DEFAULT '' COMMENT '商品跨站公共键(同一台真机跨站共享,铺货/联动按此关联)'");
        return $type . '->varchar(64)';
    }

    /** @param mixed $value */
    protected function normalizeAttrIds($value): string
    {
        if (is_array($value)) {
            $ids = $value;
        } else {
            $text = trim((string)$value);
            if ($text === '' || $text === '0') return '[]';

            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                $ids = preg_split('/[,，\s]+/', $text) ?: [];
            }
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn($id) => (string)(int)$id,
            $ids
        ), static fn($id) => $id !== '0')));
        return json_encode($ids, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /**
     * 从 install.sql 解析每张表的列定义与索引。
     * @return array<string, array{cols: array<string,string>, idx: array<string,array>}> 表名(含前缀) => [cols=>[列=>DDL], idx=>[名=>[unique,cols]]]
     */
    protected function parseInstallSql(): array
    {
        $file = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
        if (!is_file($file)) {
            Log::write('[phone_shop SchemaSync] 找不到 install.sql: ' . $file);
            return [];
        }
        $sql = (string) file_get_contents($file);
        $sql = str_replace('{{prefix}}', $this->prefix, $sql);
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];

        $tables = [];
        $current = null;   // 当前表名
        $inBody = false;   // 是否进入 ( ... ) 列定义区
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') continue;

            if (preg_match('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`/i', $trim, $m)) {
                $current = $m[1];
                if (!isset($tables[$current])) $tables[$current] = ['cols' => [], 'idx' => []];
                $inBody = (strpos($trim, '(') !== false && !str_ends_with($trim, '`'));
                continue;
            }
            if ($current === null) continue;

            if (!$inBody) {
                if (strpos($trim, '(') === 0) $inBody = true;
                continue;
            }

            // 列定义区结束
            if (strpos($trim, ')') === 0) {
                $current = null;
                $inBody = false;
                continue;
            }
            // 索引行：KEY / UNIQUE KEY `name` (cols)   —— PRIMARY KEY 无反引号名,自动跳过
            if (preg_match('/^(UNIQUE\s+KEY|KEY)\s+`([^`]+)`\s*\((.+)\)\s*,?$/i', $trim, $im)) {
                $tables[$current]['idx'][$im[2]] = [
                    'unique' => stripos($im[1], 'UNIQUE') !== false,
                    'cols'   => trim($im[3]),
                ];
                continue;
            }
            // 列行：以反引号开头
            if ($trim[0] === '`' && preg_match('/^`([^`]+)`\s+(.+?),?$/', $trim, $cm)) {
                $tables[$current]['cols'][$cm[1]] = rtrim(rtrim($cm[2]), ',');
            }
        }
        return $tables;
    }

    /**
     * 解析 install.sql 里每张表的原始 CREATE 语句（{{prefix}} 已替换为真实前缀）。
     * 用于升级时自动创建库里还不存在的新表。
     * @return array<string,string> 表名(含前缀) => 原始 CREATE TABLE 语句
     */
    protected function rawCreateStatements(): array
    {
        $file = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
        if (!is_file($file)) return [];
        $sql = str_replace('{{prefix}}', $this->prefix, (string) file_get_contents($file));
        $out = [];
        // 非贪婪匹配 CREATE TABLE ... 到第一个分号（建表语句内不含分号）。
        if (preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`.*?;/is', $sql, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $out[$m[1]] = $m[0];
            }
        }
        return $out;
    }

    /**
     * attr_id(老单值) -> attr_ids(多值JSON) 回填：仅当老 attr_id 列存在、且该行 attr_ids 为空。
     */
    protected function migrateAttr(): string
    {
        $full = $this->prefix . 'phone_shop_goods';
        if (!$this->tableExists($full)) return 'skip';
        $cols = $this->tableColumns($full);
        if (!in_array('attr_id', $cols, true) || !in_array('attr_ids', $cols, true)) {
            return 'skip(无需迁移:列不全)';
        }
        $rows = Db::table($full)->where('attr_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('attr_ids')->whereOr('attr_ids', '');
            })
            ->field('goods_id, attr_id')->select()->toArray();
        $n = 0;
        foreach ($rows as $r) {
            Db::table($full)->where('goods_id', $r['goods_id'])
                ->update([ 'attr_ids' => json_encode([ (string) $r['attr_id'] ]) ]);
            $n++;
        }
        return "attr_id->attr_ids 回填 {$n} 行";
    }

    /**
     * 老评价补齐末级分类及成交商品快照。
     * 只更新尚未归类的记录，可重复执行，不修改原评价事实。
     */
    protected function migrateEvaluateSubject(): string
    {
        $evaluateTable = $this->prefix . 'phone_shop_goods_evaluate';
        $goodsTable = $this->prefix . 'phone_shop_goods';
        $orderGoodsTable = $this->prefix . 'phone_shop_order_goods';
        $categoryTable = $this->prefix . 'phone_shop_goods_category';
        foreach ([$evaluateTable, $goodsTable, $orderGoodsTable, $categoryTable] as $table) {
            if (!$this->tableExists($table)) return 'skip(依赖表不存在)';
        }
        $columns = $this->tableColumns($evaluateTable);
        if (!in_array('category_id', $columns, true)) return 'skip(目标列不存在)';

        $rows = Db::table($evaluateTable)->alias('e')
            ->leftJoin($goodsTable . ' g', 'g.goods_id = e.goods_id AND g.site_id = e.site_id')
            ->leftJoin($orderGoodsTable . ' og', 'og.order_goods_id = e.order_goods_id AND og.site_id = e.site_id')
            ->where('e.category_id', '=', 0)
            ->field('e.evaluate_id,e.site_id,e.order_goods_id,g.goods_category,g.goods_name AS current_goods_name,g.goods_cover AS current_goods_image,og.goods_name AS order_goods_name,og.sku_name AS order_sku_name,og.goods_image AS order_goods_image')
            ->select()->toArray();

        $categoryCache = [];
        $updated = 0;
        foreach ($rows as $row) {
            $categoryIds = json_decode((string)($row['goods_category'] ?? ''), true);
            $categoryIds = is_array($categoryIds)
                ? array_values(array_filter(array_map('intval', $categoryIds)))
                : [];
            $categoryId = (int)(end($categoryIds) ?: 0);
            $category = [];
            if ($categoryId > 0) {
                $cacheKey = (int)$row['site_id'] . ':' . $categoryId;
                if (!array_key_exists($cacheKey, $categoryCache)) {
                    $categoryCache[$cacheKey] = Db::table($categoryTable)
                        ->where([['site_id', '=', (int)$row['site_id']], ['category_id', '=', $categoryId]])
                        ->field('category_name,category_full_name')->find() ?: [];
                }
                $category = $categoryCache[$cacheKey];
            }
            Db::table($evaluateTable)->where('evaluate_id', '=', (int)$row['evaluate_id'])->update([
                'category_id' => $categoryId,
                'category_name' => trim((string)($category['category_name'] ?? '')),
                'category_path' => trim((string)($category['category_full_name'] ?? ($category['category_name'] ?? ''))),
                'goods_name' => trim((string)($row['order_goods_name'] ?? '')) ?: trim((string)($row['current_goods_name'] ?? '')),
                'sku_name' => trim((string)($row['order_sku_name'] ?? '')),
                'goods_image' => trim((string)($row['order_goods_image'] ?? '')) ?: trim((string)($row['current_goods_image'] ?? '')),
                'is_verified_purchase' => (int)($row['order_goods_id'] ?? 0) > 0 ? 1 : 0,
            ]);
            $updated++;
        }
        return "评价归属回填 {$updated} 行";
    }

    /**
     * 清理历史版本误写进商品详情的结构化质检数据。
     * 只处理本身已存在 qc_report 的商品，正常运营文案不会被修改。
     */
    protected function migrateGoodsDescription(): string
    {
        $goodsTable = $this->prefix . 'phone_shop_goods';
        if (!$this->tableExists($goodsTable)) return 'skip(商品表不存在)';
        $columns = $this->tableColumns($goodsTable);
        if (!in_array('goods_desc', $columns, true) || !in_array('qc_report', $columns, true)) {
            return 'skip(目标列不存在)';
        }

        $rows = Db::table($goodsTable)
            ->where('qc_report', '<>', '')
            ->whereNotNull('qc_report')
            ->where('goods_desc', '<>', '')
            ->field('goods_id,goods_desc')
            ->select()->toArray();
        $service = new CoreGoodsDescriptionService();
        $updated = 0;
        foreach ($rows as $row) {
            $description = (string)($row['goods_desc'] ?? '');
            $sanitized = $service->sanitize($description);
            if ($sanitized === $description) continue;
            Db::table($goodsTable)->where('goods_id', '=', (int)$row['goods_id'])->update([
                'goods_desc' => $service->defaultDescription(),
                'update_time' => time(),
            ]);
            $updated++;
        }

        return "质检脏详情清理 {$updated} 行";
    }

    protected function tableExists(string $full): bool
    {
        return !empty(Db::query("SHOW TABLES LIKE '{$full}'"));
    }

    protected function tableColumns(string $full): array
    {
        $rows = Db::query("SHOW COLUMNS FROM `{$full}`");
        return array_column($rows, 'Field');
    }

    protected function tableIndexes(string $full): array
    {
        $rows = Db::query("SHOW INDEX FROM `{$full}`");
        return array_values(array_unique(array_column($rows, 'Key_name')));
    }
}
