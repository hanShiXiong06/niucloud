<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;

use addon\hsx_boyuan\app\service\{PermissionService,PurchaseSyncService};
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
                if(!$canOperate) foreach(['editable','can_submit','can_verify'] as $flag) $view[$flag]=false;
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
        if($verify) $fields[]=['external_no','']; else { $fields[]=['selection',[]]; $fields[]=['remember',false]; }
        $input=$this->request->params($fields);
        if(!$verify && !empty($input['remember'])) (new PermissionService())->assertApi('post','boyuan/mappings');
        return $input;
    }
    public function saveDraft(int $id) { return $this->run('post','tasks/<id>/action',fn($site,$uid)=>(new PurchaseSyncService())->saveDraft($site,$id,$uid,$this->input())); }
    public function submit(int $id) { return $this->run('post','tasks/<id>/action',fn($site,$uid)=>(new PurchaseSyncService())->submit($site,$id,$uid,$this->input())); }
    public function verify(int $id) { return $this->run('post','tasks/<id>/action',fn($site,$uid)=>(new PurchaseSyncService())->queueVerify($site,$id,$uid,$this->input(true))); }
    public function capture(int $id) { return $this->run('post','tasks/<id>/action',fn($site,$uid)=>(new PurchaseSyncService())->capturePurchaseId($site,$uid,$id)); }
}
