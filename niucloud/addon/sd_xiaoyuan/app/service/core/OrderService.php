<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Credit;
use addon\sd_xiaoyuan\app\dict\order\OrderDict;
use app\model\member\Member;
use app\dict\member\MemberAccountTypeDict;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\pay\CorePayService;
use app\service\core\pay\CoreRefundService;
use core\base\BaseApiService;
use think\facade\Db;

class OrderService extends BaseApiService
{
    /** @var int|null 当前实例内缓存的接单员表主键 */
    private $memoViewerRunnerRecordId = null;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 发布人始终可见；已接单且当前用户为接单员时可见；其余情况不暴露（列表/详情均走此规则，后台传 mask_yinsi=false）
     */
    protected function getViewerRunnerRecordId(): int
    {
        if ($this->memoViewerRunnerRecordId !== null) {
            return $this->memoViewerRunnerRecordId;
        }
        $mid = intval($this->member_id ?? 0);
        if ($mid <= 0) {
            $this->memoViewerRunnerRecordId = 0;
            return 0;
        }
        $id = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where([
            ['member_id', '=', $mid],
            ['site_id', '=', $this->site_id],
        ])->value('id');
        $this->memoViewerRunnerRecordId = $id ? (int) $id : 0;
        return $this->memoViewerRunnerRecordId;
    }

    protected function canViewOrderYinsi(array $order, int $viewerMemberId, int $viewerRunnerId): bool
    {
        if (!empty($order['member_id']) && $viewerMemberId > 0 && (int) $order['member_id'] === $viewerMemberId) {
            return true;
        }
        $status = (int) ($order['status'] ?? 0);
        $runnerOid = (int) ($order['runner_id'] ?? 0);
        if ($status >= 20 && $runnerOid > 0 && $viewerRunnerId > 0 && $runnerOid === $viewerRunnerId) {
            return true;
        }
        return false;
    }

    /**
     * @param array $row 订单单行（引用）
     */
    public function maskOrderYinsiForViewer(array &$row, int $viewerMemberId, int $viewerRunnerId): void
    {
        if (empty($row)) {
            return;
        }
        if (!$this->canViewOrderYinsi($row, $viewerMemberId, $viewerRunnerId)) {
            $row['yinsi_text'] = '';
            $row['pickup_code'] = '';
            if (!empty($row['ext_data']) && is_array($row['ext_data']) && isset($row['ext_data']['pickup_code'])) {
                $row['ext_data']['pickup_code'] = '';
            }
        }
    }

    /**
     * 兜底补全订单经纬度（历史订单可能只保存了地址文本）
     */
    protected function hydrateOrderAddressCoord(array &$item, int $sid): void
    {
        $memberId = intval($item['member_id'] ?? 0);
        if ($memberId <= 0) return;

        $addressModel = new \addon\sd_xiaoyuan\app\model\Address();

        if ((empty($item['pickup_lng']) || empty($item['pickup_lat'])) && !empty($item['pickup_address'])) {
            $pickup = $addressModel->where([
                ['site_id', '=', $sid],
                ['member_id', '=', $memberId],
                ['address', '=', $item['pickup_address']]
            ])->field('lng,lat')->findOrEmpty()->toArray();
            if (!empty($pickup['lng']) && !empty($pickup['lat'])) {
                $item['pickup_lng'] = $pickup['lng'];
                $item['pickup_lat'] = $pickup['lat'];
            }
        }

        if ((empty($item['receive_lng']) || empty($item['receive_lat'])) && !empty($item['receive_address'])) {
            $receive = $addressModel->where([
                ['site_id', '=', $sid],
                ['member_id', '=', $memberId],
                ['address', '=', $item['receive_address']]
            ])->field('lng,lat')->findOrEmpty()->toArray();
            if (!empty($receive['lng']) && !empty($receive['lat'])) {
                $item['receive_lng'] = $receive['lng'];
                $item['receive_lat'] = $receive['lat'];
            }
        }
    }

    public function create($data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['order_no'] = $this->generateOrderNo();
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['expire_pay_time'] = time() + 1800;
        
                
        // 确保actual_fee有值，如果没有则使用total_fee
        if (!isset($data['actual_fee']) || $data['actual_fee'] <= 0) {
            $data['actual_fee'] = $data['total_fee'] ?? 0;
        }
        
        $result = $this->model->create($data);
        return ['id' => $result->id, 'order_no' => $data['order_no']];
    }

    public function calculateFee($params)
    {
        $distance = $params['distance'] ?? 0;
        $weight = $params['weight'] ?? 0;
        $isUrgent = $params['is_urgent'] ?? 0;
        
        $baseFee = 3.00;
        $distanceFee = $distance > 2 ? ($distance - 2) * 1.5 : 0;
        $weightFee = $weight > 5 ? ($weight - 5) * 0.5 : 0;
        $urgentFee = $isUrgent ? 5.00 : 0;
        
        $totalFee = $baseFee + $distanceFee + $weightFee + $urgentFee;
        
        return [
            'base_fee' => $baseFee,
            'distance_fee' => $distanceFee,
            'weight_fee' => $weightFee,
            'urgent_fee' => $urgentFee,
            'total_fee' => $totalFee
        ];
    }

    private function generateOrderNo()
    {
        return 'XY' . date('YmdHis') . rand(1000, 9999);
    }

    public function getOrderList($params)
    {
        $memberId = $params['member_id'] ?? 0;
        $runnerId = $params['runner_id'] ?? 0;
        $status = $params['status'] ?? '';
        $taskType = $params['task_type'] ?? '';
        $schoolId = $params['school_id'] ?? 0;
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 10);

        $where = [['site_id', '=', $this->site_id]];
        
        if ($memberId) {
            $where[] = ['member_id', '=', $memberId];
        }

        if ($runnerId) {
            $where[] = ['runner_id', '=', $runnerId];
        }
        
        if ($status !== '') {
            if (is_string($status) && strpos($status, ',') !== false) {
                $where[] = ['status', 'in', array_map('intval', explode(',', $status))];
            } else {
                $where[] = ['status', '=', $status];
            }
        }

        if ($taskType !== '') {
            $where[] = ['task_type', '=', $taskType];
        }

        if ($schoolId) {
            $where[] = ['school_id', '=', $schoolId];
        }

        $orderNo = $params['order_no'] ?? '';
        if ($orderNo !== '') {
            $where[] = ['order_no', 'like', '%' . $orderNo . '%'];
        }

        $keyword = $params['keyword'] ?? '';
        if ($keyword !== '') {
            $where[] = ['order_no|pickup_name|pickup_mobile|receive_name|receive_mobile|task_desc', 'like', '%' . $keyword . '%'];
        }

        // 日期范围筛选
        $startDate = $params['start_date'] ?? '';
        $endDate = $params['end_date'] ?? '';
        if ($startDate !== '') {
            $where[] = ['create_time', '>=', strtotime($startDate)];
        }
        if ($endDate !== '') {
            $where[] = ['create_time', '<=', strtotime($endDate . ' 23:59:59')];
        }

        $maskYinsi = array_key_exists('mask_yinsi', $params) ? (bool) $params['mask_yinsi'] : true;

        // 接单员订单按接单时间倒序，其他按创建时间倒序
        $orderBy = $runnerId ? 'accept_time desc, create_time desc' : 'create_time desc';

        $list = $this->model->where($where)
            ->order($orderBy)
            ->page($page, $limit)
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();

        // 附加会员头像、昵称和信誉分
        $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
        $members = [];
        $credits = [];
        if (!empty($member_ids)) {
            $member_list = (new Member())->where([['member_id', 'in', $member_ids]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($member_list as $m) {
                $members[$m['member_id']] = $m;
            }
            $credit_list = (new Credit())->where([['member_id', 'in', $member_ids], ['site_id', '=', $this->site_id]])->field('member_id,credit_score')->select()->toArray();
            foreach ($credit_list as $c) {
                $credits[$c['member_id']] = $c['credit_score'];
            }
        }
        // 附加接单员信息
        $runner_ids = array_unique(array_filter(array_column($list, 'runner_id')));
        $runners = [];
        if (!empty($runner_ids)) {
            $runner_list = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where([['id', 'in', $runner_ids]])->field('id,real_name,mobile,avatar,score')->select()->toArray();
            foreach ($runner_list as $r) {
                $runners[$r['id']] = $r;
            }
        }

        // 附加学校信息
        $school_ids = array_unique(array_filter(array_column($list, 'school_id')));
        $schools = [];
        if (!empty($school_ids)) {
            $school_list = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', 'in', $school_ids]])->field('id,name')->select()->toArray();
            foreach ($school_list as $s) {
                $schools[$s['id']] = $s['name'];
            }
        }

        // 批量查询已评价的订单ID
        $order_ids = array_column($list, 'id');
        $evaluated_ids = [];
        if (!empty($order_ids)) {
            $evaluateModel = new \addon\sd_xiaoyuan\app\model\Evaluate();
            $evaluated_list = $evaluateModel->where([['order_id', 'in', $order_ids]])->column('order_id');
            $evaluated_ids = array_flip($evaluated_list);
        }

        $viewerMemberId = $maskYinsi ? intval($this->member_id ?? 0) : 0;
        $viewerRunnerId = $maskYinsi ? $this->getViewerRunnerRecordId() : 0;

        foreach ($list as &$item) {
            $mid = $item['member_id'] ?? 0;
            $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
            $item['member_headimg'] = $members[$mid]['headimg'] ?? '';
            $item['credit_score'] = $credits[$mid] ?? Credit::INIT_SCORE;

            // 附加接单员信息（列表与 sd-order-item 展示一致）
            $rid = $item['runner_id'] ?? 0;
            $item['runner_name'] = $runners[$rid]['real_name'] ?? '';
            $item['runner_mobile'] = $runners[$rid]['mobile'] ?? '';
            $item['runner_avatar'] = $runners[$rid]['avatar'] ?? '';
            $item['runner_score'] = $runners[$rid]['score'] ?? 0;

            // 附加学校名称
            $sid = $item['school_id'] ?? 0;
            $item['school_name'] = $schools[$sid] ?? '';

            // 是否已评价
            $item['is_evaluated'] = isset($evaluated_ids[$item['id']]) ? 1 : 0;

            // 解析ext JSON，展开到item中方便前端使用
            if (!empty($item['ext'])) {
                $extData = json_decode($item['ext'], true);
                if (is_array($extData)) {
                    $item['ext_data'] = $extData;
                } else {
                    $item['ext_data'] = [];
                }
            } else {
                $item['ext_data'] = [];
            }

            if ($maskYinsi) {
                $this->maskOrderYinsiForViewer($item, $viewerMemberId, $viewerRunnerId);
            }
        }
        unset($item);

        return [
            'list' => $list,
            'count' => $count,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public function getMyOrderList($params)
    {
        $params['member_id'] = $this->member_id;
        return $this->getOrderList($params);
    }

    public function getOrderDetail($orderId, $siteId = 0, $maskYinsi = true)
    {
        $sid = $siteId ?: $this->site_id;
        $item = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid]
        ])->findOrEmpty()->toArray();

        if (!empty($item)) {
            $this->hydrateOrderAddressCoord($item, $sid);

            if (!empty($item['ext'])) {
                $extData = json_decode($item['ext'], true);
                $item['ext_data'] = is_array($extData) ? $extData : [];
            } else {
                $item['ext_data'] = [];
            }

            // 附加会员信息
            if (!empty($item['member_id'])) {
                $member = (new Member())->where('member_id', $item['member_id'])->field('member_id,nickname,headimg')->findOrEmpty()->toArray();
                $item['member_nickname'] = $member['nickname'] ?? '';
                $item['member_headimg'] = $member['headimg'] ?? '';
                $credit = (new Credit())->where([['member_id', '=', $item['member_id']], ['site_id', '=', $sid]])->value('credit_score');
                $item['credit_score'] = $credit ?: Credit::INIT_SCORE;
            }

            // 是否已评价
            $evaluateModel = new \addon\sd_xiaoyuan\app\model\Evaluate();
            $item['is_evaluated'] = $evaluateModel->where('order_id', $orderId)->count() > 0 ? 1 : 0;

            // 附加接单员信息
            if (!empty($item['runner_id'])) {
                $runner = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where('id', $item['runner_id'])->field('id,real_name,mobile,avatar,score')->findOrEmpty()->toArray();
                $item['runner_name'] = $runner['real_name'] ?? '';
                $item['runner_mobile'] = $runner['mobile'] ?? '';
                $item['runner_avatar'] = $runner['avatar'] ?? '';
                $item['runner_score'] = $runner['score'] ?? 0;
            } else {
                $item['runner_name'] = '';
                $item['runner_mobile'] = '';
                $item['runner_avatar'] = '';
                $item['runner_score'] = 0;
            }

            if ($maskYinsi) {
                $vm = intval($this->member_id ?? 0);
                $vr = $this->getViewerRunnerRecordId();
                $this->maskOrderYinsiForViewer($item, $vm, $vr);
            }
        }

        return $item;
    }

    public function updateStatus($orderId, $status, $data = [])
    {
        $updateData = array_merge($data, [
            'status' => $status,
            'update_time' => time()
        ]);
        
        $result = $this->model->where('id', $orderId)->update($updateData);
        
        // 如果是接单状态，发送接单消息
        if ($status == 20 && $result) {
            $order = $this->model->where('id', $orderId)->find();
            if ($order) {
                (new \addon\sd_xiaoyuan\app\service\core\MessageService())->send(
                    $order['member_id'], 
                    'ORDER', 
                    '订单已被接单', 
                    '您的订单已被接单员接单，接单员正在处理中', 
                    [
                        'link_type' => 'order_detail',
                        'link_id' => $orderId,
                        'order_id' => $orderId
                    ]
                );
            }
        }
        
        return $result;
    }

    /**
     * 接单员提交完成，进入用户确认状态。
     */
    public function completeToUserConfirm(int $orderId, array $proofImages = [], int $siteId = 0): bool
    {
        $sid = $siteId ?: $this->site_id;
        $order = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid]
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new \Exception('订单不存在');
        }
        if ((int)$order['status'] !== OrderDict::STATUS_DELIVERING) {
            throw new \Exception('订单状态不正确');
        }

        $config = (new \addon\sd_xiaoyuan\app\service\admin\ConfigService())->getConfig();
        $autoConfirmDays = max(0, intval($config['auto_confirm_days'] ?? 0));
        $autoConfirmTime = $autoConfirmDays > 0 ? (time() + $autoConfirmDays * 86400) : 0;

        $extData = [];
        if (!empty($order['ext'])) {
            $extData = is_string($order['ext']) ? json_decode($order['ext'], true) : $order['ext'];
            if (!is_array($extData)) $extData = [];
        }
        if (!empty($proofImages)) {
            $extData['proof_images'] = $proofImages;
        }

        $this->model->where('id', $orderId)->update([
            'status' => OrderDict::STATUS_WAIT_USER_CONFIRM,
            'complete_time' => time(),
            'auto_confirm_time' => $autoConfirmTime,
            'ext' => json_encode($extData, JSON_UNESCAPED_UNICODE),
            'update_time' => time()
        ]);

        if (!empty($order['member_id'])) {
            (new \addon\sd_xiaoyuan\app\service\core\MessageService())->send(
                (int)$order['member_id'],
                'ORDER',
                '订单待确认',
                '接单员已提交完成，请确认收货',
                [
                    'link_type' => 'order_detail',
                    'link_id' => $orderId,
                    'order_id' => $orderId
                ]
            );
        }

        return true;
    }

    /**
     * 用户确认完成。
     */
    public function confirmCompletedByUser(int $orderId, int $memberId, int $siteId = 0): bool
    {
        $sid = $siteId ?: $this->site_id;
        $order = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid]
        ])->findOrEmpty()->toArray();
        if (empty($order)) throw new \Exception('订单不存在');
        if ((int)$order['member_id'] !== $memberId) throw new \Exception('无权操作此订单');
        if ((int)$order['status'] !== OrderDict::STATUS_WAIT_USER_CONFIRM) throw new \Exception('订单状态不正确');
        return $this->finalizeCompleted($order, 'USER');
    }

    /**
     * 后台确认完成。
     */
    public function confirmCompletedByAdmin(int $orderId, int $siteId = 0): bool
    {
        $sid = $siteId ?: $this->site_id;
        $order = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid]
        ])->findOrEmpty()->toArray();
        if (empty($order)) throw new \Exception('订单不存在');
        if ((int)$order['status'] !== OrderDict::STATUS_WAIT_USER_CONFIRM) throw new \Exception('订单状态不正确');
        return $this->finalizeCompleted($order, 'ADMIN');
    }

    /**
     * 自动确认完成。
     */
    public function autoConfirmCompleted(int $orderId, int $siteId = 0): bool
    {
        $sid = $siteId ?: $this->site_id;
        $order = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid]
        ])->findOrEmpty()->toArray();
        if (empty($order)) return false;
        if ((int)$order['status'] !== OrderDict::STATUS_WAIT_USER_CONFIRM) return false;
        if (empty($order['auto_confirm_time']) || (int)$order['auto_confirm_time'] > time()) return false;
        return $this->finalizeCompleted($order, 'AUTO');
    }

    /**
     * 最终完成统一入口：到账、积分、通知。
     */
    protected function finalizeCompleted(array $order, string $source = 'USER'): bool
    {
        Db::startTrans();
        try {
            $orderId = (int)$order['id'];
            $this->model->where('id', $orderId)->update([
                'status' => OrderDict::STATUS_COMPLETED,
                'auto_confirm_time' => 0,
                'confirm_source' => $source,
                'confirm_time' => time(),
                'update_time' => time()
            ]);

            $runnerId = (int)($order['runner_id'] ?? 0);
            if ($runnerId > 0) {
                $runnerIncome = (float)($order['runner_income'] ?? 0);
                if ($runnerIncome <= 0) {
                    $commissionRate = (float)($order['commission_rate'] ?? 20);
                    $runnerIncome = round((float)$order['actual_fee'] * (100 - $commissionRate) / 100, 2);
                    $this->model->where('id', $orderId)->update(['runner_income' => $runnerIncome]);
                }

                if ($runnerIncome > 0) {
                    (new RunnerService())->addIncome($runnerId, $runnerIncome, $orderId, '订单完成到账');
                }

                (new \addon\sd_xiaoyuan\app\service\core\RunnerLevelService())->checkAndUpgrade($runnerId);

                $config = (new \addon\sd_xiaoyuan\app\service\admin\ConfigService())->getConfig();
                $runnerPoint = max(0, intval($config['runner_complete_point'] ?? 0));
                if ($runnerPoint > 0) {
                    $runnerMemberId = (int)Db::name('xiaoyuan_runner')->where('id', $runnerId)->value('member_id');
                    if ($runnerMemberId > 0) {
                        (new CoreMemberAccountService())->addLog(
                            (int)$order['site_id'],
                            $runnerMemberId,
                            MemberAccountTypeDict::POINT,
                            $runnerPoint,
                            'sd_xiaoyuan_runner_complete_point',
                            '接单完成奖励积分',
                            $orderId
                        );
                    }
                }
            }

            if (!empty($order['member_id'])) {
                try {
                    (new \addon\sd_xiaoyuan\app\service\core\CreditService())->onOrderComplete((int)$order['member_id'], 'ORDER', $orderId);
                } catch (\Throwable $e) {
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        if (!empty($order['member_id'])) {
            (new \addon\sd_xiaoyuan\app\service\core\MessageService())->send(
                (int)$order['member_id'],
                'ORDER',
                '订单已完成',
                '您的订单已完成，感谢使用',
                [
                    'link_type' => 'order_detail',
                    'link_id' => (int)$order['id'],
                    'order_id' => (int)$order['id']
                ]
            );
        }

        return true;
    }

    /**
     * 取消订单（未支付关闭支付单；已支付走统一退款，成功后状态由退款回调置为 91）
     * @param int $orderId
     * @param int $siteId
     * @param string $cancelReason
     * @param string $cancelRole USER|RUNNER|SYSTEM|ADMIN
     * @return bool
     */
    public function cancel($orderId, $siteId = 0, string $cancelReason = '', string $cancelRole = 'USER')
    {
        $sid = $siteId ?: $this->site_id;

        $order = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid],
        ])->findOrEmpty();

        if ($order->isEmpty()) {
            throw new \Exception('订单不存在');
        }

        // 只有待支付、待接单、已接单的订单可以取消
        if (!in_array((int)$order['status'], [0, 10, 20], true)) {
            throw new \Exception('当前订单状态不允许取消');
        }

        Db::startTrans();
        try {
            if ((int)$order['pay_status'] === 1) {
                // 与商城/二手等一致：退款只依赖 pay 表，用 trade_type + 订单 id(trade_id) 取 out_trade_no，业务订单表无需存流水号
                $payRow = (new CorePayService())->findPayInfoByTrade($sid, 'sd_xiaoyuan_order', (int)$orderId);
                $outTradeNo = $payRow->isEmpty() ? '' : (string)($payRow['out_trade_no'] ?? '');
                if ($outTradeNo === '') {
                    throw new \Exception('未找到支付单据，无法退款');
                }

                $refundAmount = (float)($order['actual_fee'] ?? 0);
                if ($refundAmount > 0) {
                    $refundNo = (new CoreRefundService())->create(
                        $sid,
                        $outTradeNo,
                        $refundAmount,
                        $cancelReason ?: '订单取消退款',
                        'sd_xiaoyuan_order',
                        $orderId
                    );
                    (new CoreRefundService())->refund($sid, $refundNo);
                    $this->model->where('id', $orderId)->update([
                        'cancel_time' => time(),
                        'cancel_reason' => $cancelReason,
                        'cancel_role' => $cancelRole,
                        'refund_fee' => $refundAmount,
                        'refund_time' => time(),
                        'pay_status' => 2,
                        'update_time' => time(),
                    ]);
                } else {
                    $this->model->where('id', $orderId)->update([
                        'status' => 90,
                        'cancel_time' => time(),
                        'cancel_reason' => $cancelReason,
                        'cancel_role' => $cancelRole,
                        'refund_fee' => 0,
                        'refund_time' => time(),
                        'pay_status' => 2,
                        'update_time' => time(),
                    ]);
                }
            } else {
                try {
                    (new CorePayService())->closeByTrade($sid, 'sd_xiaoyuan_order', (int)$orderId);
                } catch (\Throwable $e) {
                    trace('sd_xiaoyuan_order close pay: ' . $e->getMessage(), 'error');
                }
                $this->model->where('id', $orderId)->update([
                    'status' => 90,
                    'cancel_time' => time(),
                    'cancel_reason' => $cancelReason,
                    'cancel_role' => $cancelRole,
                    'update_time' => time(),
                ]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        if (!empty($order['member_id'])) {
            try {
                (new \addon\sd_xiaoyuan\app\service\core\CreditService())->onOrderCancel((int)$order['member_id'], 'ORDER', (int)$orderId);
            } catch (\Throwable $e) {
            }
            (new \addon\sd_xiaoyuan\app\service\core\MessageService())->send(
                $order['member_id'],
                'ORDER',
                '订单已取消',
                '您的订单已被取消',
                [
                    'link_type' => 'order_detail',
                    'link_id' => $orderId,
                    'order_id' => $orderId,
                ]
            );
        }

        return true;
    }
}
