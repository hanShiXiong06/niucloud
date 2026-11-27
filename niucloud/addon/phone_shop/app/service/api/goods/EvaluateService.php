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

namespace addon\phone_shop\app\service\api\goods;

use addon\phone_shop\app\dict\goods\EvaluateDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\goods\Evaluate;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\goods\CoreGoodsEvaluateService;
use addon\phone_shop\app\service\core\goods\CoreGoodsEvaluateSubjectService;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use app\model\member\Member;
use core\exception\CommonException;
use core\base\BaseApiService;


/**
 * 商品评价服务层
 * Class EvaluateService
 * @package addon\phone_shop\app\service\admin\goods
 */
class EvaluateService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }

    /**
     * 获取商品评价列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = $this->displayFields();
        $order = 'topping desc,update_time desc,create_time desc';
        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id], ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]]])
            ->withSearch(['scores'], $where);
        $subject = $this->applyDisplaySubject($search_model, (int)($where['goods_id'] ?? 0));
        $search_model->field($field)->order($order)->append(['image_mid']);
        $list = $this->pageQuery($search_model);
        $list['subject'] = $subject;
        return $list;
    }

    /**
     * 获取商品评价信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = $this->displayFields();
        $info = $this->model->field($field)->where([['evaluate_id', '=', $id], ['site_id', '=', $this->site_id]])->append(['image_mid', 'image_big'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加商品评价
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {

        $member_info = (new Member())->where([['member_id', '=', $this->member_id]])->field('nickname, headimg')->findOrEmpty()->toArray();
        if (empty($member_info)) throw new CommonException();

        $config = (new CoreOrderConfigService())->getEvaluateConfig($this->site_id);

        foreach ($data['evaluate_array'] as $key => $val) {
            $orderId = (int)($val['order_id'] ?? 0);
            $orderGoodsId = (int)($val['order_goods_id'] ?? 0);
            $order = (new Order())->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['order_id', '=', $orderId],
                ['status', '=', OrderDict::FINISH],
            ])->field('order_id')->findOrEmpty();
            if ($order->isEmpty()) throw new CommonException('仅已完成的本人订单可以评价');

            $orderGoods = (new OrderGoods())->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['order_id', '=', $orderId],
                ['order_goods_id', '=', $orderGoodsId],
                ['status', '=', 1],
            ])->field('order_goods_id,order_id,goods_id,sku_id,goods_name,sku_name,goods_image')->findOrEmpty()->toArray();
            if (empty($orderGoods)) throw new CommonException('订单商品不存在或已失效');
            $exists = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['order_goods_id', '=', $orderGoodsId],
            ])->count();
            if ($exists > 0) throw new CommonException('该商品已经评价，请勿重复提交');

            $params = $val;
            $params['order_id'] = $orderId;
            $params['order_goods_id'] = $orderGoodsId;
            $params['goods_id'] = (int)$orderGoods['goods_id'];
            $params['order_goods_snapshot'] = $orderGoods;
            $params['is_verified_purchase'] = 1;
            $params['site_id'] = $this->site_id;
            $params['member_id'] = $this->member_id;
            $params['member_name'] = $member_info['nickname'];
            $params['member_head'] = $member_info['headimg'];
            $params['is_audit'] = $config['evaluate_is_to_examine'] == 1 ? 1 : 0;
            (new CoreGoodsEvaluateService)->addEvaluate($params);
        }

        return true;
    }

    /**
     * 获取商品评价统计
     * @param $goods_id
     * @return mixed
     * @throws \think\db\exception\DbException
     */
    public function getCount($goods_id)
    {
        $count = function(array $scores) use ($goods_id): int {
            $query = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['scores', 'in', $scores],
                ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]],
            ]);
            $this->applyDisplaySubject($query, (int)$goods_id);
            return $query->count();
        };
        $data['good_evaluate'] = $count([4, 5]);
        $data['centre_evaluate'] = $count([2, 3]);
        $data['wanting_centre_evaluate'] = $count([1]);
        return $data;
    }

    /**
     * 详情页展示评价
     * @param $goods_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getList($goods_id)
    {
        $data = [];
        $field = $this->displayFields();
        $order = 'topping desc,update_time desc,create_time desc';
        $query = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]],
        ]);
        $subject = $this->applyDisplaySubject($query, (int)$goods_id);
        $data['list'] = (clone $query)->field($field)->limit(3)->order($order)->append(['image_mid'])->select()->toArray();
        $data['count'] = $query->count();
        $data['subject'] = $subject;

        return $data;
    }

    /**
     * 获取评价详情
     * @param $order_id
     */
    public function getDetail($order_id)
    {
        $field = $this->displayFields();
        $list = $this->model->field($field)->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])->with([
            'order_goods' => function ($query) {
                $query->field('order_goods_id, site_id, goods_name, sku_name, goods_image, num, price')->append(['goods_image_thumb_mid']);
            }
        ])->append(['image_mid', 'image_big'])->select()->toArray();
        return $list;
    }

    private function displayFields(): string
    {
        return 'evaluate_id,site_id,order_id,order_goods_id,goods_id,category_id,category_name,category_path,goods_name,sku_name,goods_image,is_verified_purchase,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time,topping,update_time';
    }

    /**
     * 有末级分类时聚合同类评价；没有分类时保持原 goods_id 口径。
     * category_id=0 的历史评价仍在原商品下可见，升级迁移后会自动归类。
     */
    private function applyDisplaySubject($query, int $goodsId): array
    {
        $subject = (new CoreGoodsEvaluateSubjectService())->resolveDisplaySubject($this->site_id, $goodsId);
        $categoryId = (int)($subject['category_id'] ?? 0);
        if ($categoryId > 0) {
            $query->where(function($scope) use ($categoryId, $goodsId) {
                $scope->where('category_id', '=', $categoryId)
                    ->whereOr(function($legacy) use ($goodsId) {
                        $legacy->where('category_id', '=', 0)->where('goods_id', '=', $goodsId);
                    });
            });
        } else {
            $query->where('goods_id', '=', $goodsId);
        }
        return $subject;
    }
}
