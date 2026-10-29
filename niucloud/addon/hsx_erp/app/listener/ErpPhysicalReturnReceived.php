<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\listener;
use addon\hsx_erp\app\service\admin\{ErpIntegrationDocumentService,ErpIntegrationService};
use think\facade\Db;
use core\exception\CommonException;
/** Records physical receipt only; an earlier payment refund must never imply third-party stock receipt. */
final class ErpPhysicalReturnReceived
{
    public function handle(array $event=[]): array
    {
        $site=(int)($event['site_id']??0);
        if ($site<=0 || ($event['source_plugin']??'')!=='phone_shop' || !Db::connect()->getPdo()->inTransaction()) throw new CommonException('实物收回事实缺少合法来源或业务事务');
        $table=(new \addon\hsx_erp\app\model\ErpSaleOrder())->getTable();
        $item=Db::name('erp_sale_item')->alias('i')->join($table.' o','o.id=i.sale_order_id AND o.site_id=i.site_id')
            ->where('i.site_id',$site)->where('o.origin_plugin','phone_shop')->where('o.origin_id',(string)$event['source_order_id'])
            ->where('i.external_line_id',(string)$event['source_line_id'])->where('i.asset_id',(int)$event['asset_id'])->where('i.status','returned')->field('i.id,i.sale_order_id')->find();
        if (!$item) throw new CommonException('未找到已退款的原销售设备，未生成第三方回库事件');
        $snapshot=ErpIntegrationDocumentService::record($site,'sale',(int)$item['sale_order_id'],[(int)$event['asset_id']],false);
        if (!$snapshot || count($snapshot['lines'])!==1) throw new CommonException('实物退货原销售快照不完整');
        $key=hash('sha256',$event['source_order_id'].':'.$event['source_line_id']);
        $snapshot['kind']='sale_return'; $snapshot['original_document_ref']=$snapshot['source_document_id'];
        $snapshot['source_document_id']='physical_return:'.$key; $snapshot['source_document_no']='实物收回-'.$event['source_line_id'];
        $snapshot['occurred_at']=time(); $snapshot['reason']='原路退款完成后确认实物收回'; $snapshot['financial_items']=[];
        $snapshot['lines'][0]['original_line_ref']=$snapshot['lines'][0]['source_line_id'];
        $snapshot['lines'][0]['source_line_id']='physical_return_line:'.$key;
        $snapshot['batch_ref']=$key;
        ErpIntegrationService::forSite($site,0,(string)($event['receiver']??'管理员'))->enqueueDomainEvent('erp.integration.document.confirmed.v1','physical_return',(int)$item['id'],$snapshot);
        return ['consumer'=>'hsx_erp','recorded'=>true];
    }
}
