<?php
// +----------------------------------------------------------------------
// | Niushop商城系统 - 团队十年电商经验汇集巨献!
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2025 https://www.niushop.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Niushop并不是自由软件，未经许可不能去掉Niushop相关版权
// +----------------------------------------------------------------------
// | Author: Niushop Team <niucloud@outlook.com>
// +----------------------------------------------------------------------

use think\facade\Route;

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;

/**
 * 上门服务功能插件定义
 */
Route::group('home_service', function () {

    /*****************************************************  商品分类管理 ****************************************************/
    // 服务分类列表
    Route::get('category', 'addon\home_service\app\adminapi\controller\category\Category@lists');
    // 服务分类列表
    Route::get('category/list', 'addon\home_service\app\adminapi\controller\category\Category@getCategoryList');
    // 服务分类树
    Route::get('category/tree', 'addon\home_service\app\adminapi\controller\category\Category@tree');
    // 服务分类详情
    Route::get('category/:id', 'addon\home_service\app\adminapi\controller\category\Category@info');
    //添加 服务分类
    Route::post('category', 'addon\home_service\app\adminapi\controller\category\Category@add');
    //编辑 服务分类
    Route::put('category/:id', 'addon\home_service\app\adminapi\controller\category\Category@edit');
    //删除 服务分类
    Route::delete('category/:id', 'addon\home_service\app\adminapi\controller\category\Category@del');
    //分类 排序
    Route::put('category/sort', 'addon\home_service\app\adminapi\controller\category\Category@changeSort');


    /*****************************************************  服务管理 ****************************************************/
    // 服务列表
    Route::get('goods', 'addon\home_service\app\adminapi\controller\goods\Goods@lists');
    // 获取商品选择分页列表
    Route::get('goods/select', 'addon\home_service\app\adminapi\controller\goods\Goods@select');
    // 获取商品选择分页列表带sku
    Route::get('goods/selectgoodssku', 'addon\home_service\app\adminapi\controller\goods\Goods@selectGoodsSku');
    // 服务列表
    Route::get('goods/list', 'addon\home_service\app\adminapi\controller\goods\Goods@getLists');
    // 服务详情
    Route::get('goods/:id', 'addon\home_service\app\adminapi\controller\goods\Goods@info');
    //添加 服务
    Route::post('goods', 'addon\home_service\app\adminapi\controller\goods\Goods@add');
    //编辑 详情
    Route::get('goods/init', 'addon\home_service\app\adminapi\controller\goods\Goods@init');
    //编辑 服务
    Route::put('goods/:id', 'addon\home_service\app\adminapi\controller\goods\Goods@edit');
    //商品sku
    Route::get('goods/sku', 'addon\home_service\app\adminapi\controller\goods\Goods@sku');
    // 复制服务
    Route::put('goods/copy/:id', 'addon\home_service\app\adminapi\controller\goods\Goods@copy');
    //删除 服务
    Route::delete('goods/delete', 'addon\home_service\app\adminapi\controller\goods\Goods@del');
    // 修改商品上下架状态
    Route::put('goods/status', 'addon\home_service\app\adminapi\controller\goods\Goods@editStatus');

    //修改排序
    Route::put('goods/sort/:id', 'addon\home_service\app\adminapi\controller\goods\Goods@editSort');

    // 编辑商品规格列表会员价格
    Route::put('goods/member_price', 'addon\home_service\app\adminapi\controller\goods\Goods@editGoodsListMemberPrice');

    // 获取商品下单选择分页列表
    Route::get('goods/buy/goods/select', 'addon\home_service\app\adminapi\controller\goods\Goods@buyGoodsSelect');

    // 获取商品下单已选分页列表
    Route::get('goods/buy/goods/selected', 'addon\home_service\app\adminapi\controller\goods\Goods@buyGoodsSelected');

    // 获取商品下单SKU规格列表
    Route::get('goods/buy/sku/select', 'addon\home_service\app\adminapi\controller\goods\Goods@buySkuSelect');



    /*****************************************************  服务保障管理 ****************************************************/
    // 服务保障列表
    Route::get('goods/guarantee', 'addon\home_service\app\adminapi\controller\goods\Guarantee@lists');
    // 服务保障详情
    Route::get('goods/guarantee/:id', 'addon\home_service\app\adminapi\controller\goods\Guarantee@info');
    // 添加服务保障
    Route::post('goods/guarantee', 'addon\home_service\app\adminapi\controller\goods\Guarantee@add');
    // 编辑服务保障
    Route::put('goods/guarantee/:id', 'addon\home_service\app\adminapi\controller\goods\Guarantee@edit');
    // 删除服务保障
    Route::delete('goods/guarantee/:id', 'addon\home_service\app\adminapi\controller\goods\Guarantee@del');

    /*****************************************************  次卡管理 ****************************************************/
    // 次卡有效性管理
    Route::get('card/valid_type', 'addon\home_service\app\adminapi\controller\goods\Card@getValidType');
    //添加 次卡
    Route::post('card', 'addon\home_service\app\adminapi\controller\goods\Card@add');
    // 次卡列表
    Route::get('card', 'addon\home_service\app\adminapi\controller\goods\Card@lists');
    // 服务详情
    Route::get('card/:id', 'addon\home_service\app\adminapi\controller\goods\Card@info');
    //编辑 详情
    Route::get('card/init', 'addon\home_service\app\adminapi\controller\goods\Card@init');
    //编辑 服务
    Route::put('card/:id', 'addon\home_service\app\adminapi\controller\goods\Card@edit');
    //修改排序
    Route::put('card/sort/:id', 'addon\home_service\app\adminapi\controller\goods\Card@editSort');
    //删除 服务
    Route::delete('card/:id', 'addon\home_service\app\adminapi\controller\goods\Card@del');
    //商品sku
    Route::get('card/sku', 'addon\home_service\app\adminapi\controller\goods\Card@sku');
    //编辑 服务状态
    Route::put('card/status/:id', 'addon\home_service\app\adminapi\controller\goods\Card@editStatus');
    // 编辑商品规格列表会员价格
    Route::put('card/member_price', 'addon\home_service\app\adminapi\controller\goods\Card@editCardListMemberPrice');
    // 获取次卡选择分页列表
    Route::get('card/select', 'addon\home_service\app\adminapi\controller\goods\Card@select');
    // 获取次卡选择分页列表带sku
    Route::get('card/selectcardsku', 'addon\home_service\app\adminapi\controller\goods\Card@selectCardSku');
    /*****************************************************  次卡订单 ****************************************************/
    // 次卡订单来源
    Route::get('card_order/orderfrom', 'addon\home_service\app\adminapi\controller\order\CardOrder@getOrderFrom');
    // 次卡订单状态
    Route::get('card_order/status', 'addon\home_service\app\adminapi\controller\order\CardOrder@status');
    // 次卡订单状态 （带数字）
    Route::get('card_order/taskstatus', 'addon\home_service\app\adminapi\controller\order\CardOrder@taskStatus');
    // 次卡订单列表
    Route::get('card_order', 'addon\home_service\app\adminapi\controller\order\CardOrder@lists');
    // 次卡订单列表
    Route::get('card_order/:card_order_id', 'addon\home_service\app\adminapi\controller\order\CardOrder@detail');
    // 次卡订单订单关闭
    Route::put('card_order/close/:card_order_id', 'addon\home_service\app\adminapi\controller\order\CardOrder@orderClose');
    // 次卡订单订单删除
    Route::delete('card_order/delete', 'addon\home_service\app\adminapi\controller\order\CardOrder@delete');

    /***************************************************** 师傅入驻 ****************************************************/
    //师傅入驻申请详情
    Route::get('technicianapplication/status', 'addon\home_service\app\adminapi\controller\technician\TechnicianApplication@getStatus');
    //师傅入驻申请列表
    Route::get('technicianapplication', 'addon\home_service\app\adminapi\controller\technician\TechnicianApplication@pages');
    //师傅入驻申请详情
    Route::get('technicianapplication/info/:id', 'addon\home_service\app\adminapi\controller\technician\TechnicianApplication@info');
    //师傅入驻审核
    Route::put('technicianapplication/examine/:id', 'addon\home_service\app\adminapi\controller\technician\TechnicianApplication@examine');

    /***************************************************** 师傅管理 ****************************************************/
    //师傅  入驻类型
    Route::get('technician/source', 'addon\home_service\app\adminapi\controller\technician\Technician@getSource');
    //师傅 列表
    Route::get('technician', 'addon\home_service\app\adminapi\controller\technician\Technician@pages');
    //师傅  可以选择的会员数据
    Route::get('technician/selectmember', 'addon\home_service\app\adminapi\controller\technician\Technician@getEligibleMembers');
    //师傅 分成方式
    Route::get('technician/distributetype', 'addon\home_service\app\adminapi\controller\technician\Technician@getDistributeType');
    //师傅  状态
    Route::get('technician/status', 'addon\home_service\app\adminapi\controller\technician\Technician@getTechnicianStatus');
    //师傅 添加
    Route::post('technician/add', 'addon\home_service\app\adminapi\controller\technician\Technician@add');
    //师傅 订单
    Route::get('technician/order', 'addon\home_service\app\adminapi\controller\order\TechnicianOrder@lists');
    //师傅 账单
    Route::get('technician/:technician_id/account', 'addon\home_service\app\adminapi\controller\account\TechnicianAccount@lists');
    //师傅 评价
    Route::get('technician/evaluate', 'addon\home_service\app\adminapi\controller\order\Evaluate@lists');
    //师傅 评价（顶部数据业务）
    Route::get('technician/techevalstats', 'addon\home_service\app\adminapi\controller\statistics\Evaluate@getTechEvalStats');
    //师傅 维权
    Route::get('technician/refund', 'addon\home_service\app\adminapi\controller\order\Refund@lists');
    //师傅 维权 （顶部数据业务）
    Route::get('technician/techrefundstats', 'addon\home_service\app\adminapi\controller\statistics\Refund@getTechRefundStats');
    //师傅 维权
    Route::get('technician/refund', 'addon\home_service\app\adminapi\controller\order\Refund@lists');
    //师傅 维权 （顶部数据业务）
    Route::get('technician/techrefundstats', 'addon\home_service\app\adminapi\controller\statistics\Refund@getTechRefundStats');
    //获取师傅休息记录
    Route::get('technician/rest', 'addon\home_service\app\adminapi\controller\technician\TechnicianRest@getTechnicianRestList');
    //设置师傅休息
    Route::post('technician/rest', 'addon\home_service\app\adminapi\controller\technician\TechnicianRest@setTechnicianRest');

    //请假理由
    Route::get('technician/restreason', 'addon\home_service\app\adminapi\controller\technician\TechnicianRest@getRestReason');


    //设置师傅休息  月数据查询
    Route::get('technician/restMonthstats', 'addon\home_service\app\adminapi\controller\technician\TechnicianRest@getRestMonthstats');
    //师傅 详情
    Route::get('technician/:technician_id', 'addon\home_service\app\adminapi\controller\technician\Technician@info');
    //师傅 状态
    Route::put('technician/status/:id', 'addon\home_service\app\adminapi\controller\technician\Technician@status');
    //师傅 编辑
    Route::put('technician/edit/:id', 'addon\home_service\app\adminapi\controller\technician\Technician@edit');

    /***************************************************** 师傅等级 ****************************************************/
    //师傅分级选择
    Route::get('technician_level/getlist', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@getList');
    //分销等级列表
    Route::get('technician_level', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@pages');
    //分销等级详情
    Route::get('technician_level/:level_id', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@info');
    //添加分销等级
    Route::post('technician_level', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@add');
    //编辑分销等级
    Route::put('technician_level/:level_id', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@edit');
    //删除分销等级
    Route::delete('technician_level/:level_id', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@del');
    //师傅等级权重
    Route::get('technician_level/level_num', 'addon\home_service\app\adminapi\controller\technician\TechnicianLevel@getLevelNumList');


    /***************************************************** 门店入驻 ****************************************************/
    //门店入驻申请状态
    Route::get('storeapplication/status', 'addon\home_service\app\adminapi\controller\store\StoreApplication@getStatus');
    //门店入驻申请列表
    Route::get('storeapplication', 'addon\home_service\app\adminapi\controller\store\StoreApplication@pages');
    //门店入驻申请详情
    Route::get('storeapplication/info/:id', 'addon\home_service\app\adminapi\controller\store\StoreApplication@info');
    //门店入驻审核
    Route::put('storeapplication/examine/:id', 'addon\home_service\app\adminapi\controller\store\StoreApplication@examine');

    /***************************************************** 门店管理 ****************************************************/

    //门店列表
    Route::get('store', 'addon\home_service\app\adminapi\controller\store\Store@pages');
    // 门店 列表不分页
    Route::get('store/list', 'addon\home_service\app\adminapi\controller\store\Store@getList');
    //门店详情
    Route::get('store/:store_id', 'addon\home_service\app\adminapi\controller\store\Store@info');
    //门店师傅
    Route::get('store/:store_id/technician', 'addon\home_service\app\adminapi\controller\store\Technician@lists');
    //设置门店师傅分成比例
    Route::post('store/technician/rate', 'addon\home_service\app\adminapi\controller\store\Technician@setTechnicianRate');
    //门店 订单
    Route::get('store/order', 'addon\home_service\app\adminapi\controller\order\StoreOrder@lists');
    //门店 账单
    Route::get('store/:store_id/account', 'addon\home_service\app\adminapi\controller\account\StoreAccount@lists');
    //门店 评价（顶部数据业务）
    Route::get('store/storeevalstats', 'addon\home_service\app\adminapi\controller\statistics\Evaluate@getStoreEvalStats');
    //门店 维权
    Route::get('store/refund', 'addon\home_service\app\adminapi\controller\order\Refund@lists');
    //门店 维权 （顶部数据业务）
    Route::get('store/storerefundstats', 'addon\home_service\app\adminapi\controller\statistics\Refund@getStoreRefundStats');

    // 选择会员
    Route::get('store/selectmember', 'addon\home_service\app\adminapi\controller\store\Store@getSelsecMembers');
    //添加门店
    Route::post('store', 'addon\home_service\app\adminapi\controller\store\Store@add');
    //师傅 编辑
    Route::put('store/:id', 'addon\home_service\app\adminapi\controller\store\Store@edit');


    /***************************************************** 城市策略管理 ****************************************************/
    //城市策略
    Route::post('citystrategy', 'addon\home_service\app\adminapi\controller\strategy\CityStrategy@add');
    //城市策略 详情
    Route::get('citystrategy/:id', 'addon\home_service\app\adminapi\controller\strategy\CityStrategy@info');
    //城市策略 编辑
    Route::put('citystrategy/:id', 'addon\home_service\app\adminapi\controller\strategy\CityStrategy@edit');
    //城市策略 列表
    Route::get('citystrategy', 'addon\home_service\app\adminapi\controller\strategy\CityStrategy@pages');
    //城市策略  删除
    Route::delete('citystrategy/:id', 'addon\home_service\app\adminapi\controller\strategy\CityStrategy@del');


    /*****************************************************  统计相关接口 ****************************************************/
    // 全部统计
    Route::get('stat/total', 'addon\home_service\app\adminapi\controller\Stat@total');
    // 当日统计
    Route::get('stat/today', 'addon\home_service\app\adminapi\controller\Stat@today');
    // 昨日统计
    Route::get('stat/yesterday', 'addon\home_service\app\adminapi\controller\Stat@yesterday');
    // 月统计
    Route::get('stat/month', 'addon\home_service\app\adminapi\controller\Stat@month');

    /***************************************************** 订单管理管理 ****************************************************/
    // 订单支付方式
    Route::get('order/paytype', 'addon\home_service\app\adminapi\controller\order\Order@getPayType');
    // 订单来源
    Route::get('order/orderfrom', 'addon\home_service\app\adminapi\controller\order\Order@getOrderFrom');
    // 订单状态
    Route::get('order/status', 'addon\home_service\app\adminapi\controller\order\Order@status');
    // 订单状态 （带数字）
    Route::get('order/taskstatus', 'addon\home_service\app\adminapi\controller\order\Order@taskStatus');
    // 订单列表
    Route::get('order', 'addon\home_service\app\adminapi\controller\order\Order@lists');
    // 订单标签
    Route::post('order/label', 'addon\home_service\app\adminapi\controller\order\Order@setLabel');
    // 订单详情
    Route::get('order/:order_id', 'addon\home_service\app\adminapi\controller\order\Order@detail');


    // 订单选择师傅
    Route::get('order/selecttechnician', 'addon\home_service\app\adminapi\controller\order\Order@selecttechnician');
    // 订单派单
    Route::post('order/dispatch', 'addon\home_service\app\adminapi\controller\order\Order@dispatch');
    // 订单重新派单
    Route::post('order/transfer', 'addon\home_service\app\adminapi\controller\order\Order@transfer');


    //订单关闭
    Route::put('order/close/:order_id', 'addon\home_service\app\adminapi\controller\order\Order@orderClose');


    //订单删除
    Route::delete('order/delete', 'addon\home_service\app\adminapi\controller\order\Order@delete');

    //催单
    Route::post('order/reminder', 'addon\home_service\app\adminapi\controller\order\Order@reminder');


    /*****************************************************  售后相关接口 ****************************************************/
    // 订单售后记录
    Route::get('refund', 'addon\home_service\app\adminapi\controller\order\Refund@lists');
    // 订单售后详情
    Route::get('refund/:refund_id', 'addon\home_service\app\adminapi\controller\order\Refund@detail');
    // 拒绝
    Route::put('refund/refuse/:refund_id', 'addon\home_service\app\adminapi\controller\order\Refund@refuse');
    // 确认转账
    Route::put('refund/:refund_id', 'addon\home_service\app\adminapi\controller\order\Refund@refund');
    // 售后状态
    Route::get('refund/status', 'addon\home_service\app\adminapi\controller\order\Refund@status');
    // 售后状态 （带数字）
    Route::get('refund/taskstatus', 'addon\home_service\app\adminapi\controller\order\Refund@taskStatus');


    /*****************************************************  评价相关接口 ****************************************************/
    //评价列表
    Route::get('order/evaluate', 'addon\home_service\app\adminapi\controller\order\Evaluate@lists');
    //评价删除
    Route::delete('order/evaluate/:id', 'addon\home_service\app\adminapi\controller\order\Evaluate@del');
    //评价详情
    Route::get('order/evaluate/:order_id', 'addon\home_service\app\adminapi\controller\order\Evaluate@detail');
    //评价通过
    Route::put('order/evaluate/adopt/:id', 'addon\home_service\app\adminapi\controller\order\Evaluate@adopt');
    //评价拒绝
    Route::put('order/evaluate/refuse/:id', 'addon\home_service\app\adminapi\controller\order\Evaluate@refuse');
    //获取评价审核状态
    Route::get('order/evaluate/taskstatus', 'addon\home_service\app\adminapi\controller\order\Evaluate@taskStatus');
    //批量通过
    Route::post('order/evaluate/batch/adopt', 'addon\home_service\app\adminapi\controller\order\Evaluate@batchAdopt');
    //批量拒绝
    Route::post('order/evaluate/batch/refuse', 'addon\home_service\app\adminapi\controller\order\Evaluate@batchRefuse');
    //批量删除
    Route::post('order/evaluate/batch/del', 'addon\home_service\app\adminapi\controller\order\Evaluate@batchDel');


    /***************************************************** 订单回访 管理 ****************************************************/
    //订单回访
    Route::get('orderfollow', 'addon\home_service\app\adminapi\controller\order\OrderFollow@lists');
    //回访状态（带数字）
    Route::get('orderfollow/taskstatus', 'addon\home_service\app\adminapi\controller\order\OrderFollow@taskStatus');

    //回访结果
    Route::get('orderfollow/followresult', 'addon\home_service\app\adminapi\controller\order\OrderFollow@getFollowResult');
    //收费情况
    Route::get('orderfollow/feesituation', 'addon\home_service\app\adminapi\controller\order\OrderFollow@getFeeSituation');
    //订单回访
    Route::put('orderfollow/:order_id', 'addon\home_service\app\adminapi\controller\order\OrderFollow@follow');
    //回访
    Route::get('orderfollow/:order_id', 'addon\home_service\app\adminapi\controller\order\OrderFollow@getInfo');

    /***************************************************** 订单标签 管理 ****************************************************/
    //订单标签  添加
    Route::post('orderlaber', 'addon\home_service\app\adminapi\controller\order\OrderLaber@add');
    //订单标签 详情
    Route::get('orderlaber/:label_id', 'addon\home_service\app\adminapi\controller\order\OrderLaber@info');
    //订单标签 编辑
    Route::put('orderlaber/:label_id', 'addon\home_service\app\adminapi\controller\order\OrderLaber@edit');
    //订单标签 列表
    Route::get('orderlaber', 'addon\home_service\app\adminapi\controller\order\OrderLaber@lists');
    //订单标签 删除
    Route::delete('orderlaber/:label_id', 'addon\home_service\app\adminapi\controller\order\OrderLaber@del');


    /*****************************************************  订单设置 ****************************************************/
    //订单配置
    Route::post('order/config', 'addon\home_service\app\adminapi\controller\Config@setOrderConfig');
    //获取订单配置
    Route::get('order/config', 'addon\home_service\app\adminapi\controller\Config@getOrderConfig');
    //订单维权配置
    Route::post('order_refund/config', 'addon\home_service\app\adminapi\controller\Config@setOrderRefundConfig');
    //获取订单维权配置
    Route::get('order_refund/config', 'addon\home_service\app\adminapi\controller\Config@getOrderRefundConfig');
    //评价配置
    Route::post('evaluate/config', 'addon\home_service\app\adminapi\controller\Config@setEvaluateConfig');
    //获取评价配置
    Route::get('evaluate/config', 'addon\home_service\app\adminapi\controller\Config@getEvaluateConfig');

    /***************************************************** 优惠券 ****************************************************/
    //优惠券类型
    Route::get('goods/coupon/type', 'addon\home_service\app\adminapi\controller\coupon\Coupon@type');

    //优惠券列表
    Route::get('goods/coupon', 'addon\home_service\app\adminapi\controller\coupon\Coupon@lists');

    //优惠券初始化信息
    Route::get('goods/coupon/init', 'addon\home_service\app\adminapi\controller\coupon\Coupon@init');

    //添加优惠券
    Route::post('goods/coupon', 'addon\home_service\app\adminapi\controller\coupon\Coupon@add');

    //优惠券领取记录
    Route::get('goods/coupon/records', 'addon\home_service\app\adminapi\controller\coupon\Coupon@getMemberCoupon');

    //优惠券详情
    Route::get('goods/coupon/detail/:id', 'addon\home_service\app\adminapi\controller\coupon\Coupon@info');

    //编辑优惠券
    Route::put('goods/coupon/edit/:id', 'addon\home_service\app\adminapi\controller\coupon\Coupon@edit');

    //删除优惠券基于有批量删除
    Route::post('goods/coupon/delete', 'addon\home_service\app\adminapi\controller\coupon\Coupon@del');

    //优惠券设置状态
    Route::put('goods/coupon/setstatus/:status', 'addon\home_service\app\adminapi\controller\coupon\Coupon@setCouponStatus');

    //优惠券失效
    Route::put('goods/coupon/invalid', 'addon\home_service\app\adminapi\controller\coupon\Coupon@couponInvalid');

    //删除优惠券
    Route::delete('goods/coupon/:id', 'addon\home_service\app\adminapi\controller\coupon\Coupon@del');

    //查询优惠券选择分页列表
    Route::get('goods/coupon/select', 'addon\home_service\app\adminapi\controller\coupon\Coupon@select');

    //查询选中的优惠券
    Route::get('goods/coupon/selected', 'addon\home_service\app\adminapi\controller\coupon\Coupon@getSelectedLists');

    //优惠券状态列表
    Route::get('goods/coupon/status', 'addon\home_service\app\adminapi\controller\coupon\Coupon@getCouponStatus');

    //发送优惠券范围列表
    Route::get('goods/coupon/send/init', 'addon\home_service\app\adminapi\controller\coupon\Coupon@getSendRangeInit');
    Route::get('goods/coupon/send/pages/:coupon_id', 'addon\home_service\app\adminapi\controller\coupon\Coupon@getSendPages');
    Route::post('goods/coupon/send/:coupon_id', 'addon\home_service\app\adminapi\controller\coupon\Coupon@sendCoupon');

    /***************************************************** 发票管理 ****************************************************/
    //发票列表
    Route::get('invoice', 'addon\home_service\app\adminapi\controller\order\Invoice@page');
    //开具发票
    Route::put('invoice/issue/:id', 'addon\home_service\app\adminapi\controller\order\Invoice@issueInvoice');
    //订单发票详情
    Route::get('order/invoice/info', 'addon\home_service\app\adminapi\controller\order\Invoice@orderInvoiceInfo');

    /***************************************************** 帮助反馈 ****************************************************/
    //帮助分类列表
    Route::get('help/category', 'addon\home_service\app\adminapi\controller\help_feedback\HelpCategory@page');
    //帮助分类列表(不分页)
    Route::get('help/category/list', 'addon\home_service\app\adminapi\controller\help_feedback\HelpCategory@lists');
    //添加帮助分类
    Route::post('help/category', 'addon\home_service\app\adminapi\controller\help_feedback\HelpCategory@add');
    //编辑帮助分类
    Route::put('help/category/:category_id', 'addon\home_service\app\adminapi\controller\help_feedback\HelpCategory@edit');
    //删除帮助分类
    Route::delete('help/category/:category_id', 'addon\home_service\app\adminapi\controller\help_feedback\HelpCategory@del');

    //帮助类型
    Route::get('help/type', 'addon\home_service\app\adminapi\controller\help_feedback\Help@type');
    //帮助列表
    Route::get('help', 'addon\home_service\app\adminapi\controller\help_feedback\Help@page');
    //帮助列表
    Route::get('help/:help_id', 'addon\home_service\app\adminapi\controller\help_feedback\Help@info');
    //添加帮助
    Route::post('help', 'addon\home_service\app\adminapi\controller\help_feedback\Help@add');
    //编辑帮助
    Route::put('help/:help_id', 'addon\home_service\app\adminapi\controller\help_feedback\Help@edit');
    //删除帮助
    Route::delete('help/:help_id', 'addon\home_service\app\adminapi\controller\help_feedback\Help@del');

    //反馈来源
    Route::get('feedback/source', 'addon\home_service\app\adminapi\controller\help_feedback\Feedback@source');
    //反馈列表
    Route::get('feedback', 'addon\home_service\app\adminapi\controller\help_feedback\Feedback@page');

    /***************************************************** 提现业务 ****************************************************/
    //提现列表
    Route::get('cash_out', 'addon\home_service\app\adminapi\controller\cash_out\CashOut@page');
    //提现详情
    Route::get('cash_out/:cash_out_id', 'addon\home_service\app\adminapi\controller\cash_out\CashOut@info');
    //提现备注
    Route::put('cash_out/remark/:cash_out_id', 'addon\home_service\app\adminapi\controller\cash_out\CashOut@remark');
    //提现状态字典
    Route::get('cash_out/status/dict', 'addon\home_service\app\adminapi\controller\cash_out\CashOut@getStatusList');
    //转账
    Route::put('cash_out/transfer/:cash_out_id', 'addon\home_service\app\adminapi\controller\cash_out\CashOut@transfer');

    /***************************************************** 结算业务 ****************************************************/
    //门店结算列表
    Route::get('store/settlement', 'addon\home_service\app\adminapi\controller\settlement\StoreSettlement@page');
    //门店结算账单列表
    Route::get('store/settlement/account', 'addon\home_service\app\adminapi\controller\settlement\StoreSettlement@accountPage');
    //门店结算统计
    Route::get('store/settlement/stat', 'addon\home_service\app\adminapi\controller\settlement\StoreSettlement@settlementStat');

    //师傅结算列表
    Route::get('technician/settlement', 'addon\home_service\app\adminapi\controller\settlement\TechnicianSettlement@page');
    //师傅结算账单列表
    Route::get('technician/settlement/account', 'addon\home_service\app\adminapi\controller\settlement\TechnicianSettlement@accountPage');
    //师傅结算统计
    Route::get('technician/settlement/stat', 'addon\home_service\app\adminapi\controller\settlement\TechnicianSettlement@settlementStat');

    /***************************************************** 统计 ****************************************************/
    //首页-基础数据
    Route::get('statistics/basicData', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getBasicData');
    //首页-工单数据
    Route::get('statistics/workOrderData', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getWorkOrderData');
    //首页-代办总览数据
    Route::get('statistics/todoData', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getTodoData');
    //首页-交易趋势
    Route::get('statistics/tradingTrend', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getTradingTrend');
    //首页-服务类型占比
    Route::get('statistics/categoryRate', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getCategoryRate');
    //首页-热门服务排行
    Route::get('statistics/popularServiceRank', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getPopularServiceRank');
    //首页-师傅排行榜
    Route::get('statistics/technicianRank', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getTechnicianRank');
    //师傅报表
    Route::get('statistics/technician', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getTechnicianStatistics');
    //机构报表
    Route::get('statistics/store', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getStoreStatistics');
    //财务报表-收入趋势
    Route::get('statistics/finance/income_trend', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getFinanceIncomeTrendStats');
    //财务报表-门店收入趋势
    Route::get('statistics/finance/store_income_trend', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getFinanceStoreIncomeTrendStats');
    //财务报表-师傅收入趋势
    Route::get('statistics/finance/technician_income_trend', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getFinanceTechnicianIncomeTrendStats');
    //财务报表-售后支出趋势
    Route::get('statistics/finance/refund_expenditure', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getRefundExpenditureStats');
    //财务报表-收支盈利分析
    Route::get('statistics/finance/receipt_expenditure', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getReceiptExpenditureAnalyze');
    //客户报表
    Route::get('statistics/finance/member_stats', 'addon\home_service\app\adminapi\controller\statistics\Statistics@getMemberStats');
    /************************************************** 代客下单 *****************************************************/
    //已选商品计算
    Route::get('replace_buy/select/calculate', 'addon\home_service\app\adminapi\controller\replace_buy\OrderCreate@selectGoodsCalculate');
    //创建订单
    Route::post('replace_buy/create', 'addon\home_service\app\adminapi\controller\replace_buy\OrderCreate@create');
    //计算
    Route::get('replace_buy/calculate', 'addon\home_service\app\adminapi\controller\replace_buy\OrderCreate@calculate');
    //检测订单支付
    Route::get('replace_buy/check', 'addon\home_service\app\adminapi\controller\replace_buy\OrderCreate@checkPay');
    //查询优惠券
    Route::get('replace_buy/coupon', 'addon\home_service\app\adminapi\controller\replace_buy\OrderCreate@getCoupon');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
