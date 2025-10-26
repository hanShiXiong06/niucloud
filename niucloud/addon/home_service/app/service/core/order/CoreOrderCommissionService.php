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

namespace addon\home_service\app\service\core\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\service\core\store\CoreStoreService;
use addon\home_service\app\service\core\technician\CoreTechnicianService;
use addon\home_service\app\service\core\account\CoreStoreAccountService;
use addon\home_service\app\service\core\account\CoreTechnicianAccountService;
use addon\home_service\app\service\core\order\CoreOrderService;


/**
 * 佣金
 * Class
 */
class  CoreOrderCommissionService extends BaseCoreService
{

    use SubStatusTrait;

    private $scene;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 订单佣金计算
     * @param array $data
     */
    public function computeOrderCommission(array $data)
    {
        $order = $this->model->where('order_id', $data['order_id'])->find();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if (!in_array($order->order_status, [OrderDict::WAIT_SERVICE, OrderDict::WAIT_DISPATCH])) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        $order_item = (new OrderItem())->where([['order_id', '=', $data['order_id']], ['item_id', '>', 0]])->findOrEmpty();
        Db::startTrans();
        try {
            $store_commission = 0;
            $technician_commission = 0;
            if ($order->store_id > 0) {
                $store_info = (new CoreStoreService)->getInfo($order->store_id);
                $store_ratio = $store_info['service_ratio'];
                $store_commission = bcmul($order->order_money, bcmul($store_ratio, 0.01, 2), 2);
            }
            if ($order->technician_id > 0) {
                $technician_ratio = (new  CoreTechnicianService)->getTechnicianRate($order->site_id, $order->technician_id, $order->store_id);
                $compute_money = !empty($store_commission) ? $store_commission : $order->order_money;
                $technician_commission = bcmul($compute_money, bcmul($technician_ratio, 0.01, 2), 2);
                if ($order->store_id > 0 && isset($store_commission)) {
                    $store_commission = bcsub($store_commission, $technician_commission, 2);
                }
            }
            $order->store_ratio = $store_ratio ?? 0.00;
            $order->store_commission = $store_commission ?? 0.00;
            $order->technician_ratio = $technician_ratio ?? 0.00;
            $order->technician_commission = $technician_commission ?? 0.00;
            $order->save();
            $order_item->order_itme_commission_ratio = 100.00;
            $order_item->store_ratio = $store_ratio ?? 0.00;
            $order_item->store_commission = $store_commission ?? 0.00;
            $order_item->technician_ratio = $technician_ratio ?? 0.00;
            $order_item->technician_commission = $technician_commission ?? 0.00;
            $order_item->save();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 订单增项佣金计算
     * @param array $data
     */
    public function computeOrderItemCommission(array $data)
    {

        $order = $this->model->where('order_id', $data['order_id'])->find();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if (!in_array($order->order_status, [OrderDict::IN_SERVICE])) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        $order_item_list = (new OrderItem())->where([['order_item_id', 'in', array_column($data['order_item_list'], 'order_item_id')]])->select()->toArray();
        if (empty($order_item_list) || count($order_item_list) != count($order_item_list)) throw new CommonException('HOME_SERVICE_ORDER_ADDED_ITEM_NOT_EXIST');

        $order_item_model = new OrderItem();
        Db::startTrans();
        try {
            $store_additional_commission = 0.00;
            $technician_additional_commission = 0.00;
            foreach ($order_item_list as $value) {
                $store_commission = 0.00;
                $technician_commission = 0.00;
                if ($order->store_id > 0) {
                    $store_info = (new CoreStoreService)->getInfo($order->store_id);
                    $store_ratio = $store_info['service_ratio'];
                    $store_commission = bcmul(bcmul($value['item_money'], bcmul($value['order_itme_commission_ratio'], 0.01, 2), 2), bcmul($store_ratio, 0.01, 2), 2);
                }
                if ($order->technician_id > 0) {
                    $technician_ratio = (new  CoreTechnicianService)->getTechnicianRate($order->site_id, $order->technician_id, $order->store_id);

                    if ($order->store_id > 0){
                        $compute_money = !empty($store_commission) ? $store_commission : $value['item_money'];
                        $technician_commission = bcmul($compute_money, bcmul($technician_ratio, 0.01, 2), 2);
                    }else{
                        $technician_commission = bcmul(bcmul($value['item_money'], bcmul($value['order_itme_commission_ratio'], 0.01, 2), 2), bcmul($technician_ratio, 0.01, 2), 2);
                    }

                    if ($order->store_id > 0 && !empty($store_commission)) {
                        $store_commission = bcsub($store_commission, $technician_commission, 2);
                    }
                }
                $store_additional_commission += $store_commission;
                $technician_additional_commission += $technician_commission;
                $save_data = [
                    'store_ratio' => $store_ratio ?? 0.00,
                    'store_commission' => $store_commission ?? 0.00,
                    'technician_ratio' => $technician_ratio ?? 0.00,
                    'technician_commission' => $technician_commission ?? 0.00,
                ];
                $order_item_model->where([['order_item_id', '=', $value['order_item_id']]])->update($save_data);
            }
            $order->store_additional_commission += $store_additional_commission;
            $order->technician_additional_commission += $technician_additional_commission;
            $order->save();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 计算师傅佣金
     * 计算金额  $compute_money
     * @param array $data
     * todo 合并上面业务
     *
     */
    public function computeTechnicianCommission($site_id, $technician_id, $store_id, $compute_money = 0)
    {
        $technician_ratio = (new  CoreTechnicianService)->getTechnicianRate($site_id, $technician_id, $store_id);
        $technician_commission = bcmul($compute_money, bcmul($technician_ratio, 0.01, 2), 2);
        return $technician_commission;
    }


    /**
     * 订单预结算计算
     * @param array $data
     */
    public function preSettlementOrderCommission(array $data)
    {
        $order = $this->model->where('order_id', $data['order_id'])->find();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != OrderDict::WAIT_CHECK) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        if ($order->store_commission > 0) {
            $res = (new CoreStoreAccountService)->orderCommissGrant($order);
        }
        if ($order->technician_commission > 0) {
            $res = (new CoreTechnicianAccountService)->orderCommissGrant($order);
        }
        return true;
    }


    /**
     * 订单结算
     * @param array $data
     */
    public function settlementOrderCommission($data)
    {
        $order = $this->model->where('order_id', $data['order_id'])->find();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != OrderDict::FINISH) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        if ($order->store_commission > 0) (new CoreStoreAccountService)->orderCommissRelease($order);
        if ($order->technician_commission > 0) (new CoreTechnicianAccountService)->orderCommissRelease($order);
        (new CoreOrderService)->orderSettlement($order->order_id);
        return true;
    }


}
