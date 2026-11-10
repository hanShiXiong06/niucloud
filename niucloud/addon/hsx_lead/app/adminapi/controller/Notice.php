<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;

use addon\hsx_lead\app\service\{Context,WeappNoticeLibrary};
use addon\hsx_lead\app\service\notice\{AccountNoticePolicyService,FinancialNoticeService};
use core\base\BaseAdminController;

/** Administrative reads and explicit queue actions; HTTP requests never send a message. */
final class Notice extends BaseAdminController
{
    private function context(?string $action=null): Context
    {
        $context=new Context((int)$this->request->siteId(),(int)$this->request->uid());
        $context->permission('hsx_lead_notice_center_read');
        if($action!==null)$context->permission($action);
        return $context;
    }
    public function tasks() { return $this->reply((new FinancialNoticeService())->page($this->context(),$this->request->param())); }
    public function attempts(int $id) { return $this->reply((new FinancialNoticeService())->detail($this->context('hsx_lead_notice_attempts'),$id)); }
    public function retry(int $id) { return $this->reply((new FinancialNoticeService())->retry($this->context('hsx_lead_notice_center_retry'),$id,$this->request->param())); }
    public function settings() { $context=$this->context('hsx_lead_notice_settings');return $this->reply((new AccountNoticePolicyService())->get($context->site)); }
    public function saveSettings() { return $this->reply((new AccountNoticePolicyService())->save($this->context('hsx_lead_notice_center_save'),$this->request->param())); }
    public function library() { $context=$this->context('hsx_lead_notice_library');return $this->reply((new WeappNoticeLibrary())->read($context->site,$this->request->param())); }
    private function reply(array $data) { return success($data)->header(['Cache-Control'=>'private, no-store']); }
}
