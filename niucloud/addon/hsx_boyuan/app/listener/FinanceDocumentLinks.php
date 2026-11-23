<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;

use addon\hsx_boyuan\app\service\{ConfigService,FinanceResultService,PermissionService};
use core\exception\CommonException;

final class FinanceDocumentLinks
{
    public function handle(array $event): array
    {
        $site=(int)($event['site_id']??0); $dir=$event['direction']??'';
        if($site<=0 || (int)request()->siteId()!==$site || !in_array($dir,['payable','receivable'],true)) return [];
        $svc=new ConfigService();
        if(!$svc->installed($site)) return [];
        $c=$svc->get($site); $p=$c['profiles'][$c['mode']]??[];
        if(!in_array($c['mode'],['test','production'],true) || ($p['protocol']??'')!=='openapi_v153' || empty($p['connection_key']) || empty($p['probe_at'])) return [];
        try { (new PermissionService())->assertApi('get','boyuan/tasks/<id>'); }
        catch(CommonException $e) { return []; }
        $facts=(array)($event['facts']??[]);
        try { return (new FinanceResultService())->links($site,$dir,$facts,$c); }
        catch(\Throwable $e) {
            \think\facade\Log::warning('博远财务关联暂时不可读',['site_id'=>$site,'direction'=>$dir,'type'=>get_class($e)]);
            // An unavailable local query is not evidence that no external bill exists.
            $unavailable=[['key'=>'boyuan_finance_unavailable','provider_label'=>'博远单据','status'=>'unavailable','status_label'=>'读取失败','tone'=>'warning',
                'next_step'=>'请关闭明细后重新打开。不要据此重复提交或判断款项已结清。']];
            return array_fill_keys(array_keys($facts),$unavailable);
        }
    }
}
