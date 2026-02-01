<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\fast_pay\app\service\api\order;

use addon\fast_pay\app\dict\order\OrderDict;
use addon\fast_pay\app\model\order\Order;
use addon\fast_pay\app\service\core\CorePayService;
use app\dict\pay\PayDict;
use core\base\BaseApiService;
use core\exception\CommonException;
use Exception;
use think\facade\Db;


class OrderService extends BaseApiService
{

    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new Order();
    }

    /**
     * @Notes:生成订单
     * @Interface create
     * @param $data
     * @return array
     * @author: TK
     * @Time: 2024/8/7   上午9:01
     */
    public function create($data)
    {
        Db::startTrans();
        try {
            $info = [
                "site_id" => $this->site_id,
                "member_id" => $this->member_id,
                'ip' => request()->ip() ?? '',
                "order_from" => $this->channel,
                "order_id" => create_no(),
                "order_money" => $data['price'],
                "remark" => $data['remark'],
                "business_id" => $data['business_id'] ?? 0
            ];;
            $res = $this->orderModel->create($info);
            //添加订单支付表
            (new CorePayService())->create($this->site_id, PayDict::MEMBER, $this->member_id, $res['order_money'], OrderDict::getOrderType()['type'], $res['id'], OrderDict::getOrderType()['name']);
            Db::commit();
            return [
                'trade_type' => OrderDict::getOrderType()['type'],
                'trade_id' => $res['id']
            ];
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * @Notes:订单列表
     * @Interface getPage
     * @param array $where
     * @return array
     * @throws \think\db\exception\DbException
     * @author: TK
     * @Time: 2024/8/7   上午9:01
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,order_status,refund_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time';
        $order = 'create_time desc';
        $search_model = $this->orderModel->where([['site_id', "=", $this->site_id]])->withSearch(["member_id", "order_from", "order_id", "out_trade_no", "pay_time", "is_enable_refund", "close_time", "ip", "update_time", "delete_time"], $where)->with(['member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }
}
