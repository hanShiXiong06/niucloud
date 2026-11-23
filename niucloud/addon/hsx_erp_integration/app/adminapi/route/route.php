<?php
declare(strict_types=1);

use addon\hsx_erp_integration\app\support\ConsoleMenu;
use app\adminapi\middleware\{AdminCheckToken, AdminCheckRole};
use think\facade\Route;

Route::group('integration', function () {
    foreach (ConsoleMenu::PAGES as [, , $actions]) {
        foreach ($actions as [, $method, $path, $action]) {
            $rule = Route::rule($path, 'addon\\hsx_erp_integration\\app\\adminapi\\controller\\' . $action, strtoupper($method));
            $rule->completeMatch()->pattern(['id' => '[1-9][0-9]*']);
        }
    }
})->middleware([AdminCheckToken::class, AdminCheckRole::class, \addon\hsx_erp_integration\app\adminapi\middleware\ConsoleAccess::class]);
