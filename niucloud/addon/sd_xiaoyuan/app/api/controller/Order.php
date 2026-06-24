<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\dict\order\OrderDict;
use addon\sd_xiaoyuan\app\service\core\OrderService;
use addon\sd_xiaoyuan\app\service\core\CampusAuthService;
use addon\sd_xiaoyuan\app\service\core\RunnerLevelService;
use core\base\BaseApiController;
use core\exception\CommonException;

class Order extends BaseApiController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['task_type', ''],
            ['school_id', 0],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new OrderService();
        $result = $service->getMyOrderList($params);
        
        return success($result);
    }

    public function hall()
    {
        $params = $this->request->params([
            ['task_type', ''],
            ['school_id', 0],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10]
        ]);

        $cfg = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig();
        if (!empty($cfg['require_auth_publish']) && empty($cfg['unauth_show_order_hall'])) {
            if (!(new CampusAuthService())->checkAuth()) {
                $page = (int)($params['page'] ?? 1);
                $limit = (int)($params['limit'] ?? 10);
                return success([
                    'list' => [],
                    'count' => 0,
                    'page' => $page,
                    'limit' => $limit,
                ]);
            }
        }
        
        $params['status'] = 10; // 只显示待接单订单
        
        $service = new OrderService();
        $result = $service->getOrderList($params);

        // 任务大厅展示：按接单员等级配置第一档佣金预估收益
        if (!empty($result['list'])) {
            $cfg = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig($this->request->siteId());
            (new RunnerLevelService())->fillListRunnerIncomeEstimate($result['list'], $cfg, [], true);
        }
        
        return success($result);
    }

    public function detail()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $detail = $service->getOrderDetail($orderId);
        if (!empty($detail) && !$service->canViewFullOrderDetail($detail)) {
            return fail('该订单已被接单');
        }
        if (!empty($detail) && (int)($detail['pay_status'] ?? 0) === 0) {
            $ext = [];
            if (!empty($detail['ext'])) {
                $ext = json_decode((string)$detail['ext'], true);
            }
            if (is_array($ext) && !empty($ext['use_card'])) {
                if ((float)($detail['actual_fee'] ?? 0) > 0) {
                    (new \addon\sd_xiaoyuan\app\model\order\Order())->where('id', (int)$orderId)->update([
                        'actual_fee' => 0,
                        'update_time' => time(),
                    ]);
                }
                (new \addon\sd_xiaoyuan\app\service\core\XiaoyuanPayService())->confirmCardOrderPay(
                    (int)$this->request->siteId(),
                    (int)$orderId
                );
                $detail = $service->getOrderDetail($orderId);
            }
        }
        
        return success($detail);
    }

    public function create()
    {
        $data = $this->request->params([
            ['task_type', ''],
            ['school_id', 0],
            ['campus', ''],
            ['pickup_name', ''],
            ['pickup_mobile', ''],
            ['pickup_address', ''],
            ['pickup_lng', ''],
            ['pickup_lat', ''],
            ['receive_name', ''],
            ['receive_mobile', ''],
            ['receive_address', ''],
            ['receive_lng', ''],
            ['receive_lat', ''],
            ['express_company', ''],
            ['express_no', ''],
            ['pickup_code', ''],
            ['goods_name', ''],
            ['goods_image', ''],
            ['task_desc', ''],
            ['remark', ''],
            ['yinsi_text', ''],
            ['ext', ''],
            ['distance', 0],
            ['weight', 0],
            ['is_urgent', 0],
            ['is_appointment', 0],
            ['appointment_time', ''],
            ['tip_fee', 0],
            ['total_fee', 0],
            ['base_fee', 0],
            ['urgent_fee', 0],
            ['use_card', 0],
            ['images', '']
        ]);
        
        if (!empty($data['appointment_time']) && !is_numeric($data['appointment_time'])) {
            $data['appointment_time'] = strtotime($data['appointment_time']);
        }
        $data['appointment_time'] = intval($data['appointment_time']);

        $yinsi = trim((string)($data['yinsi_text'] ?? ''));
        $data['yinsi_text'] = $yinsi !== '' ? mb_substr(strip_tags($yinsi), 0, 500) : '';

        $imgRaw = $data['images'] ?? '';
        if ($imgRaw !== '' && $imgRaw !== null && trim((string)$data['goods_image']) === '') {
            $data['goods_image'] = is_array($imgRaw) ? implode(',', $imgRaw) : (string)$imgRaw;
        }
        unset($data['images']);
        
        $data['status'] = 0;

        $memberId = $this->request->memberId();
        if ($memberId) {
            $creditSvc = new \addon\sd_xiaoyuan\app\service\core\CreditService();
            if ($creditSvc->isRestricted((int)$memberId)) {
                return fail('您的信誉分低于' . \addon\sd_xiaoyuan\app\model\Credit::RESTRICT_THRESHOLD . '分，暂时无法接单或发布任务');
            }
        }

        // 开启发布认证时，未通过校园认证不可发布
        $config = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig((int)$this->request->siteId());
        if (!empty($config['require_auth_publish'])) {
            $isAuth = (new CampusAuthService())->checkAuth();
            if (!$isAuth) {
                return fail('请先完成校园认证');
            }
        }

        // 兜底：未传 school_id 时，从校园认证取当前 school_id
        $data['school_id'] = intval($data['school_id'] ?? 0);
        if ($data['school_id'] <= 0) {
            $authInfo = (new CampusAuthService())->getAuthInfo();
            $data['school_id'] = intval($authInfo['school_id'] ?? 0);
        }
        if ($data['school_id'] <= 0) {
            return fail('请选择学校');
        }
        
        $service = new OrderService();
        
        $data['total_fee'] = floatval($data['total_fee']);
        if ($data['total_fee'] <= 0) {
            return fail('请输入服务费用');
        }
        $data['base_fee'] = floatval($data['base_fee']) > 0 ? floatval($data['base_fee']) : $data['total_fee'];
        $data['urgent_fee'] = floatval($data['urgent_fee']);
        $data['distance_fee'] = 0;
        $data['weight_fee'] = 0;
        $data['actual_fee'] = $data['total_fee'];

        $useCard = intval($data['use_card'] ?? 0);
        unset($data['use_card']);
        $extData = [];
        if (!empty($data['ext'])) {
            $extData = json_decode((string)$data['ext'], true);
            if (!is_array($extData)) {
                $extData = [];
            }
        }
        if ($useCard === 1) {
            $cardApply = (new \addon\sd_xiaoyuan\app\service\core\CardService())->applyCardForOrder(
                (int)$this->request->siteId(),
                (int)$memberId,
                (string)$data['task_type'],
                (float)$data['total_fee'],
                $extData
            );
            $extData = $cardApply['ext'];
            $data['ext'] = json_encode($extData, JSON_UNESCAPED_UNICODE);
            $data['actual_fee'] = $cardApply['actual_fee'];
        }

        $result = null;
        try {
            $result = $service->create($data);
        } catch (\Throwable $e) {
            return fail('下单失败：' . $e->getMessage());
        }
        $result['need_pay'] = floatval($data['actual_fee']) > 0 ? 1 : 0;
        if ((int)$result['need_pay'] === 0) {
            (new \addon\sd_xiaoyuan\app\service\core\XiaoyuanPayService())->confirmCardOrderPay(
                (int)$this->request->siteId(),
                (int)$result['id']
            );
        }
        return success($result);
    }

    public function cancel()
    {
        $orderId = $this->request->param('id', 0);
        $reason = $this->request->param('reason', '');

        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);

        if (empty($order) || $order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }

        try {
            $service->cancel((int)$orderId, (int)$this->request->siteId(), $reason ?: '用户取消', 'USER');
        } catch (\Throwable $e) {
            return fail($e->getMessage());
        }

        return success('取消成功');
    }

    public function calculateFee()
    {
        $params = $this->request->params([
            ['distance', 0],
            ['weight', 0],
            ['is_urgent', 0]
        ]);
        
        $service = new OrderService();
        $result = $service->calculateFee($params);
        
        return success($result);
    }

    public function getRunnerLocation()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('无权查看此订单');
        }
        
        if (empty($order['runner_id'])) {
            return fail('订单暂无接单员接单');
        }
        
        $runnerModel = new \addon\sd_xiaoyuan\app\model\runner\Runner();
        $runner = $runnerModel->where('id', $order['runner_id'])->find();
        
        if (empty($runner)) {
            return fail('接单员信息不存在');
        }
        
        return success([
            'runner_id' => $runner['id'],
            'real_name' => $runner['real_name'],
            'mobile' => $runner['mobile'],
            'avatar' => $runner['avatar'],
            'score' => $runner['score'],
            'lng' => $runner['lng'],
            'lat' => $runner['lat'],
            'last_location_time' => $runner['last_location_time'],
            'order_status' => $order['status']
        ]);
    }

    public function confirm()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('无权操作此订单');
        }
        
        if ((int)$order['status'] !== OrderDict::STATUS_WAIT_USER_CONFIRM) {
            return fail('当前订单无需确认');
        }

        try {
            $service->confirmCompletedByUser((int)$orderId, (int)$this->request->memberId(), (int)$this->request->siteId());
        } catch (\Throwable $e) {
            return fail($e->getMessage());
        }

        return success('确认收货成功');
    }

    public function tip()
    {
        $orderId = $this->request->param('id', 0);
        $tipAmount = floatval($this->request->param('amount', 0));
        
        if ($tipAmount <= 0) {
            return fail('小费金额必须大于0');
        }
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }

        try {
            $service->checkOrderOwner($order, (int)$this->request->memberId(), '无权操作此订单');
        } catch (CommonException $e) {
            return fail($e->getMessage());
        }

        if ($order['status'] != 50) {
            return fail('只能对已完成订单支付小费');
        }
        
        // 创建小费订单记录
        $tipOrder = new \addon\sd_xiaoyuan\app\model\TipOrder();
        $tipOrderData = $tipOrder->create([
            'site_id' => $this->request->siteId(),
            'order_id' => $orderId,
            'member_id' => $this->request->memberId(),
            'runner_id' => $order['runner_id'] ?? 0,
            'amount' => $tipAmount,
            'pay_status' => 0,
            'create_time' => time()
        ]);
        
        return success([
            'tip_order_id' => $tipOrderData->id,
            'trade_type' => 'sd_xiaoyuan_tip',
            'trade_id' => $tipOrderData->id
        ]);
    }

    public function pay()
    {
        $orderId = $this->request->param('id', 0);
        $payType = $this->request->param('pay_type', 'wechat');
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }
        
        if ($order['status'] != 0) {
            return fail('订单状态不正确');
        }
        
        // 检查支付过期时间（如果设置了的话）
        if (!empty($order['expire_pay_time']) && $order['expire_pay_time'] > 0 && $order['expire_pay_time'] < time()) {
            return fail('订单已过期，请重新下单');
        }
        
        $service->updateStatus($orderId, 10, [
            'pay_status' => 1,
            'pay_type' => $payType,
            'pay_time' => time(),
            'expire_accept_time' => time() + 1800
        ]);
        
        return success('支付成功');
    }
}
