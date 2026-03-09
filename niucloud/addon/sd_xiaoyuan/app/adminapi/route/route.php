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

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

/**
 * 校园帮系统
 */
Route::group('sd_xiaoyuan', function () {

    // 数据概览
    Route::group('dashboard', function () {
        Route::get('index', 'addon\sd_xiaoyuan\app\adminapi\controller\Dashboard@index');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Dashboard@stat');
        Route::get('overview', 'addon\sd_xiaoyuan\app\adminapi\controller\Dashboard@overview');
        Route::get('trend', 'addon\sd_xiaoyuan\app\adminapi\controller\Dashboard@trend');
    });

    // 订单管理
    Route::group('order', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@detail');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@stat');
        Route::post('cancel', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@cancel');
        Route::post('refund', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@refund');
        Route::get('logs/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@logs');
        Route::post('assign_runner', 'addon\sd_xiaoyuan\app\adminapi\controller\Order@assignRunner');
    });

    // 接单员管理
    Route::group('runner', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@detail');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@audit');
        Route::post('disable', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@disable');
        Route::post('enable', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@enable');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@stat');
        Route::get('income/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@income');
        Route::get('balance_log/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@balanceLog');
        Route::get('online_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Runner@onlineList');
    });

    // 校园认证
    Route::group('campus_auth', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\CampusAuth@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\CampusAuth@detail');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\CampusAuth@audit');
        Route::post('cancel', 'addon\sd_xiaoyuan\app\adminapi\controller\CampusAuth@cancel');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\CampusAuth@stat');
    });

    // 提现管理
    Route::group('withdraw', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Withdraw@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Withdraw@detail');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\Withdraw@audit');
        Route::post('transfer', 'addon\sd_xiaoyuan\app\adminapi\controller\Withdraw@transfer');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Withdraw@stat');
    });

    // 评价管理
    Route::group('evaluate', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Evaluate@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Evaluate@detail');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\Evaluate@delete');
    });

    // 申诉管理
    Route::group('appeal', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Appeal@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Appeal@detail');
        Route::post('handle', 'addon\sd_xiaoyuan\app\adminapi\controller\Appeal@handle');
    });

    // 优惠券管理
    Route::group('coupon', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@detail');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@delete');
        Route::post('status', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@status');
        Route::get('records/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Coupon@records');
    });

    // 系统配置
    Route::group('config', function () {
        Route::get('index', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@index');
        Route::post('save', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@save');
        Route::get('get', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@get');
        Route::post('set', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@set');
        Route::get('fee', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@getFee');
        Route::post('fee', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@setFee');
        Route::get('range', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@getRange');
        Route::post('range', 'addon\sd_xiaoyuan\app\adminapi\controller\Config@setRange');
    });

    // 树洞管理
    Route::group('community', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Community@lists');
        Route::get('info/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\Community@info');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\Community@audit');
        Route::post('set_top', 'addon\sd_xiaoyuan\app\adminapi\controller\Community@setTop');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\Community@delete');
    });

    // 表白墙管理
    Route::group('confession', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Confession@lists');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\Confession@audit');
        Route::post('set_top', 'addon\sd_xiaoyuan\app\adminapi\controller\Confession@setTop');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\Confession@delete');
    });

    // 签到管理
    Route::group('sign', function () {
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Sign@stat');
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Sign@lists');
        Route::get('config', 'addon\sd_xiaoyuan\app\adminapi\controller\Sign@config');
        Route::post('config', 'addon\sd_xiaoyuan\app\adminapi\controller\Sign@config');
    });

    // 邀请分销管理
    Route::group('invite', function () {
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Invite@stat');
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Invite@lists');
        Route::get('config', 'addon\sd_xiaoyuan\app\adminapi\controller\Invite@config');
        Route::post('config', 'addon\sd_xiaoyuan\app\adminapi\controller\Invite@config');
    });

    // 房屋租赁管理
    Route::group('house', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\House@lists');
        Route::get('info/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\House@info');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\House@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\House@edit');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\House@audit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\House@delete');
    });

    // 房源订单管理
    Route::group('house_order', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\HouseOrder@lists');
        Route::post('handle', 'addon\sd_xiaoyuan\app\adminapi\controller\HouseOrder@handle');
        Route::post('refund_deposit', 'addon\sd_xiaoyuan\app\adminapi\controller\HouseOrder@refundDeposit');
        Route::post('refund_all', 'addon\sd_xiaoyuan\app\adminapi\controller\HouseOrder@refundAll');
    });

    // 树洞分类管理
    Route::group('community_category', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\CommunityCategory@lists');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\CommunityCategory@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\CommunityCategory@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\CommunityCategory@delete');
    });

    // 评论管理
    Route::group('comment', function () {
        Route::get('community', 'addon\sd_xiaoyuan\app\adminapi\controller\Comment@communityList');
        Route::get('confession', 'addon\sd_xiaoyuan\app\adminapi\controller\Comment@confessionList');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\Comment@audit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\Comment@delete');
    });

    // 接单员等级管理
    Route::group('runner_level', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\RunnerLevel@lists');
        Route::post('save', 'addon\sd_xiaoyuan\app\adminapi\controller\RunnerLevel@save');
        Route::get('invite_config', 'addon\sd_xiaoyuan\app\adminapi\controller\RunnerLevel@getInviteConfig');
        Route::post('invite_config', 'addon\sd_xiaoyuan\app\adminapi\controller\RunnerLevel@saveInviteConfig');
        Route::get('invite_rewards', 'addon\sd_xiaoyuan\app\adminapi\controller\RunnerLevel@inviteRewardList');
    });

    // 学校管理
    Route::group('school', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\School@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\School@info');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\School@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\School@edit');
        Route::post('del', 'addon\sd_xiaoyuan\app\adminapi\controller\School@del');
        Route::post('set_status', 'addon\sd_xiaoyuan\app\adminapi\controller\School@setStatus');
        Route::get('all', 'addon\sd_xiaoyuan\app\adminapi\controller\School@all');
    });

    // 校区管理
    Route::group('campus', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@info');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@delete');
        Route::post('set_status', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@setStatus');
        Route::get('all', 'addon\sd_xiaoyuan\app\adminapi\controller\Campus@all');
    });

    // 任务悬赏管理
    Route::group('task', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Task@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\Task@info');
        Route::get('type_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Task@typeList');
        Route::get('status_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Task@statusList');
    });

    // 拼单好饭管理
    Route::group('group_order', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\GroupOrder@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\GroupOrder@info');
        Route::get('type_list', 'addon\sd_xiaoyuan\app\adminapi\controller\GroupOrder@typeList');
        Route::get('status_list', 'addon\sd_xiaoyuan\app\adminapi\controller\GroupOrder@statusList');
    });

    // 二手交易管理
    Route::group('secondhand', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Secondhand@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\Secondhand@info');
        Route::get('category_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Secondhand@categoryList');
        Route::post('add_category', 'addon\sd_xiaoyuan\app\adminapi\controller\Secondhand@addCategory');
        Route::post('edit_category', 'addon\sd_xiaoyuan\app\adminapi\controller\Secondhand@editCategory');
        Route::post('del_category', 'addon\sd_xiaoyuan\app\adminapi\controller\Secondhand@delCategory');
    });

    // 失物招领管理
    Route::group('lost_found', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFound@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFound@info');
        Route::get('type_list', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFound@typeList');
        Route::get('category_list', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFound@categoryList');
    });

    // 系统消息管理
    Route::group('message', function () {
        Route::post('send', 'addon\sd_xiaoyuan\app\adminapi\controller\Message@send');
        Route::post('batch_send', 'addon\sd_xiaoyuan\app\adminapi\controller\Message@batchSend');
        Route::get('type_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Message@typeList');
    });

    // 信誉分管理
    Route::group('credit', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\Credit@lists');
        Route::get('info', 'addon\sd_xiaoyuan\app\adminapi\controller\Credit@info');
        Route::get('log_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Credit@logList');
        Route::post('adjust', 'addon\sd_xiaoyuan\app\adminapi\controller\Credit@adjust');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\Credit@stat');
        Route::get('type_list', 'addon\sd_xiaoyuan\app\adminapi\controller\Credit@typeList');
    });

    // 快递站点管理
    Route::group('express_station', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@lists');
        Route::get('info/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@info');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@add');
        Route::put('edit/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@edit');
        Route::delete('del/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@del');
        Route::put('status/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@setStatus');
        Route::get('all', 'addon\sd_xiaoyuan\app\adminapi\controller\ExpressStation@all');
    });

    // 积分商城管理
    Route::group('points_mall', function () {
        Route::get('goods_list', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@goodsList');
        Route::post('add_goods', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@addGoods');
        Route::post('edit_goods', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@editGoods');
        Route::post('del_goods', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@delGoods');
        Route::get('order_list', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@orderList');
        Route::post('set_order_status', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@setOrderStatus');
        Route::post('ship_order', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@shipOrder');
        Route::get('logistics', 'addon\sd_xiaoyuan\app\adminapi\controller\PointsMall@logistics');
    });

    // 游戏陪玩管理
    Route::group('game_companion', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCompanion@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCompanion@detail');
        Route::post('audit', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCompanion@audit');
        Route::post('set_top', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCompanion@setTop');
        Route::post('del', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCompanion@del');
        Route::get('stat', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCompanion@stat');
    });

    // 游戏类型分类管理
    Route::group('game_category', function () {
        Route::get('lists', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCategory@lists');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCategory@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCategory@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\GameCategory@delete');
    });

    // 失物招领分类管理
    Route::group('lost_found_category', function () {
        Route::get('lists', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFoundCategory@lists');
        Route::post('add', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFoundCategory@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFoundCategory@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\adminapi\controller\LostFoundCategory@delete');
    });

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
