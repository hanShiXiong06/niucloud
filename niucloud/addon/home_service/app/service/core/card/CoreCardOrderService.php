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

namespace addon\home_service\app\service\core\card;


use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\dict\card\CardOrderLogDict;
use addon\home_service\app\job\card\AfterHomeServiceCardOrderCreate;
use addon\home_service\app\model\card\CardOrder;
use addon\home_service\app\model\card\CardOrderItem;
use addon\home_service\app\model\goods\Card;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CorePayService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 订单
 * Class CoreCardOrderService
 */
class  CoreCardOrderService extends BaseCoreService
{
    private $scene;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CardOrder();
    }

    /**
     * 订单信息
     * @param int $site_id
     * @param int $order_id
     * @return array
     */
    public function orderInfo(int $site_id, int $order_id)
    {
        return $this->model->where([
            ['site_id', '=', $site_id],
            ['order_id', '=', $order_id]
        ])->field('*')->findOrEmpty()->toArray();
    }

    /**
     * 通用获取订单方法
     */
    private function getOrder($orderId)
    {
        $order = $this->model->where('order_id', $orderId)
            ->with(['item' => fn($q) => $q->field('order_id')])
            ->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->item->isEmpty()) throw new CommonException('ORDER_ITEM_NOT_EXIST');
        return $order;
    }

    /**
     * 订单关闭
     * @param $order
     * @return true
     */
    public function close($order) {
        if ($order['order_status'] != CardOrderDict::WAIT_PAY ) throw new CommonException('ORDER_NOT_ALLOW_CLOSE');

        Db::startTrans();
        try {
            //关闭相关的支付
            if ($order['order_status'] == CardOrderDict::WAIT_PAY) {
                (new CorePayService())->closeByTrade($order['site_id'], $order['order_type'], $order['order_id']);
            }

            $order->order_status = CardOrderDict::CLOSE;
            $order->close_time = time();
            $order->is_enable_refund = 0;
            $order->save();

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 订单自动关闭
     * @param int $order_id
     * @return void
     */
    public function autoClose(int $order_id) {
        $order = (new CardOrder())->where([ ['order_id', '=', $order_id ] ])->findOrEmpty();
        if ($order->isEmpty()) return true;
        if ($order->order_status != CardOrderDict::WAIT_PAY) return true;

        try {
            $this->close($order);
            // 添加订单日志
            CoreCardOrderLogService::addLog($order['site_id'], $order_id, CardOrderLogDict::ORDER_OVERTIME, 'system', 0, CardOrderDict::getStatus(CardOrderDict::CLOSE));

            // 发送订单关闭通知
            (new NoticeService())->send($order['site_id'], 'home_service_card_order_auto_close', [ 'order_id' => $order['order_id'] ]);

            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 待确定删除逻辑之后再细化
     * @param $order_ids
     * @return bool|void
     */
    public function delete($order_ids, $site_id)
    {
        if (empty($order_ids)) throw new CommonException('HOME_SERVICE_ORDER_NOT_FOUND');
        $status_list = $this->model->field('order_id, order_status,order_no,create_time')->whereIn('order_id', $order_ids)->with(['item' => function ($query) {
            $query->field('site_id, order_id, goods_id');
        }])->select()->toArray();

        $error_order_str = '';
        foreach ($status_list as $item) {
            if ($item['order_status'] == CardOrderDict::CLOSE) {
                continue;
            }
            $error_order_str .= $item['order_no'] . ',';
        }
        $error_order_str = rtrim($error_order_str, ',');
        if (!empty($error_order_str)) {
            $error_str = sprintf(get_lang('HOME_SERVICE_ORDER_DELETE_STATUS_ERROR'), $error_order_str);
            throw new CommonException($error_str);
        }

        Db::startTrans();
        try {
            $this->model->whereIn('order_id', $order_ids)->update(['delete_time' => time()]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 订单关闭  (会员 + 总平台)
     * @param array $data
     * @return bool
     */
    public function orderClose(array $data)
    {
        $order = $this->getOrder($data['order_id']);
        if (!in_array($order->order_status, [CardOrderDict::WAIT_PAY])) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_CLOSE');
        Db::startTrans();
        try {
            $order->order_status = CardOrderDict::CLOSE;
            $order->close_time = time();
            $order->save();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }
}
