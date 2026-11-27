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

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\dict\delivery\DeliveryDict;
use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderGoodsDict;
use addon\phone_shop\app\model\cart\Cart;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\listener\marketing\ShopNewcomerCalculate;
use addon\phone_shop\app\service\api\marketing\NewcomerService;
use addon\phone_shop\app\service\core\goods\CoreGoodsActivePriceService;
use addon\phone_shop\app\service\core\goods\CoreMemberPriceService;
use addon\phone_shop\app\service\core\marketing\CoreNewcomerService;
use addon\phone_shop\app\service\core\member\ForwardBenefitService;
use app\dict\member\MemberDict;
use app\model\member\MemberLevel;
use app\service\core\member\CoreMemberService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 *  订单服务层
 */
class CoreOrderCreateService extends BaseCoreService
{

    use CoreOrderCreateTrait;

    /** 购买人是否具备同行身份，与本次商品是否设置会员价无关。 */
    private bool $peerPriceApplied = false;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 订单创建
     * @param array $data
     * @return array
     */
    public function create(array $data)
    {
        //参数赋值
        $this->setParam($data);
        $member_info = ( new CoreMemberService() )->getInfoByMemberId($data[ 'site_id' ], $data[ 'member_id' ], 'status');

        if (empty($member_info)) throw new CommonException('SHOP_ORDER_BUYER_NOT_FOUND');//无效的账号
        if ($member_info[ 'status' ] == MemberDict::OFF) throw new CommonException('SHOP_ORDER_BUYER_LOCKED');//账号被锁定

        $order_key = $this->param[ 'order_key' ] ?? '';
        //获取订单缓存缓存
        $this->getOrderCache($order_key);

        if (empty($this->basic['quote_ready'])) {
            throw new CommonException('订单金额尚未计算完成，请重新确认订单');
        }
        // 客户端金额仅用于核对最后看到的报价，不能作为服务端定价依据。
        $expectedAmount = $this->param['expected_order_money'] ?? '';
        if ($expectedAmount !== '' && (!is_scalar($expectedAmount)
            || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string)$expectedAmount)
            || bccomp((string)$expectedAmount, (string)$this->basic['order_money'], 2) !== 0)) {
            throw new CommonException('订单金额已变化，请重新计算并确认后再提交');
        }

        // 支付方式参与同行手续费计算，最终提交必须与用户最后一次试算一致。
        // 禁止前端切换支付方式后复用旧缓存，造成页面显示金额和落单金额不同。
        $requestedPaymentMode = (string)($this->param['payment_mode'] ?? '');
        $cachedPaymentMode = (string)($this->basic['payment_mode'] ?? '');
        if ($requestedPaymentMode !== '' && $cachedPaymentMode !== ''
            && $requestedPaymentMode !== $cachedPaymentMode) {
            throw new CommonException('支付方式或订单金额已更新，请重新确认订单');
        }

        // 最终创建前再次核对当前商品价。禁止把数小时前的缓存价、已结束的
        // 活动价或其他账号的会员价直接带入支付。
        $this->validateCachedGoodsPrice();


        $this->goods_data = array_merge($this->goods_data, $this->gift_goods);
        $this->form_data = $this->param[ 'form_data' ] ?? []; // 万能表单数据
        //校验错误
        $this->checkError();
        //普通订单校验库存
        $this->checkStock($this->goods_data);
        $local_delivery_type = $data[ 'delivery' ]['local_delivery_type'] ?? '';
        $delivery_type = $this->delivery[ 'delivery_type' ] ?? '';
        $order_data = [
            //订单整体
            'site_id' => $data[ 'site_id' ],
            'order_type' => OrderDict::TYPE,
            'status' => OrderDict::WAIT_PAY,
            'body' => $this->basic[ 'body' ],
            'member_id' => $this->member_id,
            'goods_money' => $this->basic[ 'goods_money' ],
            'delivery_money' => $this->basic[ 'delivery_money' ] ?? 0,
            'discount_money' => $this->basic[ 'discount_money' ] ?? 0,
            'order_money' => $this->basic[ 'order_money' ],
            'base_order_money' => $this->basic['base_order_money'] ?? $this->basic['order_money'],
            'pricing_identity' => $this->basic['pricing_identity'] ?? 'retail',
            'payment_fee_rate' => $this->basic['payment_fee_rate'] ?? 0,
            'payment_fee_bearer' => $this->basic['payment_fee_bearer'] ?? 'merchant',
            'payment_fee_amount' => $this->basic['payment_fee_amount'] ?? 0,
            'merchant_net_amount' => $this->basic['merchant_net_amount'] ?? $this->basic['order_money'],
            'payment_mode' => $this->basic['payment_mode'] ?? 'online',
            'settle_status' => 0,
            'buyer_type' => ($this->basic['pricing_identity'] ?? 'retail') === 'peer' ? 'b' : 'c',
            'has_goods_types' => $this->basic[ 'has_goods_types' ],//包含的商品形式
//            'invoice_id' => $order_array['invoice_id'] ?? 0,

            //收发货相关
            'delivery_type' => $delivery_type,
            'taker_name' => $data[ 'delivery' ][ 'taker_name' ] ?? $this->delivery[ 'take_address' ][ 'name' ] ?? '',
            'taker_mobile' => $data[ 'delivery' ][ 'taker_mobile' ] ?? $this->delivery[ 'take_address' ][ 'mobile' ] ?? '',
            'buyer_ask_delivery_time' => ($local_delivery_type == 'now' && $delivery_type == DeliveryDict::LOCAL_DELIVERY) ? "" :  $data[ 'delivery' ][ 'buyer_ask_delivery_time' ] ?? '', // 购买者期望的时间
            'taker_province' => $this->delivery[ 'take_address' ][ 'province_id' ] ?? 0,
            'taker_city' => $this->delivery[ 'take_address' ][ 'city_id' ] ?? 0,
            'taker_district' => $this->delivery[ 'take_address' ][ 'district_id' ] ?? 0,
            'taker_address' => $this->delivery[ 'take_address' ][ 'address' ] ?? '',
            'taker_full_address' => $this->delivery[ 'take_address' ][ 'full_address' ] ?? '',
            'taker_longitude' => $this->delivery[ 'take_address' ][ 'lng' ] ?? '',
            'taker_latitude' => $this->delivery[ 'take_address' ][ 'lat' ] ?? '',
            'take_store_id' => $this->delivery[ 'take_store' ][ 'store_id' ] ?? 0,

            // 附属信息
            'member_remark' => $this->param[ 'member_remark' ] ?? '',//买家留言
            'relate_id' => $this->extend_data[ 'relate_id' ] ?? 0,//关联id
            'activity_type' => $this->extend_data[ 'activity_type' ] ?? '', // 活动类型

        ];//总

        $order_goods_data = [];//项
        $write_goods_data = array_merge($this->goods_data, $this->impulse_buy_list);
        foreach ($write_goods_data as $v) {
            $order_goods_data[] = [
                'site_id' => $data[ 'site_id' ],
                'member_id' => $data[ 'member_id' ],
                'goods_id' => $v[ 'goods_id' ],
                'sku_id' => $v[ 'sku_id' ],
                'cost_price_snapshot' => round((float)($v['cost_price'] ?? 0), 2),
                'total_cost_snapshot' => round((float)($v['cost_price'] ?? 0) * (int)$v['num'], 2),
                'supplier_id_snapshot' => (int)($v['goods']['supplier_id'] ?? 0),
                'inventory_source' => (int)($v['erp_asset_id'] ?? 0) > 0
                    ? 'erp_asset'
                    : ((int)($v['goods']['supplier_id'] ?? 0) > 0 ? 'supplier' : 'self_owned'),
                'goods_name' => $v[ 'goods' ][ 'goods_name' ],
                'sku_name' => $v[ 'sku_name' ],
                'goods_image' => $v[ 'goods' ][ 'goods_cover' ],
                'sku_image' => $v[ 'sku_image' ],
                'price' => $v[ 'price' ],
                'original_price' => $v[ 'original_price' ] ?? 0,
                'num' => $v[ 'num' ],
                'goods_money' => $v[ 'goods_money' ],
                'goods_type' => $v[ 'goods' ][ 'goods_type' ],
                'order_id' => &$this->order_id,
                'discount_money' => $v[ 'discount_money' ] ?? 0,
                'status' => OrderGoodsDict::NORMAL,
                'extend' => ErpDeviceSnapshot::extend($v['extend'] ?? [], $v, (array)($v['goods'] ?? [])),
                'is_gift' => $v[ 'is_gift' ] ?? 0,
            ];
        }
        $create_order_data = array(
            'order_data' => $order_data,
            'order_goods_data' => $order_goods_data,
        );
        $result = $this->createOrder($create_order_data);
        $result['order_money'] = $order_data['order_money'];
        $result['payment_mode'] = $order_data['payment_mode'];
        return $result;
    }

    /**
     * 整理
     * @param array $data
     * @return void
     */
    public function calculate(array $data)
    {
        //参数赋值
        $this->setParam($data);
        $this->order_key = $this->param[ 'order_key' ] ?? '';
        $is_need_recalculate = $data[ 'is_need_recalculate' ] ?? 0;
        if (empty($this->order_key) || $is_need_recalculate == 1) {
            $this->confirm();
        }
        //获取订单数据的缓存
        $this->getOrderCache($this->order_key . '_basic');
        $basicCache = get_object_vars($this);
        unset($basicCache['param'], $basicCache['order_key']);
        //计算优惠和营销
        $this->calculateDiscount();
        //计算运费
        $this->calculateDelivery();
        //检测限购
        $this->checkGoodsLimitBuy();

        //金额格式化
        $discount_money = $this->moneyFormat($this->basic[ 'discount_money' ] ?? 0);//优惠金额
        $delivery_money = $this->moneyFormat($this->basic[ 'delivery_money' ] ?? 0);
        $goods_money = $this->moneyFormat($this->basic[ 'goods_money' ] ?? 0);
        $order_money = $this->moneyFormat($this->basic[ 'order_money' ] ?? 0);

        $order_money = $this->moneyFormat($this->moneyCalculate($order_money, $delivery_money, $goods_money, -$discount_money));
        $this->basic[ 'discount_money' ] = $discount_money;
        $this->basic[ 'delivery_money' ] = $delivery_money;
        $this->basic[ 'goods_money' ] = $goods_money;
        //todo 校验控制,不能小于0
        $order_money = $order_money < 0 ? 0 : $order_money;
        $this->basic[ 'order_money' ] = $order_money;
        $this->applyOnlineTradePricing();
        $this->basic['quote_ready'] = 1;
        // 每次试算是独立快照。并发切换优惠/配送/支付方式不能覆盖旧报价。
        $this->order_key = create_no('', $this->member_id);
        $this->setOrderCache($this->order_key . '_basic', $basicCache);
//        $this->basic['pay_money'] = $this->basic['pay_money'] ?? $order_money;
        //订单创建数据写入缓存,并将标识返回给前端
        $order_cache = get_object_vars($this);
        unset($order_cache[ 'param' ]);
        $this->setOrderCache($this->order_key, $order_cache);
        return $order_cache;
    }

    /**
     * 订单确认(基础信息查询并缓存)
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function confirm($order_key = '')
    {
        //查看会员信息
        $member_id = $this->param[ 'member_id' ];
        $this->member_id = $member_id;
        $member_info = ( new CoreMemberService() )->getInfoByMemberId($this->site_id, $member_id, 'nickname, headimg, balance, point, member_level,member_label');
        if (empty($member_info)) throw new CommonException('SHOP_ORDER_BUYER_NOT_FOUND');//无效的账号

        // 查询会员等级信息
        $member_info['member_level_id'] = $member_info[ 'member_level' ];
        $member_info[ 'member_level' ] = ( new MemberLevel() )->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'level_id', '=', $member_info[ 'member_level' ] ],
        ])->field('site_id,level_id,level_name,level_no,level_benefits')->findOrEmpty()->toArray();

        // B/C 端身份取决于购买人本身，不能取决于某一件商品是否恰好配置了
        // 固定会员价。否则同一个同行账号购买不同商品时会被识别成两种身份。
        $this->peerPriceApplied = (new ForwardBenefitService())->canUse(
            $this->site_id,
            (int)($member_info['member_level_id'] ?? 0)
        );

        //会员账户信息
        $this->buyer = $member_info;

        $order_config = ( new CoreOrderConfigService() )->getConfig($this->site_id) ?? [];
        $this->form_id = $order_config[ 'form_id' ];

        //查询商品信息
        $this->getGoodsData();
        ///$this->createGoodsData();
        //配送相关信息
        $this->getDelivery();

        $order_cache = get_object_vars($this);
        unset($order_cache[ 'param' ]);
        unset($order_cache[ 'order_key' ]);
        if (empty($order_key)) {
            $order_key = $this->setOrderCache('', $order_cache);
        }
        //将基础订单数据单独存放一个缓存
        $order_basic_key = $order_key . '_basic';
        $this->setOrderCache($order_basic_key, $order_cache);

        $this->order_key = $order_key;
        return true;
    }


    /**
     * 商品相关数据
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getGoodsData()
    {
        $cart_ids = $this->param[ 'cart_ids' ] ?? [];
        $this->extend_data = $this->param[ 'extend_data' ] ?? []; //活动扩展数据：[ 'relate_id' => 1, 'activity_type' => 'giftcard' ]
        if (!empty($cart_ids)) {
            $this->cart_ids = $cart_ids;
            //查询购物车
            $cart = ( new Cart() )->where([ [ 'id', 'in', $cart_ids ], [ 'site_id', '=', $this->site_id ], [ 'member_id', '=', $this->member_id ] ])->field('goods_id, sku_id, num')->select();
            if ($cart->isEmpty()) throw new CommonException('SHOP_ORDER_CARTS_EXPIRE');//无效的数据
            if ($cart->count() != count($cart_ids)) throw new CommonException('SHOP_ORDER_CARTS_EXPIRE');//无效的商品
            $sku_data = $cart->toArray();
        } else {
            $sku_data = $this->param[ 'sku_data' ] ?? [];
            if (empty($sku_data)) throw new CommonException('SHOP_ORDER_CARTS_EXPIRE');//无效的数据
        }
        // 数量必须是正整数；同一 SKU 重复会造成总价累加、明细却被覆盖。
        $seenSkus = [];
        foreach ($sku_data as &$item) {
            $skuId = filter_var($item['sku_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $num = filter_var($item['num'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($skuId === false || $num === false) {
                throw new CommonException('商品或购买数量无效，购买数量必须为正整数');
            }
            if (isset($seenSkus[$skuId])) {
                throw new CommonException('商品重复，请返回购物车刷新后重新选择');
            }
            $seenSkus[$skuId] = true;
            $item['sku_id'] = $skuId;
            $item['num'] = $num;
        }
        unset($item);
        $sku_ids = array_column($sku_data, 'sku_id');
        $sku_condition = array(
            [ 'sku_id', 'in', $sku_ids ],
            [ 'site_id', '=', $this->site_id ]
        );
        $sku_list = ( new  GoodsSku() )->where($sku_condition)->with([ 'goods' ])->field('sku_id, site_id, sku_name, sku_no, sku_image, goods_id, price, cost_price, stock, weight, volume,sku_id, sku_spec_format,member_price, sale_price, erp_asset_id,is_unique,device_snapshot')->select()->toArray();
        foreach ($sku_list as &$value){
            if (empty($value['sku_image']) && isset($value['goods']['goods_cover'])){
                $value['sku_image'] = $value['goods']['goods_cover'];
            }
        }
        $sku_list = array_column($sku_list, null, 'sku_id');
        //商品数据  查询商品列表
        $order_data = [];
        $goods_list = [];
        $order_money = $goods_money = $delivery_money = 0;
        $total_num = 0;
        $body = '';
        //订单中包含的商品形式
        $has_goods_types = [];
        $activity_type = $this->extend_data[ 'activity_type' ] ?? '';
        foreach ($sku_data as $v) {
            $sku_id = $v[ 'sku_id' ];
            $num = $v[ 'num' ];
            $total_num += $num;
            $market_type = $v[ 'market_type' ] ?? '';
            $market_type_id = $v[ 'market_type_id' ] ?? 0;
            $sku_info = $sku_list[ $sku_id ] ?? [];
            if (empty($sku_info)) throw new CommonException('SHOP_ORDER_CARTS_EXPIRE');//无效的商品
            // 不信任客户端按钮状态。代理跟随副本按从站普通商品参与下单；服务端
            // 统一兜住禁止线上出售、已下架、已锁定、已售和库存不足的商品。
            $sale_state = GoodsDict::getSaleState((array)($sku_info['goods'] ?? []));
            if (empty($sale_state['can_sell'])) {
                throw new CommonException((string)($sale_state['reason'] ?? '该商品当前不可购买'));
            }
            $sku_info[ 'member_discount' ] = $sku_info[ 'goods' ][ 'member_discount' ] ?? '';

            //商品原价
            $sku_info[ 'original_price' ] = $sku_info[ 'price' ];
            //获取活动价格
            //todo 需仔细看看
            $sku_info[ 'show_type' ] = 'original_price';
            $sku_info[ 'active_id' ] = '';
            $goods_active_price_service = (new CoreGoodsActivePriceService());
            $show_price_data = $goods_active_price_service->getActivePrice($sku_info, $this->site_id, $this->member_id);
            $sku_info[ 'show_type' ] = $show_price_data[ 'show_type' ];
            $sku_info[ 'price' ] = $show_price_data[ 'show_price' ];
            $sku_info[ 'active_id' ] = $show_price_data[ 'discount_id' ] ?? '';
            // 会员候选价已经由统一价格服务按商品原价计算完成。这里如果再次
            // 调用 getMemberPrice，会在新人多件等流程中出现二次折扣。
            $sku_info[ 'member_price' ] = $show_price_data[ 'member_price' ]
                ?? $sku_info[ 'original_price' ];



            //默认金额填充
            $sku_info[ 'discount_money' ] = 0;
            $item_goods_type = $sku_info[ 'goods' ][ 'goods_type' ];

            // 商品限购处理
            if (isset($this->limit_buy[ 'goods_' . $sku_info[ 'goods_id' ] ])) {
                $this->limit_buy[ 'goods_' . $sku_info[ 'goods_id' ] ][ 'num' ] += $num;
            } else {
                $this->limit_buy[ 'goods_' . $sku_info[ 'goods_id' ] ] = [
                    'goods_id' => $sku_info[ 'goods_id' ],
                    'site_id' => $sku_info[ 'site_id' ],
                    'goods_name' => $sku_info[ 'goods' ][ 'goods_name' ],
                    'stock' => $sku_info[ 'goods' ][ 'stock' ],
                    'num' => $num,
                    'is_limit' => $sku_info[ 'goods' ][ 'is_limit' ],
                    'limit_type' => $sku_info[ 'goods' ][ 'limit_type' ],
                    'max_buy' => $sku_info[ 'goods' ][ 'max_buy' ],
                    'min_buy' => $sku_info[ 'goods' ][ 'min_buy' ]
                ];
            }

            if (!in_array($item_goods_type, $has_goods_types)) $has_goods_types[] = $item_goods_type;

            $sku_info[ 'num' ] = $num;
            $sku_info[ 'market_type' ] = $market_type;//活动类型
            $sku_info[ 'market_type_id' ] = $market_type_id;//活动id

            // 计算商品小计
            $price = $sku_info[ 'price' ];
            $sku_info[ 'goods_money' ] = $price * $num;

            /****顺手买商品不参与任何其他活动 进行单独计算****/
            if (!isset($v[ 'impulse_buy_goods_id' ])) {
                // 活动操纵数据  market_data 活动信息
                $temp = [];
                // 过滤活动计算结果，去除空元素
                $temp_list = array_filter(event('PhoneShopGoodsMarketCalculate', [
                    'sku_info' => $sku_info,
                    'sku_data' => $sku_data,
                    'order_obj' => $this
                ]));

                // 获取最后一个非空的活动计算结果
                if (!empty($temp_list)) {
                    $temp = end($temp_list);
                }

                if (!empty($temp)) {
                    // 更新 SKU 信息
                    $sku_info = $temp[ 'sku_info' ];
                    //todo 需仔细看看
                    $sku_info['relate_id'] = $temp[ 'relate_id' ];
                    //todo 需仔细看看
                    if (!empty($this->extend_data)) {
                        // 更新扩展数据中的关联 ID 和活动类型
                        $this->extend_data[ 'relate_id' ] = $temp[ 'relate_id' ] ?? 0;
                        $this->extend_data[ 'activity_type' ] = $temp[ 'activity_type' ] ?? '';
                    }
                    if (isset($temp['error']) && !empty($temp['error'])) {
                        if(is_array($temp['error'])){
                            foreach ($temp['error'] as $error){
                                $this->setError($error);
                            }
                        }else{
                            $this->setError($temp['error']);
                        }

                    }
                }
            }

            $goods_money += $sku_info[ 'goods_money' ];
            $body = $body ? $body . ( $sku_info[ 'sku_name' ] . $sku_info[ 'goods' ][ 'goods_name' ] ) : ( $sku_info[ 'sku_name' ] . $sku_info[ 'goods' ][ 'goods_name' ] );
            $goods_list[ $sku_id ] = $sku_info;
        }

        /*****顺手买开始*****/
        $impulse_buy_goods = $this->param[ 'impulse_buy_goods' ] ?? [];
        $impulse_buy_list = [];
        if (!empty($impulse_buy_goods)) {
            $temp_list = array_filter(event('PhoneShopImpulseBuyGoodsMarketCalculate', [
                'data' => $impulse_buy_goods,
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'goods_money' => $goods_money,
                'order_obj' => $this
            ]))[ 0 ] ?? [];
            if (!empty($temp_list)) {
                $impulse_buy_list = $temp_list[ 'impulse_buy_data' ];
                $goods_money = $temp_list[ 'goods_money' ];
                $order_money = $temp_list[ 'order_money' ];
                $this->basic[ 'order_money' ] += $order_money;
            }
        }
        /*****顺手买结束*****/

        $this->basic[ 'has_goods_types' ] = $has_goods_types;
        $this->basic[ 'total_num' ] = $total_num;
        $this->goods_data = $goods_list;
        $this->impulse_buy_list = $impulse_buy_list;
        $this->basic[ 'goods_money' ] = $goods_money;
        $this->basic[ 'body' ] = $body;
        return $order_data;
    }

    /**
     * 获取商品的会员价
     * @param $sku_info
     * @return string
     */
    public function getMemberPrice($sku_info)
    {
        $level = (array)($this->buyer['member_level'] ?? []);
        return CoreMemberPriceService::calculate([
            'site_id' => (int)($this->site_id ?? 0),
            'member_level' => (int)($level['level_id'] ?? 0),
            'memberLevelData' => $level,
        ], (string)($sku_info['goods']['member_discount'] ?? ''), $sku_info['member_price'] ?? '', $sku_info['price'] ?? 0);
    }

    /**
     * 最终下单价格防线。
     *
     * 普通、会员和限时折扣价均重新从数据库解析。发生变化时不静默成交，
     * 让前端重新计算并向客户展示新价格；新人活动复用原监听器重新核价。
     */
    private function validateCachedGoodsPrice(): void
    {
        $goods_data = array_filter($this->goods_data, function ($item) {
            return empty($item['is_gift']) && !empty($item['sku_id']);
        });
        if (empty($goods_data)) {
            return;
        }

        $sku_ids = array_values(array_unique(array_map('intval', array_column($goods_data, 'sku_id'))));
        $sku_list = (new GoodsSku())->where([
            ['site_id', '=', $this->site_id],
            ['sku_id', 'in', $sku_ids],
        ])->with(['goods'])->field(
            'sku_id,site_id,goods_id,price,member_price,sale_price,stock'
        )->select()->toArray();
        $sku_list = array_column($sku_list, null, 'sku_id');
        $price_service = new CoreGoodsActivePriceService();

        foreach ($goods_data as $cached) {
            $sku_id = (int)$cached['sku_id'];
            $current = $sku_list[$sku_id] ?? [];
            if (empty($current) || empty($current['goods'])) {
                throw new CommonException('商品信息已发生变化，请返回订单页重新确认');
            }
            $sale_state = GoodsDict::getSaleState((array)$current['goods']);
            if (empty($sale_state['can_sell'])) {
                throw new CommonException((string)($sale_state['reason'] ?? '该商品当前不可购买'));
            }
            $current['member_discount'] = (string)($current['goods']['member_discount'] ?? '');
            $resolved = $price_service->getActivePrice($current, $this->site_id, $this->member_id);
            $cached_price = round((float)($cached['price'] ?? 0), 2);
            $current_price = round((float)($resolved['show_price'] ?? 0), 2);
            if ($current_price <= 0) {
                throw new CommonException('商品价格无效，请联系商家确认后再提交');
            }
            $currentMoney = round($current_price * (int)$cached['num'], 2);
            if (($this->extend_data['activity_type'] ?? '') === ActiveDict::NEWCOMER_DISCOUNT) {
                $current['price'] = $current_price;
                $current['member_price'] = $resolved['member_price'] ?? $current_price;
                $current['num'] = (int)$cached['num'];
                $current['goods_money'] = $currentMoney;
                // 监听器会填充优惠信息，使用副本校验，不能改动已确认快照。
                $validationOrder = clone $this;
                $activity = (new ShopNewcomerCalculate())->handle([
                    'sku_info' => $current, 'order_obj' => $validationOrder,
                ]);
                $current = $activity['sku_info'] ?? $current;
                $current_price = (float)$current['price'];
                $currentMoney = (float)$current['goods_money'];
            }
            if (bccomp((string)$current_price, (string)$cached_price, 2) !== 0
                || bccomp((string)$currentMoney, (string)$cached['goods_money'], 2) !== 0) {
                throw new CommonException('商品价格已更新，请返回订单页确认最新价格后再提交');
            }
        }
    }

    /**
     * 商城成交方式及手续费计价。
     *
     * 客户承担：采用净额反推，确保微信扣费后覆盖同行销售价；
     * 商家承担：客户不加价，手续费从商家收入中扣除；
     * 零售价：按产品约定不追加、不参与同行手续费计算。
     */
    private function applyOnlineTradePricing(): void
    {
        $config = (new CoreOrderConfigService())->getOnlineTradeConfig($this->site_id);
        $identity = $this->peerPriceApplied ? 'peer' : 'retail';
        $requestedMode = (string)($this->param['payment_mode'] ?? '');
        if (!in_array($requestedMode, ['', 'online', 'offline_pending'], true)) {
            $requestedMode = '';
        }
        $offlineAllowed = (int)($config['offline_order_enabled'] ?? 1) === 1
            && ($identity !== 'peer' || (int)($config['offline_peer_enabled'] ?? 1) === 1);
        $onlineAllowed = (int)($config['online_order_enabled'] ?? 1) === 1
            && ($identity !== 'peer' || (int)($config['peer_online_enabled'] ?? 1) === 1);

        if ($requestedMode === '') {
            $requestedMode = $offlineAllowed && (int)($config['offline_order_default'] ?? 1) === 1
                ? 'offline_pending'
                : 'online';
        }
        if ($requestedMode === 'offline_pending' && !$offlineAllowed) {
            $requestedMode = $onlineAllowed ? 'online' : 'offline_pending';
        }
        if ($requestedMode === 'online' && !$onlineAllowed) {
            $requestedMode = $offlineAllowed ? 'offline_pending' : 'online';
        }

        $pricingConfig = $requestedMode === 'offline_pending'
            ? array_merge($config, ['peer_fee_rate' => 0, 'peer_fee_bearer' => 'merchant'])
            : $config;
        $snapshot = (new CoreOnlineTradePricingService())->calculate(
            (float)($this->basic['order_money'] ?? 0),
            $identity,
            $pricingConfig
        );
        $this->basic = array_merge($this->basic, $snapshot);
        $this->basic['payment_mode'] = $requestedMode;
        $this->basic['online_trade_config'] = $config;
    }

}
