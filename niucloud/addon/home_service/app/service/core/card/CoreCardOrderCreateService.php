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
use addon\home_service\app\service\core\strategy\CoreCityStrategyService;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CorePayService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 订单
 * Class CoreCardOrderCreateService
 */
class  CoreCardOrderCreateService extends BaseCoreService
{
    private $scene;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CardOrder();
    }

    /**
     * 订单确认
     * @param array $data
     * @return void
     */
    public function confirm(array $data) {
        $this->scene = 'confirm';
        return $this->calculate($data);
    }

    /**
     * 订单确认
     * @param array $data
     * @return array
     */
    public function create(array $data) {
        $this->scene = 'create';
        $card_info = $this->getGoodsInfo($data['site_id'], $data['card_id']);

        //先走策略业务
        $card_info['price'] = (new  CoreCityStrategyService())->getStrategyPrice($card_info['price'], $data['city_id'] ?? 0, $data['site_id']);

        $original_price = 0;
        foreach($card_info['skuList'] as &$value){
            $original_price += $value['original_price'];
        }
        $card_info['original_price'] =  number_format($original_price, 2, '.', '');

        Db::startTrans();
        try {
            $order_data = [
                'site_id' => $data['site_id'],
                'member_id' => $data['member_id'],
                'card_id' => $card_info['card_id'],
                'order_from' => $data['order_from'] ?? 'h5',
                'order_type' => 'home_service_card',
                'order_no' => create_no(),
                'order_status' => CardOrderDict::WAIT_PAY,
                'ip' => request()->ip(),
                'member_message' => '',
                'order_money' => $card_info['price'],
                'pay_money' => $card_info['price'],
                'original_price' => $card_info['original_price'],
                'create_time' => time(),
                'auto_close_time' => (time() + 120 * 60),
                'order_name' => $card_info['card_name']
            ];

            $create_order = $this->model->create($order_data);
            $order_id = $create_order->order_id;
            $order_data['order_id'] = $order_id;
            //添加订单项目表
            $order_item_model = new CardOrderItem();
            foreach ($card_info['skuList'] as $k => $item)
            {
                $item_data = [
                    'order_id' => $order_id,
                    'site_id' => $data['site_id'],
                    'member_id' => $data['member_id'],
                    'card_id' => $item['card_id'],
                    'card_sku_id' => $item['sku_id'],
                    'goods_id' => $item['goods_id'],
                    'goods_sku_id' => $item['goods_sku_id'],
                    'goods_sku_name' => $item['sku_name'],
                    'goods_sku_image' => $item['sku_image'],
                    'price' => $item['price'],
                    'num' => 1,
                    'item_money' => $item['price'],
                    'max_use_times' => $item['max_use_times'],
                    'original_price' => $item['original_price'],
                    'sku_unit' => $item['sku_unit'],
                    'valid_type' => $card_info['valid_type'],
                ];
                $order_item_model->create($item_data);
            }
            //创建支付单据  置后
//            (new CorePayService())->create($data['site_id'], PayDict::MEMBER, $data['member_id'], $data['pay_money'], 'vipcard', $order_id, $data['goods'][0]['goods_name']);

            // 添加订单超时关闭任务
             AfterHomeServiceCardOrderCreate::dispatch(['site_id' => $data['site_id'], 'order_id' => $order_id, 'order_data' => $order_data, 'time' => time()]);

            Db::commit();

            if ($order_data['order_money'] == 0){
                $this->pay(['trade_id' => $order_id]);
            }

            //返回订单信息
            return [
                'trade_type' => 'home_service_card',
                'trade_id' => $order_id
            ];
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage().$e->getLine());
        }
    }

    /**
     * 查询商品信息
     * @param int $site_id
     * @param int $card_id
     * @return void
     */
    private function getGoodsInfo(int $site_id, int $card_id) {
        $card = (new Card())->where([
            ['site_id', '=', $site_id ],
            ['card_id', '=', $card_id ],
            ['status', '=', 1]
        ])->field('site_id,card_id,card_name,price,original_price,valid_type')
            ->with(['skuList'])
            ->findOrEmpty();
        if ($card->isEmpty()) throw new CommonException('HOME_SERVICE_CARD_NOT_EXIST');

        return $card->toArray();
    }

    /**
     * 支付成功
     * @param array $pay_info
     * @return void
     */
    public function pay(array $pay_info) {
        $order = (new CardOrder())->where([ ['order_id', '=', $pay_info['trade_id'] ] ])->with(['item'])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('HOME_SERVICE_CARD_ORDER_EXPIRE');
        if ($order->order_status != CardOrderDict::WAIT_PAY) throw new CommonException('HOME_SERVICE_CARD_ORDER_PAID');

        Db::startTrans();
        try {
            // 修改订单
            $order->order_status = CardOrderDict::FINISH;
            $order->pay_time = time();
            $order->is_enable_refund = 1;
            $order->out_trade_no = $pay_info['out_trade_no'] ?? '';
            $order->save();

            // 生成卡项
            CoreMemberCardService::create($order->item);

            // 添加订单日志
            CoreCardOrderLogService::addLog($order['site_id'], $order['order_id'], CardOrderLogDict::ORDER_PAY, 'member', $order['member_id'], CardOrderDict::getStatus(CardOrderDict::FINISH));

//            // 发送支付成功通知
//            (new NoticeService())->send($order['site_id'], 'home_service_order_pay', ['order_id' => $order['order_id'] ]);

            // 增加销量
            (new Card())->where([['site_id', '=', $order->site_id], ['card_id', '=', $order->card_id] ])->inc('sale_num')->update();

            Db::commit();

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 订单关闭
     * @param Order $order
     * @return true
     */
    public function close(Order $order) {
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
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != CardOrderDict::WAIT_PAY) return true;

        try {
            $this->close($order);
            // 添加订单日志
            CoreCardOrderLogService::addLog($order['site_id'], $order_id, CardOrderLogDict::ORDER_OVERTIME, 'system', 0, CardOrderDict::getStatus(CardOrderDict::CLOSE));

            // 发送订单关闭通知
            (new NoticeService())->send($order['site_id'], 'vipcard_order_auto_close', [ 'order_id' => $order['order_id'] ]);

            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 订单信息
     * @param int $site_id
     * @param int $order_id
     * @return array
     */
    public function orderInfo(int $site_id, int $order_id) {
        return (new CardOrder())->where([
            ['site_id', '=', $site_id],
            ['order_id', '=', $order_id]
        ])->field('*')->findOrEmpty()->toArray();
    }
}
