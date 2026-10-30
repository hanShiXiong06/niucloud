<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\middleware;
use Closure;
use addon\hsx_lead\app\support\SafeExceptionHandle;
use think\App;
use think\exception\Handle;

/**
 * Plugin-local error boundary. The global debug handler records full request
 * bodies; ERP credentials and consumer contact data must never reach it.
 */
final class SafeErrors
{
    public function __construct(private readonly ?App $app=null) {}

    public function handle($request,Closure $next)
    {
        $app=$this->app??app();
        $previous=$app->make(Handle::class);
        $safe=new SafeExceptionHandle($app);
        // ThinkPHP's nested route/controller pipelines consume exceptions before
        // they reach an outer try/catch. Replace only this request's handler instance
        // while the plugin pipeline executes; never replace the framework binding.
        $app->instance(Handle::class,$safe);
        try {
            return $next($request);
        } catch(\Throwable $error) {
            $safe->report($error);
            return $safe->render($request,$error);
        } finally {
            // Also restore on success, rendering failure, or a nested plugin boundary.
            $app->instance(Handle::class,$previous);
        }
    }
}
