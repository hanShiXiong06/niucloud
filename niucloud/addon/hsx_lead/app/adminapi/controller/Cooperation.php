<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{ConfigService,CooperationService,PartnerService,ReviewService,PaymentService,Store,UploadService};
use core\base\BaseAdminController;

final class Cooperation extends BaseAdminController
{
    private function context(): Context { return new Context((int)$this->request->siteId(),(int)$this->request->uid()); }
    public function config() { $ctx=$this->context();Store::permission($ctx,'config');return success((new ConfigService($ctx))->get()); }
    public function saveConfig() { return success((new ConfigService($this->context()))->save($this->request->param())); }
    public function dashboard() { return success((new CooperationService($this->context()))->dashboard()); }
    public function owners() { return success((new CooperationService($this->context()))->owners($this->request->param())); }
    public function leads() { return success((new CooperationService($this->context()))->lists($this->request->param())); }
    public function detail(int $id) { return success((new CooperationService($this->context()))->detail($id)); }
    public function transferOwner(int $id) { return success((new CooperationService($this->context()))->transferOwner($id,$this->request->param())); }
    public function partners() { return success((new PartnerService($this->context()))->lists($this->request->param())); }
    public function updatePartner(int $id) { return success((new PartnerService($this->context()))->update($id,$this->request->param())); }
    public function blockPartner(int $id) { return success((new PartnerService($this->context()))->block($id,$this->request->param())); }
    public function orders() { return success((new PaymentService($this->context()))->adminOrders($this->request->param())); }
    public function withdrawals() { return success((new PaymentService($this->context()))->adminWithdrawals($this->request->param())); }
    public function withdrawalDetail(int $id) { return success((new PaymentService($this->context()))->withdrawalDetail($id)); }
    public function paid(int $id) { return success((new PaymentService($this->context()))->markPaid($id,$this->request->param())); }
    public function reject(int $id) { return success((new PaymentService($this->context()))->reject($id,$this->request->param())); }
    public function reviews() { return success((new ReviewService($this->context()))->lists($this->request->param())); }
    public function createReview() { return success((new ReviewService($this->context()))->create($this->request->param())); }
    public function resolve(int $id) { return success((new ReviewService($this->context()))->resolve($id,$this->request->param())); }
    public function attachment() { return success((new UploadService($this->context()))->image()); }
}
