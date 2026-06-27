<?php
// +----------------------------------------------------------------------
// | 会员等级"站内序号" level_no 维护与解析
// | 目标:每个站点的会员等级从 1 开始独立编号,跨站统一引用口径(会员价/同行价等),
// |       不改 core 主键 level_id。core 无等级事件钩子,故采用"懒补号":
// |       任何读取前先 ensureForSite,把 level_no=0 的新等级按创建顺序补号。
// +----------------------------------------------------------------------

namespace addon\hsx_erp\app\service\admin;

use app\model\member\MemberLevel;
use think\facade\Db;

class MemberLevelNoService
{
    /** 请求内缓存:site_id => [level_id => level_no],避免列表逐条查库 */
    protected static array $idToNoCache = [];
    /** 本次请求是否已确认过 level_no 列存在(避免每次都查 information_schema) */
    protected static bool $columnEnsured = false;

    /**
     * 按需建列:判断共享表 member_level 是否已有 level_no 列,没有才加(有则跳过,绝不碰已有数据)。
     * 取代 install.sql 里的裸 ALTER —— 后者在重装时会撞 "Duplicate column" 把整个安装回滚。
     * 这里用 PHP 判断,幂等、安全,不删任何字段。
     */
    protected static function ensureColumn(): void
    {
        if (self::$columnEnsured) {
            return;
        }
        self::$columnEnsured = true;
        try {
            $table = (new MemberLevel())->getTable(); // 带前缀真实表名,如 saas_member_level
            $has = Db::query(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'level_no'",
                [$table]
            );
            if ((int) ($has[0]['c'] ?? 0) === 0) {
                Db::execute("ALTER TABLE `{$table}` ADD COLUMN `level_no` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '站内序号(每站从1开始)' AFTER `site_id`");
                try {
                    Db::execute("ALTER TABLE `{$table}` ADD INDEX `idx_site_level_no` (`site_id`, `level_no`)");
                } catch (\Throwable $e) {
                    // 索引已存在等情况静默
                }
            }
        } catch (\Throwable $e) {
            // 权限不足/库不支持 information_schema 等情况静默,不打断业务
        }
    }

    /**
     * 给指定站点尚未编号(level_no=0)的等级补号,从该站当前最大号往后续,按 level_id 升序(创建顺序)。
     * 幂等:已编号的不动;无待补号直接返回。先确保 level_no 列存在(按需建列),不存在则静默。
     */
    public static function ensureForSite(int $siteId): void
    {
        if ($siteId <= 0) {
            return;
        }
        self::ensureColumn();
        try {
            $pending = MemberLevel::where([['site_id', '=', $siteId], ['level_no', '=', 0]])
                ->order('level_id asc')->column('level_id');
            if (empty($pending)) {
                return;
            }
            $no = (int) MemberLevel::where([['site_id', '=', $siteId]])->max('level_no');
            foreach ($pending as $levelId) {
                $no++;
                MemberLevel::where([['level_id', '=', (int) $levelId]])->update(['level_no' => $no]);
            }
        } catch (\Throwable $e) {
            // 迁移未执行(无 level_no 列)等情况静默,调用方不应因此失败
        }
    }

    /** [level_id => level_no](请求内缓存) */
    public static function idToNoMap(int $siteId): array
    {
        if (isset(self::$idToNoCache[$siteId])) {
            return self::$idToNoCache[$siteId];
        }
        self::ensureForSite($siteId);
        try {
            $map = array_map('intval', MemberLevel::where([['site_id', '=', $siteId]])->column('level_no', 'level_id'));
        } catch (\Throwable $e) {
            $map = [];
        }
        return self::$idToNoCache[$siteId] = $map;
    }

    /** [level_no => level_id] */
    public static function noToIdMap(int $siteId): array
    {
        self::ensureForSite($siteId);
        try {
            return array_map('intval', MemberLevel::where([['site_id', '=', $siteId]])->column('level_id', 'level_no'));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 该站等级列表(含 level_no),按 level_no 升序 */
    public static function levelsWithNo(int $siteId): array
    {
        self::ensureForSite($siteId);
        try {
            return MemberLevel::where([['site_id', '=', $siteId]])
                ->field('level_id,site_id,level_no,level_name,growth,status')
                ->order('level_no asc')->select()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 单个解析:站内序号 → level_id(找不到返回0) */
    public static function noToId(int $siteId, int $levelNo): int
    {
        return (int) (self::noToIdMap($siteId)[$levelNo] ?? 0);
    }

    /** 单个解析:level_id → 站内序号(找不到返回0) */
    public static function idToNo(int $siteId, int $levelId): int
    {
        return (int) (self::idToNoMap($siteId)[$levelId] ?? 0);
    }
}
