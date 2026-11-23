<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\adminapi\middleware;

use app\Request;
use app\service\admin\auth\AuthService;
use Closure;
use think\facade\Log;

/** 未刷新菜单/未授予接口权限时不放行。审计只记操作、主体与结果，不记配置正文。 */
final class ConsoleAccess
{
    public function handle(Request $request, Closure $next)
    {
        $method = strtolower($request->method());
        $rule = strtolower(trim($request->rule()->getRule()));
        $auth = (new AuthService())->getAuthApiList();
        if (((int)$request->siteId() <= 0 && $request->appType() !== 'admin') || !in_array($rule, $auth[$method] ?? [], true)) {
            return fail('无此操作权限，请核对插件菜单和角色授权');
        }
        $response = $next($request);
        if ($method !== 'get') {
            Log::info(sprintf('hsx_erp_integration site=%d uid=%d action=%s %s id=%d result=%d',
                $request->siteId(), $request->uid(), $method, $rule, (int)$request->route('id', 0), $response->getData()['code'] ?? 0));
        }
        return $response;
    }
}
