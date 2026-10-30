<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;
use addon\hsx_lead\app\service\{Context,WorkflowService};
use core\base\BaseApiController;
final class Merchant extends BaseApiController
{
    private function workflow(): WorkflowService
    {
        // Identity comes exclusively from the framework member token, never a request merchant_id.
        return new WorkflowService(new Context((int)$this->request->siteId(),0,(int)$this->request->memberId()));
    }
    public function workbench() { return success($this->workflow()->portal($this->request->param())); }
    public function detail(int $id) { return success($this->workflow()->detail($id)); }
    public function accept(int $id) { return $this->action($id,'accept'); }
    public function reject(int $id) { return $this->action($id,'reject'); }
    public function follow(int $id) { return $this->action($id,'follow'); }
    public function deal(int $id) { return $this->action($id,'deal'); }
    public function close(int $id) { return $this->action($id,'close'); }
    private function action(int $id,string $action) { return success($this->workflow()->act($id,$action,$this->request->param())); }
}
