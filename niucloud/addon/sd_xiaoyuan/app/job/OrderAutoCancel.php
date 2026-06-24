<?php

namespace addon\sd_xiaoyuan\app\job;

use addon\sd_xiaoyuan\app\model\Task;
use addon\sd_xiaoyuan\app\service\admin\ConfigService;
use think\facade\Db;
use app\model\site\Site;

/**
 * 订单超时自动取消任务
 * 订单创建后超过指定时间未被接单，自动取消
 */
class OrderAutoCancel
{
    public function fire()
    {
        $sites = (new Site())->select();
        
        foreach ($sites as $site) {
            $config = (new ConfigService())->getConfig($site->site_id);
            // 订单超时时间（分钟），默认30分钟
            $order_timeout = (int)($config['order_timeout'] ?? 30);
            
            if ($order_timeout <= 0) {
                continue;
            }
            
            $timeout = time() - ($order_timeout * 60);
            
            // 查找待接单且超时的任务（已支付未接单）
            $orders = (new Task())->where([
                ['site_id', '=', $site->site_id],
                ['status', '=', Task::STATUS_PENDING], // 待接单
                ['create_time', '<=', $timeout]
            ])->select();
            
            foreach ($orders as $order) {
                Db::startTrans();
                
                $order->status = Task::STATUS_CANCELLED; // 已取消
                $order->cancel_reason = '超时未接单自动取消';
                $order->cancel_time = time();
                $order->save();
                
                // TODO: 退款逻辑（如果已支付）
                
                Db::commit();
            }
        }
    }
}
