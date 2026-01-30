<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址:https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\admin\order;

use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderGoodsDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\order\OrderLog;
use addon\phone_shop\app\service\core\goods\CoreGoodsStockService;
use addon\phone_shop\app\service\core\goods\CoreGoodsSyncService;
use addon\phone_shop\app\service\core\order\CoreOrderEventService;
use app\model\sys\SysUser;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 线下订单服务类
 * 阶段1: 基础线下销售功能
 */
class OfflineOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 创建线下订单
     * @param array $data
     * @return array
     * @throws CommonException
     */
    public function create(array $data): array
    {
        // 1. 参数校验
        $this->validateParams($data);

        Db::startTrans();
        try {
            // 2. 获取商品SKU列表信息
            $skuList = $this->getSkuList($data['goods_list']);

            // 3. 校验可用库存(总库存 - 已锁定库存)
            $this->checkAvailableStock($skuList, $data['goods_list']);

            // 4. 计算订单金额
            $priceInfo = $this->calculateOrderPrice($skuList, $data['goods_list']);

            // 5. 确定订单状态
            $orderStatus = $this->determineOrderStatus($data['pay_status']);

            // 6. 生成订单数据
            $orderData = $this->buildOrderData($data, $priceInfo, $orderStatus);

            // 7. 创建订单主表
            $orderId = $this->model->insertGetId($orderData);

            // 8. 创建订单商品项(支持多商品)
            $this->createOrderGoods($orderId, $data, $skuList, $priceInfo);

            // 9. 处理库存(根据支付状态)
            $this->handleStock($data['pay_status'], $skuList, $data['goods_list']);

            // 10. 记录操作日志
            $this->addOrderLog($orderId, $data, $priceInfo);

            // 11. 触发订单创建事件
            // 将order_id添加到orderData中，供事件监听器使用
            $orderData['order_id'] = $orderId;
            CoreOrderEventService::orderCreateAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'member_id' => $data['member_id'],
                'order_data' => $orderData,
                'order_goods_data' => [], // 线下订单不需要处理购物车商品
                'cart_ids' => [],
                'basic' => [
                    'discount_money' => 0,
                    'delivery_money' => 0,
                    'goods_money' => $priceInfo['goods_money'],
                    'order_money' => $priceInfo['order_money'],
                    'invoice' => [], // 线下订单暂不支持发票
                ],
                'main_type' => OrderLogDict::STORE,
                'main_id' => $this->uid,
                'time' => time()
            ]);

            Db::commit();

            return [
                'order_id' => $orderId,
                'order_no' => $orderData['order_no'],
                'order_money' => $priceInfo['order_money'],
                'pay_status' => $data['pay_status']
            ];

        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 参数校验
     */
    private function validateParams(array $data): void
    {
        if (empty($data['member_id'])) {
            throw new CommonException('请选择会员');
        }
        if (empty($data['goods_list']) || !is_array($data['goods_list'])) {
            throw new CommonException('商品列表不能为空');
        }
        if (!in_array($data['pay_status'], ['paid', 'unpaid', 'hold'])) {
            throw new CommonException('支付状态参数错误');
        }

        // 校验每个商品项
        foreach ($data['goods_list'] as $goods) {
            if (empty($goods['goods_id']) || empty($goods['sku_id'])) {
                throw new CommonException('商品信息不完整');
            }
            if (empty($goods['num']) || $goods['num'] <= 0) {
                throw new CommonException('商品数量必须大于0');
            }
        }

        // 已付款时必须选择收款账户
        if ($data['pay_status'] === 'paid' && empty($data['offline_pay_account'])) {
            throw new CommonException('请选择收款账户');
        }
    }

    /**
     * 获取SKU列表信息
     */
    private function getSkuList(array $goodsList): array
    {
        $skuIds = array_column($goodsList, 'sku_id');
        $skus = GoodsSku::whereIn('sku_id', $skuIds)
            ->where([['site_id', '=', $this->site_id]])
            ->select()
            ->toArray();

        // 转换为以sku_id为键的数组
        $skuList = [];
        foreach ($skus as $sku) {
            $skuList[$sku['sku_id']] = $sku;
        }

        // 验证所有SKU都存在
        foreach ($goodsList as $goods) {
            if (!isset($skuList[$goods['sku_id']])) {
                throw new CommonException('商品SKU不存在');
            }
        }

        return $skuList;
    }

    /**
     * 检查可用库存(总库存 - 已锁定库存)
     */
    private function checkAvailableStock(array $skuList, array $goodsList): void
    {
        foreach ($goodsList as $goods) {
            $sku = $skuList[$goods['sku_id']];
            $lockedStock = $sku['locked_stock'] ?? 0;
            $availableStock = $sku['stock'] - $lockedStock;

            if ($availableStock < $goods['num']) {
                throw new CommonException(
                    "商品【{$sku['sku_name']}】库存不足。总库存：{$sku['stock']}，已锁定：{$lockedStock}，可用：{$availableStock}，需要：{$goods['num']}"
                );
            }
        }
    }

    /**
     * 计算订单总金额(支持多商品)
     */
    private function calculateOrderPrice(array $skuList, array $goodsList): array
    {
        $totalGoodsMoney = 0;
        $goodsDetails = [];

        foreach ($goodsList as $goods) {
            $sku = $skuList[$goods['sku_id']];

            // 使用自定义价格或商品价格
            $salePrice = isset($goods['sale_price']) && $goods['sale_price'] > 0
                ? $goods['sale_price']
                : $sku['price'];

            $goodsMoney = round($salePrice * $goods['num'], 2);
            $totalGoodsMoney += $goodsMoney;

            $goodsDetails[$goods['sku_id']] = [
                'sale_price' => $salePrice,
                'goods_money' => $goodsMoney,
                'cost_price' => $sku['cost_price'] ?? 0,
            ];
        }

        return [
            'goods_money' => $totalGoodsMoney,
            'order_money' => $totalGoodsMoney, // 线下订单无配送费、无优惠
            'goods_details' => $goodsDetails,
        ];
    }

    /**
     * 确定订单状态
     */
    private function determineOrderStatus(string $payStatus): string
    {
        switch ($payStatus) {
            case 'paid':
                return OrderDict::WAIT_DELIVERY; // 已付款,待发货
            case 'unpaid':
                return OrderDict::WAIT_PAY; // 未付款,待支付
            case 'hold':
                return OrderDict::HOLD; // 挂单(商品已取走,待付款)
            default:
                throw new CommonException('未知的支付状态');
        }
    }

    /**
     * 构建订单主表数据
     */
    private function buildOrderData(array $data, array $priceInfo, string $orderStatus): array
    {
        return [
            'site_id' => $this->site_id,
            'order_no' => $this->generateOrderNo(),
            'order_type' => 'hsx_offline',        // 线下订单
            'order_from' => 'admin',          // 后台下单
            'status' => $orderStatus,
            'member_id' => $data['member_id'],
            'body' => '线下销售订单',
            'goods_money' => $priceInfo['goods_money'],
            'delivery_money' => 0,
            'discount_money' => 0,
            'order_money' => $priceInfo['order_money'],
            'pay_money' => $orderStatus === OrderDict::WAIT_DELIVERY ? $priceInfo['order_money'] : 0,
            'pay_type' => 'hsx_offlinepay',       // 线下支付
            'offline_pay_account' => $data['offline_pay_account'] ?? '',
            'create_time' => time(),
            'pay_time' => $orderStatus === OrderDict::WAIT_DELIVERY ? time() : 0,
            'shop_remark' => $data['remark'] ?? '后台线下销售订单',
            'delivery_type' => $data['delivery_type'] ?? 'store',  // 配送方式：store(到店自提) 或 express(物流配送)
            'taker_name' => $data['taker_name'] ?? '',             // 收货人姓名
            'taker_mobile' => $data['taker_mobile'] ?? '',         // 收货人电话
        ];
    }

    /**
     * 创建订单商品项(支持多商品)
     */
    private function createOrderGoods(int $orderId, array $data, array $skuList, array $priceInfo): void
    {
        // 确定配送状态：已付款订单为待发货，未付款订单为空
        $deliveryStatus = ($data['pay_status'] === 'paid') ? OrderDeliveryDict::WAIT_DELIVERY : '';

        foreach ($data['goods_list'] as $goodsItem) {
            $sku = $skuList[$goodsItem['sku_id']];
            $goods = Goods::where('goods_id', $goodsItem['goods_id'])->findOrEmpty();

            if ($goods->isEmpty()) {
                throw new CommonException('商品不存在');
            }

            $priceDetail = $priceInfo['goods_details'][$goodsItem['sku_id']];

            $orderGoods = new OrderGoods();
            $orderGoods->save([
                'site_id' => $this->site_id,
                'order_id' => $orderId,
                'member_id' => $data['member_id'],
                'goods_id' => $goodsItem['goods_id'],
                'sku_id' => $goodsItem['sku_id'],
                'sku_no' => $sku['sku_no'],
                'goods_name' => $goods->goods_name,
                'sku_name' => $sku['sku_name'],
                'goods_image' => $goods->goods_cover,
                'sku_image' => $sku['sku_image'],
                'price' => $priceDetail['sale_price'],
                'original_price' => $sku['price'],
                'cost_price' => $priceDetail['cost_price'],
                'num' => $goodsItem['num'],
                'goods_money' => $priceDetail['goods_money'],
                'goods_type' => $goods->goods_type,
                'discount_money' => 0,
                'status' => OrderGoodsDict::NORMAL,
                'delivery_status' => $deliveryStatus,  // 设置配送状态
                'is_enable_refund' => ($data['pay_status'] === 'paid') ? 1 : 0,  // 已付款才能退款
            ]);
        }
    }

    /**
     * 处理库存(扣减或锁定)
     */
    private function handleStock(string $payStatus, array $skuList, array $goodsList): void
    {
        $coreGoodsStockService = new CoreGoodsStockService();

        foreach ($goodsList as $goods) {
            $skuId = $goods['sku_id'];
            $goodsId = $goods['goods_id'];
            $num = $goods['num'];

            switch ($payStatus) {
                case 'paid':
                    // 已付款: 使用CoreGoodsStockService减少库存(同时更新goods和goods_sku表)
                    \think\facade\Log::write("线下订单-已付款: 扣减库存 goods_id={$goodsId}, sku_id={$skuId}, num={$num}");
                    $coreGoodsStockService->dec([
                        'num' => $num,
                        'goods_id' => $goodsId,
                        'sku_id' => $skuId
                    ]);
                    // 检查库存并同步下架
                    $this->checkAndSyncGoodsOffline($skuId, $goodsId, $num);
                    break;

                case 'unpaid':
                    // 未付款: 只锁定SKU库存(商品还在店里)
                    \think\facade\Log::write("线下订单-未付款: 锁定库存 sku_id={$skuId}, num={$num}");
                    GoodsSku::where('sku_id', $skuId)
                        ->inc('locked_stock', $num)
                        ->update();
                    break;

                case 'hold':
                    // 挂单: 扣减实际库存(商品已离开店铺)
                    \think\facade\Log::write("线下订单-挂单: 扣减库存 goods_id={$goodsId}, sku_id={$skuId}, num={$num}");
                    $coreGoodsStockService->dec([
                        'num' => $num,
                        'goods_id' => $goodsId,
                        'sku_id' => $skuId
                    ]);
                    // 检查库存并同步下架
                    $this->checkAndSyncGoodsOffline($skuId, $goodsId, $num);
                    break;
            }
        }
    }

    /**
     * 检查库存并同步商品下架状态
     * @param int $skuId SKU ID
     * @param int $goodsId 商品ID
     * @param int $num 购买数量
     */
    private function checkAndSyncGoodsOffline(int $skuId, int $goodsId, int $num): void
    {
        // 重新查询SKU的最新库存
        $sku = GoodsSku::find($skuId);
        if (!$sku) {
            return;
        }

        // 如果库存为0或不足，下架商品并同步
        if ($sku->stock <= 0) {
            $goods = Goods::find($goodsId);
            if ($goods && $goods->status != '0') {
                // 下架商品
                $goods->status = '0';
                $goods->save();
                \think\facade\Log::write("线下订单-商品下架: goods_id={$goodsId}, goods_no={$goods->goods_no}, 库存不足");

                // 同步下架到其他站点
                if (!empty($goods->goods_no)) {
                    $syncService = new CoreGoodsSyncService();
                    $syncService->syncGoodsOffline((string)$goods->goods_no, $this->site_id);
                }
            }
        }
    }

    /**
     * 记录订单日志
     */
    private function addOrderLog(int $orderId, array $data, array $priceInfo): void
    {
        $payStatusText = [
            'paid' => '已付款',
            'unpaid' => '未付款',
            'hold' => '挂单',
        ];

        // 查询操作人姓名
        $operator = SysUser::where('uid', $this->uid)->find();
        $operatorName = $operator ? $operator->username : '未知操作员';

        // 根据支付状态决定是否显示收款账户
        $paymentInfo = '';
        if ($data['pay_status'] === 'paid') {
            $paymentInfo = sprintf('，收款账户：%s', $data['offline_pay_account'] ?? '无');
        }

        $orderLog = new OrderLog();
        $orderLog->save([
            'site_id' => $this->site_id,
            'order_id' => $orderId,
            'action' => '后台线下销售',
            'main_type' => OrderLogDict::STORE,
            'main_id' => $this->uid, // 记录具体操作人ID
            'create_time' => time(),
            'content' => sprintf(
                '操作人：%s，状态：%s，商品数：%d，订单金额：%.2f%s',
                $operatorName,
                $payStatusText[$data['pay_status']],
                count($data['goods_list']),
                $priceInfo['order_money'],
                $paymentInfo
            ),
            'remark' => sprintf(
                '操作员ID：%d，状态：%s，商品数：%d，订单金额：%.2f，收款账户：%s',
                $this->uid,
                $payStatusText[$data['pay_status']],
                count($data['goods_list']),
                $priceInfo['order_money'],
                $data['offline_pay_account'] ?? '无'
            ),
        ]);
    }

    /**
     * 生成订单号
     */
    private function generateOrderNo(): string
    {
        return 'OFF' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * 挂单确认收款 - 逐步推进订单状态
     * 用于财务人员确认挂单客户已付款，并逐步推进订单状态
     *
     * 状态流转：
     * 1. HOLD(10) → WAIT_DELIVERY(2) - 确认收款
     * 2. WAIT_DELIVERY(2) → WAIT_TAKE(3) - 确认发货
     * 3. WAIT_TAKE(3) → FINISH(5) - 确认收货
     *
     * @param array $data
     * @return array
     * @throws CommonException
     */
    public function confirmHoldOrderPayment(array $data): array
    {
        // 1. 参数校验
        if (empty($data['order_id'])) {
            throw new CommonException('订单ID不能为空');
        }

        // 2. 查询订单
        $order = $this->model->where([
            ['order_id', '=', $data['order_id']],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if ($order->isEmpty()) {
            throw new CommonException('订单不存在');
        }

        Db::startTrans();
        try {
            $currentStatus = $order->status;
            $newStatus = null;
            $logAction = '';
            $logContent = '';

            // 3. 根据当前状态决定下一步操作
            switch ($currentStatus) {
                case OrderDict::HOLD:
                    // 挂单 → 已支付(待发货)
                    if (empty($data['pay_type'])) {
                        throw new CommonException('请选择支付方式');
                    }
                    if (empty($data['offline_pay_account'])) {
                        throw new CommonException('请选择收款账户');
                    }

                    $newStatus = OrderDict::WAIT_DELIVERY;
                    $logAction = '确认收款';

                    // 计算新的订单总金额（如果传递了order_goods数组）
                    $newOrderMoney = null;
                    if (!empty($data['order_goods']) && is_array($data['order_goods'])) {
                        $newOrderMoney = 0;
                        foreach ($data['order_goods'] as $goodsItem) {
                            // 累加商品金额（只累加未删除的商品）
                            if (isset($goodsItem['goods_money']) && (!isset($goodsItem['is_deleted']) || $goodsItem['is_deleted'] == 0)) {
                                $newOrderMoney += floatval($goodsItem['goods_money']);
                            }
                        }
                    }

                    // 更新订单支付信息
                    $order->status = $newStatus;
                    $order->pay_time = time();
                    $order->pay_type = $data['pay_type'];
                    $order->offline_pay_account = $data['offline_pay_account'];

                    // 如果计算了新的订单金额，则更新订单金额
                    if ($newOrderMoney !== null && $newOrderMoney > 0) {
                        $order->goods_money = $newOrderMoney;
                        $order->order_money = $newOrderMoney;
                        $order->pay_money = $newOrderMoney;
                    } else {
                        $order->pay_money = $order->order_money;
                    }

                    $order->save();

                    // 更新订单商品的配送状态、退款权限、价格和删除状态
                    if (!empty($data['order_goods']) && is_array($data['order_goods'])) {
                        foreach ($data['order_goods'] as $goodsItem) {
                            $updateData = [
                                'delivery_status' => OrderDeliveryDict::WAIT_DELIVERY,
                                'is_enable_refund' => 1
                            ];

                            // 如果传递了价格，则更新价格
                            if (isset($goodsItem['price'])) {
                                $updateData['price'] = $goodsItem['price'];
                            }

                            // 如果传递了删除状态，则更新删除状态
                            if (isset($goodsItem['is_deleted'])) {
                                $updateData['is_deleted'] = $goodsItem['is_deleted'];
                            }

                            // 如果传递了商品金额，则更新商品金额
                            if (isset($goodsItem['goods_money'])) {
                                $updateData['goods_money'] = $goodsItem['goods_money'];
                            }

                            OrderGoods::where('order_goods_id', $goodsItem['order_goods_id'])
                                ->update($updateData);
                        }
                    } else {
                        // 如果没有传递order_goods数组，则使用原来的批量更新方式
                        OrderGoods::where('order_id', $data['order_id'])
                            ->update([
                                'delivery_status' => OrderDeliveryDict::WAIT_DELIVERY,
                                'is_enable_refund' => 1
                            ]);
                    }

                    $logContent = sprintf(
                        '财务确认收款，支付方式：%s，收款账户：%s，订单金额：%.2f元',
                        $data['pay_type'],
                        $data['offline_pay_account'],
                        $order->order_money
                    );
                    break;
                    // 已发货 → 已完成
                    $newStatus = OrderDict::FINISH;
                    $logAction = '确认收货';

                    $order->status = $newStatus;
                    $order->finish_time = time();
                    $order->save();

                    $logContent = '财务确认收货，订单已完成';
                    break;

                default:
                    throw new CommonException('当前订单状态不支持此操作');
            }

            // 4. 记录操作日志
            $operator = SysUser::where('uid', $this->uid)->find();
            $operatorName = $operator ? $operator->username : '未知操作员';

            $orderLog = new OrderLog();
            $orderLog->save([
                'site_id' => $this->site_id,
                'order_id' => $data['order_id'],
                'action' => $logAction,
                'main_type' => OrderLogDict::STORE,
                'main_id' => $this->uid,
                'create_time' => time(),
                'content' => sprintf('操作人：%s，%s', $operatorName, $logContent),
                'remark' => sprintf(
                    '操作员ID：%d，操作员：%s，操作时间：%s，%s',
                    $this->uid,
                    $operatorName,
                    date('Y-m-d H:i:s'),
                    $logContent
                ),
            ]);

            Db::commit();

            return [
                'order_id' => $data['order_id'],
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
                'action' => $logAction,
                'message' => $logContent
            ];

        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取线下订单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $search_model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['order_type', '=', 'offline']
        ])->with([
            'orderGoods' => function($query) {
                $query->field('order_id,goods_id,sku_id,sku_no,goods_name,sku_name,price,num,goods_money,cost_price');
            },
            'member' => function($query) {
                $query->field('member_id,username,nickname,mobile');
            }
        ])->order('create_time desc');

        // 订单号搜索
        if (!empty($where['order_no'])) {
            $search_model->where('order_no', 'like', '%' . $where['order_no'] . '%');
        }

        // 会员搜索
        if (!empty($where['member_id'])) {
            $search_model->where('member_id', $where['member_id']);
        }

        // 支付状态搜索
        if (!empty($where['pay_status'])) {
            if ($where['pay_status'] === 'paid') {
                $search_model->where('status', OrderDict::WAIT_DELIVERY);
            } else {
                $search_model->where('status', OrderDict::WAIT_PAY);
            }
        }

        // 时间搜索
        if (!empty($where['create_time']) && is_array($where['create_time'])) {
            $search_model->whereBetweenTime('create_time', $where['create_time'][0], $where['create_time'][1]);
        }

        return $this->pageQuery($search_model);
    }
}
