<?php

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\dict\order\TechnicianOrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderLogService;
use addon\home_service\app\service\core\order\CoreOrderCommissionService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\service\core\order\SubStatusTrait;
use addon\home_service\app\service\core\graborder\CoreGrabOrderService;


/**
 * 师傅抢单订单服务层
 */
class GrabOrderService extends BaseApiService
{

    use TechnicianTrait, SubStatusTrait;


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_TECHNICIAN;
        $this->checkTechnician();
    }


    public function __destruct()
    {
        Order::$contextRole = null;
    }


    /**
     * 抢单分类查询
     * @param array $where
     * @return mixed
     */
    public function grabCategory()
    {
        $where['category_ids'] = $this->technician_info['category_id'] ?? '';
        return (new CoreGrabOrderService)->grabCategory($where, $this->site_id);
    }


    /**
     * 订单分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $where['category_ids'] = ($where['category_id'] === 'all') ? ($this->technician_info['category_id'] ?? '') : $where['category_id'] ?? '';
        $list = (new CoreGrabOrderService)->getPage($where, $this->site_id);
        foreach ($list['data'] as &$team) {
            $team['technician_commission'] = (new CoreOrderCommissionService)->computeTechnicianCommission($team['site_id'], $this->technician_info['id'], $team['store_id'], $team['order_money']);
            $team['reserve_service_time'] = Order::formatTime($team['reserve_service_time_stamp']);
        }
        return $list;
    }

    /**
     * 订单详情
     * @param array $where
     * @return mixed
     */
    public function getDetail(int $order_id)
    {
        $field = 'sub_status,order_id, member_message,site_id, reserve_service_time,member_id, order_from, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,technician_id,service_time,dispatch_time,finish_time, buy_type, reserve_service_time_stamp, technician_commission,technician_additional_commission,is_card_order,is_abnormal,take_photos,discount_money,store_id,errand_items';
        $detail = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])->field($field)->with(['item' => function ($query) {
            $query->field('order_id, item_id, item_name,order_item_id, item_type, is_refund, item_image,price, num, item_money, site_id, out_trade_no,pay_time,is_enable_refund,item_images,refund_no,refund_status')->append(['item_image_thumb_small', 'item_images_thumb_mid', 'item_images_thumb_small', 'item_type_name']);
        }, 'member' => function ($query) {
            $query->field('member_id, nickname, mobile, headimg');
        }, 'order_log' => function ($query) {
            $query->field('order_id, action, action_time, nick_name, action_way');
        }])->append(['order_status_info', 'time_reminder', 'order_from_name'])->findOrEmpty()->toArray();
        if (!empty($detail)){
            $detail['technician_commission'] = (new CoreOrderCommissionService)->computeTechnicianCommission($detail['site_id'], $this->technician_info['id'], $detail['store_id'], $detail['order_money']);
            $detail['reserve_service_time'] = Order::formatTime($detail['reserve_service_time_stamp']);
            $detail['take_photos'] = $detail['take_photos'] ? explode(',',$detail['take_photos']) : [];

        }

        // 将 errand_items {}  转为  [] 数组 --hsx
        if(!empty($detail['errand_items'])){
            $detail['errand_items'] = json_decode($detail['errand_items'], true);
        }else{
            $detail['errand_items'] = [];
        }
        return $detail;
    }


    /**
     * 抢单
     * @param array $where
     * @return mixed
     */
    public function grab($orderId)
    {
        $order = $this->model
            ->where('order_id', $orderId) // 简化查询条件
            ->with(['item' => function ($query) {
                $query->field('order_id, is_force_clock_in, is_force_departure');
            }])
            ->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('ORDER_NOT_EXIST');
        }
        $this->validateOrderCanBeGrabbed($order);
        $orderItem = $order->item->first();
        if (empty($orderItem)) {
            throw new CommonException('ORDER_ITEM_NOT_EXIST'); // 补充商品不存在的校验
        }
        Db::startTrans();
        try {
            $updateResult = $this->model
                ->where('order_id', $orderId)
                ->where('order_status', OrderDict::WAIT_DISPATCH) // 确保状态未变
                ->where('technician_id', 0) // 确保未被其他师傅抢单
                ->where('store_id', 0) // 确保未被门店接单
                ->update([
                    'order_status' => TechnicianOrderDict::WAIT_SERVICE,
                    'technician_id' => $this->technician_id,
                    'dispatch_time' => time(),
                    'sub_status' => self::getSubStatus($orderItem), // 提取为独立方法
                ]);
            if ($updateResult === 0) {
                throw new CommonException('HOME_SERVICE_ORDER_GRAB_FAILED');
            }
            (new CoreOrderLogService())->addLog($order->site_id, $order->order_id, OrderLogDict::ORDER_GRAB, 'technician', $this->technician_id, OrderDict::getStatus(OrderDict::WAIT_SERVICE));
            event('ComputeOrderCommission', [
                'order_id' => $order->order_id,
                'order_type' => $order->order_type,
            ]);

            event('NotificationEvent', [
                'identity' => [NoticeDict::TECHNICIAN],
                'type' => NoticeDict::GRAB_SUCCESS,
                'notice_source' => NoticeDict::ORDER,
                'order_id' => $order->order_id,
                'technician_id' => $this->technician_id,
                'member_id' => $order->member_id,
                'site_id' => $this->site_id,
            ]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback(); // 显式回滚，确保事务安全
            throw new CommonException($e->getMessage() ?: 'HOME_SERVICE_ORDER_GRAB_ERROR');
        }
    }

    /**
     * 校验订单是否可被抢单（提取为独立方法，增强可读性）
     */
    private function validateOrderCanBeGrabbed($order)
    {
        $invalid =
            $order->order_status != OrderDict::WAIT_DISPATCH ||
            !empty($order->technician_id) ||
            !empty($order->store_id);
        if ($invalid) {
            throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_GRAB');
        }
    }


}
