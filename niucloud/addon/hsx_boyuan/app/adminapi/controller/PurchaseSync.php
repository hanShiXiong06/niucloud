<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;

use addon\hsx_boyuan\app\service\{ManualDocumentCommandService,PermissionService,PurchaseSyncService,PurchaseInventoryService};
use addon\hsx_boyuan\app\support\Problem;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Log;

final class PurchaseSync extends BaseAdminController
{
    private function run(string $method,string $permission,callable $callback)
    {
        (new PermissionService())->assertApi($method,'boyuan/'.$permission);
        try {
            $result=$callback((int)$this->request->siteId(),(int)$this->request->uid());
            $canRemember=false; $canOperate=false;
            try { (new PermissionService())->assertApi('post','boyuan/mappings'); $canRemember=true; } catch(CommonException $ignored) {}
            try { (new PermissionService())->assertApi('post','boyuan/tasks/<id>/action'); $canOperate=true; } catch(CommonException $ignored) {}
            $limit=static function(array $view) use($canRemember,$canOperate): array {
                $view['can_remember']=$canRemember && $canOperate;
                $view['can_operate']=$canOperate;
                if(!$canOperate) foreach(['editable','can_submit','can_verify','can_check_inventory','can_process_current'] as $flag) $view[$flag]=false;
                return $view;
            };
            if(is_array($result) && isset($result['task'])) $result=$limit($result);
            if(is_array($result) && isset($result['view']['task'])) $result['view']=$limit($result['view']);
            return success($result);
        }
        catch(Problem $e) { throw new CommonException('['.$e->reason.'] '.$e->getMessage()); }
        catch(\Throwable $e) { Log::error('hsx_boyuan purchase command failed; type='.get_class($e).'; location='.basename($e->getFile()).':'.$e->getLine()); throw new CommonException('采购核对未完成，请按任务编号检查服务日志；不要重复在博远建单'); }
    }
    public function view(int $id) { return $this->run('get','tasks/<id>',fn($site)=>(new PurchaseSyncService())->view($site,$id)); }
    private function input(bool $verify=false): array
    {
        $fields=[['revision',-1],['mode',''],['connection_key','']];
        if($verify) $fields[]=['external_no','']; else { $fields[]=['selection',[]]; $fields[]=['remember',false]; $fields[]=['confirm_pricing_buyer',false]; }
        $input=$this->request->params($fields);
        if(!$verify && !empty($input['remember'])) (new PermissionService())->assertApi('post','boyuan/mappings');
        return $input;
    }
    public function saveDraft(int $id) { return $this->run('post','tasks/<id>/action',fn($site,$uid)=>(new PurchaseSyncService())->saveDraft($site,$id,$uid,$this->input())); }
    private function command(int $id,bool $read) {
        return $this->run('post','tasks/<id>/action',function($site,$uid) use($id,$read) {
            $intent=$this->request->params([['intent','']])['intent']; if(!in_array($intent,['','process_current'],true)) throw new Problem('MANUAL_TASK_INVALID','不支持的单据处理动作');
            $input=$intent==='process_current'?$this->request->params([['revision',-1],['mode',''],['connection_key','']]):$this->input($read);
            // A real boolean is required; never treat a string such as "false" as approval.
            if(!$read && $intent==='') {
                $pilot=$this->request->params([['confirm_pilot',false]])['confirm_pilot'];
                if($pilot===true) $input['confirm_pilot']=true;
            }
            return (new ManualDocumentCommandService())->run($site,$id,$uid,'purchase',$read,$input,$intent==='process_current');
        });
    }
    public function submit(int $id) { return $this->command($id,false); }
    public function verify(int $id) { return $this->command($id,true); }
    public function capture(int $id) { return $this->run('post','tasks/<id>/action',function($site,$uid) use($id) {
        $intent=$this->request->params([['intent','']])['intent'];
        if(!in_array($intent,['','open_review','prepare_original'],true)) throw new Problem('INPUT_INVALID','不支持的原单操作');
        return \addon\hsx_boyuan\app\service\ConfigService::localPreparation($site,['kind'=>'purchase','source_id'=>$id,'operator_uid'=>$uid],function() use($id,$site,$uid,$intent) {
            $service=new PurchaseSyncService();
            if($intent!=='prepare_original') {
                try { return $service->capturePurchaseId($site,$uid,$id); }
                catch(Problem $e) { if($intent!=='open_review' || $e->reason!=='PURCHASE_FACT_MISSING') throw $e; }
            }
            (new PermissionService())->assertApi('get','erp/purchase/lists');
            return $service->original($site,$uid,$id,$intent==='open_review'?null:$this->request->params([
                ['mode',''],['connection_key',''],['revision',-1],['event_id',''],['purchase_no',''],['imei',''],['amount',''],['account_id','']]));
        });
    }); }
    public function inventoryCheck(int $id) { return $this->run('post','tasks/<id>/action',fn($site,$uid)=>(new PurchaseInventoryService())->queue($site,$id,$uid,$this->request->params([['revision',-1],['mode',''],['connection_key','']]),true)); }
}
