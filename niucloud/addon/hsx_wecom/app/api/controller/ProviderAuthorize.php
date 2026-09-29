<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\api\controller;

use addon\hsx_wecom\app\service\core\WecomProviderAuthorizationService;
use addon\hsx_wecom\app\support\WecomBindingResultPage;
use core\base\BaseController;
use core\exception\CommonException;
use think\facade\Log;
use think\Response;

final class ProviderAuthorize extends BaseController
{
    public function complete(string $channel): Response
    {
        try {
            $url = (new WecomProviderAuthorizationService())->completeInstall(
                $channel,
                trim((string)$this->request->param('state', '')),
                trim((string)$this->request->param('auth_code', ''))
            );
            return redirect($url);
        } catch (\Throwable $e) {
            Log::error('企业微信授权回调失败', ['channel' => $channel, 'message' => $e->getMessage()]);
            return response('企业微信授权失败：' . htmlspecialchars($e->getMessage()), 400)
                ->header(['Content-Type' => 'text/html;charset=utf-8']);
        }
    }

    public function member(string $channel): Response
    {
        try {
            $result = (new WecomProviderAuthorizationService())->completeMemberBind(
                $channel,
                trim((string)$this->request->param('state', '')),
                trim((string)$this->request->param('code', ''))
            );
            return response(WecomBindingResultPage::render($result))->header(WecomBindingResultPage::headers());
        } catch (\Throwable $e) {
            // 不把第三方原始错误、SQL 或请求中的 OAuth 参数直接显示给员工。
            Log::error('企业微信成员绑定回调失败', ['channel' => $channel, 'error_type' => get_class($e)]);
            $message = $e instanceof CommonException ? $e->getMessage() : '绑定服务暂不可用，请联系管理员稍后重新生成二维码';
            return response(WecomBindingResultPage::render(['status' => 'failed', 'message' => $message]), 400)
                ->header(WecomBindingResultPage::headers());
        }
    }
}
