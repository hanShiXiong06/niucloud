<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\PromotionService;
use core\base\BaseApiController;
final class Promotion extends BaseApiController
{
    private function service(): PromotionService { return new PromotionService(new Context((int)$this->request->siteId(),0,(int)$this->request->memberId())); }
    public function overview() { return success($this->service()->overview()); }
    public function team() { return success($this->service()->team($this->request->param())); }
    public function rewards() { return success($this->service()->rewards($this->request->param())); }
    public function rules() { return success($this->service()->rules()); }
    public function invitation() { return success($this->service()->invitation((int)$this->request->param('mid',0))); }
    public function bind() { return success($this->service()->bind($this->request->param())); }
}
