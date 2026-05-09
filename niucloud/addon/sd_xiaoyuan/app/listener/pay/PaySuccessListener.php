<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\pay;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Task;
use addon\sd_xiaoyuan\app\service\core\MessageService;

/**
 * 支付成功监听器
 */
class PaySuccessListener
{
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
                    'status' => 10, // 待接单
                    'update_time' => time(),
                ]);
                
                \think\facade\Db::commit();
                
                // 发送系统消息给下单人
                try {
                    (new MessageService())->sendDirect(
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
                } catch (\Exception $e) {
                    // 消息发送失败不影响主流程
                    trace('sd_xiaoyuan订单支付消息发送失败: ' . $e->getMessage(), 'error');
                }
                
                // 通知同校所有在线接单员有新订单
                if (!empty($order['school_id'])) {
                    try {
                        (new MessageService())->notifyOnlineRunners(
                            $order['site_id'],
                            $order['school_id'],
                            '新订单提醒',
                            '有新的订单等待接单，快去抢单吧！',
                            ['link_type' => 'order_hall', 'link_id' => $order_id, 'order_id' => $order_id]
                        );
                    } catch (\Exception $e) {
                        // 消息发送失败不影响主流程
                        trace('sd_xiaoyuan订单支付通知接单员失败: ' . $e->getMessage(), 'error');
                    }
                }
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                trace('sd_xiaoyuan订单支付回调失败: order_id=' . $order_id . ', error=' . $e->getMessage(), 'error');
                throw $e;
            }
            
            return true;
        }
        
        // 打赏支付成功
        if ($trade_type == 'sd_xiaoyuan_tip') {
            $tip_id = $data['trade_id'] ?? 0;
            if (!$tip_id) return true;
            
            $tipOrder = (new \addon\sd_xiaoyuan\app\model\TipOrder())->where('id', $tip_id)->find();
            if (!$tipOrder) return true;
            
            // 检查是否已支付
            if ($tipOrder['pay_status'] == 1) return true;
            
            \think\facade\Db::startTrans();
            try {
                // 标记打赏订单已支付
                $tipOrder->save([
                    'pay_status' => 1,
                    'pay_time' => time(),
                ]);
                
                // 更新关联订单的累计打赏金额
                $order = (new Order())->where('id', $tipOrder['order_id'])->find();
                if ($order) {
                    $newTip = round(($order['tip_fee'] ?? 0) + $tipOrder['amount'], 2);
                    $order->save(['tip_fee' => $newTip, 'update_time' => time()]);
                }
                
                // 打赏全额发放给接单员可提现金额
                if (!empty($tipOrder['runner_id'])) {
                    $runner = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where('id', $tipOrder['runner_id'])->find();
                    if ($runner) {
                        (new \app\service\core\member\CoreMemberAccountService())->addLog(
                            $tipOrder['site_id'],
                            $runner['member_id'],
                            \app\dict\member\MemberAccountTypeDict::MONEY,
                            $tipOrder['amount'],
                            'sd_xiaoyuan_runner_income',
                            '用户打赏收益',
                            $tip_id
                        );
                        // 更新接单员总收入
                        $runner->inc('total_income', $tipOrder['amount'])->update();
                        
                        // 发送消息通知接单员
                        try {
                            (new MessageService())->sendDirect(
                                $tipOrder['site_id'],
                                $runner['member_id'],
                                'ORDER',
                                '收到打赏',
                                '您收到了¥' . $tipOrder['amount'] . '的打赏，已到账',
                                ['order_id' => $tipOrder['order_id'], 'tip_id' => $tip_id]
                            );
                        } catch (\Exception $e) {
                            // 消息发送失败不影响主流程
                            trace('sd_xiaoyuan打赏消息发送失败: ' . $e->getMessage(), 'error');
                        }
                    }
                }
                
                \think\facade\Db::commit();
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                trace('sd_xiaoyuan打赏支付回调失败: tip_id=' . $tip_id . ', error=' . $e->getMessage(), 'error');
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
                    (new MessageService())->sendDirect(
                        $task['site_id'],
                        $task['member_id'],
                        'TASK',
                        '任务发布成功',
                        '您的任务已发布成功，等待接单员接单',
                        ['task_id' => $task_id]
                    );
                } catch (\Exception $e) {
                    // 消息发送失败不影响主流程
                    trace('sd_xiaoyuan任务支付消息发送失败: ' . $e->getMessage(), 'error');
                }
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                trace('sd_xiaoyuan任务支付回调失败: task_id=' . $task_id . ', error=' . $e->getMessage(), 'error');
                throw $e;
            }
            
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
                trace('sd_xiaoyuan房屋订单支付回调失败: order_id=' . $order_id . ', error=' . $e->getMessage(), 'error');
                throw $e;
            }
            
            return true;
        }
        
        return true;
    }
}
