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

use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;

/**
 * 无需验证登录
 */
Route::group('home_service', function () {

    Route::get('pay/pay', 'addon\home_service\app\api\controller\Pay@notify');


})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false) //false表示不验证登录
    ->middleware(ApiLog::class);

/**
 * 验证登录!!!    会员业务
 */
Route::group('home_service', function () {


    /*****************************************************   会员 订单相关接口 ****************************************************/

    // 订单状态
    Route::get('order/status', 'addon\home_service\app\api\controller\order\Order@status');
    // 订单计算
    Route::post('order/calculate', 'addon\home_service\app\api\controller\order\OrderCreate@calculate');
    // 订单创建
    Route::post('order/create', 'addon\home_service\app\api\controller\order\OrderCreate@create');
    // 订单创建
    Route::get('order/confirm', 'addon\home_service\app\api\controller\order\OrderCreate@confirm');
    // 获取 订单配置
    Route::get('order/config', 'addon\home_service\app\api\controller\Config@getOrderConfig');
    // 订单列表
    Route::get('order', 'addon\home_service\app\api\controller\order\Order@lists');
    // 订单详情
    Route::get('order/:order_id', 'addon\home_service\app\api\controller\order\Order@detail');
    // 订单验收
    Route::post('order/check', 'addon\home_service\app\api\controller\order\Order@check');

    //查询优惠券
    Route::get('order_create/coupon', 'addon\home_service\app\api\controller\order\OrderCreate@getCoupon');


    // 订单数量
    //    Route::get('order/num', 'addon\home_service\app\api\controller\order\Order@getNum');

        // 订单取消
        Route::put('order/cancel/:order_id', 'addon\home_service\app\api\controller\order\Order@cancel');
    //    // 删除订单
    //    Route::delete('order/:order_id', 'addon\home_service\app\api\controller\Order@delete');
    //
    /*****************************************************  订单售后相关接口 ****************************************************/

    // 订单的最新售后详情（通过订单ID查询）
    Route::get('refund/latest/order/:order_id', 'addon\home_service\app\api\controller\order\Refund@latestByOrder');

    // 申请退款
    Route::post('refund/apply', 'addon\home_service\app\api\controller\order\Refund@apply');
    // 取消售后申请
    Route::put('refund/cancel/:refund_id', 'addon\home_service\app\api\controller\order\Refund@cancel');
    // 售后详情
    Route::get('refund/:refund_id', 'addon\home_service\app\api\controller\order\Refund@detail');

    //退款状态
    Route::get('refund/status', 'addon\home_service\app\api\controller\order\Refund@status');
    //退款列表
    Route::get('refund/lists', 'addon\home_service\app\api\controller\order\Refund@lists');
    //退款原因
    Route::get('refund/reason', 'addon\home_service\app\api\controller\order\Refund@reason');
    // 售后详情(售后订单号)
    Route::get('refund/orderRefund/:refund_no', 'addon\home_service\app\api\controller\order\Refund@orderDetail');

    /*****************************************************  订单评价相关接口 ****************************************************/
    // 获取 评价列表
    Route::get('order/evaluate', 'addon\home_service\app\api\controller\order\Evaluate@pages');
    // 获取 商品评价
    Route::get('order/goodsevaluate', 'addon\home_service\app\api\controller\order\Evaluate@goodsEvaluate');
    // 获取 评价数量
    Route::get('order/evaluate/count', 'addon\home_service\app\api\controller\order\Evaluate@count');
    // 获取 评价详情（评价）
    Route::get('order/evaluate/:id', 'addon\home_service\app\api\controller\order\Evaluate@info');
    // 添加 商品评价
    Route::post('order/evaluate', 'addon\home_service\app\api\controller\order\Evaluate@add');
    // 获取商品搜索配置
    Route::get('order/config/search', 'addon\home_service\app\api\controller\order\Config@getSearchConfig');
    // 评价 （订单页）
    Route::get('order/evaluate/:id', 'addon\home_service\app\api\controller\order\Evaluate@getEvaluate');

    /***************************************************** 次卡相关接口 ****************************************************/
    //获取次卡套餐字典
    Route::get('card/dict', 'addon\home_service\app\api\controller\card\Card@getValidType');

    //获取次卡套餐详情
    Route::get('card/:card_id', 'addon\home_service\app\api\controller\card\Card@info')->pattern(['card_id' => '\d+']);

    //次卡订单创建
    Route::post('card_order/create', 'addon\home_service\app\api\controller\card\CardOrderCreate@create');

    //会员次卡状态
    Route::get('member/card/status', 'addon\home_service\app\api\controller\card\MemberCard@status');
    //会员次卡列表
    Route::get('member/card', 'addon\home_service\app\api\controller\card\MemberCard@lists');
    //会员次卡项目列表
    Route::get('member/card/item', 'addon\home_service\app\api\controller\card\MemberCard@item');
    //会员次卡使用记录
    Route::get('member/card/records', 'addon\home_service\app\api\controller\card\MemberCard@getCardUseRecords');

    /*****************************************************  优惠券 ****************************************************/
    //查询优惠券
    Route::get('order/create/coupon', 'addon\home_service\app\api\controller\order\OrderCreate@getCoupon');
    //添加优惠券
    Route::post('coupon', 'addon\home_service\app\api\controller\coupon\Coupon@receive');

    //优惠券列表
    Route::get('member/coupon', 'addon\home_service\app\api\controller\coupon\Coupon@memberCouponlists');

    //优惠券数量
    Route::get('member/coupon/count', 'addon\home_service\app\api\controller\coupon\Coupon@memberCouponCount');

    //优惠券状态数量
    Route::get('member/coupon/status_count', 'addon\home_service\app\api\controller\coupon\Coupon@memberCouponStatusCount');

    /*****************************************************  发票 ****************************************************/
    //发票订单列表
    Route::get('member/invoice/order', 'addon\home_service\app\api\controller\order\Invoice@getOrderPage');
    //发票订单详情
    Route::get('member/invoice/:id', 'addon\home_service\app\api\controller\order\Invoice@info');
    //开发票
    Route::post('member/invoice', 'addon\home_service\app\api\controller\order\Invoice@add');

    //发票状态
    Route::get('member/invoice/status', 'addon\home_service\app\api\controller\order\Invoice@status');
    //发票类型
    Route::get('member/invoice/type', 'addon\home_service\app\api\controller\order\Invoice@type');
    //发票内容
    Route::get('member/invoice/content', 'addon\home_service\app\api\controller\order\Invoice@content');
    //发票状态
    Route::get('member/invoice/header_type', 'addon\home_service\app\api\controller\order\Invoice@headerType');
    //发票列表
    Route::get('member/invoice', 'addon\home_service\app\api\controller\order\Invoice@pages');

    /*****************************************************  帮助反馈 ****************************************************/
    //获取帮助列表
    Route::get('member/help', 'addon\home_service\app\api\controller\help_feedback\Help@page');
    //获取帮助详情
    Route::get('member/help/info', 'addon\home_service\app\api\controller\help_feedback\Help@info');

    //添加反馈信息
    Route::post('member/feedback', 'addon\home_service\app\api\controller\help_feedback\Feedback@add');

    /*****************************************************  配置信息 ****************************************************/
    // 获取 评价设置
    Route::get('order/evaluate/config', 'addon\home_service\app\api\controller\Config@evaluate');
    // 获取 提现设置
    Route::get('cash_out/config', 'addon\home_service\app\api\controller\Config@cashOutConfig');

    /*****************************************************  会员中心信息 ****************************************************/
    // 获取 会员优惠项数量
    Route::get('member/memberDiscountCount', 'addon\home_service\app\api\controller\member\Member@MemberDiscountCount');
    //获取个人中心最新次卡
    Route::get('member/firstCard', 'addon\home_service\app\api\controller\card\Card@getFirstInfo');

    /*****************************************************  门店申请业务 ****************************************************/
    //获取用户门店申请信息
    Route::get('member/store/application', 'addon\home_service\app\api\controller\member\StoreApplication@getStoreApplicationInfo');
    //门店申请
    Route::post('member/store/application', 'addon\home_service\app\api\controller\member\StoreApplication@StoreApply');
    //门店申请编辑
    Route::put('member/store/application/:id', 'addon\home_service\app\api\controller\member\StoreApplication@StoreApplyEdit');

    /*****************************************************  服务足迹业务 ****************************************************/
    //服务足迹
    Route::get('goods/browse', 'addon\home_service\app\api\controller\goods\GoodsBrowse@getMemberGoodsBrowseList');
    //服务足迹添加
    Route::post('goods/browse', 'addon\home_service\app\api\controller\goods\GoodsBrowse@addGoodsBrowse');
    //服务足迹删除
    Route::delete('goods/browse', 'addon\home_service\app\api\controller\goods\GoodsBrowse@deleteGoodsBrowse');

    /*****************************************************  服务收藏接口 ****************************************************/
    //商品收藏列表
    Route::get('goods/collect', 'addon\home_service\app\api\controller\goods\GoodsCollect@getMemberGoodsCollectList');
    //商品添加收藏
    Route::post('goods/collect', 'addon\home_service\app\api\controller\goods\GoodsCollect@addGoodsCollect');
    //商品取消收藏
    Route::put('goods/collect', 'addon\home_service\app\api\controller\goods\GoodsCollect@cancelGoodsCollect');




})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)  //true表示要验证登录
    ->middleware(ApiLog::class);


/**
 * 验证登录!!!   师傅业务
 */
Route::group('home_service', function () {
    /*****************************************************  师傅申请业务 ****************************************************/
    //师傅申请
    Route::post('technician/apply', 'addon\home_service\app\api\controller\technician\TechnicianApplication@apply');
    // 门店列表
    Route::get('technician/store/list', 'addon\home_service\app\api\controller\technician\Store@getStoreList');

    //师傅门店详情
    Route::get('technician/mystore', 'addon\home_service\app\api\controller\technician\Store@getMyStore');

    //师傅申请详情
    Route::get('technician/apply', 'addon\home_service\app\api\controller\technician\TechnicianApplication@detail');
    // 验证是否是师傅
    Route::get('checktechnician', 'addon\home_service\app\api\controller\technician\Technician@checkTechnician');

    /***************************************************** 师傅休息 ****************************************************/

    //获取师傅休息记录
    Route::get('technician/rest', 'addon\home_service\app\api\controller\technician\TechnicianRest@getRestMonthstats');
    //设置师傅休息
    Route::post('technician/rest', 'addon\home_service\app\api\controller\technician\TechnicianRest@setTechnicianRest');
    //取消师傅休息
    Route::post('technician/cancelrest', 'addon\home_service\app\api\controller\technician\TechnicianRest@cancelRest');
    //请假理由
    Route::get('technician/restreason', 'addon\home_service\app\api\controller\technician\TechnicianRest@getRestReason');


    /*****************************************************  师傅订单 ****************************************************/
    // 师傅抢单大厅  ok
    Route::get('technician/grab', 'addon\home_service\app\api\controller\technician\GrabOrder@lists');
    // 师傅抢单大厅详情
    Route::get('technician/grab/:order_id', 'addon\home_service\app\api\controller\technician\GrabOrder@detail');
    // 师傅抢单大厅  抢单分类待数字   ok
    Route::get('technician/grabcategory', 'addon\home_service\app\api\controller\technician\GrabOrder@grabCategory');
    // 师傅抢单大厅  抢单距离数组   ok
    Route::get('technician/grabdistance', 'addon\home_service\app\api\controller\technician\GrabOrder@getGrabdistance');
    // 师傅抢单
    Route::put('technician/grab/:id', 'addon\home_service\app\api\controller\technician\GrabOrder@grab');
    // 师傅订单状态
    Route::get('technician/order/status', 'addon\home_service\app\api\controller\technician\TechnicianOrder@status');
    // 任务订单状态
    Route::get('technician/order/task_status', 'addon\home_service\app\api\controller\technician\TechnicianOrder@taskStatus');
    // 师傅订单
    Route::get('technician/order', 'addon\home_service\app\api\controller\technician\TechnicianOrder@lists');
    //师傅订单详情
    Route::get('technician/order/:id', 'addon\home_service\app\api\controller\technician\TechnicianOrder@detail');
    // 师傅出发
    Route::post('technician/order/depart', 'addon\home_service\app\api\controller\technician\TechnicianOrder@depart');
    // 师傅拍照
    Route::post('technician/order/phototaken', 'addon\home_service\app\api\controller\technician\TechnicianOrder@photoTaken');
    //开始服务
    Route::post('technician/order/start', 'addon\home_service\app\api\controller\technician\TechnicianOrder@start');
    //服务完成
    Route::post('technician/order/savecheck', 'addon\home_service\app\api\controller\technician\TechnicianOrder@savecheck');
    //切换门店
    Route::put('technician/switchStore', 'addon\home_service\app\api\controller\technician\Technician@switchStore');
    //获取商品服务项
    Route::get('technician/order/goodsItem', 'addon\home_service\app\api\controller\technician\TechnicianOrder@getGoodsItemList');
    //获取服务项
    Route::get('technician/order/item', 'addon\home_service\app\api\controller\technician\TechnicianOrder@getItemList');
    //添加服务项
    Route::post('technician/order/item', 'addon\home_service\app\api\controller\technician\TechnicianOrder@addItem');
    //编辑服务项
    Route::put('technician/order/item', 'addon\home_service\app\api\controller\technician\TechnicianOrder@editItem');
    //消息来源字典
    Route::get('technician/notice/source', 'addon\home_service\app\api\controller\technician\Notice@getNoticeSource');
    //消息通知
    Route::get('technician/notice/list', 'addon\home_service\app\api\controller\technician\Notice@lists');
    // 订单编辑服务时间
    Route::put('technician/order/editServiceTime/:order_id', 'addon\home_service\app\api\controller\technician\TechnicianOrder@editReserveServiceTime');

    /*****************************************************  评价 ****************************************************/
    //评价中心
    Route::get('technician/evaluate', 'addon\home_service\app\api\controller\technician\Evaluate@pages');


    /*****************************************************  师傅个人中心 ****************************************************/
    //师傅详情
    Route::get('technician/info', 'addon\home_service\app\api\controller\technician\Technician@info');
    //师傅修改部分字段
    Route::put('technician/modify/:field', 'addon\home_service\app\api\controller\technician\Technician@modify');
//师傅修改
    Route::put('technician/edit', 'addon\home_service\app\api\controller\technician\Technician@edit');

    //师傅统计 (今日数据)
    Route::get('technician/statistics/todayData', 'addon\home_service\app\api\controller\technician\Statistics@getTodayData');

    /*****************************************************  帮助反馈 ****************************************************/
    //获取帮助列表
    Route::get('technician/help', 'addon\home_service\app\api\controller\technician\Help@page');
    //获取帮助详情
    Route::get('technician/help/info', 'addon\home_service\app\api\controller\technician\Help@info');

    //添加反馈信息
    Route::post('technician/feedback', 'addon\home_service\app\api\controller\technician\Feedback@add');

    /*****************************************************  提现 ****************************************************/
    //获取提现账户列表
    Route::get('technician/cash_out_account', 'addon\home_service\app\api\controller\technician\CashOutAccount@lists');
    //获取提现账户详情
    Route::get('technician/cash_out_account/:account_id', 'addon\home_service\app\api\controller\technician\CashOutAccount@info');
    //获取提现账户首条信息
    Route::get('technician/cash_out_account/first_info', 'addon\home_service\app\api\controller\technician\CashOutAccount@firstInfo');
    //新增账户信息
    Route::post('technician/cash_out_account', 'addon\home_service\app\api\controller\technician\CashOutAccount@add');
    //编辑账户信息
    Route::put('technician/cash_out_account/:account_id', 'addon\home_service\app\api\controller\technician\CashOutAccount@edit');
    //编辑账户信息
    Route::delete('technician/cash_out_account/:account_id', 'addon\home_service\app\api\controller\technician\CashOutAccount@del');

    //获取提现列表
    Route::get('technician/cash_out', 'addon\home_service\app\api\controller\technician\CashOut@lists');
    //获取提现详情
    Route::get('technician/cash_out/:cash_id', 'addon\home_service\app\api\controller\technician\CashOut@info');
    //获取转账方式
    Route::get('technician/cash_out/transfer_type', 'addon\home_service\app\api\controller\technician\CashOut@getTransferType');
    //申请提现
    Route::post('technician/cash_out/apply', 'addon\home_service\app\api\controller\technician\CashOut@apply');
    //取消提现
    Route::put('technician/cash_out/cancel/:cash_id', 'addon\home_service\app\api\controller\technician\CashOut@cancel');

    /*****************************************************  师傅统计 ****************************************************/
    //师傅日订单统计
    Route::get('technician/statistics/dayOrderStat', 'addon\home_service\app\api\controller\technician\Statistics@getDayOrderStat');
    //师傅月订单统计
    Route::get('technician/statistics/monthOrderStat', 'addon\home_service\app\api\controller\technician\Statistics@getMonthOrderStat');
    //师傅订单明细列表
    Route::get('technician/statistics/orderPage', 'addon\home_service\app\api\controller\technician\Statistics@getOrderPage');
    //师傅日账单统计
    Route::get('technician/statistics/dayBillStat', 'addon\home_service\app\api\controller\technician\Statistics@getDayBillStat');
    //师傅收支统计图
    Route::get('technician/statistics/incomeAndExpenseStatChart', 'addon\home_service\app\api\controller\technician\Statistics@getIncomeAndExpenseStatChart');
    //师傅收支统计图
    Route::get('technician/statistics/incomeAndExpenseStat', 'addon\home_service\app\api\controller\technician\Statistics@getIncomeAndExpenseStat');
    //获取排行榜
    Route::get('technician/rank', 'addon\home_service\app\api\controller\technician\Statistics@getRank');
    /*****************************************************  师傅账单 ****************************************************/
    //账单类型
    Route::get('technician/account/type', 'addon\home_service\app\api\controller\technician\TechnicianAccount@type');
    //账单状态
    Route::get('technician/account/status', 'addon\home_service\app\api\controller\technician\TechnicianAccount@status');
    //师傅日账单列表
    Route::get('technician/statistics/dayBillPage', 'addon\home_service\app\api\controller\technician\TechnicianAccount@getDayBillPage');
    //师傅账单明细列表
    Route::get('technician/account/technicianAccountList', 'addon\home_service\app\api\controller\technician\TechnicianAccount@getTechnicianAccountList');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)  //true表示要验证登录
    ->middleware(ApiLog::class);


/**
 * 验证登录!!!   门店业务
 */
Route::group('home_service', function () {

    /*****************************************************  门店申请业务 ****************************************************/


    // 门店入驻
    Route::post('store/apply', 'addon\home_service\app\api\controller\store\StoreApplication@apply');
    // 门店入驻信息编辑
    Route::put('store/apply/:id', 'addon\home_service\app\api\controller\store\StoreApplication@update');
    // 门店入驻详情
    Route::get('store/apply', 'addon\home_service\app\api\controller\store\StoreApplication@detail');

    /*****************************************************门店业务 ****************************************************/
    // 门店抢单大厅
    Route::get('store/grab', 'addon\home_service\app\api\controller\store\GrabOrder@lists');
    // 师傅抢单大厅详情
    Route::get('store/grab/:order_id', 'addon\home_service\app\api\controller\store\GrabOrder@detail');
    // 抢单大厅  抢单分类待数字   ok
    Route::get('store/grabcategory', 'addon\home_service\app\api\controller\store\GrabOrder@grabCategory');
    //抢单大厅  抢单距离数组
    Route::get('store/grabdistance', 'addon\home_service\app\api\controller\store\GrabOrder@getGrabdistance');
    // 任务订单状态
    Route::get('store/order/task_status', 'addon\home_service\app\api\controller\store\StoreOrder@taskStatus');

    // 门店抢单
    Route::put('store/grab/:id', 'addon\home_service\app\api\controller\store\GrabOrder@grab');
    // 门店订单状态
    Route::get('store/order/status', 'addon\home_service\app\api\controller\store\StoreOrder@status');
    // 门店订单
    Route::get('store/order', 'addon\home_service\app\api\controller\store\StoreOrder@lists');
    //门店订单详情
    Route::get('store/order/:id', 'addon\home_service\app\api\controller\store\StoreOrder@detail');
    //门店订单  排班师傅  选择
    Route::post('store/order/selecttechnician', 'addon\home_service\app\api\controller\store\StoreOrder@selectTechnician');
    //门店订单 派单
    Route::post('store/order/dispatch', 'addon\home_service\app\api\controller\store\StoreOrder@dispatch');
    //转单
    Route::post('store/order/transfer', 'addon\home_service\app\api\controller\store\StoreOrder@transfer');
    //催单
    Route::post('store/order/reminder', 'addon\home_service\app\api\controller\store\StoreOrder@reminder');


    /*****************************************************  门店个人中心 ****************************************************/
    //师傅的门店
    Route::get('store/lists', 'addon\home_service\app\api\controller\store\Store@lists');
    //师傅详情
    Route::get('store/info', 'addon\home_service\app\api\controller\store\Store@info');
    //门店修改联系人信息
    Route::put('store/editContact', 'addon\home_service\app\api\controller\store\Store@editContact');
    //门店切换
    Route::put('store/storeSwitch', 'addon\home_service\app\api\controller\store\Store@storeSwitch');
    //师傅修改部分字段
    //Route::put('store/modify/:field', 'addon\home_service\app\api\controller\store\Store@modify');
    /*****************************************************  门店个人中心 ****************************************************/
    //统计 (今日数据)
    Route::get('store/statistics/todayData', 'addon\home_service\app\api\controller\store\Statistics@getTodayData');
    //统计  (订单看板)
    Route::get('store/statistics/orderdashboard', 'addon\home_service\app\api\controller\store\Statistics@getOrderDashboard');
    //统计  (师傅动态)
    Route::get('store/statistics/technicianDynamic', 'addon\home_service\app\api\controller\store\Statistics@getTechnicianDynamic');
    /*****************************************************  帮助反馈 ****************************************************/
    //获取帮助列表
    Route::get('store/help', 'addon\home_service\app\api\controller\store\Help@page');
    //获取帮助详情
    Route::get('store/help/info', 'addon\home_service\app\api\controller\store\Help@info');

    //添加反馈信息
    Route::post('store/feedback', 'addon\home_service\app\api\controller\store\Feedback@add');

    /*****************************************************  提现 ****************************************************/
    //获取提现账户列表
    Route::get('store/cash_out_account', 'addon\home_service\app\api\controller\store\CashOutAccount@lists');
    //获取提现账户详情
    Route::get('store/cash_out_account/:account_id', 'addon\home_service\app\api\controller\store\CashOutAccount@info');
    //获取提现账户首条信息
    Route::get('store/cash_out_account/first_info', 'addon\home_service\app\api\controller\store\CashOutAccount@firstInfo');
    //新增账户信息
    Route::post('store/cash_out_account', 'addon\home_service\app\api\controller\store\CashOutAccount@add');
    //编辑账户信息
    Route::put('store/cash_out_account/:account_id', 'addon\home_service\app\api\controller\store\CashOutAccount@edit');
    //编辑账户信息
    Route::delete('store/cash_out_account/:account_id', 'addon\home_service\app\api\controller\store\CashOutAccount@del');

    //获取提现列表
    Route::get('store/cash_out', 'addon\home_service\app\api\controller\store\CashOut@lists');
    //获取提现详情
    Route::get('store/cash_out/:cash_id', 'addon\home_service\app\api\controller\store\CashOut@info');
    //获取转账方式
    Route::get('store/cash_out/transfer_type', 'addon\home_service\app\api\controller\store\CashOut@getTransferType');
    //申请提现
    Route::post('store/cash_out/apply', 'addon\home_service\app\api\controller\store\CashOut@apply');
    //取消提现
    Route::put('store/cash_out/cancel/:cash_id', 'addon\home_service\app\api\controller\store\CashOut@cancel');

    /*****************************************************  门店师傅 ****************************************************/
    //获取师傅状态
    Route::get('store/technician/status', 'addon\home_service\app\api\controller\store\Technician@status');
    //获取师傅列表
    Route::get('store/technician', 'addon\home_service\app\api\controller\store\Technician@pages');
    //获取师傅列表
    Route::get('store/technician/:technician_id', 'addon\home_service\app\api\controller\store\Technician@info');
    //设置门店师傅分成比例
    Route::post('store/technician/rate', 'addon\home_service\app\api\controller\store\Technician@setTechnicianRate');

    /*****************************************************  门店统计 ****************************************************/
    //门店日订单统计
    Route::get('store/statistics/dayOrderStat', 'addon\home_service\app\api\controller\store\Statistics@getDayOrderStat');
    //门店月订单统计
    Route::get('store/statistics/monthOrderStat', 'addon\home_service\app\api\controller\store\Statistics@getMonthOrderStat');
    //门店订单明细列表
    Route::get('store/statistics/orderPage', 'addon\home_service\app\api\controller\store\Statistics@getOrderPage');
    //门店日账单统计
    Route::get('store/statistics/dayBillStat', 'addon\home_service\app\api\controller\store\Statistics@getDayBillStat');
    //门店收支统计图
    Route::get('store/statistics/incomeAndExpenseStatChart', 'addon\home_service\app\api\controller\store\Statistics@getIncomeAndExpenseStatChart');
    //门店收支统计图
    Route::get('store/statistics/incomeAndExpenseStat', 'addon\home_service\app\api\controller\store\Statistics@getIncomeAndExpenseStat');

    /*****************************************************  门店账单 ****************************************************/
    //账单类型
    Route::get('store/account/type', 'addon\home_service\app\api\controller\store\StoreAccount@type');
    //账单状态
    Route::get('store/account/status', 'addon\home_service\app\api\controller\store\StoreAccount@status');
    //门店日账单列表
    Route::get('store/statistics/dayBillPage', 'addon\home_service\app\api\controller\store\StoreAccount@getDayBillPage');
    //门店账单明细列表
    Route::get('store/account/storeAccountList', 'addon\home_service\app\api\controller\store\StoreAccount@getStoreAccountList');

    /*****************************************************  门店评价 ****************************************************/
    //评价中心
    Route::get('store/evaluate', 'addon\home_service\app\api\controller\store\Evaluate@pages');

    /***************************************************** 师傅休息 ****************************************************/

    //获取师傅休息记录
    Route::get('store/technician/rest', 'addon\home_service\app\api\controller\store\TechnicianRest@getRestMonthstats');
    //设置师傅休息
    Route::post('store/technician/rest', 'addon\home_service\app\api\controller\store\TechnicianRest@setTechnicianRest');
    //取消师傅休息
    Route::post('store/technician/cancelrest', 'addon\home_service\app\api\controller\store\TechnicianRest@cancelRest');
    //请假理由
    Route::get('store/technician/restreason', 'addon\home_service\app\api\controller\store\TechnicianRest@getRestReason');

    /***************************************************** 师傅收藏接口 ****************************************************/
    //商品收藏列表
    Route::get('technician/collect', 'addon\home_service\app\api\controller\member\TechnicianCollect@getMemberTechnicianCollectList');
    //商品添加收藏
    Route::post('technician/collect', 'addon\home_service\app\api\controller\member\TechnicianCollect@addTechnicianCollect');
    //商品取消收藏
    Route::put('technician/collect', 'addon\home_service\app\api\controller\member\TechnicianCollect@cancelTechnicianCollect');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)  //true表示要验证登录
    ->middleware(ApiLog::class);

/**
 * 不验证登录!!!
 */
Route::group('home_service', function () {
    /*****************************************************  分类相关接口 ****************************************************/
    // 分类列表 树
    Route::get('category', 'addon\home_service\app\api\controller\goods\Category@tree');
    // 分类列表
    Route::get('category/list', 'addon\home_service\app\api\controller\goods\Category@list');
    /*****************************************************  项目相关接口 ****************************************************/
    // 项目列表
    Route::get('goods', 'addon\home_service\app\api\controller\goods\Goods@pages');
    // 项目详情
    Route::get('goods/detail', 'addon\home_service\app\api\controller\goods\Goods@detail');

    // 附近师傅
    Route::get('technician/nearbyTechs', 'addon\home_service\app\api\controller\technician\TechnicianSearch@getTechnicianSearchList');


//
//    Route::get('goods/list', 'addon\home_service\app\api\controller\goods\Goods@lists');
    // 获取商品列表供组件调用
    Route::get('goods/components', 'addon\home_service\app\api\controller\goods\Goods@components');
    //次卡组件
    Route::get('card/components', 'addon\home_service\app\api\controller\card\Card@components');
//
//    /*****************************************************  师傅列表 ****************************************************/
//    //师傅 列表
//    Route::get('technician', 'addon\home_service\app\api\controller\Technician@pages');
//    //列表，不分页
//    Route::get('technician/list', 'addon\home_service\app\api\controller\Technician@lists');
//
//    //支持商品的师傅
//    Route::get('technician/goods/:id', 'addon\home_service\app\api\controller\Technician@goodsTechnician');
//

    /***************************************************** 优惠券 ****************************************************/
    // 获取优惠券列表
    Route::get('coupon', 'addon\home_service\app\api\controller\coupon\Coupon@lists');

    //优惠券类型
    Route::get('coupon_type', 'addon\home_service\app\api\controller\coupon\Coupon@getCouponType');


    //定时器
    Route::put('schedule/:publicname', 'addon\home_service\app\api\controller\test\Schedule@schedule');

    //获取次卡套餐列表
    Route::get('card', 'addon\home_service\app\api\controller\card\Card@lists');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)  //true表示要验证登录
    ->middleware(ApiLog::class);
