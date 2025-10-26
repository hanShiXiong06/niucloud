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

namespace addon\home_service\app\service\api\order;

use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\model\order\Evaluate;
use addon\home_service\app\service\core\order\CoreGoodsEvaluateService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use app\model\member\Member;
use core\exception\ApiException;
use core\exception\CommonException;
use core\base\BaseApiService;
use think\facade\Db;


/**
 * 商品评价服务层
 * Class EvaluateService
 * @package addon\home_service\app\service\admin\order
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
        $field = 'evaluate_id,site_id,order_id,goods_id,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time,update_time';
        $order = 'create_time ' . $where['sort'];
        if (!empty($where['order'])) {
            $order = $where['order'] . ' ' . $where['sort'];
        }
        $search_model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT, EvaluateDict::AUDIT_ADOPT]],
        ])->field($field)->with([
            'order' => function ($query) {
                $query->field('order_id,order_name')->with(['itemImage']);
            },
        ])->order($order)->append(['image_mid', 'anonymous_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商品评价信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'evaluate_id,order_id,site_id,goods_id,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time';
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
        if (empty($member_info)) throw new ApiException('MEMBER_NOT_EXIST');

        $evaluate_info = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $data['order_id']]])->findOrEmpty();
        if (!$evaluate_info->isEmpty()) throw new ApiException('ORDER_IS_EVALUATE');


        $config = (new CoreOrderConfigService())->getEvaluateConfig($this->site_id);

        $save_data = $data;
        $save_data['site_id'] = $this->site_id;
        $save_data['member_id'] = $this->member_id;
        $save_data['member_name'] = $member_info['nickname'];
        $save_data['member_head'] = $member_info['headimg'];
        $save_data['is_audit'] = $config['evaluate_is_to_examine'] ? EvaluateDict::AUDIT : EvaluateDict::AUDIT_NO;
        if ($save_data['is_audit'] == EvaluateDict::AUDIT && $config['auto_adopt_examine'] == 1) {
            $save_data['auto_adopt_time'] = time() + $config['auto_adopt_examine_time'] * 24 * 60 * 60;
        }
        Db::startTrans();
        try {
            (new CoreGoodsEvaluateService)->addEvaluate($save_data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取商品评价统计
     * @param $goods_id
     * @return mixed
     * @throws \think\db\exception\DbException
     */
    public function getCount($goods_id)
    {
        $data['good_evaluate'] = $this->model->where([['goods_id', '=', $goods_id], ['scores', 'in', [4, 5]], ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]]])->count();
        $data['centre_evaluate'] = $this->model->where([['goods_id', '=', $goods_id], ['scores', 'in', [2, 3]], ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]]])->count();
        $data['wanting_centre_evaluate'] = $this->model->where([['goods_id', '=', $goods_id], ['scores', 'in', [1]], ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]]])->count();
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

    /**
     * 获取商品评价列表（带分页）
     * @param int $goods_id 商品ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodsEvaluateList($goods_id)
    {
        $field = 'evaluate_id,site_id,order_id,goods_id,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time,update_time';
        $order = 'update_time desc,create_time desc';
        // 共同查询条件
        $where = [
            ['goods_id', '=', $goods_id],
            ['site_id', '=', $this->site_id],
            ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]]
        ];
        // 获取评价列表
        $search_model = $this->model->field($field)
            ->where($where)
            ->order($order)
            ->with(
                [
                    'member' => function ($query) {
                        $query->field('nickname, member_id, headimg');
                    }
                ])
            ->append(['image_mid']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取评价详情
     * @param $order_id
     */
    public function getDetail($order_id)
    {
        $field = 'evaluate_id,order_id,site_id,goods_id,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time';
        $list = $this->model->field($field)->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])->with([
            'order_goods' => function ($query) {
                $query->field(' site_id, goods_name, sku_name, goods_image, num, price')->append(['goods_image_thumb_mid']);
            }
        ])->append(['image_mid', 'image_big'])->select()->toArray();
        return $list;
    }

    /**
     * 获取指定商品和师傅的最新评价
     * @param int $goods_id 商品ID
     * @param int $technician_id 师傅ID
     * @return array
     */
    public function getLatestByGoodsAndTechnician(int $goods_id, int $technician_id)
    {
        $field = 'evaluate_id,order_id,site_id,goods_id,technician_id,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time,update_time';
        $info = $this->model->field($field)
            ->where([
                ['site_id', '=', $this->site_id],
                ['goods_id', '=', $goods_id],
                ['technician_id', '=', $technician_id],
                ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]]
            ])
            ->order('create_time desc')
            ->append(['image_mid', 'image_big'])
            ->findOrEmpty()
            ->toArray();


        return $info;
    }


}
