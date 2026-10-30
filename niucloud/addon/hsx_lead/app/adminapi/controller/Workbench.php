<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\{Context,MerchantService,WorkflowService,ReadService,SyncService,CatalogService,ConnectionService,NoticeService,NoticeConfigService};
use addon\hsx_lead\app\provider\ProviderRegistry;
use core\base\BaseAdminController;

final class Workbench extends BaseAdminController
{
    private function context(): Context { return new Context((int)$this->request->siteId(),(int)$this->request->uid()); }
    private function workflow(): WorkflowService { return new WorkflowService($this->context()); }
    public function dashboard() { return success((new ReadService($this->context()))->dashboard()); }
    public function members() { return success((new MerchantService($this->context()))->members($this->request->param())); }
    public function providers() { $this->context()->manager(); return success(['items'=>ProviderRegistry::supported()]); }
    public function connection(int $id) { return success((new ConnectionService($this->context()))->get($id)); }
    public function probeConnection(int $id) { return success((new ConnectionService($this->context()))->probe($id,$this->request->param())); }
    public function activateConnection(int $id) { return success((new ConnectionService($this->context()))->activate($id,$this->request->param())); }
    public function connectionSettings(int $id) { return success((new ConnectionService($this->context()))->syncSettings($id,$this->request->param())); }
    public function noticeConfig() { $ctx=$this->context(); $ctx->manager(); return success((new NoticeConfigService())->get($ctx->site)); }
    public function saveNoticeConfig() { $ctx=$this->context(); $ctx->manager(); return success((new NoticeConfigService())->save($ctx->site,$this->request->param(),$ctx->uid)); }
    public function taskNotice(int $id) { $ctx=$this->context(); $ctx->manager(); $ctx->task($id); return success((new NoticeService())->statusForTask($ctx->site,$id)); }
    public function retryNotice(int $id) { $ctx=$this->context(); $ctx->manager(); $ctx->task($id); return success((new NoticeService())->retryTask($ctx->site,$id,$ctx->uid)); }
    public function catalog() { return success((new CatalogService($this->context()))->get()); }
    public function saveCatalog() { return success((new CatalogService($this->context()))->save($this->request->param())); }
    public function merchants() { return success((new MerchantService($this->context()))->lists($this->request->param())); }
    public function merchant(int $id) { return success((new MerchantService($this->context()))->detail($id)); }
    public function addMerchant() { return success((new MerchantService($this->context()))->save(0,$this->request->param())); }
    public function editMerchant(int $id) { return success((new MerchantService($this->context()))->save($id,$this->request->param())); }
    public function inventory() { return success((new ReadService($this->context()))->inventory($this->request->param())); }
    public function leads() { return success($this->workflow()->leads($this->request->param())); }
    public function lead(int $id) { return success($this->workflow()->lead($id)); }
    public function addLead() { return success($this->workflow()->saveLead(0,$this->request->param())); }
    public function editLead(int $id) { return success($this->workflow()->saveLead($id,$this->request->param())); }
    public function matches(int $id) { return success((new ReadService($this->context()))->matches($id)); }
    public function assign(int $id) { return success($this->workflow()->assign($id,$this->request->param())); }
    public function tasks() { return success($this->workflow()->tasks($this->request->param())); }
    public function task(int $id) { return success($this->workflow()->detail($id)); }
    public function accept(int $id) { return $this->action($id,'accept'); }
    public function reject(int $id) { return $this->action($id,'reject'); }
    public function follow(int $id) { return $this->action($id,'follow'); }
    public function deal(int $id) { return $this->action($id,'deal'); }
    public function verify(int $id) { return $this->action($id,'verify'); }
    public function close(int $id) { return $this->action($id,'close'); }
    public function cancel(int $id) { return $this->action($id,'cancel'); }
    private function action(int $id,string $action) { return success($this->workflow()->act($id,$action,$this->request->param())); }
    public function commissions() { return success($this->workflow()->commissions($this->request->param())); }
    public function receive(int $id) { return success($this->workflow()->commissionAction($id,'receive',$this->request->param())); }
    public function refund(int $id) { return success($this->workflow()->commissionAction($id,'refund',$this->request->param())); }
    public function sync(int $id)
    {
        $ctx=$this->context();
        $run=(new SyncService())->start($ctx->site,$id,$ctx->uid);
        return success((new SyncService())->advance($ctx->site,(int)$run['run_id']));
    }
    public function syncInfo(int $id) { return success((new SyncService())->detail($this->context()->site,$id)); }
    public function advanceSync(int $id) { return success((new SyncService())->advance($this->context()->site,$id)); }
}
