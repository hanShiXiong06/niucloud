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
     * @deprecated 与订单结算语义不一致（runner 表 commission_rate 为接单员占比，订单 commission_rate 为平台占比），请用 estimateRunnerIncomeFromOrder
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
     * 平台抽成比例(%)：与 runner/Order::complete 一致（默认 commission_rate 为平台抽成；等级表 commission_rate 为接单员所得占比）
     */
    public function resolvePlatformCommissionPercent(string $taskType, array $runnerInfo, array $config): float
    {
        $taskType = strtolower($taskType ?? '');
        $rateKey = 'rate_' . $taskType;
        $runnerRate = $this->pickRunnerCommissionPercent($runnerInfo, $rateKey);
        if ($runnerRate <= 0) {
            $first = $this->getFirstLevelRow();
            $runnerRate = $this->pickRunnerCommissionPercent($first, $rateKey);
        }
        if ($runnerRate <= 0) {
            $runnerRate = 70;
        }
        return min(100, max(0, 100 - $runnerRate));
    }

    private function pickRunnerCommissionPercent(array $row, string $rateKey): float
    {
        if (array_key_exists($rateKey, $row) && $row[$rateKey] !== null && $row[$rateKey] !== '') {
            return floatval($row[$rateKey]);
        }
        if (!empty($row['commission_rate']) && (float)$row['commission_rate'] > 0) {
            return floatval($row['commission_rate']);
        }
        return 0;
    }

    /**
     * 等级配置第一个等级（后台 runner/level 列表按 level 升序的第一条）
     */
    public function getFirstLevelRow(): array
    {
        $levels = $this->getLevelList();
        if (!empty($levels[0])) {
            return $levels[0];
        }
        $defaults = $this->getDefaultLevels();
        return $defaults[0] ?? ['level' => 1, 'commission_rate' => 70];
    }

    /**
     * 按第一等级佣金预估接单员实得（任务大厅待接单展示用）
     */
    public function estimateRunnerIncomeByFirstLevel(float $actualFee, string $taskType, array $config): float
    {
        $first = $this->getFirstLevelRow();
        $runnerInfo = ['level' => (int)($first['level'] ?? 1)];
        return $this->estimateRunnerIncomeFromOrder($actualFee, $taskType, $runnerInfo, $config);
    }

    /**
     * 列表补全 runner_income 预估（未结算订单）
     * @param bool $useFirstLevel true=用第一等级佣金，false=用当前接单员等级
     */
    public function fillListRunnerIncomeEstimate(array &$list, array $config, array $runnerInfo = [], bool $useFirstLevel = true): void
    {
        foreach ($list as &$row) {
            if ((float)($row['runner_income'] ?? 0) > 0) {
                continue;
            }
            $fee = (float)($row['actual_fee'] ?? $row['total_fee'] ?? 0);
            if ($fee <= 0) {
                continue;
            }
            $st = (int)($row['status'] ?? 0);
            if (!in_array($st, [10, 20, 30, 40, 45], true)) {
                continue;
            }
            $taskType = (string)($row['task_type'] ?? '');
            if ($useFirstLevel) {
                $row['runner_income'] = $this->estimateRunnerIncomeByFirstLevel($fee, $taskType, $config);
            } else {
                $row['runner_income'] = $this->estimateRunnerIncomeFromOrder($fee, $taskType, $runnerInfo, $config);
            }
        }
        unset($row);
    }

    /**
     * 接单员预估实得（与提交完成时写入 runner_income 算法一致）
     */
    public function estimateRunnerIncomeFromOrder(float $actualFee, string $taskType, array $runnerInfo, array $config): float
    {
        if ($actualFee <= 0) {
            return 0.0;
        }
        $pct = $this->resolvePlatformCommissionPercent($taskType, $runnerInfo, $config);
        return round($actualFee * (100 - $pct) / 100, 2);
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
