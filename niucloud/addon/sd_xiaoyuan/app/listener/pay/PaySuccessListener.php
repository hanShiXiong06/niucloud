<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\pay;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Task;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use addon\sd_xiaoyuan\app\service\core\NoticeWechatService;
use addon\sd_xiaoyuan\app\service\core\NoticeWeappService;

/**
 * 支付成功监听器
 */
class PaySuccessListener
{
    /**
     * 记录JSON中文日志
     */
    private function writeLog(string $event, array $data = [], string $level = 'info')
    {
        trace(json_encode(array_merge(['event' => $event], $data), JSON_UNESCAPED_UNICODE), $level);
    }

    public function handle($data)
    {
        $trade_type = $data['trade_type'] ?? '';
        
        // 订单支付成功
        if ($trade_type == 'sd_xiaoyuan_order') {
            $order_id = $data['trade_id'] ?? 0;
            if (!$order_id) return true;
            
            $order = (new Order())->where('id', $order_id)->find();
            if (!$order) return true;
            
            // 检查是否已支付，避免重复处理
            if ($order['pay_status'] == 1) return true;
            
            \think\facade\Db::startTrans();
            try {
                $order->save([
                    'pay_status' => 1,
                    'pay_time' => time(),
                    'status' => 10,
                    'update_time' => time(),
                ]);
                (new \addon\sd_xiaoyuan\app\service\core\CardService())->handleOrderPaySuccess($order->toArray());
                
                \think\facade\Db::commit();
                
                // 发送系统消息给下单人
                try {
                    $msg_id = (new MessageService())->sendDirect(
                        $order['site_id'],
                        $order['member_id'],
                        'ORDER',
                        '订单支付成功',
                        '您的订单已支付成功，等待接单员接单',
                        [
                            'link_type' => 'order_detail',
                            'link_id' => $order_id,
                            'order_id' => $order_id
                        ]
                    );
                    $this->writeLog('订单支付消息发送成功', [
                        'order_id' => $order_id,
                        'member_id' => $order['member_id'],
                        'message_id' => $msg_id,
                    ]);
                } catch (\Exception $e) {
                    $this->writeLog('订单支付消息发送失败', [
                        'order_id' => $order_id,
                        'member_id' => $order['member_id'],
                        'error' => $e->getMessage(),
                    ], 'error');
                }
                
                // 通知同校所有在线接单员有新订单
                if (!empty($order['school_id'])) {
                    try {
                        $notify_result = (new MessageService())->notifyOnlineRunners(
                            $order['site_id'],
                            $order['school_id'],
                            '新订单提醒',
                            '有新的订单等待接单，快去抢单吧！',
                            ['link_type' => 'order_hall', 'link_id' => $order_id, 'order_id' => $order_id]
                        );
                        if (!empty($notify_result['list'])) {
                            $this->writeLog('订单支付通知在线接单员成功', [
                                'order_id' => $order_id,
                                'count' => $notify_result['count'],
                                'list' => $notify_result['list'],
                            ]);
                        }
                        if (!empty($notify_result['fail_list'])) {
                            $this->writeLog('订单支付通知在线接单员失败', [
                                'order_id' => $order_id,
                                'fail_count' => $notify_result['fail_count'],
                                'fail_list' => $notify_result['fail_list'],
                            ], 'error');
                        }
                        if (empty($notify_result['list']) && empty($notify_result['fail_list'])) {
                            $this->writeLog('订单支付通知在线接单员', [
                                'order_id' => $order_id,
                                'msg' => '无在线接单员',
                            ]);
                        }
                    } catch (\Exception $e) {
                        $this->writeLog('订单支付通知在线接单员异常', [
                            'order_id' => $order_id,
                            'error' => $e->getMessage(),
                        ], 'error');
                    }
                    // 公众号+小程序订阅推送给同校接单员
                    $noticeWechat = new NoticeWechatService();
                    $noticeWeapp = new NoticeWeappService();
                    $runnerService = new \addon\sd_xiaoyuan\app\service\core\RunnerService();
                    $runners = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where([
                        ['site_id', '=', $order['site_id']],
                        ['status', '=', 1],
                        ['school_id', '=', $order['school_id']],
                    ])->field('id,member_id,weapp_subscribe_num')->select()->toArray();
                    foreach ($runners as $runner) {
                        if (empty($runner['member_id'])) {
                            continue;
                        }
                        $sendData = [
                            'order_id' => $order_id,
                            'member_id' => $runner['member_id'],
                        ];
                        $noticeWechat->send((int)$order['site_id'], 'sd_xiaoyuan_order_pay_runner', $sendData);
                        if ((int)($runner['weapp_subscribe_num'] ?? 0) <= 0) {
                            continue;
                        }
                        $sendRes = $noticeWeapp->send((int)$order['site_id'], 'sd_xiaoyuan_order_pay_runner', $sendData);
                        $errcode = is_array($sendRes) ? (int)($sendRes['errcode'] ?? -1) : -1;
                        if ($errcode === 0) {
                            $runnerService->useWeappSubscribe((int)$runner['id']);
                        }
                    }
                }
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                $this->writeLog('订单支付回调失败', [
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                ], 'error');
                throw $e;
            }
            
            return true;
        }
        
        // 小费支付成功
        if ($trade_type == 'sd_xiaoyuan_tip') {
            $tip_id = $data['trade_id'] ?? 0;
            if (!$tip_id) return true;
            
            $tipOrder = (new \addon\sd_xiaoyuan\app\model\TipOrder())->where('id', $tip_id)->find();
            if (!$tipOrder) return true;
            
            // 检查是否已支付
            if ($tipOrder['pay_status'] == 1) return true;
            
            \think\facade\Db::startTrans();
            try {
                // 标记小费订单已支付
                $tipOrder->save([
                    'pay_status' => 1,
                    'pay_time' => time(),
                ]);
                
                // 更新关联订单的累计小费金额
                $order = (new Order())->where('id', $tipOrder['order_id'])->find();
                if ($order) {
                    $newTip = round(($order['tip_fee'] ?? 0) + $tipOrder['amount'], 2);
                    $order->save(['tip_fee' => $newTip, 'update_time' => time()]);
                }
                
                // 小费全额发放给接单员可提现金额
                if (!empty($tipOrder['runner_id'])) {
                    $runner = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where('id', $tipOrder['runner_id'])->find();
                    if ($runner) {
                        (new \app\service\core\member\CoreMemberAccountService())->addLog(
                            $tipOrder['site_id'],
                            $runner['member_id'],
                            \app\dict\member\MemberAccountTypeDict::MONEY,
                            $tipOrder['amount'],
                            'sd_xiaoyuan_runner_income',
                            '用户小费收益',
                            $tip_id
                        );
                        // 更新接单员总收入
                        $runner->inc('total_income', $tipOrder['amount'])->update();
                        
                        // 发送消息通知接单员
                        try {
                            $tip_msg_id = (new MessageService())->sendDirect(
                                $tipOrder['site_id'],
                                $runner['member_id'],
                                'ORDER',
                                '收到小费',
                                '您收到了¥' . $tipOrder['amount'] . '的小费，已到账',
                                ['order_id' => $tipOrder['order_id'], 'tip_id' => $tip_id]
                            );
                            $this->writeLog('小费消息发送成功', [
                                'tip_id' => $tip_id,
                                'member_id' => $runner['member_id'],
                                'message_id' => $tip_msg_id,
                            ]);
                        } catch (\Exception $e) {
                            $this->writeLog('小费消息发送失败', [
                                'tip_id' => $tip_id,
                                'member_id' => $runner['member_id'],
                                'error' => $e->getMessage(),
                            ], 'error');
                        }
                    }
                }
                
                \think\facade\Db::commit();
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                $this->writeLog('小费支付回调失败', [
                    'tip_id' => $tip_id,
                    'error' => $e->getMessage(),
                ], 'error');
                throw $e;
            }
            
            return true;
        }
        
        // 任务支付成功
        if ($trade_type == 'sd_xiaoyuan_task') {
            $task_id = $data['trade_id'] ?? 0;
            if (!$task_id) return true;
            
            $task = (new Task())->where('id', $task_id)->find();
            if (!$task) return true;
            
            // 检查是否已支付
            if ($task['pay_status'] == 1) return true;
            
            \think\facade\Db::startTrans();
            try {
                $task->save([
                    'pay_status' => 1,
                    'pay_time' => time(),
                    'status' => 10, // 待接单
                    'update_time' => time(),
                ]);
                
                \think\facade\Db::commit();
                
                // 发送系统消息
                try {
                    $task_msg_id = (new MessageService())->sendDirect(
                        $task['site_id'],
                        $task['member_id'],
                        'TASK',
                        '任务发布成功',
                        '您的任务已发布成功，等待接单员接单',
                        ['task_id' => $task_id]
                    );
                    $this->writeLog('任务支付消息发送成功', [
                        'task_id' => $task_id,
                        'member_id' => $task['member_id'],
                        'message_id' => $task_msg_id,
                    ]);
                } catch (\Exception $e) {
                    $this->writeLog('任务支付消息发送失败', [
                        'task_id' => $task_id,
                        'member_id' => $task['member_id'],
                        'error' => $e->getMessage(),
                    ], 'error');
                }
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                $this->writeLog('任务支付回调失败', [
                    'task_id' => $task_id,
                    'error' => $e->getMessage(),
                ], 'error');
                throw $e;
            }
            
            return true;
        }
        
        if ($trade_type == 'sd_xiaoyuan_card') {
            $order_id = (int)($data['trade_id'] ?? 0);
            if (!$order_id) {
                return true;
            }
            (new \addon\sd_xiaoyuan\app\service\core\CardService())->handlePaySuccess($order_id);
            return true;
        }

        // 房屋租赁支付成功
        if ($trade_type == 'sd_xiaoyuan_house') {
            $order_id = $data['trade_id'] ?? 0;
            if (!$order_id) return true;
            
            $houseOrder = (new \addon\sd_xiaoyuan\app\model\HouseOrder())->where('id', $order_id)->find();
            if (!$houseOrder) return true;
            
            // 检查是否已支付
            if ($houseOrder['status'] == 1) return true;
            
            \think\facade\Db::startTrans();
            try {
                $houseOrder->save([
                    'status' => 1,
                    'pay_time' => time(),
                    'update_time' => time(),
                ]);
                
                \think\facade\Db::commit();
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                $this->writeLog('房屋订单支付回调失败', [
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                ], 'error');
                throw $e;
            }
            
            return true;
        }
        
        return true;
    }
}
