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

namespace addon\home_service\app\api\controller\order;

use addon\home_service\app\service\api\order\EvaluateService;
use core\base\BaseApiController;


/**
 * 商品评价控制器
 * Class Evaluate
 * @package addon\home_service\app\adminapi\controller\order
 */
class Evaluate extends BaseApiController
{
    /**
     * 获取商品评价列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ['order', ''],
            ['sort', 'desc'],
        ]);
        return success((new EvaluateService())->getPage($data));
    }

    /**
     * 商品评价详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new EvaluateService())->getInfo($id));
    }

    /**
     * 添加商品评价
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["order_id", 0],
            ["order_goods_id", 0],
            ["store_id", 0],
            ["technician_id", 0],
            ["goods_id", 0],
            ["content", ""],
            ["images", []],
            ["scores", 5],
            ["is_anonymous", 2],
        ]);
        (new EvaluateService())->add($data);
        return success('HOME_SERVICE_GOODS_EVALUATE_SUCCESS');
    }

    /**
     * 评价统计
     * @return \think\Response
     * @throws \think\db\exception\DbException
     */
    public function count()
    {
        $data = $this->request->params([
            ['goods_id', 0],
        ]);
        return success((new EvaluateService())->getCount($data['goods_id']));
    }


    /**
     * 商品详情展示
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function goodsEvaluate()
    {
        $data = $this->request->params([
            ['goods_id', 0],
        ]);
        return success((new EvaluateService())->getGoodsEvaluateList(
            $data['goods_id']
        ));
    }


    /**
     * 评价信息
     * @param $id
     */
    public function getEvaluate($id)
    {
        return success((new EvaluateService())->getDetail($id));
    }
}
