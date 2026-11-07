<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\{Context,ProviderChannelService};
use addon\hsx_lead\app\service\cooperation\{ConfigService,CooperationService,PartnerService,ReviewService,PaymentService,Store,UploadService,BusinessLicenseService,BusinessService};
use core\base\BaseAdminController;

final class Cooperation extends BaseAdminController
{
    private function context(): Context { return new Context((int)$this->request->siteId(),(int)$this->request->uid()); }
    public function config() { $ctx=$this->context();Store::permission($ctx,'config');return success($this->request->param('section')==='erp_channels'?(new ProviderChannelService($ctx->site))->configuration():$this->businessConfiguration((new ConfigService($ctx))->get())); }
    public function saveConfig() { $ctx=$this->context();$input=$this->request->param();return success(($input['section']??'')==='erp_channels'?(new ProviderChannelService($ctx->site))->save($ctx,$input):$this->businessConfiguration((new ConfigService($ctx))->save($input))); }
    /** Available presets belong to the editor response, never the site's persisted rules. */
    private function businessConfiguration(array $config): array { return array_replace($config,['business_presets'=>BusinessService::defaults()]); }
    public function dashboard() { return success((new CooperationService($this->context()))->dashboard()); }
    public function scope() { $ctx=$this->context();$ctx->manager();return success(Store::scope($ctx)); }
    public function owners() { return success((new CooperationService($this->context()))->owners($this->request->param())); }
    public function leads() { return success((new CooperationService($this->context()))->lists($this->request->param())); }
    public function detail(int $id) { return success((new CooperationService($this->context()))->detail($id)); }
    public function transferOwner(int $id) { return success((new CooperationService($this->context()))->transferOwner($id,$this->request->param())); }
    public function partners() { return success((new PartnerService($this->context()))->lists($this->request->param())); }
    public function blockPartner(int $id) { return success((new PartnerService($this->context()))->block($id,$this->request->param())); }
    public function businessLicensePreview(int $id) { return success((new BusinessLicenseService($this->context()))->adminPreview($id))->header(['Cache-Control'=>'private, no-store','Pragma'=>'no-cache']); }
    public function orders() { return success((new PaymentService($this->context()))->adminOrders($this->request->param())); }
    public function orderOptions() { $ctx=$this->context();Store::permission($ctx,'finance');return success(\addon\hsx_lead\app\service\cooperation\OrderPaymentReadService::options()); }
    public function orderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->detail($id,true)); }
    public function requestOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->apply($id,$this->request->param(),true)); }
    public function reviewOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->review($id,$this->request->param())); }
    public function refreshOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->refresh($id,true)); }
    public function continueOrderRefund(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->continue($id,$this->request->param())); }
    public function closeOrder(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\PaymentOrderLifecycle($this->context()))->close($id,$this->request->param(),true)); }
    public function repriceOrder(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\OrderPricingService($this->context()))->reprice($id,$this->request->param())); }
    public function orderPricingLogs(int $id) { return success((new \addon\hsx_lead\app\service\cooperation\OrderPricingService($this->context()))->logs($id,$this->request->param())); }
    public function reviews() { return success((new ReviewService($this->context()))->lists($this->request->param())); }
    public function createReview() { return success((new ReviewService($this->context()))->create($this->request->param())); }
    public function resolve(int $id) { return success((new ReviewService($this->context()))->resolve($id,$this->request->param())); }
    public function attachment() { return success((new UploadService($this->context()))->image()); }
}
