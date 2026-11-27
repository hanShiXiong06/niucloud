<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;
final class Plan
{
    public static function build(array $snapshot,array $maps,array $bindings,string $operation): array
    {
        if (!empty($snapshot['outside_scope'])) throw new Problem('HISTORICAL_MANUAL','含有未纳入新流程的机器，请沿原流程到博远处理；不会自动出库');
        $map=static function (string $kind,string $ref) use ($maps): array {
            $m=$maps[$kind.':'.$ref]??null;
            if (!$m) throw new Problem('MAPPING_REQUIRED','请补充'.(['product'=>'商品','warehouse'=>'仓库','location'=>'库位','supplier'=>'供应商','customer'=>'客户','staff'=>'业务员','method'=>'收付方式','account'=>'资金账户'][$kind]??$kind).'对应，在任务详情中选择档案');
            return $m;
        };
        $binding=static function (string $kind,string $ref) use ($bindings): array {
            $b=$bindings[$kind.':'.$ref]??null;
            if (!$b) throw new Problem('DEPENDENCY_PENDING','等待原业务同步或核验：'.$ref);
            return $b;
        };
        $kind=$snapshot['kind']; $type=$kind;
        if (in_array($kind,['sale','sale_return'],true)) {
            if (!in_array($snapshot['sale_type']??'', ['retail','wholesale'],true)) throw new Problem('SALE_TYPE_REQUIRED','尚未明确零售或批发，请先核对原销售渠道');
            $type=$snapshot['sale_type'].($kind==='sale'?'_sale':'_return');
        }
        if ($kind==='purchase') $type='purchase_inbound';
        $businessTime=(new \DateTimeImmutable('@'.(int)($snapshot['occurred_at']??time())))->setTimezone(new \DateTimeZone('Asia/Shanghai'));
        $request=['schema_version'=>'1.0','operation_id'=>$operation,'operation_type'=>$type,
            'business_time'=>$businessTime->format(DATE_ATOM), 'business_date'=>$businessTime->format('Y-m-d'),
            'supersedes_operation_id'=>$snapshot['supersedes_operation_id']??null,'data'=>[]];
        if ($kind==='party_resolve') { $request['data']=$snapshot['data']; return $request; }
        if ($kind==='financial_item_ensure') {
            $items=[];
            foreach ($snapshot['items'] as $item) {
                $doc=$binding('document',$item['source_document_id']); $party=$map($item['party_role'],$item['party_ref']);
                $components=$item['components']; $sourceDocuments=[];
                foreach ($components as $component) {
                    $linked=$binding('line',$component['source_line_id']);
                    $sourceDocuments[]=['external_document_id'=>$linked['detail']['external_document_id'],'external_line_id'=>$linked['external_id']];
                }
                $items[]=['source_financial_item_id'=>$item['source_financial_item_id'],'source_financial_item_no'=>$item['source_financial_item_no'],
                    'financial_item_type'=>$item['financial_item_type'],'business_kind'=>$item['business_kind'],'source_document_id'=>$item['source_document_id'],
                    'source_line_id'=>$item['source_line_id'],'device_ref'=>$item['device_ref'],'external_party_id'=>$party['external_id'],'party_role'=>$item['party_role'],
                    'currency'=>'CNY','amount'=>$item['amount'],'source_documents'=>$sourceDocuments,'components'=>$components];
            }
            $request['data']=['items'=>$items]; return $request;
        }
        if ($kind==='settlement_register') {
            $party=$map($snapshot['party_role'],$snapshot['party_ref']); $account=$map('account',$snapshot['account_ref']); $method=$map('method',$snapshot['method_ref']); $allocations=[];
            foreach ($snapshot['allocations'] as $item) {
                if (empty($item['source_document_id'])) throw new Problem('FINANCE_SCOPE_REQUIRED','该退款或调整账目需要按约定在博远人工核对，本次未重复登记资金');
                $origin=$binding('document',$item['source_document_id']);
                if (($origin['detail']['funds_effect']??'unknown')!=='none') throw new Problem('EXISTING_FUNDS_REVIEW_REQUIRED','原单存在自动收付款或资金状态不明确，请先在博远核对并关联，未重复记款');
                $b=$binding('financial',$item['source_financial_item_id']); $actual=$b['detail'];
                if (($actual['financial_item_type']??'')!==($snapshot['direction']==='payment'?'payable':'receivable') || ($actual['external_party_id']??'')!==$party['external_id']) throw new Problem('FINANCE_SCOPE_CONFLICT','收付方向或往来主体与原账不一致');
                $allocations[]=$item+['external_financial_item_id'=>$b['external_id'],'expected_balance_amount'=>$actual['balance_amount'],'expected_version'=>$actual['version']];
            }
            $request['data']=['source_settlement_id'=>$snapshot['source_settlement_id'],'source_settlement_no'=>$snapshot['source_settlement_no'],'action'=>'register','external_settlement_id'=>null,
                'direction'=>$snapshot['direction'],'registration_mode'=>count($allocations)>1?'merged':'single','external_party_id'=>$party['external_id'],'party_role'=>$snapshot['party_role'],
                'capital_account_id'=>$account['external_id'],'method_code'=>$method['external_code'],'currency'=>'CNY','amount'=>$snapshot['amount'],
                'transaction_time'=>$request['business_time'],'transaction_reference'=>$snapshot['transaction_reference']??null,'operator'=>$snapshot['operator'],'voucher_refs'=>[],'allocations'=>$allocations,'remark'=>'财务已确认款项记账，不发起银行转账'];
            return $request;
        }
        $role=in_array($kind,['purchase','purchase_return'],true)?'supplier':'customer'; $party=$map($role,$snapshot['party']['ref']); $sum=0; $lines=[];
        foreach ($snapshot['lines'] as $item) {
            if (($item['ownership_type']??'owned')!=='owned') throw new Problem('OUT_OF_SCOPE','代卖业务不在自动对接范围，请人工处理');
            if ((float)$item['quantity']!==1.0 || $item['device_ref']==='asset:0') throw new Problem('OUT_OF_SCOPE','本次仅对接一机一码的新流程设备，不自动处理标品或未关联库存的商品');
            $product=$map('product',$item['product_ref']); $warehouse=$map('warehouse',$item['warehouse_ref']);
            $line=['source_line_id'=>$item['source_line_id'],'device_ref'=>$item['device_ref'],'external_product_id'=>$product['external_id'],'product_code'=>$product['external_code'],
                'product_name'=>$item['product_name'],'attributes'=>$item['attributes'],'imei'=>$item['imei']?:null,'sn'=>$item['sn']?:null,
                'warehouse_id'=>$warehouse['external_id'],'location_id'=>null,'quantity'=>1,'unit_code'=>$product['extra']['unit_code']??'',
                'unit_price'=>$item['amount'],'line_amount'=>$item['amount']];
            if (!empty($warehouse['extra']['location_required'])) $line['location_id']=$map('location',$item['location_ref'])['external_id'];
            if ($kind==='sale') {
                $original=$binding('line',$item['inbound_line_ref']);
                $line+=['external_inbound_document_id'=>$original['detail']['external_document_id'],'external_inbound_line_id'=>$original['external_id']];
            }
            if (str_ends_with($kind,'_return')) {
                $original=$binding('line',$item['original_line_ref']);
                $line+=['original_source_line_id'=>$item['original_line_ref'],'original_external_document_id'=>$original['detail']['external_document_id'],'original_external_line_id'=>$original['external_id']];
            }
            $sum+=Contract::cents($item['amount']); $lines[]=$line;
        }
        $request['data']=['source_document_id'=>$snapshot['source_document_id'],'source_document_no'=>$snapshot['source_document_no'],'batch_ref'=>$snapshot['batch_ref'],
            'counterparty'=>['source_party_ref'=>$snapshot['party']['ref'],'external_party_id'=>$party['external_id'],'name'=>$snapshot['party']['name'],'role'=>$role],
            'operator'=>$snapshot['operator'],'currency'=>'CNY','total_amount'=>Contract::amount($sum),'lines'=>$lines];
        if ($kind==='purchase') $request['data']['purchase_source']=$snapshot['purchase_source'];
        if (in_array($kind,['sale','sale_return'],true)) {
            $staff=$map('staff',$snapshot['salesperson_ref']);
            $request['data']['salesperson']=['external_staff_id'=>$staff['external_id'],'name'=>$snapshot['salesperson_name']];
            $method=$map('method',$snapshot['payment_method_ref']); $request['data']['payment_method_code']=$method['external_code'];
        }
        if (str_ends_with($kind,'_return')) {
            $doc=$binding('document',$snapshot['original_document_ref']);
            $originalIds=array_unique(array_column($lines,'original_external_document_id'));
            if (count($originalIds)!==1) throw new Problem('ORIGINAL_SPLIT_REVIEW','原业务对应多张博远单据，请分组核对退货明细，本次未发送');
            $request['data']['original_document']=['source_document_id'=>$snapshot['original_document_ref'],'external_document_id'=>reset($originalIds),'operation_type'=>$doc['detail']['document_type']];
            $request['data']['reason']=$snapshot['reason']?:'实物退货';
        }
        return $request;
    }
}
