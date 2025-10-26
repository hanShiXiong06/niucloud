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
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\model\order\OrderRefund;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\service\core\store\CoreStoreService;
use addon\home_service\app\service\core\technician\CoreTechnicianService;
use addon\home_service\app\service\core\account\CoreStoreAccountService;
use addon\home_service\app\service\core\account\CoreTechnicianAccountService;

/**
 * 维权佣金
 * Class CoreOrderService
 */
class  CoreOrderRefundCommissionService extends BaseCoreService
{

    use SubStatusTrait;

    private $scene;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 退款订单佣金计算
     * @param array $data
     */
    public function ComputeOrderRefundCommission($data) {
        $order = $this->model->where('order_id', $data['order_id'])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        $order_refund = (new OrderRefund())->where([['refund_id', '=', $data['refund_id']]])->findOrEmpty();
        if ($order_refund->isEmpty()) throw new CommonException('REFUND_NOT_EXIST');
        $order_item_model = (new OrderItem());
        $order_item_list = $order_item_model->where([['order_id', '=', $data['order_id']]])->order('order_item_id asc')->select()->toArray();
        if (empty($order_item_list)) throw new CommonException('ORDER_ITEM_NOT_EXIST');
        $refundTo_customer = number_format((float)$order_refund->money, 2, '.', '');
        $techRatio = number_format((float)bcmul($order->technician_ratio, 0.01, 2), 2, '.', '');
        $core_order_config_service = new CoreOrderConfigService();
        $refund_expect_revenue_rate = $core_order_config_service->getOrderRefundConfig($order->site_id)['order_refund']['refund_expect_revenue_rate'] ?? '';
        $platform_hold = number_format((float)bcmul($refund_expect_revenue_rate, 0.01, 2), 2, '.', '');


        Db::startTrans();
        try {
            //总退款
            $remain_refund = $refundTo_customer;
            $order_store_commission = $order_technician_commission = $order_item_store_commission = $order_item_technician_commission = 0;
            foreach ($order_item_list as $it) {
                $item_money = number_format((float)$it['item_money'], 2, '.', '');
                $commission_ratio = isset($it['order_itme_commission_ratio']) ? number_format((float)bcmul($it['order_itme_commission_ratio'], 0.01, 2), 2, '.', '') : '1';
                if($remain_refund>=$item_money){
                    $item_remaining=0;
                    $remain_refund= bcsub($remain_refund, $item_money, 2);
                }else{
                    // 剩余金额
                    $item_remaining = bcsub($item_money, $remain_refund, 2);
                    $remain_refund = 0;
                }
                // 佣金基数
                $commission_total = bcmul($item_remaining, $commission_ratio, 2);
                // 平台保价
                $platformMin = bcmul($commission_total, $platform_hold, 2);
                $s_t_c=bcsub($commission_total, $platformMin, 2);
                if($order->store_id > 0){
                    $technician_commission = bcmul($s_t_c, $techRatio, 2);
                    $store_commission = bcsub($s_t_c, $technician_commission, 2);
                }else{
                    $technician_commission = $s_t_c;
                    $store_commission = '0';
                }
                // 累计
                if ($it['item_id'] == 0){
                    $order_item_store_commission =  bcadd($order_item_store_commission, $store_commission, 2);
                    $order_item_technician_commission = bcadd($order_item_technician_commission, $technician_commission, 2);
                }else{
                    $order_store_commission =  bcadd($order_store_commission, $store_commission, 2);
                    $order_technician_commission = bcadd($order_technician_commission, $technician_commission, 2);
                }
                $update_data = [
                    'store_commission' => $store_commission,
                    'technician_commission' => $technician_commission,
                ];
                $order_item_model->where([['order_item_id', '=', $it['order_item_id']]])->update($update_data);

                $result['items'][] = [
                    'name'=>$it['name']??'',
                    'amount'=>$item_money,
                    'commission_ratio'=>$commission_ratio,
                    'item_remaining'=>$item_remaining,
                    'commission_total'=>$commission_total,
                    'platform_min'=>$platformMin,
                    'platform_get'=>$platformMin,
                    'store_get'=>$store_commission,
                    'tech_get'=>$technician_commission,
                ];
            }
            $order->store_commission = $order_store_commission;
            $order->store_additional_commission = $order_item_store_commission;
            $order->technician_commission = $order_technician_commission;
            $order->technician_additional_commission = $order_item_technician_commission;
            $order->save();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    // bcmin helper
    public function bcmin($a,$b,$scale=2){
        return bccomp($a,$b,$scale)<=0 ? $a : $b;
    }

    /**
     * 获取平台估计金额
     * @param array $data
     * @return void
     */
    public function getPlatformExpectMoney(array $data)
    {
        if (empty($data['money'])) throw new CommonException('HOME_SERVICE_REFUND_MONEY_GT_ZERO');

        $core_order_config_service = new CoreOrderConfigService();
        $refund_expect_revenue_rate = $core_order_config_service->getOrderRefundConfig($this->site_id)['order_refund']['refund_expect_revenue_rate'] ?? '';
        if ($refund_expect_revenue_rate <= 0) return $data['money'];
        return bcmul($data['money'], bcmul($refund_expect_revenue_rate, 0.01, 2), 2);

    }

    /**
     * 订单结算
     * @param array $data
     */
    public function settlementOrderCommission($data)
    {
        $order = $this->model->where('order_id', $data['order_id'])->find();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        $order_refund = (new OrderRefund())->where([['refund_id', '=', $data['refund_id']]])->findOrEmpty();
        if ($order_refund->isEmpty()) throw new CommonException('REFUND_NOT_EXIST');
        if ($order->refund_status != RefundDict::REFUND_COMPLETED && !in_array($order->order_status,[OrderDict::IN_SERVICE,OrderDict::WAIT_CHECK])) throw new CommonException('HOME_SERVICE_ORDER_REFUND_IS_NOT_SETTLEMENT');
        if (bcadd($order->store_commission, $order->store_additional_commission, 2) > 0){
            (new CoreStoreAccountService)->orderCommissGrant($order,0,true);
            (new CoreStoreAccountService)->orderCommissRelease($order,true);
        }
        if (bcadd($order->technician_commission, $order->technician_additional_commission, 2) > 0){
            (new CoreTechnicianAccountService)->orderCommissGrant($order,true);
            (new CoreTechnicianAccountService)->orderCommissRelease($order,true);
        }
        return true;
    }


}
