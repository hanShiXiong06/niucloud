<?php
declare(strict_types=1);

namespace addon\phone_shop\app\middleware;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\service\core\order\CoreOrderPaymentGuardService;
use app\Request;
use Closure;
use core\exception\CommonException;

/** 所有公共收银台发起的 phone_shop 支付都在插件内复核，包括旧页面、帮付入口。 */
class PhoneShopPaymentGuard
{
    public function handle(Request $request, Closure $next)
    {
        $controller = strtolower(str_replace(['\\', '/'], '.', $request->controller()));
        $action = strtolower($request->action());
        if (app()->http->getName() !== 'api'
            || !in_array($controller, ['pay.pay', 'app.api.controller.pay.pay'], true)
            || !in_array($action, ['pay', 'info'], true)
            || $request->param('trade_type') !== OrderDict::TYPE) {
            return $next($request);
        }

        // 复用前置认证的结果，不接受客户端另传的站点、会员作为业务身份。
        $siteId = (int)$request->siteId();
        $orderId = $request->param('trade_id');
        if ($siteId <= 0 || (int)$request->memberId() <= 0
            || !is_scalar($orderId) || !ctype_digit((string)$orderId) || (int)$orderId <= 0) {
            throw new CommonException('支付身份或订单信息不完整，请重新登录后再试');
        }

        $guard = new CoreOrderPaymentGuardService();
        return $guard->withOrderLock($siteId, (int)$orderId, function () use ($guard, $siteId, $orderId, $action, $request, $next) {
            $guard->assertPayable(
                $siteId,
                (int)$orderId,
                $action === 'pay' ? $request->param('phone_shop_expected_money', '') : ''
            );
            // 不接管扣款、渠道选择或回调：继续执行原公共支付控制器。
            return $next($request);
        });
    }
}
