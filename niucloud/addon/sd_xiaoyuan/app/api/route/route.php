<?php

use think\facade\Route;
use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;

// 不需要登录的公开接口
Route::group('sd_xiaoyuan', function () {
    // 学校接口（公开）
    Route::group('school', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\School@list');
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\School@info');
        Route::get('campus_list', 'addon\sd_xiaoyuan\app\api\controller\School@campusList');
        Route::get('grades', 'addon\sd_xiaoyuan\app\api\controller\School@grades');
        Route::get('classes', 'addon\sd_xiaoyuan\app\api\controller\School@classes');
    });
    
    // 订单大厅（公开浏览）
    Route::get('order/hall', 'addon\sd_xiaoyuan\app\api\controller\Order@hall');
    
    // 公共接口（公开）
    Route::group('common', function () {
        Route::get('config', 'addon\sd_xiaoyuan\app\api\controller\Common@config');
        Route::get('task_types', 'addon\sd_xiaoyuan\app\api\controller\Common@taskTypes');
        Route::get('home_stats', 'addon\sd_xiaoyuan\app\api\controller\Common@homeStats');
        Route::get('nearby_runners', 'addon\sd_xiaoyuan\app\api\controller\Common@nearbyRunners');
    });
    
    // 绑定分销关系（可选登录，未登录不报错）
    Route::post('invite/bind', 'addon\sd_xiaoyuan\app\api\controller\Invite@bindRelation');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)
    ->middleware(ApiLog::class);

// 需要登录的接口
Route::group('sd_xiaoyuan', function () {

    // 订单接口
    Route::group('order', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Order@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Order@detail');
        Route::post('create', 'addon\sd_xiaoyuan\app\api\controller\Order@create');
        Route::post('cancel', 'addon\sd_xiaoyuan\app\api\controller\Order@cancel');
        Route::post('pay', 'addon\sd_xiaoyuan\app\api\controller\Order@pay');
        Route::get('calculate_fee', 'addon\sd_xiaoyuan\app\api\controller\Order@calculateFee');
        Route::get('runner_location/:id', 'addon\sd_xiaoyuan\app\api\controller\Order@getRunnerLocation');
        Route::post('confirm', 'addon\sd_xiaoyuan\app\api\controller\Order@confirm');
        Route::post('tip', 'addon\sd_xiaoyuan\app\api\controller\Order@tip');
    });

    // 校园认证接口
    Route::group('campus_auth', function () {
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\CampusAuth@info');
        Route::post('apply', 'addon\sd_xiaoyuan\app\api\controller\CampusAuth@apply');
        Route::get('status', 'addon\sd_xiaoyuan\app\api\controller\CampusAuth@status');
    });

    // 地址接口
    Route::group('address', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Address@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Address@detail');
        Route::post('add', 'addon\sd_xiaoyuan\app\api\controller\Address@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\api\controller\Address@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\Address@delete');
        Route::post('set_default', 'addon\sd_xiaoyuan\app\api\controller\Address@setDefault');
        Route::get('default', 'addon\sd_xiaoyuan\app\api\controller\Address@getDefault');
    });

    // 优惠券接口
    Route::group('coupon', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Coupon@lists');
        Route::post('receive', 'addon\sd_xiaoyuan\app\api\controller\Coupon@receive');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\Coupon@myCoupons');
        Route::get('available', 'addon\sd_xiaoyuan\app\api\controller\Coupon@available');
    });

    Route::group('card', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Card@lists');
        Route::get('detail', 'addon\sd_xiaoyuan\app\api\controller\Card@detail');
        Route::post('create_order', 'addon\sd_xiaoyuan\app\api\controller\Card@createOrder');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\Card@my');
        Route::get('use_logs', 'addon\sd_xiaoyuan\app\api\controller\Card@useLogs');
    });

    // 评价接口
    Route::group('evaluate', function () {
        Route::get('list/:runner_id', 'addon\sd_xiaoyuan\app\api\controller\Evaluate@lists');
        Route::post('add', 'addon\sd_xiaoyuan\app\api\controller\Evaluate@add');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\Evaluate@myEvaluates');
    });

    // 接单员端接口
    Route::group('runner', function () {
        // 接单员信息
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@info');
        Route::post('apply', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@apply');
        Route::post('update_location', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@updateLocation');
        Route::post('set_online', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@setOnline');
        Route::post('set_range', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@setRange');
        Route::post('update_info', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@updateInfo');
        Route::post('subscribe_record', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@subscribeRecord');
        Route::get('stat', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@stat');
        Route::get('income', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@income');
        Route::get('balance_log', 'addon\sd_xiaoyuan\app\api\controller\runner\Runner@balanceLog');

        // 接单员订单
        Route::get('order/hall', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@hall');
        Route::get('order/my', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@myOrders');
        Route::get('order/detail/:id', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@detail');
        Route::post('order/accept', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@accept');
        Route::post('order/reject', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@reject');
        Route::post('order/pickup', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@pickup');
        Route::post('order/delivery', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@delivery');
        Route::post('order/complete', 'addon\sd_xiaoyuan\app\api\controller\runner\Order@complete');

        // 提现
        Route::get('withdraw/list', 'addon\sd_xiaoyuan\app\api\controller\runner\Withdraw@lists');
        Route::post('withdraw/apply', 'addon\sd_xiaoyuan\app\api\controller\runner\Withdraw@apply');
        Route::get('withdraw/config', 'addon\sd_xiaoyuan\app\api\controller\runner\Withdraw@config');

        // 评价
        Route::get('evaluate/list', 'addon\sd_xiaoyuan\app\api\controller\runner\Evaluate@lists');

        // 申诉
        Route::get('appeal/list', 'addon\sd_xiaoyuan\app\api\controller\runner\Appeal@lists');
        Route::post('appeal/add', 'addon\sd_xiaoyuan\app\api\controller\runner\Appeal@add');
        Route::get('appeal/detail/:id', 'addon\sd_xiaoyuan\app\api\controller\runner\Appeal@detail');
    });

    // 快递站点接口
    Route::group('express', function () {
        Route::get('stations', 'addon\sd_xiaoyuan\app\api\controller\Express@stations');
        Route::get('package_prices', 'addon\sd_xiaoyuan\app\api\controller\Express@packagePrices');
    });

    // 树洞接口
    Route::group('community', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Community@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Community@detail');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\Community@publish');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\Community@delete');
        Route::post('like', 'addon\sd_xiaoyuan\app\api\controller\Community@like');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\Community@myPosts');
        Route::get('categories', 'addon\sd_xiaoyuan\app\api\controller\Community@categories');
        Route::get('stats', 'addon\sd_xiaoyuan\app\api\controller\Community@stats');
    });

    // 表白墙接口
    Route::group('confession', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Confession@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Confession@detail');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\Confession@publish');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\Confession@delete');
        Route::post('like', 'addon\sd_xiaoyuan\app\api\controller\Confession@like');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\Confession@myList');
        Route::get('stats', 'addon\sd_xiaoyuan\app\api\controller\Confession@stats');
    });

    // 课表接口
    Route::group('schedule', function () {
        Route::get('index', 'addon\sd_xiaoyuan\app\api\controller\Schedule@index');
        Route::get('detail', 'addon\sd_xiaoyuan\app\api\controller\Schedule@detail');
        Route::post('add', 'addon\sd_xiaoyuan\app\api\controller\Schedule@add');
        Route::post('edit', 'addon\sd_xiaoyuan\app\api\controller\Schedule@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\Schedule@delete');
        Route::post('clear', 'addon\sd_xiaoyuan\app\api\controller\Schedule@clear');
        Route::get('class_schedule', 'addon\sd_xiaoyuan\app\api\controller\Schedule@classSchedule');
        Route::post('bind_class', 'addon\sd_xiaoyuan\app\api\controller\Schedule@bindClass');
    });

    // 签到接口
    Route::group('sign', function () {
        Route::post('sign', 'addon\sd_xiaoyuan\app\api\controller\Sign@sign');
        Route::get('status', 'addon\sd_xiaoyuan\app\api\controller\Sign@status');
        Route::get('history', 'addon\sd_xiaoyuan\app\api\controller\Sign@history');
    });

    // 邀请有礼接口（bind已移到公开接口组）
    Route::group('invite', function () {
        Route::get('stat', 'addon\sd_xiaoyuan\app\api\controller\Invite@stat');
        Route::get('team', 'addon\sd_xiaoyuan\app\api\controller\Invite@team');
        Route::get('team_stat', 'addon\sd_xiaoyuan\app\api\controller\Invite@teamStat');
        Route::get('relation', 'addon\sd_xiaoyuan\app\api\controller\Invite@relation');
        Route::get('poster', 'addon\sd_xiaoyuan\app\api\controller\Invite@poster');
    });

    // 房屋租赁接口（浏览+下单，发布由后台管理）
    Route::group('house', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\House@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\House@detail');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\House@publish');
        Route::post('edit', 'addon\sd_xiaoyuan\app\api\controller\House@edit');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\House@delete');
        Route::post('offline', 'addon\sd_xiaoyuan\app\api\controller\House@offline');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\House@my');
        Route::post('order', 'addon\sd_xiaoyuan\app\api\controller\House@order');
        Route::get('my_orders', 'addon\sd_xiaoyuan\app\api\controller\House@myOrders');
    });

    // 评论接口
    Route::group('comment', function () {
        Route::get('community', 'addon\sd_xiaoyuan\app\api\controller\Comment@communityList');
        Route::post('community/add', 'addon\sd_xiaoyuan\app\api\controller\Comment@addCommunity');
        Route::get('confession', 'addon\sd_xiaoyuan\app\api\controller\Comment@confessionList');
        Route::post('confession/add', 'addon\sd_xiaoyuan\app\api\controller\Comment@addConfession');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\Comment@delete');
    });

    // 接单员等级和位置接口
    Route::group('runner_level', function () {
        Route::get('levels', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@levels');
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@info');
        Route::post('update_location', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@updateLocation');
        Route::get('location', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@getLocation');
        Route::get('nearby', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@nearby');
        Route::post('bind_inviter', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@bindInviter');
        Route::get('invited_runners', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@invitedRunners');
        Route::get('invite_rewards', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@inviteRewards');
        Route::get('invite_stats', 'addon\sd_xiaoyuan\app\api\controller\RunnerLevel@inviteStats');
    });

    // 跑腿任务接口
    Route::group('task', function () {
        Route::get('type_list', 'addon\sd_xiaoyuan\app\api\controller\Task@typeList');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Task@list');
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\Task@info');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\Task@publish');
        Route::post('pay', 'addon\sd_xiaoyuan\app\api\controller\Task@pay');
        Route::post('accept', 'addon\sd_xiaoyuan\app\api\controller\Task@accept');
        Route::post('start', 'addon\sd_xiaoyuan\app\api\controller\Task@start');
        Route::post('submit_complete', 'addon\sd_xiaoyuan\app\api\controller\Task@submitComplete');
        Route::post('confirm_complete', 'addon\sd_xiaoyuan\app\api\controller\Task@confirmComplete');
        Route::post('cancel', 'addon\sd_xiaoyuan\app\api\controller\Task@cancel');
        Route::get('my_publish', 'addon\sd_xiaoyuan\app\api\controller\Task@myPublish');
        Route::get('my_accept', 'addon\sd_xiaoyuan\app\api\controller\Task@myAccept');
    });

    // 拼单好饭接口
    Route::group('group_order', function () {
        Route::get('type_list', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@typeList');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@list');
        Route::get('stats', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@stats');
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@info');
        Route::post('create', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@create');
        Route::post('join', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@join');
        Route::post('quit', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@quit');
        Route::post('cancel', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@cancel');
        Route::post('confirm_success', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@confirmSuccess');
        Route::post('complete', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@complete');
        Route::get('my_create', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@myCreate');
        Route::get('my_join', 'addon\sd_xiaoyuan\app\api\controller\GroupOrder@myJoin');
    });

    // 二手交易接口
    Route::group('secondhand', function () {
        Route::get('category_list', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@categoryList');
        Route::get('trade_method_list', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@tradeMethodList');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@list');
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@info');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@info');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@publish');
        Route::post('edit', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@edit');
        Route::post('off', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@off');
        Route::post('on', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@on');
        Route::post('sold', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@sold');
        Route::post('del', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@del');
        Route::post('want', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@want');
        Route::get('my_publish', 'addon\sd_xiaoyuan\app\api\controller\Secondhand@myPublish');
    });

    // 失物招领接口
    Route::group('lost_found', function () {
        Route::get('type_list', 'addon\sd_xiaoyuan\app\api\controller\LostFound@typeList');
        Route::get('category_list', 'addon\sd_xiaoyuan\app\api\controller\LostFound@categoryList');
        Route::get('stats', 'addon\sd_xiaoyuan\app\api\controller\LostFound@stats');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\LostFound@list');
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\LostFound@info');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\LostFound@info');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\LostFound@publish');
        Route::post('edit', 'addon\sd_xiaoyuan\app\api\controller\LostFound@edit');
        Route::post('resolve', 'addon\sd_xiaoyuan\app\api\controller\LostFound@resolve');
        Route::post('close', 'addon\sd_xiaoyuan\app\api\controller\LostFound@close');
        Route::post('del', 'addon\sd_xiaoyuan\app\api\controller\LostFound@del');
        Route::post('contact', 'addon\sd_xiaoyuan\app\api\controller\LostFound@contact');
        Route::get('my_publish', 'addon\sd_xiaoyuan\app\api\controller\LostFound@myPublish');
    });

    // 系统消息接口
    Route::group('message', function () {
        Route::get('type_list', 'addon\sd_xiaoyuan\app\api\controller\Message@typeList');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Message@list');
        Route::get('unread_count', 'addon\sd_xiaoyuan\app\api\controller\Message@unreadCount');
        Route::get('unread_count_by_type', 'addon\sd_xiaoyuan\app\api\controller\Message@unreadCountByType');
        Route::post('read', 'addon\sd_xiaoyuan\app\api\controller\Message@read');
        Route::post('read_all', 'addon\sd_xiaoyuan\app\api\controller\Message@readAll');
        Route::post('del', 'addon\sd_xiaoyuan\app\api\controller\Message@del');
        Route::post('clear', 'addon\sd_xiaoyuan\app\api\controller\Message@clear');
    });

    // 钱包接口
    Route::group('wallet', function () {
        Route::get('balance', 'addon\sd_xiaoyuan\app\api\controller\Wallet@balance');
        Route::get('log_list', 'addon\sd_xiaoyuan\app\api\controller\Wallet@logList');
    });

    // 信誉分接口
    Route::group('credit', function () {
        Route::get('info', 'addon\sd_xiaoyuan\app\api\controller\Credit@info');
        Route::get('log_list', 'addon\sd_xiaoyuan\app\api\controller\Credit@logList');
        Route::get('check', 'addon\sd_xiaoyuan\app\api\controller\Credit@checkCanOperate');
    });

    // 帮帮忙接口
    Route::group('help', function () {
        Route::post('create', 'addon\sd_xiaoyuan\app\api\controller\Help@create');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Help@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Help@detail');
        Route::post('accept', 'addon\sd_xiaoyuan\app\api\controller\Help@accept');
        Route::post('cancel', 'addon\sd_xiaoyuan\app\api\controller\Help@cancel');
        Route::post('complete', 'addon\sd_xiaoyuan\app\api\controller\Help@complete');
        Route::get('my_publish', 'addon\sd_xiaoyuan\app\api\controller\Help@myPublish');
        Route::get('my_accept', 'addon\sd_xiaoyuan\app\api\controller\Help@myAccept');
    });

    // 代占座位接口
    Route::group('seat', function () {
        Route::post('create', 'addon\sd_xiaoyuan\app\api\controller\Seat@create');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Seat@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Seat@detail');
    });

    // 代排队接口
    Route::group('queue', function () {
        Route::post('create', 'addon\sd_xiaoyuan\app\api\controller\Queue@create');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Queue@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Queue@detail');
    });

    // 代上课接口
    Route::group('class_order', function () {
        Route::post('create', 'addon\sd_xiaoyuan\app\api\controller\ClassOrder@create');
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\ClassOrder@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\ClassOrder@detail');
    });

    // 游戏陪玩接口
    Route::group('game_companion', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@list');
        Route::get('detail', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@detail');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@publish');
        Route::post('edit', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@edit');
        Route::post('set_status', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@setStatus');
        Route::post('del', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@del');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@my');
        Route::get('game_types', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@gameTypes');
        Route::get('service_types', 'addon\sd_xiaoyuan\app\api\controller\GameCompanion@serviceTypes');
    });

    // 积分商城接口
    Route::group('points_mall', function () {
        Route::get('goods_list', 'addon\sd_xiaoyuan\app\api\controller\PointsMall@goodsList');
        Route::get('goods_detail', 'addon\sd_xiaoyuan\app\api\controller\PointsMall@goodsDetail');
        Route::post('exchange', 'addon\sd_xiaoyuan\app\api\controller\PointsMall@exchange');
        Route::get('my_orders', 'addon\sd_xiaoyuan\app\api\controller\PointsMall@myOrders');
        Route::get('order_detail', 'addon\sd_xiaoyuan\app\api\controller\PointsMall@orderDetail');
        Route::get('logistics', 'addon\sd_xiaoyuan\app\api\controller\PointsMall@logistics');
    });

    Route::group('member', function () {
        Route::get('points', 'addon\sd_xiaoyuan\app\api\controller\Member@points');
        Route::get('points_record', 'addon\sd_xiaoyuan\app\api\controller\Member@pointsRecord');
    });

    // 留言板接口
    Route::group('guestbook', function () {
        Route::get('list', 'addon\sd_xiaoyuan\app\api\controller\Guestbook@lists');
        Route::get('detail/:id', 'addon\sd_xiaoyuan\app\api\controller\Guestbook@detail');
        Route::post('publish', 'addon\sd_xiaoyuan\app\api\controller\Guestbook@publish');
        Route::post('delete', 'addon\sd_xiaoyuan\app\api\controller\Guestbook@delete');
        Route::get('my', 'addon\sd_xiaoyuan\app\api\controller\Guestbook@my');
        Route::get('stats', 'addon\sd_xiaoyuan\app\api\controller\Guestbook@stats');
    });

    // 文件上传接口
    Route::group('upload', function () {
        Route::post('document', 'addon\sd_xiaoyuan\app\api\controller\Upload@document');
    });
    
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)
    ->middleware(ApiLog::class);

// 覆盖框架 pay 路由：校园帮支付不走会被其它插件 return true 污染的 PayCreate 事件链
Route::group('pay', function () {
    Route::get('info/:trade_type/:trade_id', 'addon\sd_xiaoyuan\app\api\controller\Pay@info');
    Route::post('', 'addon\sd_xiaoyuan\app\api\controller\Pay@pay');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)
    ->middleware(ApiLog::class);
