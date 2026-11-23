<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;

use addon\hsx_boyuan\app\service\SaleReceiptSyncService;
use addon\hsx_boyuan\app\support\Problem;
use core\base\BaseAdminController;
use core\exception\CommonException;

final class SaleReceipt extends BaseAdminController
{
    private function run(callable $f)
    {
        try { return success($f(new SaleReceiptSyncService(),(int)$this->request->siteId(),(int)$this->request->uid())); }
        catch(Problem $e){throw new CommonException('['.$e->reason.'] '.$e->getMessage());}
    }
    public function view(int $id){return $this->run(fn($s,$site)=>$s->view($site,$id));}
    public function submit(int $id){return $this->run(fn($s,$site,$uid)=>$s->submit($site,$id,$uid,$this->request->params([
        ['revision',-1],['sale_id',0],['settlement_id',0],['mode',''],['connection_key',''],['confirm_registration',false],['selection',[]],
    ])));}
}
