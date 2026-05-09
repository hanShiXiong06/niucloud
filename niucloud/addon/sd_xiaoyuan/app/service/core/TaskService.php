<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Task;
use addon\sd_xiaoyuan\app\model\CampusAuth;
use addon\sd_xiaoyuan\app\service\core\CreditService;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use app\service\core\member\CoreMemberAccountService;
use app\dict\member\MemberAccountTypeDict;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 任务悬赏服务
 */
class TaskService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Task();
    }

    /**
     * 获取任务列表(后台/前台通用)
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,task_no,member_id,runner_id,school_id,campus,task_type,title,content,reward,tip,total_amount,is_urgent,deadline,status,create_time';
        $order = 'id desc';
        
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(['task_type', 'status', 'school_id', 'campus', 'member_id', 'runner_id'], $where)->field($field)->order($order);
        $result = $this->pageQuery($search_model);

        // 附加发布人头像、昵称、信誉分
        if (!empty($result['data'])) {
            $member_ids = array_unique(array_filter(array_column($result['data'], 'member_id')));
            $members = [];
            $credits = [];
            if (!empty($member_ids)) {
                $member_list = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->field('member_id,nickname,headimg')->select()->toArray();
                foreach ($member_list as $m) {
                    $members[$m['member_id']] = $m;
                }
                $credit_list = (new Credit())->where([['member_id', 'in', $member_ids], ['site_id', '=', $this->site_id]])->field('member_id,credit_score')->select()->toArray();
                foreach ($credit_list as $c) {
                    $credits[$c['member_id']] = $c['credit_score'];
                }
            }
            foreach ($result['data'] as &$item) {
                $mid = $item['member_id'];
                $item['publisher_nickname'] = $members[$mid]['nickname'] ?? '匿名用户';
                $item['publisher_headimg'] = $members[$mid]['headimg'] ?? '';
                $item['publisher_credit_score'] = $credits[$mid] ?? Credit::INIT_SCORE;
            }
            unset($item);
        }

        return $result;
    }

    /**
     * 获取任务详情
     */
    public function getInfo(int $id)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('任务不存在');
        }
        return $info->toArray();
    }

    /**
     * 生成任务编号
     */
    public function createTaskNo(): string
    {
        return 'T' . date('YmdHis') . mt_rand(1000, 9999);
    }

    /**
     * 发布任务
     */
    public function publish(int $member_id, array $data)
    {
        // 检查用户是否已认证
        $auth = (new CampusAuth())->where([['member_id', '=', $member_id], ['site_id', '=', $this->site_id], ['status', '=', 1]])->find();
        if (empty($auth)) {
            throw new CommonException('请先完成校园帮实名认证');
        }
        
        // 检查信誉分是否受限
        (new CreditService())->checkCanOperate($member_id);
        
        $task_data = [
            'site_id' => $this->site_id,
            'task_no' => $this->createTaskNo(),
            'member_id' => $member_id,
            'school_id' => $auth['school_id'],
            'campus' => $auth['campus'],
            'task_type' => $data['task_type'],
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'images' => $data['images'] ?? '',
            'pickup_address' => $data['pickup_address'] ?? '',
            'pickup_lng' => $data['pickup_lng'] ?? '',
            'pickup_lat' => $data['pickup_lat'] ?? '',
            'delivery_address' => $data['delivery_address'] ?? '',
            'delivery_lng' => $data['delivery_lng'] ?? '',
            'delivery_lat' => $data['delivery_lat'] ?? '',
            'contact_name' => $data['contact_name'] ?? '',
            'contact_mobile' => $data['contact_mobile'] ?? '',
            'express_company' => $data['express_company'] ?? '',
            'express_no' => $data['express_no'] ?? '',
            'pickup_code' => $data['pickup_code'] ?? '',
            'reward' => $data['reward'] ?? 0,
            'tip' => $data['tip'] ?? 0,
            'total_amount' => ($data['reward'] ?? 0) + ($data['tip'] ?? 0),
            'is_urgent' => $data['is_urgent'] ?? 0,
            'deadline' => $data['deadline'] ?? 0,
            'status' => Task::STATUS_UNPAID,
            'create_time' => time(),
            'update_time' => time(),
        ];
        
        // 计算平台服务费和接单员收益
        $platform_rate = 0.1; // 平台抽成10%
        $task_data['platform_fee'] = round($task_data['total_amount'] * $platform_rate, 2);
        $task_data['runner_income'] = $task_data['total_amount'] - $task_data['platform_fee'];
        
        $res = $this->model->create($task_data);
        return $res->id;
    }

    /**
     * 支付任务
     */
    public function pay(int $task_id, int $member_id, string $pay_type = 'wechat')
    {
        $task = $this->model->where([['id', '=', $task_id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($task)) {
            throw new CommonException('任务不存在');
        }
        
        if ($task['status'] != Task::STATUS_UNPAID) {
            throw new CommonException('任务状态异常');
        }
        
        $task->save([
            'status' => Task::STATUS_PENDING,
            'pay_status' => 1,
            'pay_type' => $pay_type,
            'pay_time' => time(),
            'update_time' => time(),
        ]);
        
        // 发送系统消息
        (new MessageService())->send($member_id, 'TASK', '任务发布成功', '您的任务已发布成功，等待接单员接单', ['task_id' => $task_id]);
        
        return true;
    }

    /**
     * 接单
     */
    public function accept(int $task_id, int $runner_id)
    {
        $task = $this->model->where([['id', '=', $task_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($task)) {
            throw new CommonException('任务不存在');
        }
        
        if ($task['status'] != Task::STATUS_PENDING) {
            throw new CommonException('任务已被接单或已取消');
        }
        
        // 检查接单员是否同校
        $runner_auth = (new CampusAuth())->where([['member_id', '=', $runner_id], ['site_id', '=', $this->site_id], ['status', '=', 1]])->find();
        if (empty($runner_auth)) {
            throw new CommonException('请先完成校园帮实名认证');
        }
        
        if ($runner_auth['school_id'] != $task['school_id']) {
            throw new CommonException('仅支持本校接单');
        }
        
        // 检查信誉分是否受限
        (new CreditService())->checkCanOperate($runner_id);
        
        $task->save([
            'runner_id' => $runner_id,
            'status' => Task::STATUS_ACCEPTED,
            'accept_time' => time(),
            'update_time' => time(),
        ]);
        
        // 发送系统消息给发布者
        (new MessageService())->send($task['member_id'], 'TASK', '任务已被接单', '您的任务已被接单，接单员正在处理中', ['task_id' => $task_id]);
        
        return true;
    }

    /**
     * 开始执行任务
     */
    public function start(int $task_id, int $runner_id)
    {
        $task = $this->model->where([['id', '=', $task_id], ['runner_id', '=', $runner_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($task)) {
            throw new CommonException('任务不存在');
        }
        
        if ($task['status'] != Task::STATUS_ACCEPTED) {
            throw new CommonException('任务状态异常');
        }
        
        $task->save([
            'status' => Task::STATUS_PROCESSING,
            'update_time' => time(),
        ]);
        
        // 发送系统消息
        (new MessageService())->send($task['member_id'], 'TASK', '任务进行中', '接单员已开始执行您的任务', ['task_id' => $task_id]);
        
        return true;
    }

    /**
     * 完成任务(接单员提交)
     */
    public function submitComplete(int $task_id, int $runner_id)
    {
        $task = $this->model->where([['id', '=', $task_id], ['runner_id', '=', $runner_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($task)) {
            throw new CommonException('任务不存在');
        }
        
        if ($task['status'] != Task::STATUS_PROCESSING) {
            throw new CommonException('任务状态异常');
        }
        
        $task->save([
            'status' => Task::STATUS_CONFIRMING,
            'update_time' => time(),
        ]);
        
        // 发送系统消息
        (new MessageService())->send($task['member_id'], 'TASK', '任务待确认', '接单员已完成任务，请确认', ['task_id' => $task_id]);
        
        return true;
    }

    /**
     * 确认完成(发布者确认)
     */
    public function confirmComplete(int $task_id, int $member_id)
    {
        $task = $this->model->where([['id', '=', $task_id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($task)) {
            throw new CommonException('任务不存在');
        }
        
        if ($task['status'] != Task::STATUS_CONFIRMING) {
            throw new CommonException('任务状态异常');
        }
        
        $task->save([
            'status' => Task::STATUS_COMPLETED,
            'complete_time' => time(),
            'update_time' => time(),
        ]);
        
        // 发放接单员收益到可提现金额
        (new CoreMemberAccountService())->addLog(
            $this->site_id,
            $task['runner_id'],
            MemberAccountTypeDict::MONEY,
            $task['runner_income'],
            'sd_xiaoyuan_runner_income',
            '任务收益',
            $task_id
        );
        
        // 接单员信誉分加分
        (new CreditService())->onOrderComplete($task['runner_id'], 'TASK', $task_id);
        
        // 发布者信誉分加分
        
        // 微信小程序发货信息录入（服务类商品）
        $this->uploadShippingInfo($task);
    }
    
    /**
     * 微信小程序发货信息录入接口
     * @param $task
     * @return void
     */
    private function uploadShippingInfo($task)
    {
        try {
            // 检查是否是微信小程序支付
            $pay_model = new \app\model\pay\Pay();
            $pay_info = $pay_model->where([
                ['out_trade_no', '=', $task['out_trade_no']],
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty();
            
            if ($pay_info->isEmpty() || $pay_info['type'] != 'wechatpay' || $pay_info['channel'] != 'weapp') {
                return; // 不是微信小程序支付，不需要上传发货信息
            }
            
            // 获取用户openid
            $member_model = new \app\model\member\Member();
            $member = $member_model->where([
                ['member_id', '=', $task['member_id']],
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty();
            
            if ($member->isEmpty() || empty($member['weapp_openid'])) {
                \think\facade\Log::write('xiaoyuan发货信息录入失败：用户openid为空');
                return;
            }
            
            $weapp_delivery_service = new \app\service\core\weapp\CoreWeappDeliveryService();
            
            // 检测微信小程序是否已开通发货信息管理服务
            $is_trade_managed = $weapp_delivery_service->isTradeManaged($this->site_id);
            if (empty($is_trade_managed['is_trade_managed'])) {
                \think\facade\Log::write('xiaoyuan发货信息录入失败：' . ($is_trade_managed["errmsg"] ?? '未开通发货信息管理服务'));
                return;
            }
            
            // 获取任务类型名称
            $task_type_name = \addon\sd_xiaoyuan\app\dict\order\OrderDict::getTaskType()[$task['task_type']] ?? '校园服务';
            
            // 上传发货信息（服务类商品）
            $data = [
                'out_trade_no' => $task['out_trade_no'],
                'logistics_type' => 3, // 3-虚拟商品（服务类）
                'delivery_mode' => 1, // 1-统一发货
                'shipping_list' => [
                    [
                        'item_desc' => $task_type_name // 商品描述
                    ]
                ],
                'weapp_openid' => $member['weapp_openid'],
                'is_all_delivered' => true
            ];
            
            $weapp_delivery_service->uploadShippingInfo($this->site_id, $data);
            
        } catch (\Exception $e) {
            \think\facade\Log::write('xiaoyuan发货信息录入异常：' . $e->getMessage() . ' File:' . $e->getFile() . ' Line:' . $e->getLine());
        }
    }
        (new CreditService())->onOrderComplete($member_id, 'TASK', $task_id);
        
        // 发送系统消息
        (new MessageService())->send($task['runner_id'], 'TASK', '任务已完成', '任务已完成，收益已到账', ['task_id' => $task_id]);
        
        return true;
    }

    /**
     * 取消任务
     */
    public function cancel(int $task_id, int $member_id, string $reason = '', string $role = 'USER')
    {
        $task = $this->model->where([['id', '=', $task_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($task)) {
            throw new CommonException('任务不存在');
        }
        
        // 只有待接单状态可以取消
        if ($task['status'] > Task::STATUS_PENDING) {
            throw new CommonException('任务已被接单，无法取消');
        }
        
        $task->save([
            'status' => Task::STATUS_CANCELLED,
            'cancel_time' => time(),
            'cancel_reason' => $reason,
            'cancel_role' => $role,
            'update_time' => time(),
        ]);
        
        // 退款到余额
        if ($task['pay_status'] == 1) {
            (new CoreMemberAccountService())->addLog(
                $this->site_id,
                $task['member_id'],
                MemberAccountTypeDict::BALANCE,
                $task['total_amount'],
                'sd_xiaoyuan_refund',
                '任务取消退款',
                $task_id
            );
            $task->save(['status' => Task::STATUS_REFUNDED]);
        }
        
        // 取消订单扣分
        (new CreditService())->onOrderCancel($member_id, 'TASK', $task_id);
        
        return true;
    }

    /**
     * 获取任务类型列表
     */
    public function getTypeList()
    {
        return Task::getTypeList();
    }

    /**
     * 获取我的发布任务列表
     */
    public function getMyPublish(array $where = [])
    {
        $where['member_id'] = $this->member_id;
        return $this->getPage($where);
    }

    /**
     * 获取我的接单任务列表
     */
    public function getMyAccept(array $where = [])
    {
        $where['runner_id'] = $this->member_id;
        return $this->getPage($where);
    }

    /**
     * 获取任务状态列表
     */
    public function getStatusList()
    {
        return Task::getStatusList();
    }
}
