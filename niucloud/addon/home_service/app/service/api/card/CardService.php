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

namespace addon\home_service\app\service\api\card;

use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\model\card\MemberCard;
use addon\home_service\app\model\goods\Card;
use addon\home_service\app\model\Member;
use addon\home_service\app\service\core\strategy\CoreCityStrategyService;
use core\base\BaseApiService;
use addon\home_service\app\model\goods\Guarantee;


/**
 * 次卡套餐服务层(项目)
 * Class CardService
 * @package addon\home_service\app\service\api\card
 */
class CardService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Card();
    }


    /**
     * 获取次卡套餐列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        // 主表需要查询的字段
        $field = 'member_discount, status, card_name, card_cover, card_id, site_id, card_image, sort, 
                  sale_num, virtually_sale, price, original_price, total_times, valid_type, card_content';

        // 排序处理
        $order = 'create_time desc';
        if (!empty($where['order']) && !empty($where['sort'])) {
            $order = $where['order'] . ' ' . $where['sort'];
        }

        // 城市ID处理
        $city_id = empty($where['city_id']) ? 0 : $where['city_id'];

        $query_where[] = ['is_delete', '=', 0];
        $with_where = [];
        if (isset($where['goods_id']) && !empty($where['goods_id'])) {
            $query_where[] = ['card.site_id', '=', $this->site_id];
            $with_where = [['cardSku.goods_id', '=', $where['goods_id']]];
        } else {
            $query_where[] = ['site_id', '=', $this->site_id];
        }

        // 初始化查询构建器
        $search_model = $this->model
            ->where($query_where)
            ->withSearch(["card_name", "valid_type"], $where)
            ->field($field)
            ->order($order)
            ->append(['card_cover_thumb_small', 'valid_type_name']);

        // 当存在goods_id时，关联cardSku表
        if (isset($where['goods_id']) && $where['goods_id'] !== '') {
            $search_model->withJoin(
                [
                    'cardSku' => ['sku_id', 'goods_id'],
                ]
            );
            $search_model->group('card.card_id')->where($with_where);
        }
        // 执行分页查询并处理价格策略
        return $this->pageQuery($search_model, function ($item) use ($city_id) {
            // 处理城市价格策略
            $item['price'] = (new CoreCityStrategyService)->getStrategyPrice(
                $item['price'],
                $city_id,
                $this->site_id
            );
            $item['discount_price'] = bcsub($item['original_price'], $item['price'], 2);
        });
    }

    /**
     * 获取个人中心最新次卡
     * @return array
     */
    public function getFirstInfo()
    {
        $now = time();

        $cardModel = (new MemberCard())
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['status', '=', MemberCardDict::WAIT_USE],
            ])
            ->where(function($query) use ($now) {
                $query->where('expire_time', 0)
                    ->whereOr('expire_time', '>', $now);
            })
            ->with([
                'card' => function ($query) {
                    $query->field('card_id,site_id,member_discount, card_name, card_image, sort,
                      sale_num, virtually_sale, price, original_price, total_times, valid_type, card_cover')
                        ->append(['card_cover_thumb_small', 'valid_type_name']);
                }
            ])
            // 排序：永久放最后，其余按到期时间升序
            ->orderRaw("CASE WHEN expire_time=0 THEN 1 ELSE 0 END, expire_time ASC")
            ->find();

        if (!$cardModel) {
            return [];
        }

        $card_info = $cardModel->toArray();
        if (empty($card_info['card'])) {
            return [];
        }

        $card_info = array_merge($card_info, $card_info['card']);
        unset($card_info['card']);

        $card_info['expire_time'] = $card_info['expire_time'] == 0
            ? '永久有效'
            : date('Y-m-d H:i:s', $card_info['expire_time']);

        $card_info['remain_count'] = $card_info['total_num'] - $card_info['total_use_num'];
        $card_info['discount_price'] = bcsub($card_info['original_price'], $card_info['price'], 2);

        return $card_info;
    }


    /**
     * 获取次卡信息
     * @param int $card_id
     * @param array $data
     * @return array
     */
    public function getInfo(int $card_id,$data)
    {
        // 城市ID处理
        $city_id = empty($data['city_id']) ? 0 : $data['city_id'];

        $field = 'card_image,card_content,guarantee_id,card_name,card_id,site_id,card_cover,sort,sale_num,virtually_sale,price,original_price,total_times,valid_type';
        $info = $this->model
            ->field($field)
            ->where([['card_id', '=', $card_id], ['site_id', '=', $this->site_id]])
            ->with([
                'skuList' => function ($query) {
                    $query->field('card_id,sku_name,sku_id,goods_id,price,original_price,max_use_times,sku_unit');
                }
            ])
            ->append(['card_cover_thumb_small', 'valid_type_name'])
            ->findOrEmpty()
            ->toArray();
        // 查询服务保障信息
        if (!empty($info['guarantee_id'])) {
            $guarantee_ids = explode(',', $info['guarantee_id']);
            $guarantee_list = (new Guarantee)->where([['site_id', '=', $this->site_id], ['id', 'in', $guarantee_ids]])->select()->toArray();
            $info['guarantee_list'] = $guarantee_list;
        } else {
            $info['guarantee_list'] = [];
        }

        $info['price'] = (new CoreCityStrategyService)->getStrategyPrice(
            $info['price'],
            $city_id,
            $this->site_id
        );
        $info['original_price'] = (new CoreCityStrategyService)->getStrategyPrice(
            $info['original_price'],
            $city_id,
            $this->site_id
        );
        $info['discount_price'] = bcsub($info['original_price'], $info['price'], 2);

        return $info;
    }

    /**
     * 获取商品列表供组件调用
     * @param array $where
     * @return array
     */
    public function getCardComponents(array $where = [])
    {
        $field = 'card_id,site_id,card_image,card_name,card_cover,status,sale_num + card.virtually_sale as sale_num, price,member_discount';

        $sku_where = [
            ['cardSku.site_id', '=', $this->site_id],
            ['is_delete', '=', 0],
            ['status', '=', 1]
        ];

        // 城市ID处理
        $city_id = empty($where['city_id']) ? 0 : $where['city_id'];
        if (!empty($where[ 'card_ids' ])) {
            $sku_where[] = ['card.card_id', 'in', $where['card_ids']];
        }

        // 参数过滤
        if (!empty($where['order']) && in_array($where['order'], ['sale_num', 'price'])) {
            $order = $where['order'] . ' desc';
        } else {
            $order = 'sort desc,create_time desc';
        }
        $list = $this->model
            ->field($field)
            ->withJoin(['cardSku'])
            ->where($sku_where)
            ->order($order)->append(['card_cover_thumb_small', 'card_cover_thumb_mid'])
            ->limit($where['num'])
            ->group('card.card_id')
            ->select()->toArray();
        if (!empty($this->member_id) && !empty($list)) {
            foreach ($list as &$item) {
                // 处理城市价格策略
                $item['price'] = (new CoreCityStrategyService)->getStrategyPrice(
                    $item['price'],
                    $city_id,
                    $this->site_id
                );
            }
        }

        return $list;
    }
}
