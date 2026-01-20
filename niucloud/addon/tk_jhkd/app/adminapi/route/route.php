<?php

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

/**
 * 路由（注意最好group路由名称与插件名称一致，防止路由冲突）
 */
Route::group("tk_jhkd", function () {
    //Route::get('ceshi', 'addon\tk_jhkd\app\adminapi\controller\Ceshi@ceshi');
    //检查站点是否有快递分销插件
    Route::get('checkfenxiao', 'addon\tk_jhkd\app\adminapi\controller\Config@checkFenxiao');
    Route::get('getlink', 'addon\tk_jhkd\app\adminapi\controller\order\Order@getLink');
    Route::get('index', 'addon\tk_jhkd\app\adminapi\controller\Index@index');
    //配置列表
    Route::get('platform/list', 'addon\tk_jhkd\app\adminapi\controller\config\Platform@platformList');
    //配置详情
    Route::get('platform/config/:type', 'addon\tk_jhkd\app\adminapi\controller\config\Platform@platformConfig');
    //配置修改
    Route::put('platform/config/:type', 'addon\tk_jhkd\app\adminapi\controller\config\Platform@editPlatform');
    //配置获取
    Route::get('getconfig', 'addon\tk_jhkd\app\adminapi\controller\Config@getConfig');
    //余额获取
    Route::get('getbalance', 'addon\tk_jhkd\app\adminapi\controller\Config@getBalance');
    //增加小程序插件
    Route::get('addplugin', 'addon\tk_jhkd\app\adminapi\controller\Config@addPlugin');
    //设置配置
    Route::post('setconfig', 'addon\tk_jhkd\app\adminapi\controller\Config@setConfig');
    //协议
    Route::get('agreement', 'addon\tk_jhkd\app\adminapi\controller\Agreement@lists');
    //协议详情
    Route::get('agreement/:key', 'addon\tk_jhkd\app\adminapi\controller\Agreement@info');
    //配置协议
    Route::put('agreement/:key', 'addon\tk_jhkd\app\adminapi\controller\Agreement@edit');
    //聚合快递品牌列表列表
    Route::get('brand', 'addon\tk_jhkd\app\adminapi\controller\TkjhkdBrand@lists');
    //聚合快递品牌列表详情
    Route::get('brand/:id', 'addon\tk_jhkd\app\adminapi\controller\TkjhkdBrand@info');
    //添加聚合快递品牌列表
    Route::post('brand', 'addon\tk_jhkd\app\adminapi\controller\TkjhkdBrand@add');
    //编辑聚合快递品牌列表
    Route::put('brand/:id', 'addon\tk_jhkd\app\adminapi\controller\TkjhkdBrand@edit');
    //删除聚合快递品牌列表
    Route::delete('brand/:id', 'addon\tk_jhkd\app\adminapi\controller\TkjhkdBrand@del');
    /***************************************************** 短信配置 ****************************************************/
    //快递配置列表
    Route::get('delivery/list', 'addon\tk_jhkd\app\adminapi\controller\DeliveryConfig@deliveryList');
    //快递配置详情
    Route::get('delivery/delivery/:delivery_type', 'addon\tk_jhkd\app\adminapi\controller\DeliveryConfig@deliveryConfig');
    //快递配置修改
    Route::put('delivery/delivery/:delivery_type', 'addon\tk_jhkd\app\adminapi\controller\DeliveryConfig@editDelivery');
    //对接商城订单快递发货开始
    Route::get('shop/order/delivery', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@orderDelivery');
    //对接商城订单发货类型
    Route::get('shop/order/delivery/type', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@getDeliveryType');
    //对接商城商家发货地址库
    Route::get('shop/order/sendaddress', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@getSendAddress');
    //快递shop预下单
    Route::post('shop/order/preorder', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@preOrder');
    //订单列表
    Route::get('shop/order/lists', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@lists');
    //订单详情
    Route::get('shop/order/detail', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@detail');
    //订单发货
    Route::post('shop/order/delivery', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@orderDelivery');
    //取消发货
    Route::post('shop/order/cancelorder', 'addon\tk_jhkd\app\adminapi\controller\shop\Order@cancelOrder');
    //更改订单状态
    Route::post('changestatus', 'addon\tk_jhkd\app\adminapi\controller\order\Order@changeStatus');
    //手动发送补差价通知
    Route::get('addorder/sendnotice/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@sendNotice');
    //订单备注
    Route::post('remarkadd', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@remark');
    //订单备注
    Route::post('editordermoney', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@editOrderMoney');
    /************************************************** 优惠券相关接口 *****************************************************/
    //优惠券列表
    Route::get('coupon', 'addon\tk_jhkd\app\adminapi\controller\Coupon@lists');

    //优惠券初始化信息
    Route::get('coupon/init', 'addon\tk_jhkd\app\adminapi\controller\Coupon@init');

    //添加优惠券
    Route::post('coupon', 'addon\tk_jhkd\app\adminapi\controller\Coupon@add');

    //优惠券领取记录
    Route::get('coupon/records', 'addon\tk_jhkd\app\adminapi\controller\Coupon@getMemberCoupon');

    //优惠券详情
    Route::get('coupon/detail/:id', 'addon\tk_jhkd\app\adminapi\controller\Coupon@info');

    //编辑优惠券
    Route::put('coupon/edit/:id', 'addon\tk_jhkd\app\adminapi\controller\Coupon@edit');

    //优惠券设置状态
    Route::put('coupon/setstatus/:status', 'addon\tk_jhkd\app\adminapi\controller\Coupon@setCouponStatus');

    //优惠券失效
    Route::put('coupon/invalid/:id', 'addon\tk_jhkd\app\adminapi\controller\Coupon@couponInvalid');

    //删除优惠券
    Route::delete('coupon/:id', 'addon\tk_jhkd\app\adminapi\controller\Coupon@del');

    //查询优惠券选择分页列表
    Route::get('coupon/select', 'addon\tk_jhkd\app\adminapi\controller\Coupon@select');

    //查询选中的优惠券
    Route::get('coupon/selected', 'addon\tk_jhkd\app\adminapi\controller\Coupon@getSelectedLists');

    //优惠券状态列表
    Route::get('coupon/status', 'addon\tk_jhkd\app\adminapi\controller\Coupon@getCouponStatus');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);


// USER_CODE_BEGIN -- tkjhkd_help

Route::group('tk_jhkd', function () {

    //帮助中心列表
    Route::get('help', 'addon\tk_jhkd\app\adminapi\controller\help\Help@lists');
    //帮助中心详情
    Route::get('help/:id', 'addon\tk_jhkd\app\adminapi\controller\help\Help@info');
    Route::get('asynchelp', 'addon\tk_jhkd\app\adminapi\controller\help\Help@asyncHelp');
    //添加帮助中心
    Route::post('help', 'addon\tk_jhkd\app\adminapi\controller\help\Help@add');
    //编辑帮助中心
    Route::put('help/:id', 'addon\tk_jhkd\app\adminapi\controller\help\Help@edit');
    //删除帮助中心
    Route::delete('help/:id', 'addon\tk_jhkd\app\adminapi\controller\help\Help@del');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- tkjhkd_help

// USER_CODE_BEGIN -- tkjhkd_order

Route::group('tk_jhkd', function () {

    //订单列列表
    Route::get('order', 'addon\tk_jhkd\app\adminapi\controller\order\Order@lists');
    //订单列详情
    Route::get('order/:id', 'addon\tk_jhkd\app\adminapi\controller\order\Order@info');
    Route::get('fxorder/:id', 'addon\tk_jhkd\app\adminapi\controller\order\Order@fxinfo');
    //订单佣金手动触发
    Route::post('order/commissionorder/:id', 'addon\tk_jhkd\app\adminapi\controller\order\Order@commissionOrder');
    //订单备注
    Route::post('remark', 'addon\tk_jhkd\app\adminapi\controller\order\Order@remark');
    //发单
    Route::get('sendorder/:order_id', 'addon\tk_jhkd\app\adminapi\controller\order\Order@sendOrder');
    //退款取消发单
    Route::post('cancelorder', 'addon\tk_jhkd\app\adminapi\controller\order\Order@cancelOrder');
    //添加订单列
    Route::post('order', 'addon\tk_jhkd\app\adminapi\controller\order\Order@add');
    //编辑订单列
    Route::put('order/:id', 'addon\tk_jhkd\app\adminapi\controller\order\Order@edit');
    //删除订单列
    Route::delete('order/:id', 'addon\tk_jhkd\app\adminapi\controller\order\Order@del');
    //会员列表
    Route::get('member', 'addon\tk_jhkd\app\adminapi\controller\order\Order@getMemberAll');
    //获取 支付类型
    Route::get('order/pay/type', 'addon\tk_jhkd\app\adminapi\controller\order\Order@getPayType');
    //获取 订单来源
    Route::get('order/from', 'addon\tk_jhkd\app\adminapi\controller\order\Order@getOrderFrom');
    //查询运单轨迹
    Route::get('getdeliveryinfo/:deliveryid', 'addon\tk_jhkd\app\adminapi\controller\order\Order@deliveryInfo');
    //上传小程序发货
    Route::post('uploadweappdelivery', 'addon\tk_jhkd\app\adminapi\controller\order\Order@uploadWeappDelivery');
    //获取数据统计
    Route::get('order/getstat', 'addon\tk_jhkd\app\adminapi\controller\order\Order@getStat');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- tkjhkd_order


// USER_CODE_BEGIN -- tkjhkd_order_add

Route::group('tk_jhkd', function () {

    //订单列列表
    Route::get('orderadd', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@lists');
    //订单列详情
    Route::get('orderadd/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@info');
    //添加订单列
    Route::post('orderadd', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@add');
    //编辑订单列
    Route::put('orderadd/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@edit');
    //删除订单列
    Route::delete('orderadd/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@del');
    Route::get('member', 'addon\tk_jhkd\app\adminapi\controller\order\OrderAdd@getMemberAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- tkjhkd_order_add


// USER_CODE_BEGIN -- tkjhkd_order_log

Route::group('tk_jhkd', function () {

    //订单日志列表
    Route::get('orderlog', 'addon\tk_jhkd\app\adminapi\controller\order\OrderLog@lists');
    //订单日志详情
    Route::get('orderlog/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderLog@info');
    //添加订单日志
    Route::post('orderlog', 'addon\tk_jhkd\app\adminapi\controller\order\OrderLog@add');
    //编辑订单日志
    Route::put('orderlog/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderLog@edit');
    //删除订单日志
    Route::delete('orderlog/:id', 'addon\tk_jhkd\app\adminapi\controller\order\OrderLog@del');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- tkjhkd_order_log

// USER_CODE_BEGIN -- tkjhkd_shop_order

Route::group('tk_jhkd', function () {

    //商城发单列表
    Route::get('shop_order', 'addon\tk_jhkd\app\adminapi\controller\shop_order\ShopOrder@lists');
    //商城发单详情
    Route::get('shop_order/:id', 'addon\tk_jhkd\app\adminapi\controller\shop_order\ShopOrder@info');
    //添加商城发单
    Route::post('shop_order', 'addon\tk_jhkd\app\adminapi\controller\shop_order\ShopOrder@add');
    //编辑商城发单
    Route::put('shop_order/:id', 'addon\tk_jhkd\app\adminapi\controller\shop_order\ShopOrder@edit');
    //删除商城发单
    Route::delete('shop_order/:id', 'addon\tk_jhkd\app\adminapi\controller\shop_order\ShopOrder@del');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- tkjhkd_shop_order
