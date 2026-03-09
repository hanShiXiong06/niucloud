<?php

namespace addon\sd_xiaoyuan\app\job;

use addon\sd_xiaoyuan\app\model\Task;
use addon\sd_xiaoyuan\app\service\admin\ConfigService;
use think\facade\Db;
use app\model\site\Site;

/**
 * 接单超时自动取消任务
 * 接单员接单后超过指定时间未完成，自动取消并扣除信誉分
 */
class OrderAcceptTimeout
{
    public function fire()
    {
        $sites = (new Site())->select();
        
        foreach ($sites as $site) {
            $config = (new ConfigService())->getConfig($site->site_id);
            // 接单超时时间（分钟），默认30分钟
            $accept_timeout = (int)($config['accept_timeout'] ?? 30);
            
            if ($accept_timeout <= 0) {
                continue;
            }
            
            $timeout = time() - ($accept_timeout * 60);
            
            // 查找已接单但超时未完成的订单
            $orders = (new Task())->where([
                ['site_id', '=', $site->site_id],
                ['status', '=', 1], // 已接单/进行中
                ['accept_time', '<=', $timeout],
                ['accept_time', '>', 0]
            ])->select();
            
            foreach ($orders as $order) {
                Db::startTrans();
                
                $order->status = 4; // 已取消
                $order->cancel_reason = '接单超时未完成自动取消';
                $order->cancel_time = time();
                $order->save();
                
                // TODO: 扣除接单员信誉分
                // TODO: 退款逻辑
                
                Db::commit();
            }
        }
    }
}
