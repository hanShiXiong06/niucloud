<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

/** Classify immutable ERP facts. A partially unsupported document is held whole, never silently split. */
final class BridgeFact
{
    public const PURCHASE = 'erp.integration.document.confirmed.v1';
    public const PAYMENT = 'erp.settlement.completed.v1';
    public const INVALIDATED = 'erp.integration.document.invalidated.v1';

    public static function kind(array $event): ?string
    {
        $p = $event['payload'] ?? [];
        return match ($event['event_name'] ?? '') {
            self::PURCHASE => match ($p['kind'] ?? '') {
                'purchase' => 'purchase',
                'sale' => ($p['sale_type'] ?? '') === 'wholesale' && ($p['payment_method_ref'] ?? '') === 'method:挂账' ? 'sale' : null,
                'sale_return','purchase_return' => 'return_review',
                default => null,
            },
            self::PAYMENT => match ($p['settlement_type'] ?? '') {
                'payment' => 'payment', 'receipt' => 'receipt', default => null,
            },
            self::INVALIDATED => in_array($event['aggregate_type'] ?? '', ['purchase','sale'], true) ? 'invalidation' : null,
            default => null,
        };
    }

    public static function project(array $e, string $kind): array
    {
        $p = $e['payload'] ?? []; $id = (int)($e['aggregate_id'] ?? 0);
        $aggregate = match ($kind) {
            'payment','receipt'=>'settlement', 'sale'=>'sale', 'return_review'=>(string)($p['kind'] ?? ''),
            'invalidation'=>(string)($e['aggregate_type'] ?? ''), default=>'purchase',
        };
        if (!is_array($p) || ($e['event_version'] ?? null) !== 1 || ($e['source']['plugin'] ?? '') !== 'hsx_erp'
            || ($e['source']['type'] ?? '') !== $aggregate || (int)($e['source']['id'] ?? 0) !== $id
            || (int)($e['site_id'] ?? 0) < 1 || (int)($e['occurred_at'] ?? 0) < 1 || $id < 1
            || ($e['aggregate_type'] ?? '') !== $aggregate || !is_string($e['event_id'] ?? null)
            || trim($e['event_id']) === '' || strlen($e['event_id']) > 128) {
            throw new \InvalidArgumentException('原事件身份、站点或版本不匹配；没有接管');
        }
        $ref = $aggregate . ':' . $id;
        if (in_array($kind,['invalidation','return_review'],true)) {
            $original = (string)($p['original_document_ref'] ?? '');
            if (!preg_match('/^(sale|purchase):([1-9][0-9]*)$/D',$original,$m) ||
                ($kind==='invalidation' ? $original!==$ref : ($aggregate!==$m[1].'_return' || ($p['source_document_id'] ?? '')!==$ref))) {
                throw new \InvalidArgumentException('撤销或退货事件与业务原单不匹配');
            }
            return ['source_type'=>'erp_'.$m[1],'source_id'=>(int)$m[2]];
        }
        $reason = '';
        if ($kind === 'purchase') {
            if (($p['source_document_id'] ?? '') !== $ref || !is_string($p['batch_ref'] ?? null)
                || !preg_match('/^[a-zA-Z0-9_-]{1,96}$/D', $p['batch_ref'])) throw new \InvalidArgumentException('原采购或入库批次标识无效');
            $key = $ref . ':' . $p['batch_ref']; $no = $p['source_document_no'] ?? '';
            $seen = [];
            foreach ((array)($p['lines'] ?? []) as $line) {
                $lineRef = (string)($line['source_line_id'] ?? '');
                if (!preg_match('/^purchase_line:[1-9][0-9]*$/D', $lineRef) || isset($seen[$lineRef])) $reason = '入库批次包含缺失或重复的原始行；整单未发送';
                $seen[$lineRef] = true;
            }
            if (!$seen) $reason = '缺少原采购入库行；整单未发送';
        } elseif ($kind === 'sale') {
            if (($p['source_document_id'] ?? '') !== $ref || !is_string($p['batch_ref'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$p['batch_ref']))
                throw new \InvalidArgumentException('同行销售原单或批次标识无效');
            $key = $ref.':'.$p['batch_ref']; $no = $p['source_document_no'] ?? '';
            $lines = $p['lines'] ?? []; $ids = $devices = $imeis = [];
            if (($p['sale_type'] ?? '') !== 'wholesale' || ($p['payment_method_ref'] ?? '') !== 'method:挂账' ||
                !preg_match('/^party:[1-9][0-9]*$/D',(string)($p['party']['ref'] ?? '')) || !is_array($lines) || !$lines)
                $reason = '缺少同行客户、挂账或设备行证据；原销售整单未发送';
            foreach ((array)$lines as $line) {
                $lineRef=(string)($line['source_line_id'] ?? ''); $device=(string)($line['device_ref'] ?? ''); $imei=(string)($line['imei'] ?? '');
                if (!preg_match('/^sale_line:[1-9][0-9]*$/D',$lineRef) || !preg_match('/^asset:[1-9][0-9]*$/D',$device)
                    || !preg_match('/^purchase_line:[1-9][0-9]*$/D',(string)($line['inbound_line_ref'] ?? ''))
                    || ($line['ownership_type'] ?? '') !== 'owned' || ($line['item_type'] ?? 'device') !== 'device'
                    || !preg_match('/^1(?:\.0+)?$/D',(string)($line['quantity'] ?? '')) || trim($imei)==='' ||
                    self::cents($line['amount'] ?? null) === null || isset($ids[$lineRef]) || isset($devices[$device]) || isset($imeis[$imei]))
                    $reason = '销售包含数量商品、非自有设备、重复串号或缺少原采购行；整单未发送';
                $ids[$lineRef]=true; $devices[$device]=true; $imeis[$imei]=true;
            }
            if ($p['batch_ref'] !== hash('sha256',implode('|',array_keys($ids)))) $reason = '挂账销售批次与冻结的完整明细不一致；整单未发送';
        } elseif ($kind === 'receipt') {
            if ((int)($p['settlement_id'] ?? 0) !== $id) throw new \InvalidArgumentException('原收款凭证身份不匹配');
            $key = $ref; $no = $p['settlement_no'] ?? '';
            $sum = 0; $seen = []; $sale = 0;
            if (($p['settlement_type'] ?? '') !== 'receipt' || ($p['cash_direction'] ?? '') !== 'in'
                || empty($p['confirmed_at']) || empty($p['party_id']) || empty($p['capital_account_id'])
                || !is_array($p['targets'] ?? null) || !$p['targets']) $reason = '缺少已确认的销售收款凭证、客户、资金账户或应收分摊';
            foreach ((array)($p['targets'] ?? []) as $target) {
                $targetId=(int)($target['target_id']??0); $doc=(int)($target['source_id']??0); $cents=self::cents($target['applied_amount']??null);
                if (($target['target_type']??'')!=='receivable' || ($target['source_type']??'')!=='sale'
                    || $doc<1 || ($target['source_document_ref']??'')!=='sale:'.$doc || ($sale && $sale!==$doc)
                    || $targetId<1 || isset($seen[$targetId]) || !$cents)
                    $reason='收款凭证不是同一张销售原单的完整分摊；未拆分、未同步';
                $sum+=$cents??0; $sale=$doc; $seen[$targetId]=true;
            }
            if (!$reason && (!$sum || self::cents($p['amount']??null)!==$sum)) $reason='原收款金额与分摊合计不一致；未同步';
        } else {
            if ((int)($p['settlement_id'] ?? 0) !== $id) throw new \InvalidArgumentException('原付款结算单身份不一致');
            $key = $ref; $no = $p['settlement_no'] ?? '';
            $targets = $p['targets'] ?? []; $sum = 0; $seen = [];
            if (($p['cash_direction'] ?? '') !== 'out' || (int)($p['confirmed_at'] ?? 0) < 1 || !is_array($targets) || !$targets) $reason = '原付款方向、登记时间或核销明细不完整；整单未发送';
            foreach ((array)$targets as $target) {
                $targetId = (int)($target['target_id'] ?? 0); $amount = self::cents($target['applied_amount'] ?? null);
                if (($target['target_type'] ?? '') !== 'payable' || ($target['source_type'] ?? '') !== 'purchase_asset'
                    || !preg_match('/^purchase:[1-9][0-9]*$/D', (string)($target['source_document_ref'] ?? ''))
                    || !preg_match('/^purchase_line:[1-9][0-9]*$/D', (string)($target['source_line_ref'] ?? ''))
                    || $targetId < 1 || isset($seen[$targetId]) || $amount === null || $amount < 1) {
                    $reason = '含不可识别、重复或非采购的核销行；整笔付款未发送';
                }
                $sum += $amount ?? 0; $seen[$targetId] = true;
            }
            if (!$reason && ($sum < 1 || self::cents($p['amount'] ?? null) !== $sum)) $reason = '付款与全部核销行合计不一致；整笔未发送';
        }
        if (!is_string($no) || trim($no) === '' || mb_strlen($no) > 50) throw new \InvalidArgumentException('原始单号缺失或超长；没有接管');
        return ['business_type'=>$kind, 'source_type'=>'erp_'.$aggregate, 'source_id'=>$id,
            'source_no'=>$no, 'fact_key'=>$key, 'error_message'=>$reason ?: null];
    }

    private static function cents($value): ?int
    {
        if (!is_scalar($value) || is_bool($value) || !preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/D', (string)$value, $m)) return null;
        return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
    }
}
