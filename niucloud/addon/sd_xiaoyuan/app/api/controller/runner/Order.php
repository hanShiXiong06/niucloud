<?php

namespace addon\sd_xiaoyuan\app\api\controller\runner;

use addon\sd_xiaoyuan\app\dict\order\OrderDict;
use addon\sd_xiaoyuan\app\service\core\OrderService;
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
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['status'] != 10) {
            return fail('订单已被接单或状态不正确');
        }
        
        // 检查学校限制 - 根据配置决定是否限制接单员只能接自己学校的订单
        $configService = new \addon\sd_xiaoyuan\app\service\admin\ConfigService();
        $config = $configService->getConfig($this->request->siteId());
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
        
        // 获取佣金比例：优先使用接单员等级的比例，否则使用系统配置
        $configService = new \addon\sd_xiaoyuan\app\service\admin\ConfigService();
        $config = $configService->getConfig($this->request->siteId());
        $commissionRate = floatval($config['commission_rate'] ?? 20); // 从后台配置读取默认平台抽成比例
        $taskType = strtolower($order['task_type'] ?? '');
        $rateKey = 'rate_' . $taskType; // 等级表中各类型字段名
        
        // 1. 先检查接单员等级的佣金比例
        if (!empty($runnerInfo['level'])) {
            $levelModel = new \addon\sd_xiaoyuan\app\model\RunnerLevel();
            $levelInfo = $levelModel->where([
                ['site_id', '=', $this->request->siteId()],
                ['level', '=', $runnerInfo['level']],
                ['status', '=', 1]
            ])->find();
            if ($levelInfo) {
                // 优先使用该类型的单独比例，否则使用默认比例
                $runnerRate = null;
                if (isset($levelInfo[$rateKey]) && $levelInfo[$rateKey] !== null && $levelInfo[$rateKey] !== '') {
                    $runnerRate = floatval($levelInfo[$rateKey]);
                } elseif ($levelInfo['commission_rate'] > 0) {
                    $runnerRate = floatval($levelInfo['commission_rate']);
                }
                if ($runnerRate !== null) {
                    // 等级的rate是接单员获得的比例，平台抽成 = 100 - 接单员比例
                    $commissionRate = 100 - $runnerRate;
                }
            }
        }
        
        // 2. 如果没有等级或等级没有设置，使用系统配置
        if ($commissionRate == floatval($config['commission_rate'] ?? 20)) {
            $commissionKey = 'commission_rate_' . $taskType;
            
            // 先检查该类型的单独配置，如果为空则使用默认配置
            if (!empty($config[$commissionKey]) && $config[$commissionKey] !== '') {
                $commissionRate = floatval($config[$commissionKey]);
            }
        }
        
        $runnerIncome = round($order['actual_fee'] * (100 - $commissionRate) / 100, 2);
        $platformFee = round($order['actual_fee'] * $commissionRate / 100, 2);
        
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
        
        return success($order);
    }
}
