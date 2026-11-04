<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\{Context,ProviderChannelService};
use addon\hsx_lead\app\service\cooperation\{ConfigService,CooperationService,PartnerService,ReviewService,PaymentService,Store,UploadService};
use core\base\BaseAdminController;

final class Cooperation extends BaseAdminController
{
    private function context(): Context { return new Context((int)$this->request->siteId(),(int)$this->request->uid()); }
    public function config() { $ctx=$this->context();Store::permission($ctx,'config');return success($this->request->param('section')==='erp_channels'?(new ProviderChannelService($ctx->site))->configuration():(new ConfigService($ctx))->get()); }
    public function saveConfig() { $ctx=$this->context();$input=$this->request->param();return success(($input['section']??'')==='erp_channels'?(new ProviderChannelService($ctx->site))->save($ctx,$input):(new ConfigService($ctx))->save($input)); }
    public function dashboard() { return success((new CooperationService($this->context()))->dashboard()); }
    public function scope() { $ctx=$this->context();$ctx->manager();return success(Store::scope($ctx)); }
    public function owners() { return success((new CooperationService($this->context()))->owners($this->request->param())); }
    public function leads() { return success((new CooperationService($this->context()))->lists($this->request->param())); }
    public function detail(int $id) { return success((new CooperationService($this->context()))->detail($id)); }
    public function transferOwner(int $id) { return success((new CooperationService($this->context()))->transferOwner($id,$this->request->param())); }
    public function partners() { return success((new PartnerService($this->context()))->lists($this->request->param())); }
    public function updatePartner(int $id) { return success((new PartnerService($this->context()))->update($id,$this->request->param())); }
    public function blockPartner(int $id) { return success((new PartnerService($this->context()))->block($id,$this->request->param())); }
    public function orders() { return success((new PaymentService($this->context()))->adminOrders($this->request->param())); }
    public function reviews() { return success((new ReviewService($this->context()))->lists($this->request->param())); }
    public function createReview() { return success((new ReviewService($this->context()))->create($this->request->param())); }
    public function resolve(int $id) { return success((new ReviewService($this->context()))->resolve($id,$this->request->param())); }
    public function attachment() { return success((new UploadService($this->context()))->image()); }
}
