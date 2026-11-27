<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;

use addon\hsx_lead\app\service\notice\{NoticeEventCatalog,NoticePreferenceService};
use addon\hsx_lead\app\service\cooperation\CooperationNoticeService;
use core\base\BaseApiController;
use core\exception\CommonException;

/** Preferences always belong to the token identity, never a submitted member ID. */
final class Notice extends BaseApiController
{
    public function preferences()
    {
        return $this->reply((new NoticePreferenceService())->get((int) $this->request->siteId(), (int) $this->request->memberId()));
    }
    public function savePreferences()
    {
        return $this->reply((new NoticePreferenceService())->save((int) $this->request->siteId(), (int) $this->request->memberId(), $this->request->param()));
    }
    public function authorization()
    {
        $events = $this->request->param('event_keys', []);
        if (!is_array($events) || count($events) > count(NoticeEventCatalog::EVENTS))throw new CommonException('请重新选择需要订阅的通知');
        // This is only a wake-up hint. Channel consent/quota is still verified by WeChat on delivery.
        return $this->reply((new NoticePreferenceService())->authorizationReported((int) $this->request->siteId(), (int) $this->request->memberId(), $events));
    }
    /** Work-order notice subscription accepted in the mini program: wake 43101-rejected notices for this token's member. */
    public function cooperationAuthorization()
    {
        return $this->reply(CooperationNoticeService::resumeAfterSubscription((int) $this->request->siteId(), (int) $this->request->memberId()));
    }
    private function reply(array $data)
    {
        return success($data)->header(['Cache-Control' => 'private, no-store']);
    }
}
