<?php



namespace addon\home_service\app\service\api\goods;

use addon\home_service\app\dict\goods\GoodsDict;
use addon\home_service\app\model\goods\GoodsCollect;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\service\core\strategy\CoreCityStrategyService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\ApiException;


/**
 * 商品收藏收藏层(项目)
 * Class GoodsService
 * @package addon\home_service\app\service\api\goods
 */
class GoodsCollectService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new GoodsCollect();
    }


    /**
     * 商品收藏列表
     *
     * @return array
     */
    public function getMemberGoodsCollectList($where): array
    {
        // 提前获取会员信息，避免在循环中重复查询
        $memberInfo = $this->getMemberInfo();
        $query = $this->model->where([
            ['member_id', '=', $this->member_id],
            ['goods_collect.site_id', '=', $this->site_id],
            ['goods.delete_time', '=', 0]
        ])
            ->withJoin([
                'goods' => [
                    'goods_subtitle', 'site_id', 'goods_id', 'goods_name',
                    'goods_cover', 'status', 'member_discount', 'sale_num', 'buy_type'
                ]
            ])
            ->with(['goodsSku'])
            ->append(['goods_cover_thumb_mid'])
            ->order('create_time desc');

        return $this->pageQuery($query, function ($item) use ($memberInfo,$where) {

            if ($item['goods']['buy_type'] == GoodsDict::BUY) {
                // 查询会员价
                $member_price = $this->getMemberPrice($memberInfo, $item['goods']['member_discount'], $item['goods']['member_price'], $item['price']);
            }else{
                $cityStrategyService = new CoreCityStrategyService();
                $member_price = $cityStrategyService->getStrategyPrice($item['price'], $where['city_id'], $this->site_id);
            }
            $item['goods']['member_price'] = $member_price;
            $item['price'] = $member_price;
            $item['member_price'] = $member_price;
        });
    }

    /**
     * 计算会员价格
     *
     * @param array $memberInfo 会员信息
     * @param float $memberDiscount 会员折扣
     * @param float $memberPrice 会员价
     * @param float $originalPrice 原价
     * @return float
     */
    private function calculateMemberPrice(
        array $memberInfo,
         $memberDiscount,
         $memberPrice,
        float $originalPrice
    ): float
    {
        // 根据实际业务逻辑计算会员价格
        return $this->getMemberPrice(
            $memberInfo,
            (string)$memberDiscount, // 转换回字符串以匹配getMemberPrice的参数要求
            (string)$memberPrice,
            $originalPrice
        );
    }




    /**
     * 获取会员信息
     *
     * @return array
     */
    public function getMemberInfo()
    {
        $member_model = new Member();
        $member_field = 'member_level';
        $member_info = $member_model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->field($member_field)
            ->with([
                // 会员等级
                'memberLevelData' => function ($query) {
                    $query->field('level_id, site_id, level_name, status, level_benefits, level_gifts');
                },
            ])
            ->findOrEmpty()->toArray();
        return $member_info;
    }


    /**
     * 查询商品的会员价
     * @param $member_info
     * @param string $member_discount 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
     * @param string $member_price 会员价，json格式，指定会员价，数据结构为：{"level_12":"92.00","level_13":"72.00","level_14":"66.00","level_15":"45.00"}
     * @param $price
     * @return int|string
     */

    public function getMemberPrice($member_info, $member_discount, $member_price, $price)
    {
        // 获取城市ID并确保为整数
        $city_id = (int)request()->get('city_id', 0);
        $cityStrategyService = new CoreCityStrategyService();
        // 处理无需会员折扣的情况
        if (empty($member_discount) || empty($member_info) ||
            (!empty($member_info) && empty($member_info['member_level']))) {
            return $cityStrategyService->getStrategyPrice($price, $city_id, $this->site_id);
        }
        // 根据员折扣类型计算价格
        if ($member_discount === 'discount') {
            // 按照会员等级折扣计算
            $levelBenefits = $member_info['memberLevelData']['level_benefits'] ?? [];
            if (!empty($levelBenefits['discount']['is_use'])) {
                $discount = $levelBenefits['discount']['discount'] ?? 10; // 默认不打折
                $price = $price * $discount / 10;
            }
        } elseif ($member_discount === 'fixed_price') {
            // 指定会员价
            if (!empty($member_price)) {
                $memberPriceData = is_array($member_price) ? $member_price : json_decode($member_price, true);
                // 验证解析结果是否为数组
                if (is_array($memberPriceData)) {
                    $levelKey = 'level_' . $member_info['member_level'];
                    if (!empty($memberPriceData[$levelKey])) {
                        $price = $memberPriceData[$levelKey];
                    }
                }
            }
        }
        // 应用城市策略并格式化价格
        $finalPrice = $cityStrategyService->getStrategyPrice($price, $city_id, $this->site_id);
        return number_format($finalPrice, 2, '.', '');
    }

    /**
     * 商品添加收藏
     */
    public function addGoodsCollect($data)
    {
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        $info = $this->model->where([
            ['member_id', '=', $data['member_id']],
            ['goods_id', '=', $data['goods_id']],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        $goods_info = (new Goods())->where([
            ['goods_id', '=', $data['goods_id']],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        if (empty($goods_info)) throw new ApiException("HOME_SERVICE_GOODS_NOT_EXIST");
        if (!empty($info)) {
            throw new ApiException('HOME_SERVICE_MEMBER_ALREADY_COLLECT');//已收藏
        } else {
            // 添加
            $data['create_time'] = time();
            $res = $this->model->create($data);
            return $res->id;
        }
    }


    /**
     * 商品取消收藏
     */
    public function cancelGoodsCollect($data)
    {
        $res = $this->model->where([['goods_id', 'in', $data['goods_ids']], ['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])->delete();
        return $res;
    }


    /**
     * 商品是否收藏
     */
    public function getGoodsIsCollect($goods_id): int
    {
        $collect_info = $this->model
            ->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['goods_id', '=', $goods_id]])
            ->findOrEmpty()
            ->toArray();
        if (!empty($collect_info)) {
            return 1;
        } else {
            return 0;
        }
    }


}
