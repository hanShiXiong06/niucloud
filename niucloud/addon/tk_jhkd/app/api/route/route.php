<?php

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;
Route::group("tk_jhkd", function () {
    //易达回掉通知
    Route::post('yidanotice', 'addon\tk_jhkd\app\api\controller\Order@yidaNotice');
    //云洋回掉通知
    Route::post('yunyangnotice', 'addon\tk_jhkd\app\api\controller\Order@yunyangNotice');
    //辛达回掉通知
    Route::post('xindanotice', 'addon\tk_jhkd\app\api\controller\Order@xindaNotice');
    //快递鸟回掉通知
    Route::post('kdniaonotice', 'addon\tk_jhkd\app\api\controller\Order@kdniaoNotice');
    //亿速回调接口
    Route::post('yisunotice', 'addon\tk_jhkd\app\api\controller\Order@yisuNotice');
});
/**
 * 路由（注意最好group路由名称与插件名称一致，防止路由冲突）
 */
Route::group("tk_jhkd", function () {
    //获取通知列表
//    Route::get('ceshi', 'addon\tk_jhkd\app\api\controller\Ceshi@ceshi');
    //获取通知列表
    Route::get('notice', 'addon\tk_jhkd\app\api\controller\Notice@list');
    //地理位置逆编码
    Route::get('regeo', 'addon\tk_jhkd\app\api\controller\Amap@regeo');
    //帮助中心
    Route::get('help', 'addon\tk_jhkd\app\api\controller\Help@lists');
    //品牌列表
    Route::get('brand', 'addon\tk_jhkd\app\api\controller\Brand@list');
    /***************************************************** 优惠券 ****************************************************/
    // 获取优惠券列表
    Route::get('coupon', 'addon\tk_jhkd\app\api\controller\Coupon@lists');
    // 获取优惠券列表供组件调用
    Route::get('coupon/components', 'addon\tk_jhkd\app\api\controller\Coupon@components');
    // 详情
    Route::get('coupon/:id', 'addon\tk_jhkd\app\api\controller\Coupon@detail');
    // 优惠券二维码
    Route::get('coupon/qrcode/:id', 'addon\tk_jhkd\app\api\controller\Coupon@qrcode');
    Route::get('config/invoice', 'addon\tk_jhkd\app\api\controller\Config@invoice');
    //优惠券类型
    Route::get('coupon_type', 'addon\tk_jhkd\app\api\controller\Coupon@getCouponType');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)
    ->middleware(ApiLog::class);


Route::group("tk_jhkd", function () {
    /**************************************************** 优惠券接口 ****************************************************/
    //添加优惠券
    Route::post('coupon', 'addon\tk_jhkd\app\api\controller\Coupon@receive');

    //优惠券列表
    Route::get('member/coupon/list', 'addon\tk_jhkd\app\api\controller\Coupon@getUseMemberCouponList');

    //优惠券数量
    Route::get('member/coupon/count', 'addon\tk_jhkd\app\api\controller\Coupon@memberCouponCount');

    //优惠券状态数量
    Route::get('member/coupon/status_count', 'addon\tk_jhkd\app\api\controller\Coupon@memberCouponStatusCount');


    /***************************************************** 地址列表管理 ****************************************************/
    //地址列表
    Route::get('address', 'addon\tk_jhkd\app\api\controller\Address@lists');
    //地址详情
    Route::get('address/:id', 'addon\tk_jhkd\app\api\controller\Address@info');
    //地址详情,基于框架地址库
    Route::get('addressinfo/:id', 'addon\tk_jhkd\app\api\controller\Address@getInfo');
    //地址解析
    Route::get('address/fanyiaddress','addon\tk_jhkd\app\api\controller\Address@fanyiAdress');
    // 协议
    Route::get('agreement/:key', 'addon\tk_jhkd\app\api\controller\Agreement@info');
    //添加地址
    Route::post('address', 'addon\tk_jhkd\app\api\controller\Address@add');
    //编辑地址
    Route::put('address/:id', 'addon\tk_jhkd\app\api\controller\Address@edit');
    //删除地址
    Route::delete('address/:id', 'addon\tk_jhkd\app\api\controller\Address@del');
    //预下单
    Route::post('preorder', 'addon\tk_jhkd\app\api\controller\Order@preOrder');
    //创建订单
    Route::post('createorder', 'addon\tk_jhkd\app\api\controller\Order@createOrder');
    //订单状态
    Route::get('getorderstatus', 'addon\tk_jhkd\app\api\controller\Order@getOrderStatus');
    //订单列表
    Route::get('getorderlist', 'addon\tk_jhkd\app\api\controller\Order@getOrderList');
    //补差价订单
    Route::get('getorderaddlist', 'addon\tk_jhkd\app\api\controller\OrderAdd@getOrderList');
    //订单状态
    Route::get('getorderaddstatus', 'addon\tk_jhkd\app\api\controller\OrderAdd@getOrderStatus');
    Route::delete('deleteorderadd/:id', 'addon\tk_jhkd\app\api\controller\OrderAdd@deleteOrder');
    //订单详情
    Route::get('getorderinfo/:id', 'addon\tk_jhkd\app\api\controller\Order@getOrderInfo');
    //删除订单
    Route::delete('deleteorder/:id', 'addon\tk_jhkd\app\api\controller\Order@deleteOrder');
    //申请退款
    Route::post('applyrefund', 'addon\tk_jhkd\app\api\controller\Order@applyRefund');
    //查询运单轨迹
    Route::get('getdeliveryinfo/:deliveryid', 'addon\tk_jhkd\app\api\controller\Order@deliveryInfo');
    //关闭订单
    Route::get('closeorder/:id', 'addon\tk_jhkd\app\api\controller\Order@closeOrder');
    Route::post('checkfenxiao', 'addon\tk_jhkd\app\api\controller\Fenxiao@checkFenxiao');
    //检查未支付订单
    Route::get('checkaddpay', 'addon\tk_jhkd\app\api\controller\Order@checkAddPay');
    //统计分销订单
    Route::get('fenxiao/getfenxiaofnfo','addon\tk_jhkd\app\api\controller\Fenxiao@getFenxiaoInfo');
    //2.0分销订单统计
    Route::get('fenxiao/getorderdata','addon\tk_jhkd\app\api\controller\Fenxiao@getOrderData');
    //分销会员统一入口
    Route::get('fenxiao/getfenxiaomember','addon\tk_jhkd\app\api\controller\Fenxiao@getFenxiaoMember');
    //订单统一入口
    Route::get('fenxiao/getfenxiaoorder','addon\tk_jhkd\app\api\controller\Fenxiao@getFenxiaoOrder');
    Route::get('fenxiao/getselffenxiaoorder','addon\tk_jhkd\app\api\controller\Fenxiao@getSelfFenxiaoOrder');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)
    ->middleware(ApiLog::class);
