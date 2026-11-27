<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\middleware;
use app\Request;
use app\service\admin\auth\AuthService;
use core\exception\AuthException;
use Closure;

/** Fail closed even if a plugin API permission has not yet been imported into the menu cache. */
final class PermissionGate
{
    public function handle(Request $request,Closure $next)
    {
        $rule=strtolower(trim($request->rule()->getRule()));
        $method=strtolower($request->method());
        $allowed=(new AuthService())->getAuthApiList();
        if (!in_array($rule,$allowed[$method]??[],true)) throw new AuthException('NO_PERMISSION');
        return $next($request);
    }
}
