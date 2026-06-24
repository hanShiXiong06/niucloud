<?php

namespace addon\sd_xiaoyuan\app\api\controller\runner;

use addon\sd_xiaoyuan\app\dict\order\OrderDict;
use addon\sd_xiaoyuan\app\service\core\OrderService;
use addon\sd_xiaoyuan\app\service\core\RunnerLevelService;
use addon\sd_xiaoyuan\app\service\core\RunnerService;
use core\base\BaseApiController;

class Order extends BaseApiController
{
    public function hall()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);
        
        $params['status'] = 10;
        
        // 获取接单员信息，过滤学校
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (!empty($runnerInfo) && !empty($runnerInfo['school_id'])) {
            $params['school_id'] = $runnerInfo['school_id'];
        }
        
        $service = new OrderService();
        $result = $service->getOrderList($params);
        // 待接单列表：按等级配置第一档佣金预估收益（与后台 runner/level 首行一致）
        if (!empty($result['list'])) {
            $cfg = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig((int)$this->request->siteId());
            (new RunnerLevelService())->fillListRunnerIncomeEstimate($result['list'], $cfg, $runnerInfo ?: [], true);
        }

        return success($result);
    }

    public function myOrders()
    {
        $params = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $params['runner_id'] = $runnerInfo['id'];
        
        $service = new OrderService();
        $result = $service->getOrderList($params);
        if (!empty($result['list'])) {
            $cfg = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig((int)$this->request->siteId());
            // 待接单用第一等级；已接单未结算用当前接单员等级
            $lv = new RunnerLevelService();
            foreach ($result['list'] as &$row) {
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
                if ($st === 10) {
                    $row['runner_income'] = $lv->estimateRunnerIncomeByFirstLevel($fee, $taskType, $cfg);
                } elseif (!empty($runnerInfo)) {
                    $row['runner_income'] = $lv->estimateRunnerIncomeFromOrder($fee, $taskType, $runnerInfo, $cfg);
                }
            }
            unset($row);
        }

        return success($result);
    }

    public function accept()
    {
        $orderId = $this->request->param('id', 0);
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        if ($runnerInfo['status'] != 1) {
            return fail('您的账号未通过审核');
        }
        
        if ($runnerInfo['is_online'] != 1) {
            return fail('请先上线接单');
        }

        if ((int)($runnerInfo['can_jiedan'] ?? 1) !== 1) {
            return fail('您已被禁止接单，如有疑问请联系平台');
        }
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['status'] != 10) {
            return fail('订单已被接单或状态不正确');
        }
        
        // 检查学校限制 - 根据配置决定是否限制接单员只能接自己学校的订单
        $config = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig((int)$this->request->siteId());
        $runnerSchoolLimit = intval($config['runner_school_limit'] ?? 0);
        if ($runnerSchoolLimit && !empty($runnerInfo['school_id']) && !empty($order['school_id']) && $runnerInfo['school_id'] != $order['school_id']) {
            return fail('您只能接自己学校的订单');
        }
        
        $orderSex = intval($order['sex'] ?? 0);
        $runnerSex = intval($runnerInfo['sex'] ?? 0);
        if ($orderSex > 0 && $runnerSex > 0 && $orderSex != $runnerSex) {
            $sexText = $orderSex == 1 ? '男性' : '女性';
            return fail("该订单要求{$sexText}接单员");
        }
        
        $service->updateStatus($orderId, 20, [
            'runner_id' => $runnerInfo['id'],
            'accept_time' => time()
        ]);
        
        return success('接单成功');
    }

    public function pickup()
    {
        $orderId = $this->request->param('id', 0);
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        if (empty($runnerInfo)) return fail('跑腿员信息不存在');

        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        if (empty($order)) return fail('订单不存在');
        if ((int)$order['runner_id'] !== (int)$runnerInfo['id']) return fail('无权操作此订单');
        if ((int)$order['status'] !== OrderDict::STATUS_ACCEPTED) return fail('订单状态不正确');

        $service->updateStatus($orderId, 30, [
            'pickup_time' => time()
        ]);
        
        return success('已确认取货');
    }

    public function delivery()
    {
        $orderId = $this->request->param('id', 0);
        $proofImages = $this->request->param('proof_images', '');
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        if (empty($runnerInfo)) return fail('跑腿员信息不存在');

        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        if ((int)$order['runner_id'] !== (int)$runnerInfo['id']) {
            return fail('无权操作此订单');
        }
        if (!in_array((int)$order['status'], [OrderDict::STATUS_ACCEPTED, OrderDict::STATUS_PICKING], true)) {
            return fail('订单状态不正确');
        }
        
        $updateData = ['delivery_time' => time()];
        
        // 如果有任务凭证图片，存储到ext字段中
        if (!empty($proofImages)) {
            $extData = [];
            if (!empty($order['ext'])) {
                $extData = is_string($order['ext']) ? json_decode($order['ext'], true) : $order['ext'];
                if (!is_array($extData)) $extData = [];
            }
            $extData['delivery_images'] = is_array($proofImages) ? $proofImages : explode(',', $proofImages);
            $updateData['ext'] = json_encode($extData, JSON_UNESCAPED_UNICODE);
        }
        
        $service->updateStatus($orderId, 40, $updateData);
        
        return success('已开始配送');
    }

    public function complete()
    {
        $orderId = $this->request->param('id', 0);
        $proofImages = $this->request->param('proof_images', '');
        
        if (empty($proofImages)) {
            return fail('请上传完成凭证图片');
        }
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['runner_id'] != $runnerInfo['id']) {
            return fail('无权操作此订单');
        }
        
        if ((int)$order['status'] !== OrderDict::STATUS_DELIVERING) {
            return fail('订单状态不正确');
        }
        
        $config = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig((int)$this->request->siteId());
        $commissionRate = (new RunnerLevelService())->resolvePlatformCommissionPercent((string)($order['task_type'] ?? ''), $runnerInfo, $config);
        $actualFee = (float)($order['actual_fee'] ?? 0);
        $runnerIncome = round($actualFee * (100 - $commissionRate) / 100, 2);
        $platformFee = round($actualFee * $commissionRate / 100, 2);
        
        // 把完成凭证存到ext字段中
        $extData = [];
        if (!empty($order['ext'])) {
            $extData = is_string($order['ext']) ? json_decode($order['ext'], true) : $order['ext'];
            if (!is_array($extData)) $extData = [];
        }
        $extData['proof_images'] = is_array($proofImages) ? $proofImages : explode(',', $proofImages);
        
        $service->updateStatus($orderId, OrderDict::STATUS_DELIVERING, [
            'complete_time' => time(),
            'runner_income' => $runnerIncome,
            'platform_fee' => $platformFee,
            'commission_rate' => $commissionRate,
            'ext' => json_encode($extData, JSON_UNESCAPED_UNICODE)
        ]);

        $service->completeToUserConfirm((int)$orderId, is_array($proofImages) ? $proofImages : explode(',', $proofImages), (int)$this->request->siteId());

        return success('已提交，等待用户确认');
    }

    public function reject()
    {
        $orderId = $this->request->param('id', 0);
        $reason = $this->request->param('reason', '');
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['runner_id'] != $runnerInfo['id']) {
            return fail('无权操作此订单');
        }
        
        if (!in_array($order['status'], [20, 30])) {
            return fail('订单状态不正确，无法拒绝');
        }
        
        $result = $service->updateStatus($orderId, 10, [
            'runner_id' => 0,
            'accept_time' => null,
            'cancel_reason' => $reason,
            'cancel_role' => 'RUNNER'
        ]);
        
        if (!$result) {
            return fail('更新订单状态失败');
        }
        
        return success('已拒绝订单');
    }

    public function detail()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        if (!empty($order) && !$service->canViewFullOrderDetail($order)) {
            return fail('该订单已被接单');
        }
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        if (!empty($order) && (float)($order['runner_income'] ?? 0) <= 0) {
            $st = (int)($order['status'] ?? 0);
            if (in_array($st, [10, 20, 30, 40, 45], true)) {
                $fee = (float)($order['actual_fee'] ?? $order['total_fee'] ?? 0);
                if ($fee > 0) {
                    $cfg = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig((int)$this->request->siteId());
                    $lv = new RunnerLevelService();
                    $taskType = (string)($order['task_type'] ?? '');
                    if ($st === 10) {
                        $order['runner_income'] = $lv->estimateRunnerIncomeByFirstLevel($fee, $taskType, $cfg);
                    } elseif (!empty($runnerInfo)) {
                        $order['runner_income'] = $lv->estimateRunnerIncomeFromOrder($fee, $taskType, $runnerInfo, $cfg);
                    }
                }
            }
        }

        return success($order);
    }
}
