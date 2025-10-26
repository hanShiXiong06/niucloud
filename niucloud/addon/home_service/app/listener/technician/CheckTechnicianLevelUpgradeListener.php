<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------


namespace addon\home_service\app\listener\technician;

use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\technician\TechnicianLevel;

/**
 * 师傅升级检测
 * Class MemberAccount
 * @package app\listener\member
 */
class CheckTechnicianLevelUpgradeListener
{
    /**
     * 师傅升级检测
     * @param array $data
     */
    public function handle(array $data)
    {
        $technician = (new Technician())->where([ ['id', '=', $data['technician_id'] ] ])->findOrEmpty();
        if ($technician->isEmpty()) return true;

        // 查出所有等级
        $levels = (new TechnicianLevel())->where([['site_id', '=', $data['site_id']]])->select();
        $available = [];
        foreach ($levels as $level) {
            $orderOk = ($level->order_num == 0 || $technician->order_num >= $level->order_num);
            $achievementOk = ($level->achievement == 0 || $technician->achievement >= $level->achievement);

            if ($orderOk && $achievementOk) {
                $available[] = $level;
            }
        }

        if (empty($available)) {
            return true;
        }

        // 取条件最高的等级
        // 这里以 order_num、achievement 都大的优先
        usort($available, function ($a, $b) {
            if ($a->order_num == $b->order_num) {
                return $b->achievement <=> $a->achievement;
            }
            return $b->order_num <=> $a->order_num;
        });

        $newLevel = $available[0];

        // 等级发生变化才更新
        if ($newLevel->level_id != $technician->level_id) {
            $technician->level_id   = $newLevel->level_id;
            $technician->save();
        }

        return true;

    }
}
