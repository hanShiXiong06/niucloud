<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;
use addon\hsx_lead\app\service\{Context,ConnectionService,AuthChallengeService,SyncService};
use addon\hsx_lead\app\service\cooperation\{PartnerService,CooperationService,PaymentService,UploadService,BusinessLicenseService};
use addon\hsx_lead\app\support\Data;
use core\base\BaseApiController;
use core\exception\CommonException;

final class Cooperation extends BaseApiController
{
    public function paymentHelp()
    {
        return success(\addon\hsx_lead\app\service\cooperation\PaymentHelpService::get($this->context()))->header(['Cache-Control'=>'private, no-store']);
    }
    private function context(): Context { return new Context((int)$this->request->siteId(),0,(int)$this->request->memberId()); }
    private function service(): CooperationService { return new CooperationService($this->context()); }
    public function access() { return success(\addon\hsx_lead\app\service\cooperation\ConfigService::publicAccess((int)$this->request->siteId()))->header(['Cache-Control'=>'no-store']); }
    public function bootstrap() { return success((new PartnerService($this->context()))->bootstrap()); }
    public function profile() { return success((new PartnerService($this->context()))->saveProfile($this->request->param())); }
    public function profileForm() { return success((new \addon\hsx_lead\app\service\cooperation\ProfileFormService($this->context()))->member())->header(['Cache-Control'=>'private, no-store']); }
    public function catalog() { return success($this->service()->catalog($this->request->param())); }
    public function leads() { return success($this->service()->lists($this->request->param())); }
    public function createLead() { return success($this->service()->create($this->request->param())); }
    public function detail(int $id) { return success($this->service()->detail($id)); }
    public function stores() { return success($this->service()->stores($this->request->param())); }
    public function storeInventory(int $id) { \addon\hsx_lead\app\service\cooperation\ConfigService::assertErpVisible((int)$this->request->siteId());return success($this->service()->storeInventory($id,$this->request->param())); }
    public function leadStores(int $id) { return success($this->service()->stores($this->request->param(),$id)); }
    public function assign(int $id) { return success($this->service()->assign($id,$this->request->param())); }
    public function action(int $id) { return success($this->service()->action($id,$this->request->param())); }
    public function createOrder() { return success((new PaymentService($this->context()))->createOrder($this->request->param())); }
    public function order(int $id) { return success((new PaymentService($this->context()))->order($id)); }
    public function resumeOrder(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentOrderRecovery($this->context()))->resume($id))->header(['Cache-Control'=>'private, no-store']); }
    public function orders() { return success((new \addon\hsx_lead\app\service\cooperation\PaymentOrderLifecycle($this->context()))->orders($this->request->param())); }
    public function orderOptions() { $this->context();return success(\addon\hsx_lead\app\service\cooperation\OrderPaymentReadService::options()); }
    public function orderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->detail($id)); }
    public function requestOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->apply($id,$this->request->param())); }
    public function refreshOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->refresh($id)); }
    public function cancelOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->cancel($id,$this->request->param())); }
    public function closeOrder(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentOrderLifecycle($this->context()))->close($id,$this->request->param())); }
    public function wallet() { $ctx=$this->context();\addon\hsx_lead\app\service\cooperation\TeamService::assertOwner($ctx);return success((new PaymentService($ctx))->wallet($this->request->param())); }
    public function attachment() { return success((new UploadService($this->context()))->image()); }
    public function storefront() { return success((new UploadService($this->context()))->storefront()); }
    public function businessLicenseUpload() { return success((new BusinessLicenseService($this->context()))->upload()); }
    public function businessLicensePreview() { return success((new BusinessLicenseService($this->context()))->preview(Data::text($this->request->param(),'token',64,true)))->header(['Cache-Control'=>'private, no-store','Pragma'=>'no-cache']); }
    private function own(Context $ctx): int { \addon\hsx_lead\app\service\cooperation\ConfigService::assertErpVisible($ctx->site);\addon\hsx_lead\app\service\cooperation\TeamService::assertOwner($ctx);return (int)(new PartnerService($ctx))->own(true)['id']; }
    public function connection() { $ctx=$this->context();return success((new ConnectionService($ctx))->get($this->own($ctx))); }
    public function challenge() { $ctx=$this->context();return success((new AuthChallengeService($ctx))->begin($this->own($ctx),$this->request->param())); }
    public function probe() { $ctx=$this->context();return success((new ConnectionService($ctx))->probe($this->own($ctx),$this->request->param())); }
    public function activate() { $ctx=$this->context();return success((new ConnectionService($ctx))->activate($this->own($ctx),$this->request->param())); }
    public function connectionSettings() { $ctx=$this->context();return success((new ConnectionService($ctx))->syncSettings($this->own($ctx),$this->request->param())); }
    public function sync() { $ctx=$this->context();$id=$this->own($ctx);$service=new SyncService();return success($service->start($ctx->site,$id,0,'merchant')); }
    private function ownRun(Context $ctx,int $id): void { $mid=$this->own($ctx);$row=Data::row('sync_run',$ctx->site,$id);if ((int)$row['merchant_id']!==$mid) throw new CommonException('同步任务不属于当前门店'); }
    public function syncInfo(int $id) { $ctx=$this->context();$this->ownRun($ctx,$id);return success((new SyncService())->detail($ctx->site,$id)); }
    public function advanceSync(int $id) { $ctx=$this->context();$this->ownRun($ctx,$id);return success((new SyncService())->advance($ctx->site,$id)); }
}
