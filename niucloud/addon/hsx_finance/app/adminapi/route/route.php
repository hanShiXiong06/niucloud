<?php
declare(strict_types=1);

use think\facade\Route;

Route::group('finance', function () {
    // 能力检测(回收/ERP 是否在场) — 前端据此决定是否显示折账按钮
    Route::get('capability', 'addon\hsx_finance\app\adminapi\controller\Balance@capability');
    // 往来单位余额看板(折账入口)
    Route::get('balance/board', 'addon\hsx_finance\app\adminapi\controller\Balance@board');
    // 应付
    Route::get('payable/lists', 'addon\hsx_finance\app\adminapi\controller\Payable@lists');
    Route::get('payable/outstanding', 'addon\hsx_finance\app\adminapi\controller\Payable@outstanding');
    // 应收
    Route::get('receivable/lists', 'addon\hsx_finance\app\adminapi\controller\Receivable@lists');
    Route::get('receivable/outstanding', 'addon\hsx_finance\app\adminapi\controller\Receivable@outstanding');
    // 结算 / 折账
    Route::post('settlement/preview', 'addon\hsx_finance\app\adminapi\controller\Settlement@preview');
    Route::post('settlement/settle', 'addon\hsx_finance\app\adminapi\controller\Settlement@settle');
})->middleware(\app\adminapi\middleware\AdminCheckToken::class)
  ->middleware(\app\adminapi\middleware\AdminCheckRole::class);
