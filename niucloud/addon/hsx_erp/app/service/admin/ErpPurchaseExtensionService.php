<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\service\admin;

use think\facade\Log;

/** Optional purchase actions supplied by installed business integrations. */
final class ErpPurchaseExtensionService
{
    /** Optional, once per list/detail, never required to run standalone ERP. */
    public static function costMaintenanceHint(int $site): string
    {
        try {
            $hints=[];
            foreach((array)event('HsxErpCostMaintenanceHint',['site_id'=>$site]) as $hint)
                if(is_string($hint) && $hint!=='') $hints[]=mb_substr($hint,0,180);
            return implode(' ',array_unique($hints));
        } catch(\Throwable $e) {
            Log::warning('ERP外部调价提示读取失败',['site_id'=>$site,'type'=>get_class($e)]);
            return '外部对接状态暂时无法核对，本次只确认本站的修改。';
        }
    }
    public static function actions(int $site, array $order): array
    {
        return self::detail($site,$order)['integration_actions'];
    }
    /** Read-only finance extension. Pass document identities, never payee details or vouchers. */
    public static function attachFinance(int $site,string $direction,array &$items,array $facts): void
    {
        if (!$facts || !$items) return;
        try {
            $responses=event('HsxErpFinanceDocumentLinks',['site_id'=>$site,'direction'=>$direction,'facts'=>$facts]);
            foreach ($items as &$item) {
                $key=(int)($item[$direction.'_id']??$item['id']??0);
                if(!$key || !isset($facts[$key])) continue;
                $links=[];
                foreach ((array)$responses as $provider) foreach ((array)($provider[$key]??[]) as $row) {
                    if(!is_array($row) || !is_string($row['key']??null) || !preg_match('/^[a-z0-9_]{1,64}$/D',$row['key'])) continue;
                    $safe=[];
                    foreach(['provider_label','status_label','external_no','next_step'] as $field) $safe[$field]=mb_substr((string)($row[$field]??''),0,$field==='next_step'?255:128);
                    if ($direction==='payable' && is_string($row['pc_next_step']??null)) $safe['pc_next_step']=mb_substr($row['pc_next_step'],0,255);
                    if($safe['provider_label']==='' || $safe['status_label']==='') continue;
                    // The client must not have to infer a receipt state from translated text or an amount.
                    $safe['status']=preg_match('/^[a-z][a-z0-9_]{0,47}$/D',(string)($row['status']??''))?(string)$row['status']:'unknown';
                    $safe['issue_code']=preg_match('/^[A-Z][A-Z0-9_]{0,79}$/D',(string)($row['issue_code']??''))?(string)$row['issue_code']:'';
                    $safe['task_id']=max(0,(int)($row['task_id']??0));
                    $safe['tone']=in_array($row['tone']??null,['info','warning','success','danger'],true)?$row['tone']:'info';
                    $safe['pc_route']=preg_match('#^/(?:[a-z0-9_-]+/)+[a-z0-9_-]+$#Di',(string)($row['pc_route']??''))?(string)$row['pc_route']:'';
                    $safe['query']=[];
                    foreach((array)($row['query']??[]) as $k=>$v) if(is_string($k) && preg_match('/^[a-z][a-z0-9_]{0,40}$/Di',$k) && is_scalar($v)) $safe['query'][$k]=mb_substr((string)$v,0,128);
                    $safe['receipt_details']=[];
                    foreach(array_slice((array)($row['receipt_details']??[]),0,8) as $detail)
                        if(is_array($detail) && is_string($detail['label']??null) && is_string($detail['value']??null))
                            $safe['receipt_details'][]=['label'=>mb_substr($detail['label'],0,40),'value'=>mb_substr($detail['value'],0,128)];
                    $links[$row['key']]=['key'=>$row['key']]+$safe;
                }
                if($links) $item['integration_results']=array_values($links);
            }
            unset($item);
        } catch(\Throwable $e) { Log::warning('ERP财务关联读取失败',['site_id'=>$site,'direction'=>$direction,'type'=>get_class($e)]); }
    }
    public static function detail(int $site,array $order,bool $sale=false): array
    {
        $actions=[]; $rows=[]; $saleEvidence=[]; $purchaseTargets=[]; $fallbackTargets=[];
        try {
            $data=['site_id'=>$site,'status'=>(string)($order['status']??''),'items'=>$order['items']??[]];
            $responses=$sale ? event('HsxErpSaleActions',$data+['sale_id'=>(int)$order['id'],'business_no'=>(string)($order['sale_no']??''),
                'sale_channel'=>(string)(($order['sale_channel_key']??'')?:($order['sale_channel']??'')),'settle_method'=>(string)($order['settle_method']??'')]) :
                event('HsxErpPurchaseActions',$data+['purchase_id'=>(int)$order['id'],'business_no'=>(string)($order['purchase_no']??'')]);
            foreach ((array)$responses as $result) {
                if($sale && is_array($result['sale_evidence']??null)) {
                    $block=$result['sale_evidence']; $devices=[]; $items=array_column($data['items'],null,'id');
                    foreach((array)($block['devices']??[]) as $device) {
                        $item=$items[(int)($device['item_id']??0)]??null;
                        if(!$item || empty($item['imei']) || ($device['imei']??null)!==$item['imei']
                            || (int)($device['asset_id']??0)!==(int)($item['asset_id']??0)) continue;
                        $devices[(int)$item['id']]=['imei'=>(string)$item['imei'],'purchase_no'=>($block['show_purchase']??false)===true?mb_substr((string)($device['purchase_no']??''),0,64):'',
                            'sale_no'=>mb_substr((string)($device['sale_no']??''),0,64)];
                    }
                    if($devices && !empty($block['provider_label'])) $saleEvidence[]=['provider_label'=>mb_substr((string)$block['provider_label'],0,30),
                        'show_purchase'=>($block['show_purchase']??false)===true,'devices'=>array_values($devices),
                        'receipt_label'=>mb_substr((string)($block['receipt_label']??''),0,30),'allocation_label'=>mb_substr((string)($block['allocation_label']??''),0,30)];
                }
                foreach ((array)($result['actions']??[]) as $action) {
                    if (!is_array($action) || !preg_match('/^[a-z0-9_]{1,64}$/D',(string)($action['key']??''))) continue;
                    $mobile=(string)($action['mobile_path']??'');
                    if (!preg_match('#^/[A-Za-z0-9_/-]+$#D',(string)($action['pc_route']??''))
                        || ($mobile!=='' && !preg_match('#^/addon/[A-Za-z0-9_/-]+$#D',$mobile))) continue;
                    $actions[$action['key']]=['key'=>$action['key'],'label'=>mb_substr((string)($action['label']??'外部同步'),0,30),
                        'pc_route'=>$action['pc_route'],'mobile_path'=>$mobile];
                    if(!$sale && is_string($action['query']['purchase_no']??null) && $action['query']['purchase_no']===($order['purchase_no']??''))
                        $actions[$action['key']]['query']=['purchase_no'=>$order['purchase_no']];
                    // Advertise an optional inline handler for this exact source. Do not accept a
                    // provider's arbitrary task, foreign purchase, route, or component path here.
                    $inline=$action['query']['detail_extension']??null;
                    if(!$sale && is_string($inline) && preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$inline)
                        && (int)$order['id']>0 && (string)($action['query']['purchase_source_id']??'')===(string)$order['id'])
                        $actions[$action['key']]['query']=array_merge($actions[$action['key']]['query']??[],
                            ['detail_extension'=>$inline,'purchase_source_id'=>(string)$order['id']]);
                }
                foreach ((array)($result['results']??[]) as $row) {
                    $key=$row['key']??null;
                    if(!is_string($key)||!preg_match('/^[a-z0-9_]{1,64}$/D',$key)||isset($rows[$key])) continue;
                    $value=[]; foreach(['provider_label','status','status_label','external_no','next_step'] as $f) $value[$f]=mb_substr((string)($row[$f]??''),0,$f==='next_step'?255:128);
                    $value['tone']=in_array($row['tone']??'', ['info','warning','success','danger'],true)?$row['tone']:'info';
                    $value['task_id']=max(0,(int)($row['task_id']??0));
                    $value['pc_route']=preg_match('#^/[A-Za-z0-9_/-]+$#D',(string)($row['pc_route']??''))?(string)$row['pc_route']:'';
                    $value['mobile_path']=preg_match('#^/addon/[A-Za-z0-9_/-]+$#D',(string)($row['mobile_path']??''))?(string)$row['mobile_path']:'';
                    $value['query']=[]; foreach((array)($row['query']??[]) as $k=>$v) if(is_string($k)&&preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,40}$/D',$k)&&is_scalar($v)) $value['query'][$k]=mb_substr((string)$v,0,128);
                    $rows[$key]=['key'=>$key]+$value;
                    // Only real rows visible on this original can supersede the same target's empty hint.
                    if (!$sale && $value['task_id']>0 && ($value['query']['kind']??'')==='purchase' && preg_match('/^[a-f0-9]{64}$/D',(string)($row['purchase_target']??'')))
                        $purchaseTargets[$row['purchase_target']]=true;
                    if (!$sale && !$value['task_id'] && $value['status']==='not_recorded' && $value['external_no']==='' && preg_match('/^[a-f0-9]{64}$/D',(string)($row['fallback_target']??'')))
                        $fallbackTargets[$key]=$row['fallback_target'];
                }
            }
        } catch (\Throwable $e) {
            // Optional extensions must never prevent reading an established purchase.
            Log::warning('ERP原单扩展暂不可用', ['site_id'=>$site,'document_id'=>(int)$order['id'],'type'=>get_class($e)]);
        }
        foreach ($fallbackTargets as $key=>$target) if (isset($purchaseTargets[$target])) unset($rows[$key]);
        return ['integration_actions'=>array_values($actions),'integration_results'=>array_values($rows)]+($sale?['integration_sale_evidence'=>$saleEvidence]:[]);
    }
}
