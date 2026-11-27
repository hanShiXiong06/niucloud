<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\pay;

use addon\phone_shop\app\middleware\PhoneShopPaymentGuard;

/** 使用框架已有 HttpRun 扩展点；不改写、不覆盖公共支付路由。 */
class RegisterPaymentGuard
{
    public function handle($event = null): void
    {
        // 控制器中间件晚于路由的站点、渠道及会员认证执行，也适用于路由缓存。
        app()->middleware->controller(PhoneShopPaymentGuard::class);
    }
}
