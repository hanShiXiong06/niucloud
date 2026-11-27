<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;

use addon\hsx_boyuan\app\service\{OpeningCapitalAccounts,OpeningPostingService,PermissionService};
use addon\hsx_boyuan\app\support\Problem;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Log;

final class Opening extends BaseAdminController
{
    private function run(string $name)
    {
        $permissions=new PermissionService();
        $permissions->assertApi('get','erp/opening/lists');
        $permissions->assertApi('get','boyuan/opening-check');
        if ($name!=='check') $permissions->assertApi('post','boyuan/opening-preview');
        if ($name==='apply') {
            $permissions->assertApi('post','boyuan/opening-apply');
            $permissions->assertApi('post','erp/opening/<id>/confirm');
        }
        try {
            $site=(int)$this->request->siteId(); $uid=(int)$this->request->uid();
            $input=$this->request->params([['connection_key',''],['resource',''],['cursor',0],['token',''],['confirm',false],['choices',[]]]);
            $service=new OpeningPostingService();
            if($input['resource']===OpeningCapitalAccounts::RESOURCE) {
                $permissions->assertApi('get','erp/capital_account/lists');
                if($name==='apply') {
                    $permissions->assertApi('post','erp/capital_account/save/<id>');
                    $permissions->assertApi('post','boyuan/mappings');
                }
                if($name==='check') {
                    $cursor=filter_var($input['cursor'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
                    if($cursor===false) throw new Problem('OPENING_PARAMS','无效的账户页码，请重新打开');
                    return success($service->accountOptions($site,(string)$input['connection_key'],$cursor));
                }
            }
            if ($name!=='check') return success($service->handle($site,$uid,$input,$name==='apply'));
            $check=$service->check($site,is_string($input['connection_key'])?$input['connection_key']:'');
            $canPreview=$canApply=false;
            try { $permissions->assertApi('post','boyuan/opening-preview'); $canPreview=true;
                $permissions->assertApi('post','boyuan/opening-apply'); $permissions->assertApi('post','erp/opening/<id>/confirm'); $canApply=true;
            } catch(CommonException $e) {}
            $canReadAccounts=$canApplyAccounts=false;
            try { $permissions->assertApi('get','erp/capital_account/lists'); $canReadAccounts=$canPreview;
                $permissions->assertApi('post','erp/capital_account/save/<id>'); $permissions->assertApi('post','boyuan/mappings'); $canApplyAccounts=$canApply;
            } catch(CommonException $e) {}
            return success($check+['can_preview'=>$canPreview,'can_apply'=>$canApply,'can_read_accounts'=>$canReadAccounts,'can_apply_accounts'=>$canApplyAccounts]);
        } catch(Problem $e) { throw new CommonException('['.$e->reason.'] '.$e->getMessage()); }
        catch(CommonException $e) { throw $e; }
        catch(\Throwable $e) { Log::error('博远期初主档操作未确认',['operation'=>$name,'type'=>get_class($e),'line'=>$e->getLine()]); throw new CommonException('未能确认建档结果，请重新预览和核对本站资料，不要直接重复提交'); }
    }
    public function check() { return $this->run('check'); }
    public function preview() { return $this->run('preview'); }
    public function apply() { return $this->run('apply'); }
}
