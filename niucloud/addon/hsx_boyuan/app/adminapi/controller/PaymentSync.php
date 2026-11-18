<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;

use addon\hsx_boyuan\app\service\{PaymentSyncService,PermissionService};
use addon\hsx_boyuan\app\support\Problem;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Log;

final class PaymentSync extends BaseAdminController
{
    private function run(bool $write,callable $callback)
    {
        (new PermissionService())->assertApi($write?'post':'get',$write?'boyuan/tasks/<id>/action':'boyuan/tasks/<id>');
        try { return success($callback((int)$this->request->siteId(),(int)$this->request->uid())); }
        catch(Problem $error) { throw new CommonException('['.$error->reason.'] '.$error->getMessage()); }
        catch(CommonException $error) { throw $error; }
        catch(\Throwable $error) {
            try { Log::warning('hsx_boyuan payment command unavailable',['error_type'=>get_class($error)]); } catch(\Throwable $ignored) {}
            throw new CommonException('付款登记未完成，请按任务编号检查服务日志；不要重复登记或发起转账');
        }
    }
    private function input(bool $capture=false): array
    {
        $fields=[['revision',-1],['mode',''],['connection_key','']];
        if($capture) $fields[]=['settlement_id',0];
        else { $fields[]=['selection',[]]; $fields[]=['confirm_registration',false]; $fields[]=['confirm_unverified_ap',false]; }
        return $this->request->params($fields);
    }
    public function candidates(int $id) { return $this->run(false,fn($site)=>(new PaymentSyncService())->candidates($site,$id,$this->request->params([['page',1],['limit',20]]))); }
    public function capture(int $id) { return $this->run(true,fn($site,$uid)=>(new PaymentSyncService())->capture($site,$id,$uid,$this->input(true))); }
    public function view(int $id) { return $this->run(false,fn($site)=>(new PaymentSyncService())->view($site,$id)); }
    public function saveDraft(int $id) { return $this->run(true,fn($site,$uid)=>(new PaymentSyncService())->saveDraft($site,$id,$uid,$this->input())); }
    public function submit(int $id) { return $this->run(true,fn($site,$uid)=>(new PaymentSyncService())->submit($site,$id,$uid,$this->input())); }
}
