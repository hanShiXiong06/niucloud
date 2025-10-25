<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\job\order_event;

use addon\recycle\app\dict\order\RecycleReturnOrderDict;
use addon\recycle\app\model\RecycleReturnOrder;
use addon\recycle\app\service\core\RecycleReturnOrderService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 退货订单自动完成定时任务
 */
class ReturnOrderAutoComplete extends BaseJob
{
    /**
     * 执行定时任务
     * @param mixed $data
     * @return bool
     */
    public function doJob($data = null)
    {
        try {
            Log::info('开始执行退货订单自动完成任务');
            
            // 处理当日取件完成的订单
            $this->processExpressCarOrders();
            
            // 处理72小时后自动完成的订单
            $this->processTimeoutOrders();
            
            Log::info('退货订单自动完成任务执行完成');
            
            return true;
        } catch (\Exception $e) {
            Log::error('退货订单自动完成任务执行失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理当日取件完成的订单（express_company == 'express_car'）
     * 当天的退货订单已经被客户拿走，直接完成退货
     */
    private function processExpressCarOrders()
    {
        try {
            // 查询当日express_car且状态为退货中的订单
            $orders = (new RecycleReturnOrder())->where([
                ['express_company', '=', 'express_car'],
                ['status', '=', 1],
                ['delete_at', '=', 0],
                ['create_at', '<=', strtotime(date('Y-m-d'))],
            ])
            // ->whereTime('create_at', 'today') // 当天创建的订单
            ->select();

            if (empty($orders)) {
                Log::info('没有需要处理的当日取件订单');
                return;
            }

            $service = new RecycleReturnOrderService();
            $processedCount = 0;

            foreach ($orders as $order) {
                try {
                    // 自动完成订单
                    $result = $service->updateStatus(
                        $order->id, 
                       2,
                        [
                            'site_id' => $order->site_id,
                            'comment' => '系统自动完成：当日取件订单自动确认完成',
                            'over_at' => time(),
                            'status' => 2,
                        ]
                    );

                    if ($result['code'] == 0) {
                        $processedCount++;
                        Log::info("当日取件订单自动完成成功：订单号 {$order->order_no}");
                    } else {
                        Log::error("当日取件订单自动完成失败：订单号 {$order->order_no}，错误信息：{$result['msg']}");
                    }
                } catch (\Exception $e) {
                    Log::error("处理当日取件订单失败：订单号 {$order->order_no}，错误信息：{$e->getMessage()}");
                }
            }

            Log::info("当日取件订单处理完成，共处理 {$processedCount} 个订单");
        } catch (\Exception $e) {
            Log::error('处理当日取件订单时发生错误: ' . $e->getMessage());
        }
    }

    /**
     * 处理72小时后自动完成的订单
     * 除express_car外的其他快递方式，下单时间开始算72小时后自动确认完成
     */
    private function processTimeoutOrders()
    {
        try {
            // 计算72小时前的时间戳
            $timeoutTimestamp = time() - (72 * 60 * 60); // 72小时 = 72 * 60 * 60秒
            
            // 查询72小时前创建的、非express_car且状态为退货中的订单
            $orders = (new RecycleReturnOrder())->where([
                ['express_company', '<>', 'express_car'],
                ['status', '=', RecycleReturnOrderDict::ORDER_STATUS_RETURNING],
                ['create_at', '<=', $timeoutTimestamp],
                ['delete_at', '=', 0]
            ])->select();

            if (empty($orders)) {
                Log::info('没有需要处理的72小时超时订单');
                return;
            }

            $service = new RecycleReturnOrderService();
            $processedCount = 0;

            foreach ($orders as $order) {
                try {
                    // 自动完成订单
                    $result = $service->updateStatus(
                        $order->id, 
                        RecycleReturnOrderDict::ORDER_STATUS_COMPLETED,
                        [
                            'site_id' => $order->site_id,
                            'comment' => '系统自动完成：退货订单超时72小时自动确认完成',
                            'operator_uid' => 0,
                            'operator_name' => '系统自动',
                        ]
                    );

                    if ($result['code'] == 0) {
                        $processedCount++;
                        Log::info("72小时超时订单自动完成成功：订单号 {$order->order_no}，快递公司：{$order->express_company}");
                    } else {
                        Log::error("72小时超时订单自动完成失败：订单号 {$order->order_no}，错误信息：{$result['msg']}");
                    }
                } catch (\Exception $e) {
                    Log::error("处理72小时超时订单失败：订单号 {$order->order_no}，错误信息：{$e->getMessage()}");
                }
            }

            Log::info("72小时超时订单处理完成，共处理 {$processedCount} 个订单");
        } catch (\Exception $e) {
            Log::error('处理72小时超时订单时发生错误: ' . $e->getMessage());
        }
    }
} 