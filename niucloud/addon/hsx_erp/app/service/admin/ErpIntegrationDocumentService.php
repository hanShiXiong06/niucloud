<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\service\admin;

use think\facade\Db;

/** Immutable, vendor-neutral document snapshots written inside the business transaction. */
final class ErpIntegrationDocumentService
{
    public static function invalidated(int $site,string $kind,int $id,string $number,string $reason,string $revision='void'): void
    {
        ErpIntegrationService::forSite($site)->enqueueDomainEvent('erp.integration.document.invalidated.v1',$kind,$id,[
            'kind'=>'manual_review','source_document_id'=>'review:'.$kind.':'.$id,'source_document_no'=>$number,
            'original_document_ref'=>$kind.':'.$id,'batch_ref'=>$revision,'occurred_at'=>time(),
            'manual_required'=>'原业务已撤销或调整，请在博远核对原单并按约定冲销；'.$reason,
        ]);
    }
    public static function record(int $site, string $kind, int $id, array $assetIds = [], bool $emit = true): ?array
    {
        $definitions = [
            'purchase' => ['erp_purchase_order', 'erp_purchase_item', 'purchase_order_id', 'purchase_no', 'purchase_cost'],
            'sale' => ['erp_sale_order', 'erp_sale_item', 'sale_order_id', 'sale_no', 'sale_price'],
            'purchase_return' => ['erp_purchase_return', 'erp_purchase_return_item', 'return_id', 'return_no', 'return_cost'],
            'sale_return' => ['erp_sale_return', 'erp_sale_return_item', 'return_id', 'return_no', 'return_price'],
        ];
        [$headTable, $lineTable, $foreign, $no, $amountField] = $definitions[$kind];
        $head = Db::name($headTable)->where('site_id', $site)->where('id', $id)->find();
        if (!$head) return null;
        if ($kind === 'sale_return' && ($head['business_type'] ?? 'return_refund') !== 'return_refund') return null;
        $query = Db::name($lineTable)->where('site_id', $site)->where($foreign, $id)->order('id');
        if ($assetIds) $query->whereIn('asset_id', $assetIds);
        $rows = $query->select()->toArray();
        if (!$rows) return null;
        $party = Db::name('erp_party')->where('site_id', $site)->where('id', $head['party_id'])->find() ?: [];
        $originalSale = $kind === 'sale_return'
            ? (Db::name('erp_sale_order')->where('site_id', $site)->where('id', $head['sale_order_id'])->find() ?: []) : $head;
        $lines = [];
        foreach ($rows as $row) {
            $asset = Db::name('erp_asset')->where('site_id', $site)->where('id', (int)($row['asset_id'] ?? 0))->find() ?: [];
            $purchaseLine = Db::name('erp_purchase_item')->where('site_id', $site)->where('asset_id', (int)($row['asset_id'] ?? 0))->find() ?: [];
            $product = [
                'model' => (string)($asset['model'] ?? $row['model'] ?? ''),
                'spec' => (string)($asset['spec'] ?? $row['spec'] ?? ''),
                'color' => (string)($asset['color'] ?? $row['color'] ?? ''),
            ];
            $lines[] = [
                'source_line_id' => $kind . '_line:' . $row['id'], 'device_ref' => 'asset:' . ($row['asset_id'] ?? 0),
                'product_ref' => 'model:' . hash('sha256', json_encode($product, JSON_UNESCAPED_UNICODE)),
                'product_name' => trim(implode(' ', $product)), 'attributes' => [
                    ['key' => 'spec', 'label' => '规格', 'value_code' => null, 'value_text' => $product['spec']],
                    ['key' => 'color', 'label' => '颜色', 'value_code' => null, 'value_text' => $product['color']],
                ],
                'imei' => (string)($row['imei'] ?? $asset['imei'] ?? ''), 'sn' => (string)($row['sn'] ?? $asset['sn'] ?? ''),
                'warehouse_ref' => 'warehouse:' . ($kind === 'sale_return' ? ($head['return_to_warehouse_id'] ?: ($asset['warehouse_id'] ?? 0)) : ($row['warehouse_id'] ?? $asset['warehouse_id'] ?? 0)),
                'location_ref' => 'location:' . ($kind === 'sale_return' ? ($head['return_to_location_id'] ?: ($asset['location_id'] ?? 0)) : ($row['location_id'] ?? $asset['location_id'] ?? 0)),
                'quantity' => (string)($row['quantity'] ?? '1'), 'amount' => number_format((float)($row[$amountField] ?? 0), 2, '.', ''),
                'ownership_type' => (string)($asset['ownership_type'] ?? 'owned'),
                'inbound_line_ref' => 'purchase_line:' . ($purchaseLine['id'] ?? 0),
                'original_line_ref' => $kind === 'purchase_return' ? 'purchase_line:' . $row['purchase_item_id']
                    : ($kind === 'sale_return' ? 'sale_line:' . $row['sale_item_id'] : ''),
            ];
        }
        $channel = strtolower((string)($originalSale['sale_channel_key'] ?? $originalSale['sale_channel'] ?? ''));
        // A missing/unknown channel is left unresolved for the receiving adapter, never guessed.
        $saleType = in_array($channel, ['retail', '零售', 'mall', 'phone_shop', 'erp_store'], true) || ($originalSale['origin_plugin'] ?? '') === 'phone_shop'
            ? 'retail' : (in_array($channel, ['wholesale', '批发', 'erp_peer'], true) ? 'wholesale' : '');
        $document = [
            'kind' => $kind, 'source_document_id' => $kind . ':' . $id, 'source_document_no' => $head[$no],
            'batch_ref' => hash('sha256', implode('|', array_column($lines, 'source_line_id'))), 'sale_type' => $saleType,
            'purchase_source' => ($head['origin_plugin'] ?? '') === 'hsx_recycle' ? 'ordinary_customer' : 'wholesale_supplier',
            'party' => ['ref' => ($originalSale['origin_plugin']??'')==='phone_shop' ? 'mall-customer:' . $originalSale['origin_id'] : ((int)$head['party_id']>0 ? 'party:' . $head['party_id'] : 'guest-sale:' . $id), 'name' => $head['party_name'], 'mobile' => (string)(($party['mobile']??'')?:($party['contact_mobile']??''))],
            'operator' => ['operator_ref' => 'staff:' . ($head['operator_uid'] ?? $head['operator_id'] ?? 0), 'name' => (string)($head['operator_name'] ?? '')],
            'salesperson_ref' => 'staff:' . ($originalSale['salesman_uid'] ?? 0), 'salesperson_name' => (string)($originalSale['salesman_name'] ?? ''),
            'payment_method_ref' => 'method:' . (string)($originalSale['settle_method'] ?? $head['settle_method'] ?? ''),
            'occurred_at' => (int)($head['purchase_at'] ?? $head['sale_at'] ?? $head['occurred_at'] ?? time()),
            'original_document_ref' => $kind === 'purchase_return' ? 'purchase:' . $head['purchase_order_id'] : ($kind === 'sale_return' ? 'sale:' . $head['sale_order_id'] : ''),
            'reason' => (string)($head['remark'] ?: ($rows[0]['reason'] ?? '实物退货')), 'lines' => $lines,
        ];
        $document['financial_items'] = [];
        if ($kind === 'purchase') {
            foreach ($rows as $index => $row) {
                $payable = Db::name('erp_payable')->where('site_id', $site)->where('source_type', 'purchase_asset')->where('source_id', (int)$row['asset_id'])->find();
                if (!$payable) continue;
                $document['financial_items'][] = [
                    'source_financial_item_id'=>'payable:'.$payable['id'], 'source_financial_item_no'=>$payable['payable_no'],
                    'financial_item_type'=>'payable', 'business_kind'=>'purchase', 'party_role'=>'supplier', 'party_ref'=>$document['party']['ref'],
                    'source_document_id'=>$document['source_document_id'], 'source_line_id'=>$lines[$index]['source_line_id'], 'device_ref'=>$lines[$index]['device_ref'],
                    'amount'=>number_format((float)$payable['amount'],2,'.',''),
                    'components'=>[['source_line_id'=>$lines[$index]['source_line_id'],'device_ref'=>$lines[$index]['device_ref'],'amount'=>$lines[$index]['amount']]],
                ];
            }
        } elseif ($kind === 'sale') {
            $receivable = Db::name('erp_receivable')->where('site_id',$site)->where('source_type','sale')->where('source_id',$id)->find();
            if ($receivable) $document['financial_items'][] = [
                'source_financial_item_id'=>'receivable:'.$receivable['id'], 'source_financial_item_no'=>$receivable['receivable_no'],
                'financial_item_type'=>'receivable', 'business_kind'=>'sale', 'party_role'=>'customer', 'party_ref'=>$document['party']['ref'],
                'source_document_id'=>$document['source_document_id'], 'source_line_id'=>null, 'device_ref'=>null,
                'amount'=>number_format((float)$receivable['amount'],2,'.',''),
                'components'=>array_map(static fn($line)=>['source_line_id'=>$line['source_line_id'],'device_ref'=>$line['device_ref'],'amount'=>$line['amount']],$lines),
            ];
        }
        if ($emit) {
            $service = ErpIntegrationService::forSite($site, (int)($head['operator_uid'] ?? $head['operator_id'] ?? 0), (string)($head['operator_name'] ?? '系统'));
            $service->enqueueDomainEvent('erp.integration.document.confirmed.v1', $kind, $id, $document);
        }
        return $document;
    }
}
