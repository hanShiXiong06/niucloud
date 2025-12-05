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

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;


/**
 * 商城系统
 */
Route::group('phone_shop', function() {

    /***************************************************** 商品 ****************************************************/

    // 获取商品列表
    Route::get('goods/pages', 'addon\phone_shop\app\api\controller\goods\Goods@pages');

    // 获取商品详情
    Route::get('goods/detail', 'addon\phone_shop\app\api\controller\goods\Goods@detail');

    // 获取商品详情
    Route::get('goods/sku/:sku_id', 'addon\phone_shop\app\api\controller\goods\Goods@sku');

    // 获取商品分类配置
    Route::get('goods/category/config', 'addon\phone_shop\app\api\controller\goods\GoodsCategory@getGoodsCategoryConfig');

    // 获取商品分类树结构
    Route::get('goods/category/tree', 'addon\phone_shop\app\api\controller\goods\GoodsCategory@tree');

    // 获取商品分类列表
    Route::get('goods/category/list', 'addon\phone_shop\app\api\controller\goods\GoodsCategory@lists');

    // 获取商品详情
    Route::get('goods/category/:id', 'addon\phone_shop\app\api\controller\goods\GoodsCategory@getInfo');

    // 获取商品列表供组件调用
    Route::get('goods/components', 'addon\phone_shop\app\api\controller\goods\Goods@components');
    
    //商品品牌列表
    Route::get('goods/brand/list', 'addon\phone_shop\app\api\controller\goods\Brand@lists');

    /***************************************************** 评价 ****************************************************/
    // 获取 评价设置
    Route::get('goods/evaluate/config', 'addon\phone_shop\app\api\controller\Config@evaluate');

    // 获取 评价列表
    Route::get('goods/evaluate', 'addon\phone_shop\app\api\controller\goods\Evaluate@pages');

    // 获取 评价列表
    Route::get('goods/evaluate/list', 'addon\phone_shop\app\api\controller\goods\Evaluate@lists');

    // 获取 评价数量
    Route::get('goods/evaluate/count', 'addon\phone_shop\app\api\controller\goods\Evaluate@count');

    // 获取 评价详情（评价）
    Route::get('goods/evaluate/:id', 'addon\phone_shop\app\api\controller\goods\Evaluate@info');

    // 添加 商品评价
    Route::post('goods/evaluate', 'addon\phone_shop\app\api\controller\goods\Evaluate@add');

    // 评价 （订单页）
    Route::get('order/evaluate/:id', 'addon\phone_shop\app\api\controller\goods\Evaluate@getEvaluate');

    /***************************************************** 优惠券 ****************************************************/
    // 获取优惠券列表
    Route::get('coupon', 'addon\phone_shop\app\api\controller\marketing\Coupon@lists');

    // 获取优惠券列表供组件调用
    Route::get('coupon/components', 'addon\phone_shop\app\api\controller\marketing\Coupon@components');

    // 详情
    Route::get('coupon/:id', 'addon\phone_shop\app\api\controller\marketing\Coupon@detail');

    // 优惠券二维码
    Route::get('coupon/qrcode/:id', 'addon\phone_shop\app\api\controller\marketing\Coupon@qrcode');

    Route::get('config/invoice', 'addon\phone_shop\app\api\controller\Config@invoice');
    //优惠券类型
    Route::get('coupon_type', 'addon\phone_shop\app\api\controller\marketing\Coupon@getCouponType');

    /***************************************************** 限时折扣 ****************************************************/

    //轮播图配置
    Route::get('discount/config', 'addon\phone_shop\app\api\controller\marketing\Discount@config');

    // 限时折扣商品列表
    Route::get('discount/goods', 'addon\phone_shop\app\api\controller\marketing\Discount@goods');

    // 限时折扣列表
    Route::get('discount', 'addon\phone_shop\app\api\controller\marketing\Discount@lists');

    /*****************************************************  积分商城 ****************************************************/

    //积分商城列表
    Route::get('exchange', 'addon\phone_shop\app\api\controller\exchange\Exchange@lists');

    //积分商城商品详情
    Route::get('exchange/goods/:id', 'addon\phone_shop\app\api\controller\exchange\Exchange@detail');

    //获取商品列表供组件调用
    Route::get('exchange/components', 'addon\phone_shop\app\api\controller\exchange\Exchange@components');

    //获取用户当前积分数供组件调用
    Route::get('exchange/point', 'addon\phone_shop\app\api\controller\exchange\Exchange@getPointInfo');

    //积分商城订单计算
    Route::get('exchange_order/calculate', 'addon\phone_shop\app\api\controller\exchange\OrderCreate@calculate');

    //积分商城订单创建
    Route::post('exchange_order/create', 'addon\phone_shop\app\api\controller\exchange\OrderCreate@create');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class)//false表示不验证登录
    ->middleware(ApiLog::class);


Route::group('phone_shop', function() {

    /***************************************************** 购物车 ****************************************************/

    // 添加购物车
    Route::post('cart', 'addon\phone_shop\app\api\controller\cart\Cart@add');

    // 编辑购物车数量
    Route::put('cart', 'addon\phone_shop\app\api\controller\cart\Cart@edit');

    // 购物车删除
    Route::put('cart/delete', 'addon\phone_shop\app\api\controller\cart\Cart@del');

    // 清空购物车
    Route::delete('cart/clear', 'addon\phone_shop\app\api\controller\cart\Cart@clear');

    // 购物车列表
    Route::get('cart', 'addon\phone_shop\app\api\controller\cart\Cart@lists');

    // 购物车商品列表
    Route::get('cart/goods', 'addon\phone_shop\app\api\controller\cart\Cart@goodsLists');

    // 购物车数量
    Route::get('cart/sum', 'addon\phone_shop\app\api\controller\cart\Cart@sum');

    /***************************************************** 订单 ****************************************************/

    //列表
    Route::get('order', 'addon\phone_shop\app\api\controller\order\Order@lists');

    //数量
    Route::get('order/num', 'addon\phone_shop\app\api\controller\order\Order@getNum');

    //详情
    Route::get('order/:order_id', 'addon\phone_shop\app\api\controller\order\Order@detail');

    //订单状态
    Route::get('order/status', 'addon\phone_shop\app\api\controller\order\Order@orderStatus');

    // 订单设置
    Route::get('order/config', 'addon\phone_shop\app\api\controller\order\Order@getConfig');

    //创建订单
    Route::post('order_create/create', 'addon\phone_shop\app\api\controller\order\OrderCreate@create');

    //计算
    Route::get('order_create/calculate', 'addon\phone_shop\app\api\controller\order\OrderCreate@calculate');

    //查询优惠券
    Route::get('order_create/coupon', 'addon\phone_shop\app\api\controller\order\OrderCreate@getCoupon');

    // 查询自提点
    Route::get('order_create/store', 'addon\phone_shop\app\api\controller\order\OrderCreate@getStore');

    //获取订单确认数据
    Route::get('confirm', 'addon\phone_shop\app\api\controller\order\OrderCreate@confirm');

    // 订单关闭
    Route::put('order/close/:id', 'addon\phone_shop\app\api\controller\order\Order@orderClose');

    // 订单完成
    Route::put('order/finish/:id', 'addon\phone_shop\app\api\controller\order\Order@orderFinish');

    //物流跟踪
    Route::get('order/logistics', 'addon\phone_shop\app\api\controller\order\Order@getPackage');

    //添加优惠券
    Route::post('coupon', 'addon\phone_shop\app\api\controller\marketing\Coupon@receive');

    //优惠券列表
    Route::get('member/coupon', 'addon\phone_shop\app\api\controller\marketing\Coupon@memberCouponlists');

    //优惠券数量
    Route::get('member/coupon/count', 'addon\phone_shop\app\api\controller\marketing\Coupon@memberCouponCount');

    //优惠券状态数量
    Route::get('member/coupon/status_count', 'addon\phone_shop\app\api\controller\marketing\Coupon@memberCouponStatusCount');

    //商品收藏列表
    Route::get('goods/collect', 'addon\phone_shop\app\api\controller\goods\GoodsCollect@getMemberGoodsCollectList');

    //商品添加收藏
    Route::post('goods/collect/:goods_id', 'addon\phone_shop\app\api\controller\goods\GoodsCollect@addGoodsCollect');

    //商品取消收藏
    Route::delete('goods/collect/:goods_id', 'addon\phone_shop\app\api\controller\goods\GoodsCollect@cancelGoodsCollect');

    //订单维权 列表
    Route::get('order/refund', 'addon\phone_shop\app\api\controller\refund\Refund@lists');

    //订单维权 列表
    Route::get('order/refund/:order_refund_no', 'addon\phone_shop\app\api\controller\refund\Refund@detail');

    // 查询订单项可退款信息
    Route::get('refund/refund_data', 'addon\phone_shop\app\api\controller\refund\Refund@getRefundData');

    // 查询订单项退款信息
    Route::get('refund/refund_data_by_no', 'addon\phone_shop\app\api\controller\refund\Refund@getRefundDataByOrderRefundNo');

    // 申请维权
    Route::post('refund/apply', 'addon\phone_shop\app\api\controller\refund\Refund@apply');

    // 修改退款申请
    Route::put('refund/:order_refund_no', 'addon\phone_shop\app\api\controller\refund\Refund@edit');

    // 维权退货
    Route::post('refund/delivery/:order_refund_no', 'addon\phone_shop\app\api\controller\refund\Refund@delivery');

    // 修改维权退货信息
    Route::put('refund/delivery/:order_refund_no', 'addon\phone_shop\app\api\controller\refund\Refund@editDelivery');

    // 取消维权
    Route::put('refund/close/:order_refund_no', 'addon\phone_shop\app\api\controller\refund\Refund@close');

    // 退款原因
    Route::get('refund/reason', 'addon\phone_shop\app\api\controller\refund\Refund@getRefundReason');

    // 退款方式
    Route::get('order/refund/type', 'addon\phone_shop\app\api\controller\refund\Refund@getRefundType');
    // 操作员上下架
    Route::get('goods/operation/:goods_id', 'addon\phone_shop\app\api\controller\goods\Goods@operationGoods');
    // 添加商品
    Route::post('goods/add', 'addon\phone_shop\app\api\controller\goods\Goods@add');

    // 修改商品
    Route::put('goods/:goods_id', 'addon\phone_shop\app\api\controller\goods\Goods@put');
  // 获取内存列表
  Route::get('goods/memory/list', 'addon\phone_shop\app\api\controller\goods\Goods@getMemoryList');
  

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)//表示验证登录
    ->middleware(ApiLog::class);
