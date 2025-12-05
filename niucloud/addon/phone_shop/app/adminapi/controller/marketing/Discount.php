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

namespace addon\phone_shop\app\adminapi\controller\marketing;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\service\admin\marketing\DiscountService;
use core\base\BaseAdminController;


/**
 * 限时折扣控制器
 * Class Discount
 * @package addon\phone_shop\app\adminapi\controller\marketing
 */
class Discount extends BaseAdminController
{

    public function lists()
    {
        $data = $this->request->params([
            [ "active_name", "" ],
            [ "active_status", "" ],
        ]);
        return success(( new DiscountService() )->getPage($data));
    }

    /**
     * 详情
     * @param int $active_id
     * @return \think\Response
     */
    public function detail(int $active_id)
    {
        return success(( new DiscountService() )->getDetail($active_id));
    }

    /**
     * 添加限时折扣
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            [ "active_name", '' ],
            [ "active_desc", "" ],
            [ "active_class_category", '' ],
            [ "relate_member", '' ],
            [ "active_value", '' ],
            [ "start_time", '' ],
            [ "end_time", '' ],
            [ "goods_data", '{}' ],
        ]);

        $id = ( new DiscountService() )->add($data);
        return success('ADD_SUCCESS', [ 'id' => $id ]);
    }

    /**
     * 限时折扣编辑
     * @param int $active_id
     * @return \think\Response
     */
    public function edit(int $active_id)
    {
        $data = $this->request->params([
            [ "active_name", '' ],
            [ "active_desc", "" ],
            [ "active_class_category", '' ],
            [ "relate_member", '' ],
            [ "active_value", '' ],
            [ "start_time", '' ],
            [ "end_time", '' ],
            [ "goods_data", '{}' ],
        ]);

        ( new DiscountService() )->edit($active_id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 获取活动状态
     * @return \think\Response
     */
    public function status()
    {
        return success(ActiveDict::getStatus());
    }

    /**
     * 删除活动
     * @param int $active_id
     * @return \think\Response
     */
    public function del(int $active_id)
    {
        ( new DiscountService() )->del($active_id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 活动关闭
     * @param int $active_id
     * @return \think\Response
     */
    public function close(int $active_id)
    {
        ( new DiscountService() )->discountClose($active_id);
        return success('SUCCESS');
    }

    /**
     * 参与订单
     * @param int $active_id
     * @return \think\Response
     */
    public function order(int $active_id)
    {
        $data = $this->request->params([
            [ 'status', '' ],
            [ 'create_time', [] ],
            [ 'pay_time', [] ],
            [ 'search_type', 'order_no' ],
            [ 'search_name', '' ],
        ]);

        return success(( new DiscountService() )->order($active_id, $data));
    }

    /**
     * 参与会员
     * @param int $active_id
     * @return \think\Response
     * @throws \think\db\exception\DbException
     */
    public function member(int $active_id)
    {
        $data = $this->request->params([
            [ 'keyword', '' ],
        ]);

        return success(( new DiscountService() )->member($active_id, $data));
    }

    /**
     * 参与商品
     * @param int $active_id
     * @return \think\Response
     * @throws \think\db\exception\DbException
     */
    public function goods(int $active_id)
    {
        $data = $this->request->params([
            [ 'keyword', '' ],
        ]);

        return success(( new DiscountService() )->goods($active_id, $data));
    }

    /**
     * 获取轮播图配置
     * @return void
     */
    public function banner()
    {
        return success(( new DiscountService() )->getDiscountBannerConfig());
    }

    /**
     * 设置轮播图配置
     * @return \think\Response
     */
    public function setBanner()
    {
        $data = $this->request->params([
            [ 'list', [] ],
        ]);
        $res = ( new DiscountService() )->setConfig('SHOP_DISCOUNT_BANNER_CONFIG', $data[ 'list' ]);
        return success('SUCCESS', $res);
    }

}
