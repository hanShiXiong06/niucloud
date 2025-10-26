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

namespace addon\home_service\app\api\controller\goods;

use addon\home_service\app\service\api\goods\GoodsCollectService;
use core\base\BaseApiController;


/**
 * 服务收藏控制器
 * Class Goods
 * @package addon\home_service\app\api\controller\goods
 */
class GoodsCollect extends BaseApiController
{

    /**
     * 获取商品收藏列表
     * @return \think\Response
     */
    public function getMemberGoodsCollectList()
    {
        $data = $this->request->params([
            [ 'city_id', 0 ],
        ]);
        return success(( new GoodsCollectService() )->getMemberGoodsCollectList($data));
    }

    /**
     * 商品增加收藏
     */
    public function addGoodsCollect()
    {
        $data = $this->request->params([
            [ 'goods_id', 0 ],
        ]);
        ( new GoodsCollectService() )->addGoodsCollect($data);
        return success('COLLECT_SUCCESS');
    }

    /**
     * 商品取消收藏
     */
    public function cancelGoodsCollect()
    {
        $data = $this->request->params([
            [ 'goods_ids', [] ],
        ]);
        ( new GoodsCollectService() )->cancelGoodsCollect($data);
        return success('CANCEL_COLLECT_SUCCESS');
    }


}
