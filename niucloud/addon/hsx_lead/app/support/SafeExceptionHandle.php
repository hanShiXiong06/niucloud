<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

use core\exception\{CommonException,AuthException};
use think\exception\Handle;
use think\Response;

/** Request-scoped plugin handler: never delegate credential-bearing errors to core debug reporting. */
final class SafeExceptionHandle extends Handle
{
    public function report(\Throwable $error): void
    {
        if ($error instanceof CommonException || $error instanceof AuthException) return;
        try {
            // Deliberately exclude message, trace, request parameters, SQL and previous exception.
            $site=(int)$this->app->request->siteId();
            $this->app->log->error('hsx_lead request failed; type='.get_class($error).'; site='.$site);
        } catch(\Throwable $ignored) {
            // Logging availability must never leak the original error or trigger core reporting.
        }
    }

    public function render($request,\Throwable $error): Response
    {
        if ($error instanceof AuthException) return fail($error->getMessage(),[], $error->getCode()?:403);
        if ($error instanceof CommonException) return fail($error->getMessage(),[],400);
        return fail('操作未完成，请联系管理员核对服务状态；没有返回原始错误或凭据',[],500);
    }
}
