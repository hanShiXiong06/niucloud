<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_device;

use core\base\BaseAdminService;
use think\facade\Db;

/**
 * 选择频次统计服务（通用）
 *
 * 按"场景 + 用户"记录被选中的次数，用于下拉框"常用优先"排序。
 * 例如整备负责人(scene=refurbishment_assignee)：每次定价指派后 +1，下拉按次数倒序。
 * 全程故障隔离：统计失败绝不影响主业务。
 *
 * Class CoreRecyclePickStatService
 * @package addon\hsx_recycle\app\service\core\recycle_device
 */
class CoreRecyclePickStatService extends BaseAdminService
{
    /** 整备负责人场景 */
    public const SCENE_REFURB_ASSIGNEE = 'refurbishment_assignee';

    private string $table = 'recycle_user_pick_stat';

    /**
     * 记录一次选择（次数 +1）
     * @param string $scene
     * @param int $userId
     * @return void
     */
    public function record(string $scene, int $userId): void
    {
        try {
            if ($scene === '' || $userId <= 0) {
                return;
            }
            $now = time();
            $row = Db::name($this->table)->where([
                ['site_id', '=', $this->site_id],
                ['scene', '=', $scene],
                ['user_id', '=', $userId],
            ])->find();
            if ($row) {
                Db::name($this->table)->where('id', (int)$row['id'])
                    ->inc('pick_count')
                    ->update(['last_pick_at' => $now, 'update_at' => $now]);
            } else {
                Db::name($this->table)->insert([
                    'site_id' => $this->site_id,
                    'scene' => $scene,
                    'user_id' => $userId,
                    'pick_count' => 1,
                    'last_pick_at' => $now,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            // 统计失败不影响主流程
        }
    }

    /**
     * 取某场景下的次数映射 [user_id => pick_count]
     * @param string $scene
     * @return array<int,int>
     */
    public function getCounts(string $scene): array
    {
        try {
            $map = Db::name($this->table)->where([
                ['site_id', '=', $this->site_id],
                ['scene', '=', $scene],
            ])->column('pick_count', 'user_id');
            return is_array($map) ? $map : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
