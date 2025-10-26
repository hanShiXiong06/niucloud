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

namespace addon\home_service\app\service\core\order;

use addon\home_service\app\dict\coupon\CouponDict;
use addon\home_service\app\dict\order\OrderDiscountDict;
use addon\home_service\app\job\order\AfterHomeServiceOrderCreate;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\service\core\card\CoreMemberCardService;
use addon\home_service\app\service\core\coupon\CoreCouponMemberService;
use app\service\core\member\CoreMemberAddressService;
use core\exception\CommonException;
use Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 *  订单服务层
 */
trait CoreOrderCreateTrait
{
    public $member_id;//会员id
    public $site_id; // 站点id
    public $param = [];//入参
    public $cart_ids = [];//购物车
    public $buyer = [];//买家信息
    public $delivery = [];//地址
    public $basic = [
        'discount_money' => 0,//优惠金额
        'coupon_money' => 0,//优惠券金额
        'goods_money' => 0,
        'order_money' => 0
    ];//基本数据处理(整体的数据)
    public $goods_data = [];//商品数据处理
    public $config = [];//配置集合
    public $discount = [];//优惠整合

    public $order_id;

    public $invoice = [];

    public $order_key;
    public $error = [];

    public $card_data = [];//会员次卡套餐

    public function createOrder(array $data)
    {
        $order_data = $data['order_data'];
        $order_goods_data = $data['order_goods_data'];
        $order_no = create_no();
        $order_data['order_no'] = $order_no;
        $order_data['order_from'] = $this->param['order_from'];//来源渠道
        $order_data['ip'] = request()->ip();
        $site_id = $order_data['site_id'];
        $other_array = [];
        $other_array['main_type'] = $data['main_type'] ?? 'member';
        $other_array['main_id'] = $data['main_id'] ?? '0';
        //校验整理发票
        $this->invoice();
        Db::startTrans();
        try {
            $order = (new Order())->create($order_data);
            $this->order_id = $order['order_id'];
            //添加订单项目表
            $order_goods_model = new OrderItem();

            $order_goods_model->insertAll($order_goods_data);
            //优惠项
            $this->useDiscount();
            //次卡使用
            $this->useCard();
            //删除订单缓存
            // $this->delOrderCache($this->order_key);
            $order_data['order_id'] = $this->order_id;
            //订单金额为0或是次卡订单,要直接支付
            if ($order_data['order_money'] == 0 || $order_data['is_card_order'] > 0) {
                (new CoreOrderPayService())->pay(['site_id' => $site_id, 'trade_id' => $this->order_id]);
            }
            $order_data['other_array'] = $other_array;
            Db::commit();
            //订单创建后事件
            AfterHomeServiceOrderCreate::dispatch(['site_id' => $site_id, 'order_id' => $order_data['order_id'], 'order_data' => $order_data, 'order_goods_data' => $order_goods_data, 'cart_ids' => $this->cart_ids, 'time' => time()]);
            return [
                'order_id' => $this->order_id,
                'trade_type' => $order_data['order_type'],
            ];
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 发票整理
     * @return void
     */
    public function invoice()
    {
    }

    /**
     * 配置设置或查询
     * @param $key
     */
    public function config($key)
    {
        return [];
    }

    /**
     * 优惠写入
     * @return void
     */
    public function useDiscount()
    {
        $insert_discount_data = [];
        $discount_service = new CoreOrderDiscountService();
        if ($this->discount) {
            foreach ($this->discount as $k => $v) {
                switch ($k) {
                    case 'coupon':
                        //使用优惠券
                        (new CoreCouponMemberService())->use([
//                            'member_id' => $this->member_id,
                            'id' => $v['discount_type_id'],
                            'trade_id' => $this->order_id
                        ]);
                        break;
                }
                if (isset($v['discount_type'])) {
                    $insert_discount_data[] = [
                        'type' => $v['type'],
                        'num' => $v['num'],
                        'money' => $v['money'],
                        'discount_type' => $v['discount_type'],
                        'discount_type_id' => $v['discount_type_id'],
                        'content' => $v['content'] ?? $v['title'] ?? "",
                        'order_id' => $this->order_id,
                        'site_id' => $this->site_id
                    ];
                } else {
                    foreach ($v as $vv) {
                        $insert_discount_data[] = [
                            'type' => $vv['type'],
                            'num' => $vv['num'],
                            'money' => $vv['money'],
                            'discount_type' => $vv['discount_type'],
                            'discount_type_id' => $vv['discount_type_id'],
                            'content' => $vv['content'] ?? $vv['title'] ?? "",
                            'order_id' => $this->order_id,
                            'site_id' => $this->site_id
                        ];
                    }
                }

            }

            $discount_service->addAll($insert_discount_data);
        }
        return true;
    }

    /**
     * 使用次卡
     * @return void
     */
    public function useCard()
    {
        if ($this->card_data) {
            (new CoreMemberCardService())->use($this->site_id, $this->member_id, $this->order_id, $this->card_data);
        }
        return true;
    }


    /**
     * 获取参数
     * @param $key
     * @param $default
     * @return mixed|string
     */
    public function param($key, $default = '')
    {
        return $this->param[$key] ?? $default;
    }

    /**
     * 订单优惠
     * @return void
     */
    public function getDiscount()
    {
        //查询可用优惠券
        $this->getCoupon();
    }

    /**
     * 获取有效的优惠券
     * @param $data
     */
    public function getCoupon($data)
    {
        //参数赋值
        $this->setParam($data);
        $order_key = $this->param['order_key'] ?? '';
        //获取订单数据的缓存
        $this->getOrderCache($order_key);

        $coupon_list = (new CoreCouponMemberService())->getUseCouponListByMemberId($this->member_id);

        //如果有优惠券
        if (!empty($coupon_list)) {
            foreach ($coupon_list as &$v) {
                $type = (int)$v['type'];
                $goods_data = $v['goods'];
                $min_condition_money = $v['min_condition_money'];
                switch ($type) {
                    case CouponDict::ALL:
                        $v['is_normal'] = true;
                        //匹配的商品
                        $match_goods_list = $this->goods_data;
                        $match_goods_money = $this->basic['goods_money'];
                        break;
                    case CouponDict::CATEGORY:
                        $category_ids = array_column($goods_data, 'category_id');
                        //匹配的商品
                        $match_goods_list = [];
                        $match_goods_money = 0;
                        foreach ($this->goods_data as $goods_v) {
                            $item_goods_category = [$goods_v['goods']['top_category']];//商品分类数组
                            if (!empty(array_intersect($category_ids, $item_goods_category))) {
                                $match_goods_list[] = $goods_v;
                                $match_goods_money += $goods_v['goods_money'];
                            }
                        }
                        break;
                    case CouponDict::GOODS:
                        $goods_ids = array_column($goods_data, 'goods_id');
                        //匹配的商品
                        $match_goods_list = [];
                        $match_goods_money = 0;
                        foreach ($this->goods_data as $goods_v) {
                            if (in_array($goods_v['goods_id'], $goods_ids)) {
                                $match_goods_list[] = $goods_v;
                                $match_goods_money += $goods_v['goods_money'];
                            }
                        }
                        break;
                }

                if (empty($match_goods_list)) {
                    $v['is_normal'] = false;
                    $v['error'] = get_lang('HOME_SERVICE_ORDER_COUPON_SUPPORT_GOODS');//没有支持可用的商品
                } else {
                    if ($match_goods_money < $min_condition_money) {
                        $v['is_normal'] = false;
                        $v['error'] = get_lang('HOME_SERVICE_ORDER_COUPON_NOT_CONDITION');//没有达到商品最低使用条件
                    } else {
                        $v['is_normal'] = true;
                    }
                }
            }
        }
        return $coupon_list;
    }

    /**
     * 给传参
     * @param $param
     * @return true
     */
    public function setParam($param)
    {
        $this->param = $param;
        $this->site_id = $param['site_id'];
        return true;
    }


    /**
     * 计算优惠
     * @return void
     */
    public function calculateDiscount()
    {

        //查询可用优惠券
        $this->calculateCoupon();
    }

    /**
     * 优惠券计算
     * @return void
     */
    public function calculateCoupon()
    {
        $coupon_id = $this->param['discount']['coupon_id'] ?? 0;//使用优惠券id

        if ($coupon_id > 0) {
            $coupon_data = (new CoreCouponMemberService())->getUseCouponById($coupon_id);
            if (empty($coupon_data)) throw new CommonException('HOME_SERVICE_ORDER_COUPON_EXPIRE_OR_NOT_FOUND');//优惠券已使用或不存在

            $time = time();
            if ($time > strtotime($coupon_data['expire_time'])) {
                throw new CommonException('HOME_SERVICE_ORDER_COUPON_EXPIRE');//优惠券已使用或不存在
            }
            $type = (int)$coupon_data['type'];
            $goods_data = $coupon_data['goods'];
            $min_condition_money = $coupon_data['min_condition_money'];
            $match_order_goods_money = 0;
            switch ($type) {
                case CouponDict::ALL:
                    //匹配的商品
                    $match_goods_list = $this->goods_data;
                    $match_goods_money = $this->basic['goods_money'];
                    $match_order_goods_money = $this->basic['goods_money'] - $this->basic['discount_money'];
                    break;
                case CouponDict::CATEGORY:
                    $category_ids = array_column($goods_data, 'category_id');
                    //匹配的商品
                    $match_goods_list = [];
                    $match_goods_money = 0;
                    foreach ($this->goods_data as $goods_v) {
                        $item_goods_category = $goods_v['goods']['top_category'];//商品分类数组
                        if (!empty(array_intersect($category_ids, [$item_goods_category]))) {
                            $match_goods_list[] = $goods_v;
                            $match_goods_money += $goods_v['goods_money'];
                            $match_order_goods_money += $this->calculateOrderGoodsMoney($goods_v);
                        }
                    }
                    break;
                case CouponDict::GOODS:
                    $goods_ids = array_column($goods_data, 'goods_id');
                    //匹配的商品
                    $match_goods_list = [];
                    $match_goods_money = 0;
                    foreach ($this->goods_data as $goods_v) {
                        if (in_array($goods_v['goods_id'], $goods_ids)) {
                            $match_goods_list[] = $goods_v;
                            $match_goods_money += $goods_v['goods_money'];
                            $match_order_goods_money += $this->calculateOrderGoodsMoney($goods_v);
                        }
                    }

                    break;
            }
            if (empty($match_goods_list)) {
                $this->setError(get_lang('HOME_SERVICE_ORDER_COUPON_NOT_SUPPORT_GOODS'));//没有支持可用的商品
            } else {
                if ($match_goods_money < $min_condition_money) {
                    $this->setError(get_lang('HOME_SERVICE_ORDER_COUPON_NOT_SUPPORT_MIN_MONEY'));//没有达到商品最低使用条件
                } else {
                    $coupon_money = $coupon_data['price'];
                    if ($coupon_money > $match_order_goods_money) {
                        $coupon_money = $match_order_goods_money;
                    }
                    $surplus_money = $coupon_money;
                    $match_goods_list = array_values($match_goods_list);
                    $match_goods_list = array_filter($match_goods_list, function ($item) {
                        return ($item['goods_money'] - $item['discount_money']) != 0;
                    });
                    $match_count = count($match_goods_list);
                    //根据商品金额计算个订单项享受的优惠
                    foreach ($match_goods_list as $k => $v) {
                        $item_order_goods_money = $this->calculateOrderGoodsMoney($v);
                        $item_sku_id = $v['sku_id'];
                        if ($k == ($match_count - 1)) {
                            $item_coupon_money = $surplus_money;
                        } else {
                            if ($match_order_goods_money == 0 || $coupon_money == 0) {
                                $item_coupon_money = 0;
                            } else {
                                $item_coupon_money = $this->moneyFormat($item_order_goods_money / $match_order_goods_money * $coupon_money);
                                if ($item_coupon_money == 0) {
                                    $item_coupon_money = $item_order_goods_money;
                                }
                                if ($item_coupon_money > $surplus_money) {
                                    $item_coupon_money = $surplus_money;
                                }
                            }
                        }

                        $this->goods_data[$item_sku_id]['discount_money'] += $item_coupon_money;
//                        $this->goods_data[$item_sku_id]['order_goods_money'] = $this->calculateOrderGoodsMoney($this->goods_data[$item_sku_id]);

                        $surplus_money = bcsub($surplus_money, $item_coupon_money, 2);
                    }
                    //优惠累增
                    $this->basic['discount_money'] += $coupon_money;
                    $this->basic['coupon_money'] += $coupon_money;
//                    $discount_money = $this->basic['discount']['discount_money'];
                    $this->discount['coupon'] = $this->discountFormat(
                        array_column($match_goods_list, 'sku_id'),
                        OrderDiscountDict::DISCOUNT,
                        1,
                        $coupon_money,
                        'coupon',
                        $coupon_id,
                        '',
                        $coupon_data['title']
                    );
                }
            }

        }

    }

    /**
     * 优惠项格式
     * @param $match_goods_ids
     * @param $type
     * @param $num
     * @param $money
     * @param $discount_type
     * @param $discount_type_id
     * @param $content
     * @return array
     */
    public function discountFormat($match_goods_ids, $type, $num, $money, $discount_type, $discount_type_id, $content, $title = '')
    {
        return [
            'match_goods_ids' => $match_goods_ids,
            'type' => $type,
            'num' => $num,
            'money' => $money,
            'discount_type' => $discount_type,
            'discount_type_id' => $discount_type_id,
            'content' => $content,
            'title' => $title
        ];
    }

    /**
     * 计算订单项金额
     * @param $goods
     * @return mixed
     */
    public function calculateOrderGoodsMoney($goods)
    {
        $goods_money = $goods['goods_money'];
        $discount_money = $goods['discount_money'] ?? 0;
        return $goods_money - $discount_money;
    }


    /**
     * 使用优惠券
     * @param $data
     * @return void
     */
    public function useCoupon($data)
    {
    }

    /**
     * 选中地址
     * @return void
     */
    public function selectTakeAddress()
    {
        //查询默认收货地址
        if (!empty($this->param['delivery']['take_address_id'])) {
            $this->delivery['take_address'] = (new CoreMemberAddressService())->getMemberAddressById($this->param['delivery']['take_address_id'], $this->member_id);
        } else {
            $this->delivery['take_address'] = (new CoreMemberAddressService())->getDefaultAddressByMemberId($this->member_id);
        }
        if (empty($this->delivery['take_address'])) {
            $this->error[] = get_lang('HOME_SERVICE_NOT_SELECT_ADDRESS');
            return;
        }
        return true;
    }


    /**
     * 存在错误则抛出
     * @return void
     */
    public function checkError()
    {
        $error = $this->getError();
        if ($error) throw new CommonException($error[0]);
    }

    /**
     * 获取错误
     * @return array|mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 定义错误
     * @param $key
     * @param $error
     * @return void
     */
    public function setError($error)
    {
        $this->error[] = $error;
    }

    /**
     * 获取整合后的数据
     * @return void
     */
    public function getData()
    {
    }

    /**
     * 设置订单缓存
     * @return string
     * @throws Exception
     */
    public function setOrderCache($order_key = '', $order_cache = [])
    {
        if (empty($order_key)) {
            $order_key = create_no('', $this->member_id);
        }
        Cache::tag('order_cache')->set($order_key, $order_cache, 3000);
        return $order_key;
    }

    /**
     * 获取订单缓存
     * @param $order_key
     * @return void
     */
    public function getOrderCache($order_key)
    {
        $order_cache = Cache::get($order_key, []);
        if (empty($order_cache))
            throw new CommonException('HOME_SERVICE_ORDER_EXPIRE');//订单数据已过期
        foreach ($order_cache as $k => $v) {
            $this->$k = $v;
        }
        return true;
    }

    /**
     * 清除订单缓存
     * @param $order_key
     * @return true
     */
    public function delOrderCache($order_key = '')
    {
        Cache::delete($order_key);
        return true;
    }

    /**
     * 校验抵扣项是否可用
     * @return void
     */
    public function checkDiscount()
    {
    }

    /**
     * 比例(向下取整)
     * @param $rate
     * @return float|int
     */
    public function rateFormat($rate)
    {
        return floor(strval(($rate) * 100)) / 100;
    }

    /**
     * 金额格式化
     * @param $money
     * @return float|int
     */
    public function moneyFormat($money)
    {
        return floor(strval(($money) * 100)) / 100;
    }
}
