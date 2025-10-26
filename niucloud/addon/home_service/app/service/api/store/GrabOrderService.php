<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\api\store;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\dict\order\StoreOrderDict;

use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\graborder\CoreGrabOrderService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\CoreOrderLogService;
use app\service\core\notice\NoticeService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 门店抢单订单服务层
 */
class GrabOrderService extends BaseApiService
{

    use StoreTrait;


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_STORE;
        $this->checkStore();
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
        return (new CoreGrabOrderService)->grabCategory([], $this->site_id);
    }


    /**
     * 订单分页列表 dd($this->store_info['service_ratio']);
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $list = (new CoreGrabOrderService)->getPage($where, $this->site_id);
        foreach ($list['data'] as &$team) {
            $team['store_commission'] = bcmul($team['order_money'], bcmul($this->store_info['service_ratio'], 0.01, 2), 2);
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
        $field = 'category_id,sub_status,order_id, member_message,site_id, reserve_service_time,member_id, order_from, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,technician_id,service_time,dispatch_time,finish_time, buy_type, reserve_service_time_stamp, technician_commission,technician_additional_commission,is_card_order,is_abnormal,take_photos,discount_money,store_commission,store_additional_commission';
        $detail = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])->field($field)->with(['item' => function ($query) {
            $query->field('order_id, item_id, item_name,order_item_id, item_type, is_refund, item_image,price, num, item_money, site_id, out_trade_no,pay_time,is_enable_refund,item_images,refund_no,refund_status')->append(['item_image_thumb_small', 'item_images_thumb_mid', 'item_images_thumb_small', 'item_type_name']);
        }, 'member' => function ($query) {
            $query->field('member_id, nickname, mobile, headimg');
        }, 'order_log' => function ($query) {
            $query->field('order_id, action, action_time, nick_name, action_way');
        }])->append(['order_status_info', 'time_reminder', 'order_from_name'])->findOrEmpty()->toArray();
        if (!empty($detail)){
            $detail['store_commission'] = bcmul($detail['order_money'], bcmul($this->store_info['service_ratio'], 0.01, 2), 2);
            $detail['reserve_service_time'] = Order::formatTime($detail['reserve_service_time_stamp']);
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
            ->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('ORDER_NOT_EXIST');
        }
        $this->validateOrderCanBeGrabbed($order);

        $config = (new CoreOrderConfigService())->getOrderConfig($this->site_id);
        $dispatch_timeout_config = $config['dispatch_timeout'];

        $dispatch_timeout_time = 0;
        if ($dispatch_timeout_config['is_use'] == 1) {
            $dispatch_timeout_time = time() + $dispatch_timeout_config['timeout_time']*60;
        }
        Db::startTrans();
        try {
            $updateResult = $this->model
                ->where('order_id', $orderId)
                ->where('order_status', StoreOrderDict::WAIT_DISPATCH) // 确保状态未变
                ->where('store_id', 0) // 确保未被其他师傅抢单
                ->update([
                    'store_id' => $this->store_id,
                    'dispatch_timeout_time' => $dispatch_timeout_time,
                ]);
            if ($updateResult === 0) {
                throw new CommonException('HOME_SERVICE_ORDER_GRAB_FAILED');
            }
            (new CoreOrderLogService())->addLog($order->site_id, $order->order_id, OrderLogDict::ORDER_GRAB, 'store', $this->store_id, OrderDict::getStatus(OrderDict::WAIT_SERVICE));
            event('ComputeOrderCommission', ['order_id' => $order->order_id, 'order_type' => $order->order_type]);
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
            !empty($order->store_id) ||
            !empty($order->store_id);
        if ($invalid) {
            throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_GRAB');
        }
    }


}
