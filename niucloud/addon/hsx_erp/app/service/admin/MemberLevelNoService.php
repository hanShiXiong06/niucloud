<?php
// +----------------------------------------------------------------------
// | 会员等级"站内序号" level_no 维护与解析
// | 目标:每个站点的会员等级从 1 开始独立编号,跨站统一引用口径(会员价/同行价等),
// |       不改 core 主键 level_id。core 无等级事件钩子,故采用"懒补号":
// |       任何读取前先 ensureForSite,把 level_no=0 的新等级按创建顺序补号。
// +----------------------------------------------------------------------

namespace addon\hsx_erp\app\service\admin;

use app\model\member\MemberLevel;

class MemberLevelNoService
{
    /** 请求内缓存:site_id => [level_id => level_no],避免列表逐条查库 */
    protected static array $idToNoCache = [];
    /**
     * 给指定站点尚未编号(level_no=0)的等级补号,从该站当前最大号往后续,按 level_id 升序(创建顺序)。
     * 幂等:已编号的不动;无待补号直接返回。列不存在(未执行迁移)时静默。
     */
    public static function ensureForSite(int $siteId): void
    {
        if ($siteId <= 0) {
            return;
        }
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
