<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\RunnerLevel;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 接单员等级服务
 */
class RunnerLevelService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 获取等级配置列表
     */
    public function getLevelList()
    {
        $all = (new RunnerLevel())->where('site_id', $this->site_id)->order('level asc')->select()->toArray();

        // 优先启用中的等级；若后台全部禁用仍保留数据库配置，避免误用内置默认（默认 L2 需 50 单会导致无法升级）
        $active = array_values(array_filter($all, function ($row) {
            return (int)($row['status'] ?? 1) === 1;
        }));
        $list = !empty($active) ? $active : $all;

        if (empty($list)) {
            $list = $this->getDefaultLevels();
        }

        return $list;
    }

    /**
     * 获取默认等级配置
     */
    protected function getDefaultLevels()
    {
        return [
            ['level' => 1, 'name' => '新手接单员', 'min_orders' => 0, 'commission_rate' => 70, 'icon' => ''],
            ['level' => 2, 'name' => '初级接单员', 'min_orders' => 50, 'commission_rate' => 75, 'icon' => ''],
            ['level' => 3, 'name' => '中级接单员', 'min_orders' => 200, 'commission_rate' => 80, 'icon' => ''],
            ['level' => 4, 'name' => '高级接单员', 'min_orders' => 500, 'commission_rate' => 85, 'icon' => ''],
            ['level' => 5, 'name' => '金牌接单员', 'min_orders' => 1000, 'commission_rate' => 90, 'icon' => '']
        ];
    }

    /**
     * 根据订单数获取等级
     */
    public function getLevelByOrders(int $orderCount)
    {
        $levels = $this->getLevelList();
        $currentLevel = $levels[0] ?? null;

        foreach ($levels as $level) {
            if ($orderCount >= $level['min_orders']) {
                $currentLevel = $level;
            } else {
                break;
            }
        }

        return $currentLevel;
    }

    /**
     * 检查并升级接单员等级
     */
    public function checkAndUpgrade(int $runnerId)
    {
        $runner = (new Runner())->where('id', $runnerId)->find();
        if (empty($runner)) {
            throw new CommonException('接单员不存在');
        }

        $orderCount = (int)($runner->complete_orders ?? 0);
        $newLevel = $this->getLevelByOrders($orderCount);
        if (empty($newLevel)) {
            return ['upgraded' => false];
        }

        $oldLevelNum = (int)($runner->level ?? 1);
        $newLevelNum = (int)($newLevel['level'] ?? 1);
        $newName = (string)($newLevel['name'] ?? '');
        $newRate = (int)($newLevel['commission_rate'] ?? 70);

        // 升级：按完成单量应达到的等级高于当前记录
        if ($newLevelNum > $oldLevelNum) {
            (new Runner())->where('id', $runnerId)->update([
                'level' => $newLevelNum,
                'level_name' => $newName,
                'commission_rate' => $newRate,
                'update_time' => time(),
            ]);

            return [
                'upgraded' => true,
                'old_level' => $oldLevelNum,
                'new_level' => $newLevelNum,
                'level_name' => $newName,
                'commission_rate' => $newRate,
            ];
        }

        // 等级未变但后台改了等级名称或佣金比例时，同步展示与抽成
        if ($newLevelNum === $oldLevelNum && $newName !== '' && ($newName !== (string)($runner->level_name ?? '') || (int)($runner->commission_rate ?? 0) !== $newRate)) {
            (new Runner())->where('id', $runnerId)->update([
                'level_name' => $newName,
                'commission_rate' => $newRate,
                'update_time' => time(),
            ]);
            return ['upgraded' => false, 'synced' => true];
        }

        return ['upgraded' => false];
    }

    /**
     * 订单完成后更新接单员数据
     */
    public function onOrderCompleted(int $runnerId)
    {
        $runner = (new Runner())->where('id', $runnerId)->find();
        if (empty($runner)) {
            throw new CommonException('接单员不存在');
        }

        // 增加完成订单数
        $runner->inc('complete_orders', 1)->update();

        // 检查是否升级
        return $this->checkAndUpgrade($runnerId);
    }

    /**
     * 计算接单员收益
     */
    public function calculateCommission(int $runnerId, float $orderAmount)
    {
        $runner = (new Runner())->where('id', $runnerId)->find();
        if (empty($runner)) {
            return 0;
        }

        $commissionRate = $runner->commission_rate ?? 70;
        return round($orderAmount * $commissionRate / 100, 2);
    }

    /**
     * 获取接单员等级信息
     */
    public function getRunnerLevelInfo(int $runnerId)
    {
        $runner = (new Runner())->where('id', $runnerId)->find();
        if (empty($runner)) {
            throw new CommonException('接单员不存在');
        }

        $levels = $this->getLevelList();
        $currentLevel = null;
        $nextLevel = null;

        foreach ($levels as $index => $level) {
            if ($level['level'] == ($runner->level ?? 1)) {
                $currentLevel = $level;
                $nextLevel = $levels[$index + 1] ?? null;
                break;
            }
        }

        $orderCount = $runner->complete_orders ?? 0;
        $progress = 0;
        $ordersToNext = 0;

        if ($nextLevel && $currentLevel) {
            $ordersToNext = $nextLevel['min_orders'] - $orderCount;
            $range = $nextLevel['min_orders'] - ($currentLevel['min_orders'] ?? 0);
            $progress = $range > 0 ? min(100, (($orderCount - ($currentLevel['min_orders'] ?? 0)) / $range) * 100) : 100;
        } else {
            $progress = 100;
        }

        $cfgName = $currentLevel ? (string)($currentLevel['name'] ?? '') : '';
        $cfgRate = $currentLevel
            ? (int)($currentLevel['commission_rate'] ?? ($runner->commission_rate ?? 70))
            : (int)($runner->commission_rate ?? 70);

        return [
            'level' => $runner->level ?? 1,
            'level_name' => $cfgName !== '' ? $cfgName : ($runner->level_name ?? '新手接单员'),
            'commission_rate' => $cfgRate,
            'complete_orders' => $orderCount,
            'next_level' => $nextLevel,
            'orders_to_next' => max(0, $ordersToNext),
            'progress' => round($progress, 1)
        ];
    }
}
