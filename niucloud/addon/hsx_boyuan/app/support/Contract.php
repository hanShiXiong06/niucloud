<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;
final class Contract
{
    public const TYPES = ['purchase_inbound', 'purchase_return', 'retail_sale', 'retail_return', 'wholesale_sale', 'wholesale_return', 'financial_item_ensure', 'settlement_register', 'party_resolve'];
    public static function json(array $data): string { return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    public static function decode(string $json): array { return json_decode($json, true, 128, JSON_THROW_ON_ERROR); }
    public static function cents($value): int
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9]\d{0,9})\.\d{2}$/D', $value)) throw new Problem('AMOUNT_INVALID', '金额必须是非负两位十进制字符串');
        [$whole, $fraction] = explode('.', $value);
        return (int)$whole * 100 + (int)$fraction;
    }
    public static function amount(int $cents): string { return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT); }
    public static function id($id): string
    {
        if (!is_string($id) || $id === '' || strlen($id) > 128 || preg_match('/[\x00-\x20]/', $id)) throw new Problem('IDENTITY_INVALID', '缺少有效的稳定编号');
        return $id;
    }
    public static function request(array $request): void
    {
        self::id($request['operation_id'] ?? null);
        if (($request['schema_version'] ?? '') !== '1.0' || !in_array($request['operation_type'] ?? '', self::TYPES, true) || !is_array($request['data'] ?? null)) throw new Problem('CONTRACT_INVALID', '接口版本、业务类型或数据无效');
        $type = $request['operation_type']; $data = $request['data'];
        if (in_array($type, array_slice(self::TYPES, 0, 6), true)) {
            $lines = $data['lines'] ?? [];
            if (!$lines || count($lines) > 100) throw new Problem('LINES_INVALID', '设备明细必须为1至100行');
            $sum = 0; $ids = []; $devices = [];
            foreach ($lines as $line) {
                $id = self::id($line['source_line_id'] ?? null); $device = self::id($line['device_ref'] ?? null);
                if (isset($ids[$id]) || isset($devices[$device]) || ($line['quantity'] ?? 0) !== 1) throw new Problem('LINES_INVALID', '同一设备不可重复，一机一行且数量为1');
                $ids[$id] = $devices[$device] = true;
                foreach (['external_product_id', 'product_code', 'warehouse_id', 'unit_code'] as $field) self::id($line[$field] ?? null);
                if (trim((string)($line['imei'] ?? '') . (string)($line['sn'] ?? '')) === '') throw new Problem('SERIAL_REQUIRED', '请补充设备IMEI或SN');
                $sum += self::cents($line['line_amount'] ?? null);
            }
            if ($sum !== self::cents($data['total_amount'] ?? null)) throw new Problem('TOTAL_MISMATCH', '设备明细合计与单据金额不一致');
        }
        if ($type === 'settlement_register') {
            $allocations = $data['allocations'] ?? []; $sum = 0; $ids = [];
            if (!$allocations || count($allocations) > 100) throw new Problem('ALLOCATIONS_INVALID', '请提供1至100项款项分配');
            foreach ($allocations as $allocation) {
                $id = self::id($allocation['source_financial_item_id'] ?? null);
                if (isset($ids[$id])) throw new Problem('ALLOCATIONS_INVALID', '同一笔账不能重复分配');
                $ids[$id] = true; $sum += self::cents($allocation['amount'] ?? null);
                if (self::cents($allocation['amount']) > self::cents($allocation['expected_balance_amount'] ?? null)) throw new Problem('BALANCE_CONFLICT', '分配金额超过剩余余额');
                if (!is_int($allocation['expected_version']??null) || $allocation['expected_version']<1) throw new Problem('BALANCE_UNVERIFIED','缺少有效的当前往来版本');
            }
            if ($sum <= 0 || $sum !== self::cents($data['amount'] ?? null)) throw new Problem('TOTAL_MISMATCH', '款项分配合计不等于实际收付金额');
        }
        if ($type === 'financial_item_ensure') {
            if (empty($data['items']) || count($data['items'])>100) throw new Problem('FINANCE_INVALID','应收应付明细数量无效');
            foreach ($data['items'] as $item) {
                $components=$item['components']??[]; $sum=0;
                foreach ($components as $component) $sum+=self::cents($component['amount']??null);
                if (!$components || $sum!==self::cents($item['amount']??null)) throw new Problem('TOTAL_MISMATCH','账目明细合计与原始金额不一致');
                if ($item['financial_item_type']==='payable' && (count($components)!==1 || empty($item['source_line_id']))) throw new Problem('FINANCE_GRANULARITY','采购必须每台机器一笔应付');
                if ($item['financial_item_type']==='receivable' && $item['source_line_id']!==null) throw new Problem('FINANCE_GRANULARITY','销售必须每张销售单一笔应收');
            }
        }
    }
    public static function response(array $reply, array $request): array
    {
        $data = $reply['data'] ?? [];
        if (($data['operation_id'] ?? '') !== $request['operation_id'] || ($data['operation_type'] ?? '') !== $request['operation_type']
            || !in_array($data['operation_status'] ?? '', ['accepted', 'processing', 'effective', 'rejected', 'partial', 'unknown'], true)) throw new Problem('RESPONSE_INVALID', '博远回执身份或状态不一致，请先查单核验', 'unknown');
        if ($data['operation_status'] !== 'effective') return $data;
        if (($reply['code']??'')!=='OK') throw new Problem('RESPONSE_CONFLICT','博远回执的业务代码与完成状态矛盾','unknown');
        if (!empty($request['data']['lines'])) {
            $expected = array_column($request['data']['lines'], null, 'source_line_id'); $seen = []; $externalLines=[];
            foreach ($data['documents'] ?? [] as $document) {
                self::id($document['external_document_id'] ?? null);
                self::id($document['external_document_no'] ?? null);
                if (($document['document_status'] ?? '') !== 'effective' || ($document['inventory_applied'] ?? null) !== true
                    || ($document['document_type'] ?? '') !== $request['operation_type']
                    || ($document['source_document_id'] ?? '') !== $request['data']['source_document_id']) throw new Problem('RESULT_NOT_EFFECTIVE', '单据类型或库存尚未核验生效，请核对博远实际状态', 'unknown');
                foreach ($document['lines'] ?? [] as $line) {
                    $id = $line['source_line_id'] ?? ''; $original = $expected[$id] ?? null;
                    if (!$original || isset($seen[$id]) || ($line['device_ref'] ?? '') !== $original['device_ref']
                        || ($line['quantity'] ?? 0) !== 1 || ($line['warehouse_id'] ?? '') !== $original['warehouse_id']
                        || ($line['inventory_applied'] ?? null) !== true || ($line['line_amount'] ?? null) !== $original['line_amount']) throw new Problem('RESULT_MISMATCH', '设备、仓库或金额回执不完整，请核验逐行结果', 'unknown');
                    $serial=trim((string)($original['imei']??'')) ?: (string)$original['sn'];
                    $effect=in_array($request['operation_type'],['purchase_inbound','retail_return','wholesale_return'],true)?'in':'out';
                    if (($line['serial']??'')!==$serial || ($line['inventory_effect']??'')!==$effect) throw new Problem('RESULT_MISMATCH','串号或库存方向回执不一致','unknown');
                    $externalLine=$document['external_document_id'].':'.self::id($line['external_line_id'] ?? null);
                    if(isset($externalLines[$externalLine])) throw new Problem('RESULT_MISMATCH','多台设备回传相同博远明细行，请核验原单','unknown');
                    $externalLines[$externalLine]=true; $seen[$id] = true;
                }
            }
            if (count($seen) !== count($expected)) throw new Problem('RESULT_INCOMPLETE', '博远回执缺少设备明细，不能标记整单成功', 'unknown');
        }
        if ($request['operation_type']==='financial_item_ensure') {
            $expected=array_column($request['data']['items'],null,'source_financial_item_id'); $seen=[]; $externalItems=[];
            foreach ($data['items']??[] as $row) {
                $id=$row['source_financial_item_id']??''; $item=$expected[$id]??null;
                if (!$item || isset($seen[$id]) || ($row['original_amount']??'')!==$item['amount'] || ($row['external_party_id']??'')!==$item['external_party_id']
                    || ($row['financial_item_type']??'')!==$item['financial_item_type']) throw new Problem('FINANCE_RESULT_MISMATCH','往来账身份或金额回执不一致','unknown');
                self::financialBalance($row);
                if(isset($externalItems[$row['external_financial_item_id']])) throw new Problem('FINANCE_RESULT_MISMATCH','多笔独立往来回传同一博远账目，请核验拆分粒度','unknown');
                $externalItems[$row['external_financial_item_id']]=true; $seen[$id]=true;
            }
            if (count($seen)!==count($expected)) throw new Problem('FINANCE_RESULT_INCOMPLETE','应收应付回执不完整','unknown');
        }
        if($request['operation_type']==='party_resolve') {
            $party=$data['party']??[]; $matched=$data['match_status']??'';
            if(!in_array($matched,['matched','created'],true) || ($party['enabled']??null)!==true || ($party['role']??'')!==$request['data']['role']
                || ($matched==='created' && $request['data']['mode']!=='match_or_create')) throw new Problem('PARTY_UNRESOLVED','往来单位未按授权规则唯一对应，请人工核验','unknown');
            self::id($party['id']??null); self::id($party['code']??null);
        }
        if ($request['operation_type'] === 'settlement_register') {
            $settlement = $data['settlement'] ?? [];
            if (($settlement['funds_recorded'] ?? false) !== true || ($settlement['allocations_applied'] ?? false) !== true
                || ($settlement['amount'] ?? '') !== $request['data']['amount']
                || ($settlement['source_settlement_id']??'')!==$request['data']['source_settlement_id']
                || ($settlement['direction']??'')!==$request['data']['direction']) throw new Problem('SETTLEMENT_INCOMPLETE', '资金身份或款项分配尚未全部核验生效', 'unknown');
            self::id($settlement['external_settlement_id']??null); self::id($settlement['external_settlement_no']??null);
            $expected = array_column($request['data']['allocations'], null, 'source_financial_item_id'); $seen = [];
            foreach ($settlement['allocations'] ?? [] as $row) {
                $id = $row['source_financial_item_id'] ?? ''; $allocation=$expected[$id]??null;
                if (!$allocation || isset($seen[$id]) || ($row['applied_amount'] ?? '') !== $allocation['amount']
                    || ($row['external_financial_item_id']??'')!==$allocation['external_financial_item_id']
                    || !is_int($row['version']??null) || $row['version']<=$allocation['expected_version']
                    || self::cents($row['balance_amount']??null)!==self::cents($allocation['expected_balance_amount'])-self::cents($allocation['amount'])) throw new Problem('SETTLEMENT_MISMATCH', '结算逐笔身份、余额或版本结果不一致', 'unknown');
                self::id($row['external_allocation_id']??null); self::cents($row['settled_amount']??null);
                $seen[$id] = true;
            }
            if (count($seen) !== count($expected)) throw new Problem('SETTLEMENT_INCOMPLETE', '结算回执缺少分配明细', 'unknown');
        }
        return $data;
    }
    public static function financialBalance(array $row): void
    {
        self::id($row['external_financial_item_id']??null);
        if (!is_int($row['version']??null) || $row['version']<1) throw new Problem('BALANCE_UNVERIFIED','缺少有效的当前往来版本','unknown');
        $current=self::cents($row['current_amount']??null); $settled=self::cents($row['settled_amount']??null); $balance=self::cents($row['balance_amount']??null);
        if ($settled+$balance!==$current) throw new Problem('BALANCE_UNVERIFIED','往来余额与已结金额不平，请核对博远原账','unknown');
    }
    public static function redact(array $value): array
    {
        foreach ($value as $key => &$item) {
            if (preg_match('/token|secret|password|cipher|authorization|lease_token/i', (string)$key)) $item = '[已隐藏]';
            elseif (is_array($item)) $item = self::redact($item);
            elseif (in_array($key, ['mobile', 'contact_mobile'], true) && is_string($item)) $item = strlen($item) > 6 ? substr($item, 0, 3) . '****' . substr($item, -4) : '***';
        }
        return $value;
    }
}
