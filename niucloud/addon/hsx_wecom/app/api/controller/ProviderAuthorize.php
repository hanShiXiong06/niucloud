<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\api\controller;

use addon\hsx_wecom\app\service\core\WecomProviderAuthorizationService;
use core\base\BaseController;
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
            $url = (new WecomProviderAuthorizationService())->completeMemberBind(
                $channel,
                trim((string)$this->request->param('state', '')),
                trim((string)$this->request->param('code', ''))
            );
            return redirect($url);
        } catch (\Throwable $e) {
            Log::error('企业微信成员绑定回调失败', ['channel' => $channel, 'message' => $e->getMessage()]);
            return response('企业微信成员绑定失败：' . htmlspecialchars($e->getMessage()), 400)
                ->header(['Content-Type' => 'text/html;charset=utf-8']);
        }
    }
}
