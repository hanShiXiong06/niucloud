<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\PromotionService;
use core\base\BaseAdminController;
final class Promotion extends BaseAdminController
{
    private function service(): PromotionService { return new PromotionService(new Context((int)$this->request->siteId(),(int)$this->request->uid())); }
    public function config() { return success($this->service()->adminConfig()); }
    public function saveConfig() { return success($this->service()->saveConfig($this->request->param())); }
    public function providers() { return success($this->service()->providers($this->request->param())); }
    public function saveProvider(int $id) { return success($this->service()->saveProvider($id,$this->request->param())); }
    public function rewards() { return success($this->service()->rewards($this->request->param(),true)); }
    public function settle() { return success($this->service()->settle($this->request->param())); }
    public function hold(int $id) { return success($this->service()->hold($id,$this->request->param())); }
    public function resume(int $id) { return success($this->service()->resume($id,$this->request->param())); }
}
