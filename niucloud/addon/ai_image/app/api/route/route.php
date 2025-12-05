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

use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;


/**
 * AI设计大师
 */
Route::group('ai_image', function() {
    /***************************************************** AI设计前端接口 ****************************************************/
    //智能体列表
    Route::get('aiimagemodel', 'addon\ai_image\app\api\controller\aiimagemodel\AiimageModel@lists');
    //智能体详情
    Route::get('aiimagemodel/:id', 'addon\ai_image\app\api\controller\aiimagemodel\AiimageModel@info');
    //帮助中心列表
    Route::get('aiimagehelp', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@lists');
    //帮助中心详情
    Route::get('aiimagehelp/:id', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@info');
    //获取数据统计
    Route::get('getstat', 'addon\ai_image\app\api\controller\config\Config@getStat');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false) //false表示不验证登录
    ->middleware(ApiLog::class);

Route::group('ai_image', function() {
    //卡密列表
    Route::get('aiimagecard', 'addon\ai_image\app\api\controller\aiimagecard\AiimageCard@lists');
    //卡密兑换
    Route::post('aiimagecard/verify/:card_num', 'addon\ai_image\app\api\controller\aiimagecard\AiimageCard@verifyNum');
    //套餐列列表
    Route::get('aiimagepackage', 'addon\ai_image\app\api\controller\aiimagepackage\AiimagePackage@lists');
    //订单列列表
    Route::get('aiimageorder', 'addon\ai_image\app\api\controller\aiimageorder\AiimageOrder@lists');
    //订单列详情
    Route::get('aiimageorder/:id', 'addon\ai_image\app\api\controller\aiimageorder\AiimageOrder@info');
    //添加订单列
    Route::post('aiimageorder', 'addon\ai_image\app\api\controller\aiimageorder\AiimageOrder@add');
    //随行付扫码
    Route::get('getsxfscan/:id', 'addon\ai_image\app\api\controller\aiimageorder\AiimageOrder@getSxfScan');
    //查询随行付订单
    Route::get('querysxforder/:id', 'addon\ai_image\app\api\controller\aiimageorder\AiimageOrder@querySxfOrder');
    //更改卡密分配状态
    Route::put('aiimagecard/:id', 'addon\ai_image\app\api\controller\aiimagecard\AiimageCard@changeExport');
    //删除卡密
    Route::delete('aiimagecard/:id', 'addon\ai_image\app\api\controller\aiimagecard\AiimageCard@delete');
    //AI文案生成
    Route::post('sendtext', 'addon\ai_image\app\api\controller\ai\Chat@sendText');
    //作品列列表
    Route::get('aiimagecreate', 'addon\ai_image\app\api\controller\aiimagecreate\AiimageCreate@lists');
    //作品列详情
    Route::get('aiimagecreate/:id', 'addon\ai_image\app\api\controller\aiimagecreate\AiimageCreate@info');
    //添加作品列
    Route::post('aiimagecreate', 'addon\ai_image\app\api\controller\aiimagecreate\AiimageCreate@add');
    //删除作品列
    Route::delete('aiimagecreate/:id', 'addon\ai_image\app\api\controller\aiimagecreate\AiimageCreate@del');
    //获取配置
    Route::get('config/getconfig', 'addon\ai_image\app\api\controller\config\Config@getConfig');


})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //表示验证登录
    ->middleware(ApiLog::class);

