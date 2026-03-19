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
        $list = (new RunnerLevel())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ])->order('level asc')->select()->toArray();

        // 如果没有配置，返回默认等级
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

        $orderCount = $runner->complete_orders ?? 0;
        $newLevel = $this->getLevelByOrders($orderCount);

        if ($newLevel && $newLevel['level'] > ($runner->level ?? 1)) {
            $runner->save([
                'level' => $newLevel['level'],
                'level_name' => $newLevel['name'],
                'commission_rate' => $newLevel['commission_rate']
            ]);

            return [
                'upgraded' => true,
                'old_level' => $runner->level ?? 1,
                'new_level' => $newLevel['level'],
                'level_name' => $newLevel['name'],
                'commission_rate' => $newLevel['commission_rate']
            ];
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

        if ($nextLevel) {
            $ordersToNext = $nextLevel['min_orders'] - $orderCount;
            $range = $nextLevel['min_orders'] - ($currentLevel['min_orders'] ?? 0);
            $progress = $range > 0 ? min(100, (($orderCount - ($currentLevel['min_orders'] ?? 0)) / $range) * 100) : 100;
        } else {
            $progress = 100;
        }

        return [
            'level' => $runner->level ?? 1,
            'level_name' => $runner->level_name ?? '新手接单员',
            'commission_rate' => $runner->commission_rate ?? 70,
            'complete_orders' => $orderCount,
            'next_level' => $nextLevel,
            'orders_to_next' => max(0, $ordersToNext),
            'progress' => round($progress, 1)
        ];
    }
}
