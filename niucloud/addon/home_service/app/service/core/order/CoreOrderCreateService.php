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

use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\dict\goods\CardDict;
use addon\home_service\app\dict\goods\GoodsDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\card\MemberCard;
use addon\home_service\app\model\goods\GoodsSku;
use addon\home_service\app\model\order\Order;
use app\model\member\MemberLevel;
use app\service\core\member\CoreMemberService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use addon\home_service\app\service\core\strategy\CoreCityStrategyService;

/**
 *  订单服务层
 */
class CoreOrderCreateService extends BaseCoreService
{

    use CoreOrderCreateTrait;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }


    public function getReserveServiceTimeStamp(&$data)
    {
        $timeStr = $data['reserve_service_time'];
        // 分割日期和时间部分
        list($dateStr, $timeRange) = explode(' ', $timeStr);
        // 提取开始时间
        $startTimeStr = explode('-', $timeRange)[0];
        // 处理日期格式（替换中文为连字符，确保格式正确）
        $date = str_replace(['月', '日'], ['-', ''], $dateStr); // 关键修改：日替换为空
        $fullDate = date('Y') . "-{$date} {$startTimeStr}:00";
        // 转换为时间戳
        $startTimestamp = strtotime($fullDate);
        $this->param['reserve_service_time_stamp'] = $startTimestamp;
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
        $order_key = $this->param['order_key'] ?? '';
        //获取订单缓存缓存
        $this->getOrderCache($order_key);
        //校验错误
        $this->checkError();
        $this->getReserveServiceTimeStamp($data);
        // 检测是否为跑腿业务
        $is_errand_business = isset($data['sku']['type']) && $data['sku']['type'] === 'errand';
      
        $order_data = [
            //订单整体
            'site_id' => $data['site_id'],
            'order_type' => OrderDict::ORDER_TYPE_ORDER,
            'order_status' => OrderDict::WAIT_PAY,
            'order_name' => $this->basic['order_name'],
            'member_id' => $this->member_id,
            'goods_money' => $this->basic['goods_money'],
            'order_money' => $this->basic['order_money'],
            'discount_money' => $this->basic['discount_money'] ?? 0,
            'pay_money' => $this->basic['pay_money'],
            //地址相关
            'taker_name' => $this->delivery['take_address']['name'] ?? '',
            'taker_mobile' => $this->delivery['take_address']['mobile'] ?? '',
            'taker_province' => $this->delivery['take_address']['province_id'] ?? 0,
            'taker_city' => $this->delivery['take_address']['city_id'] ?? 0,
            'taker_district' => $this->delivery['take_address']['district_id'] ?? 0,
            'taker_address' => $this->delivery['take_address']['address'] ?? '',
            'taker_full_address' => substr($this->delivery['take_address']['full_address'], 0, strlen($this->delivery['take_address']['full_address']) - strlen($this->delivery['take_address']['address'])),
            'taker_longitude' => $this->delivery['take_address']['lng'] ?? '',
            'taker_latitude' => $this->delivery['take_address']['lat'] ?? '',
            //附属信息
            'member_message' => $this->param['member_remark'] ?? '',//买家留言
            'technician_id' => 0,//技师
            'reserve_service_time' => date('Y-m-d H:i:s', $this->param['reserve_service_time_stamp']),//希望服务时间
            'create_time' => time(),
            'reserve_service_time_stamp' => $this->param['reserve_service_time_stamp'] ?? 0,
            'is_card_order' => $this->card_data['is_card_order'] ?? 0,
            'is_auto_refund' => $data['is_auto_refund'] ? 1 : 0,
            'is_errand' => $is_errand_business ? 1 : 0, // 标记是否为跑腿业务
            'errand_items' => $is_errand_business ? json_encode($data['errand_items'] ?? [], JSON_UNESCAPED_UNICODE) : '[]', // 跑腿包裹信息JSON，非跑腿业务为空数组
        ];


        $order_goods_data = [];
        foreach ($this->goods_data as $v) {
            $order_goods_data[] = [
                'site_id' => $data['site_id'],
                'member_id' => $data['member_id'],
                'item_type' => $v['goods']['buy_type'],
                'goods_id' => $v['goods_id'],
                'item_id' => $v['sku_id'],
                'item_name' => $v['sku_name'],
                'item_image' => $v['sku_image'],
                'price' => $v['price'],
                'num' => $v['num'],
                'unit' => $v['sku_unit'],
                'item_money' => $v['goods_money'],
                'discount_money' => $v['discount_money'] ?? 0,
                'order_id' => &$this->order_id,
                'technician_id' => $order_data['technician_id'] ?? 0,
                'is_enable_refund' => $v['goods']['after_sales'] ? 0 : 1,
                'is_force_clock_in' => $v['goods']['is_force_clock_in'] ?? 0,   //强制打卡
                'is_force_departure' => $v['goods']['is_force_departure'] ?? 0,   //强制出发
                'is_finish_photograph' => $v['goods']['is_finish_photograph'] ?? 0,  //强制完成拍照
                'sku_name' => $v['sku_item_name'],
            ];
            $order_data['is_grab'] = $v['goods']['grab_orders'] ?? 0;
            $order_data['buy_type'] = $v['goods']['buy_type'] ?? '';
            $order_data['category_id'] = $v['goods']['top_category'] ?? 0;
        }
        $create_order_data = array(
            'order_data' => $order_data,
            'order_goods_data' => $order_goods_data,
            'main_type' => $data['main_type'] ?? 'member',
            'main_id' => $data['main_id'] ?? $this->member_id,
        );
        return $this->createOrder($create_order_data);
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
        $this->order_key = $this->param['order_key'] ?? '';
        $this->member_id = $this->param['member_id'] ?? 0;
        // 检测是否为跑腿业务
        $is_errand_business = isset($this->param['sku']['type']) && $this->param['sku']['type'] === 'errand';
        
        //服务地址（跑腿业务也需要收货地址）
        if (empty($this->order_key)) {
            $this->selectTakeAddress();
            $this->confirm();
        }
        //获取订单数据的缓存
        $this->getOrderCache($this->order_key . '_basic');
        
        // 如果是跑腿业务，需要特殊处理商品数据
        if ($is_errand_business) {
            $this->handleErrandBusinessGoods();
        }
        
        //计算优惠和营销
        $this->calculateDiscount();
        $this->setOrderCache($this->order_key);
        //金额格式化
        $discount_money = $this->moneyFormat($this->basic['discount_money'] ?? 0);//优惠金额
        $goods_money = $this->moneyFormat($this->basic['goods_money'] ?? 0);
        $order_money = $this->moneyFormat($goods_money - $discount_money);
        $this->basic['discount_money'] = $discount_money;
        $this->basic['goods_money'] = $goods_money;
        // 校验控制,不能小于0
        $order_money = $order_money < 0 ? 0 : $order_money;
        $this->basic['order_money'] = $order_money;
        $this->basic['pay_money'] = $order_money;
        //订单创建数据写入缓存,并将标识返回给前端
        $order_cache = get_object_vars($this);
        unset($order_cache['param']);
        $this->setOrderCache($this->order_key, $order_cache);
        return $order_cache;
    }

    /**
     * 订单确认(基础信息查询并缓存)
     * @param array $data
     * @return array|mixed
     */
    public function confirm()
    {
        //查看会员信息
        $member_id = $this->param['member_id'];
        $this->member_id = $member_id;
        $member_info = (new CoreMemberService())->getInfoByMemberId($this->site_id, $member_id, 'nickname, point, member_level, headimg, balance, mobile');
        if (empty($member_info)) throw new CommonException('HOME_SERVICE_ORDER_BUYER_NOT_FOUND');//无效的账号
        // 查询会员等级信息
        $member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray() ?? [];
        //会员账户信息
        $this->buyer = $member_info;
        //查询商品信息
        $this->getGoodsData();
        $order_cache = get_object_vars($this);
        unset($order_cache['param']);
        unset($order_cache['order_key']);
        $order_key = $this->setOrderCache('', $order_cache);
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
        $sku_id = $this->param['sku']['sku_id'] ?? 0;
        $this->card_data = $this->param['card_data'];
        $sku_info = (new  GoodsSku())->where([['sku_id', '=', $sku_id], ['site_id', '=', $this->site_id]])->with(['goods'])->field('sku_id, site_id, sku_name, sku_image, goods_id, price,sku_unit,min_buy,member_price')->findOrEmpty()->toArray();
        if (empty($sku_info)) throw new CommonException('HOME_SERVICE_GOODS_NOT_EXIST');//无效的数据
        $sku_info['sku_item_name'] = $sku_info['sku_name'];
        if ($sku_info['sku_name'] != $sku_info['goods']['goods_name']) $sku_info['sku_name'] = $sku_info['goods']['goods_name'] . ' ' . $sku_info['sku_name'];
        // 检测是否为跑腿业务
        $is_errand_business = isset($this->param['sku']['type']) && $this->param['sku']['type'] === 'errand';
        
        if (empty($this->card_data)) {
            // 跑腿业务检查商品状态
            if (!$is_errand_business && $sku_info['goods']['status'] != GoodsDict::UP) {
                throw new CommonException('HOME_SERVICE_GOODS_NOT_EXIST');//无效的数据
            }
        }
        $goods_list = [];
        $total_num = $num = $this->param['sku']['num'] ?? 1;
        
        // 跑腿业务不检查最小购买数量
        if (!$is_errand_business && $num < $sku_info['min_buy']) {
            $this->setError('购买数量不能小于' . $sku_info['min_buy'] . $sku_info['sku_unit']);
        }
        //默认金额填充
        $sku_info['discount_money'] = 0;
        $sku_info['num'] = $num;
        //先走策略业务
        if (!empty($this->card_data)) {
            $sku_info['price'] = $this->getCardPrice($sku_info);
        } else {
            $sku_info['price'] = $this->getMemberPrice($sku_info);
            // 跑腿业务不使用城市策略价格，使用前端传递的实际价格
            if (!$is_errand_business) {
                $sku_info['price'] = (new  CoreCityStrategyService)->getStrategyPrice($sku_info['price'], $this->delivery['take_address']['city_id'] ?? 0, $this->site_id, $sku_info);
            }
        }
        $price = $sku_info['price'];
        $sku_info['goods_money'] = $price * $num;//小计
        $goods_money = $sku_info['goods_money'];
        $goods_list[$sku_info['sku_id']] = $sku_info;
        $this->basic['total_num'] = $total_num;
        $this->goods_data = $goods_list;
        $this->basic['goods_money'] = $goods_money;
        $this->basic['body'] = $sku_info['sku_name'];
        $this->basic['order_name'] = $sku_info['sku_name'];
    }

    /**
     * 获取商品的会员价
     * @param $sku_info
     * @return string
     */
    public function getMemberPrice($sku_info)
    {
        if (empty($sku_info['goods']['member_discount']) || $sku_info['goods']['buy_type'] != GoodsDict::BUY) {
            return $sku_info['price'];
        }
        // 没有会员等级，排除
        if (empty($this->buyer['member_level'])) {
            return $sku_info['price'];
        }
        $price = $sku_info['price'];
        if ($sku_info['goods']['member_discount'] == 'discount') {
            // 按照会员等级折扣计算
            // 默认按会员享受折扣计算
            if (isset($this->buyer['member_level']['level_benefits'])
                && isset($this->buyer['member_level']['level_benefits']['discount'])
                && $this->buyer['member_level']['level_benefits']['discount']['is_use']) {
                $price = number_format($price * $this->buyer['member_level']['level_benefits']['discount']['discount'] / 10, 2, '.', '');
            }
        } elseif ($sku_info['goods']['member_discount'] == 'fixed_price') {
            // 指定会员价
            if (!empty($sku_info['member_price'])) {
                $sku_info['member_price'] = json_decode($sku_info['member_price'], true);
                if (!empty($sku_info['member_price']['level_' . $this->buyer['member_level']['level_id']])) {
                    $member_level_price = $sku_info['member_price']['level_' . $this->buyer['member_level']['level_id']];
                    $price = number_format($member_level_price, 2, '.', '');
                }
            }
        }
        return $price;
    }

    /**
     * 获取商品的次卡套餐价
     * @param $sku_info
     * @return string
     */
    public function getCardPrice($sku_info)
    {
        $member_card_id = $this->card_data['member_card_id'] ?? 0;
        $member_card_item_id = $this->card_data['member_card_item_id'] ?? 0;

        $card_info = (new MemberCard())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $member_card_id],
        ])->with(['item' => function ($query) use ($member_card_item_id) {
            $query->where([['item_id', '=', $member_card_item_id]]);
        }])->findOrEmpty()->toArray();

        if (empty($card_info) || empty($card_info['item'])) throw new CommonException('HOME_SERVICE_CARD_NOT_EXIST');

        $card_item_info = $card_info['item'][0];
        if ($card_item_info['goods_id'] != $sku_info['goods_id'] || $card_item_info['goods_sku_id'] != $sku_info['sku_id']) throw new CommonException('HOME_SERVICE_EXCHANGE_GOODS_ERROR');
        if ($card_info['status'] == MemberCardDict::EXPIRE || ($card_info['expire_time'] > 0 && time() > $card_info['expire_time'])) throw new CommonException('HOME_SERVICE_CARD_IS_EXPIRE');
        if ($card_item_info['num'] - $card_item_info['use_num'] < 1) throw new CommonException('HOME_SERVICE_CARD_ITEM_USABLE_NUM_INSUFFICIENT');
        $this->card_data['is_card_order'] = 1;
        return $card_item_info['price'] ?? $sku_info['price'];
    }

    /**
     * 处理跑腿业务商品数据
     * 跑腿业务有多个包裹，需要特殊处理
     * @return void
     */
    public function handleErrandBusinessGoods()
    {
        $items = $this->param['sku']['items'] ?? [];
        if (empty($items)) {
            return;
        }

        // 获取主商品信息（第一个商品的 sku_id，用于优惠计算）
        $main_sku_id = $this->param['sku']['sku_id'] ?? 0;
        if (empty($this->goods_data[$main_sku_id])) {
            return;
        }

        $main_sku_info = $this->goods_data[$main_sku_id];
        
        // 计算所有包裹的总价格
        $total_goods_money = 0;
        $total_num = count($items);
        
        foreach ($items as $item) {
            $item_price = floatval($item['price'] ?? 0);
            $total_goods_money += $item_price;
        }

        // 更新商品数据
        $main_sku_info['num'] = $total_num;
        $main_sku_info['goods_money'] = $total_goods_money;
        $main_sku_info['errand_items'] = $items; // 保存跑腿详情

        // 更新到商品列表
        $this->goods_data[$main_sku_id] = $main_sku_info;
        
        // 更新基础数据
        $this->basic['total_num'] = $total_num;
        $this->basic['goods_money'] = $total_goods_money;
        $this->basic['body'] = $main_sku_info['sku_name'] . ' x' . $total_num;
        $this->basic['order_name'] = $main_sku_info['sku_name'] . ' x' . $total_num;
    }


}
