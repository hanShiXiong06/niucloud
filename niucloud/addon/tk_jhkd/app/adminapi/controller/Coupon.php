<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\adminapi\controller;
use addon\tk_jhkd\app\dict\coupon\CouponDict;
use addon\tk_jhkd\app\service\admin\coupon\CouponService;
use core\base\BaseAdminController;


/**
 * 优惠券控制器
 */
class Coupon extends BaseAdminController
{

    public function init()
    {
//        $data = $this->request->params([]);
//        return success(( new CouponService() )->getInit());
    }

    /**
     * 获取商品优惠券列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            [ "title", "" ],
            [ "status", "" ]
        ]);
        return success(( new CouponService() )->getPage($data));
    }

    /**
     * 商品优惠券详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success(( new CouponService() )->getInfo($id));
    }

    /**
     * 添加商品优惠券
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            [ "title", '' ],
            [ "receive_time", "" ],
            [ "remain_count", 0 ],
            [ "limit", '' ],
            [ "limit_count", '' ],
            [ "price", 1 ],
            [ "min_condition_money", '' ],
            [ "type", 1 ],
            [ "receive_type", '' ],
            [ "valid_type", '' ],
            [ "length", '' ],
            [ "valid_time", '' ],
            [ "goods_ids", [] ],
            [ "goods_category_ids", "" ],
            [ 'receive_type_time', '' ],
            [ 'threshold', '' ]
        ]);
        $id = ( new CouponService() )->add($data);
        return success('ADD_SUCCESS', [ 'id' => $id ]);
    }

    /**
     * 商品优惠券编辑
     * @param $id
     */
    public function edit($id)
    {
        $data = $this->request->params([
            [ "title", '' ],
            [ "receive_time", "" ],
            [ "remain_count", 0 ],
            [ "limit", '' ],
            [ "limit_count", '' ],
            [ "price", 1 ],
            [ "min_condition_money", '' ],
            [ "type", 1 ],
            [ "receive_type", '' ],
            [ "valid_type", '' ],
            [ "length", '' ],
            [ "valid_time", '' ],
            [ "goods_ids", [] ],
            [ "goods_category_ids", "" ],
            [ 'receive_type_time', '' ],
            [ 'threshold', '' ]
        ]);
        ( new CouponService() )->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 商品优惠券删除
     * @param $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        ( new CouponService() )->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 会员领取详情
     */
    public function getMemberCoupon()
    {
        $data = $this->request->params([
            [ 'id', '' ],
            [ 'keywords', '' ]
        ]);
        return success(( new CouponService() )->getMemberCoupon($data));
    }

    /**
     * 领取状态编辑
     */
    public function setCouponStatus($status)
    {
        $data = $this->request->params([
            [ 'id', '' ],
        ]);
        ( new CouponService() )->setStatus($data[ 'id' ], $status);
        return success('EDIT_SUCCESS');
    }

    /**
     * 优惠券选择分页列表
     * @return \think\Response
     */
    public function select()
    {
        $data = $this->request->params([
            [ "title", "" ],
            [ "verify_coupon_ids", "" ]
        ]);
        return success(( new CouponService() )->getSelectPage($data));
    }

    /**
     * 查询选中的优惠券
     * @return \think\Response
     */
    public function getSelectedLists()
    {
        $data = $this->request->params([
            [ "coupon_id", '' ],
        ]);
        return success(( new CouponService() )->getSelectedList($data[ 'coupon_id' ]));
    }

    /**
     * 优惠券关闭
     */
    public function couponInvalid($id)
    {
        ( new CouponService() )->couponInvalid($id);
        return success('SUCCESS');
    }

    /**
     * 获取优惠券状态
     */
    public function getCouponStatus()
    {
        return success(data:CouponDict::getStatus());
    }

}
