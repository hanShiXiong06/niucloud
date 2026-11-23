<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;

use addon\hsx_boyuan\app\service\{ManualDocumentCommandService,PermissionService,WholesaleSyncService};
use addon\hsx_boyuan\app\support\Problem;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Log;

final class Wholesale extends BaseAdminController
{
    public function capture(int $id)
    {
        $acl=new PermissionService();
        $acl->assertApi('get','erp/sale/lists');
        $acl->assertApi('get','boyuan/tasks/<id>');
        $acl->assertApi('post','boyuan/wholesale/<id>/submit');
        $no=$this->request->params([['sale_no','']])['sale_no'];
        if(!is_string($no) || trim($no)==='' || strlen($no)>128) throw new CommonException('请从本站原销售单进入');
        $site=(int)$this->request->siteId(); $uid=(int)$this->request->uid();
        try { return success(\addon\hsx_boyuan\app\service\ConfigService::localPreparation($site,['kind'=>'wholesale_outbound','source_id'=>$id,'business_no'=>$no,'operator_uid'=>$uid],
            fn()=>(new WholesaleSyncService())->prepareSource($site,$uid,$id,$no))); }
        catch(Problem $e) { throw new CommonException($e->getMessage()); }
        catch(\Throwable $e) { Log::error('hsx_boyuan wholesale prepare sale='.$id.' type='.get_class($e)); throw new CommonException('准备核对记录未完成，请刷新原销售单；此操作不会对外发送出库单'); }
    }
    private function command(int $id,bool $read)
    {
        $action=$read?'verify':'submit';
        (new PermissionService())->assertApi('post','boyuan/wholesale/<id>/'.$action);
        $input=$this->request->params([['revision',-1],['mode',''],['connection_key',''],['customer_id',''],['creator_id','']]);
        try {
            $intent=$this->request->params([['intent','']])['intent']; if(!in_array($intent,['','process_current'],true)) throw new Problem('MANUAL_TASK_INVALID','不支持的单据处理动作');
            return success((new ManualDocumentCommandService())->run((int)$this->request->siteId(),$id,(int)$this->request->uid(),WholesaleSyncService::KIND,$read,$input,$intent==='process_current'));
        } catch(Problem $e) { throw new CommonException($e->getMessage()); }
        catch(\Throwable $e) { Log::error('hsx_boyuan wholesale '.$action.' task='.$id.' type='.get_class($e)); throw new CommonException('处理未确认，请刷新原单检查结果，不要重复出库'); }
    }
    public function submit(int $id) { return $this->command($id,false); }
    public function verify(int $id) { return $this->command($id,true); }
}
