<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use app\dict\pay\{PayDict,RefundDict};
use app\service\core\pay\CoreRefundService;
use core\exception\CommonException;
use think\facade\Db;

/** Retain the framework quota validation; only re-key the row just created in this transaction. */
final class FrameworkRefundReservation
{
    public static function create(int $site, array $original, string $amount, string $reason, string $prefix = 'LR'): array
    {
        if (!in_array($prefix, ['LR', 'LD'], true))throw new CommonException('退款业务标识无效');
        $pdo = Db::connect()->getPdo();
        if (!$pdo instanceof \PDO || !$pdo->inTransaction())throw new CommonException('退款预留必须在原审核事务中完成，当前未发起退款');
        $before = (int) Db::name('pay_refund')->max('id');
        $legacy = (new CoreRefundService())->create($site, (string) $original['out_trade_no'], (float) $amount, $reason, PaymentService::TRADE_TYPE, (string) $original['id']);
        $inserted = $pdo->lastInsertId();
        if (!is_string($legacy) || $legacy === '' || !is_string($inserted) || !preg_match('/^[1-9][0-9]{0,17}$/D', $inserted) || (int) $inserted <= $before)throw new CommonException('新退款记录无法唯一核实，当前未发起退款');
        $rows = Db::name('pay_refund')->where('id', (int) $inserted)->lock(true)->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('新退款记录缺失，当前未发起退款');
        $row = $rows[0];
        $candidates = Db::name('pay_refund')->where('id', '>', $before)->where('site_id', $site)->where('refund_no', $legacy)->where('out_trade_no', $original['out_trade_no'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', (string) $original['id'])->where('money', $amount)->where('status', RefundDict::WAIT)->lock(true)->field('id')->select()->toArray();
        if (count($candidates) !== 1 || (int) $candidates[0]['id'] !== (int) $inserted)throw new CommonException('本次新增退款记录不唯一，当前未发起退款');
        $payments = Db::name('pay')->where('site_id', $site)->where('out_trade_no', $original['out_trade_no'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $original['id'])->lock(true)->select()->toArray();
        if (count($payments) !== 1)throw new CommonException('原付款凭据无法唯一核对，当前未发起退款');
        $pay = $payments[0];
        if ((int) $row['site_id'] !== $site || $row['refund_no'] !== $legacy || $row['out_trade_no'] !== $original['out_trade_no'] || $row['trade_type'] !== PaymentService::TRADE_TYPE || (string) $row['trade_id'] !== (string) $original['id'] || $row['status'] !== RefundDict::WAIT || Rules::cents((string) $row['money']) !== Rules::cents($amount) || $row['type'] !== $pay['type'] || $row['channel'] !== $pay['channel'] || $row['reason'] !== $reason || $row['refund_type'] !== '' || $row['pay_refund_no'] !== '' || $row['voucher'] !== '' || (int) $row['refund_time'] !== 0 || (int) $row['close_time'] !== 0 || !PaymentService::receiptMatches($pay, $original))throw new CommonException('新退款记录与本次原付款不符，当前未发起退款');
        $number = '';
        for ($try = 0;$try < 3;$try++) {
            $candidate = $prefix.bin2hex(random_bytes(15));
            if (!Db::name('pay_refund')->where('refund_no', $candidate)->lock(true)->find()) {
                $number = $candidate;
                break;
            }
        }
        if ($number === '')throw new CommonException('退款标识暂未生成，请核对原申请；当前未发起退款');
        $updated = Db::name('pay_refund')->where('id', (int) $inserted)->where('site_id', $site)->where('refund_no', $legacy)->where('out_trade_no', $original['out_trade_no'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', (string) $original['id'])->where('status', RefundDict::WAIT)->where('refund_type', '')->where('pay_refund_no', '')->update(['refund_no' => $number]);
        if ($updated !== 1)throw new CommonException('新退款记录已变化，当前未发起退款');
        return ['id' => (int) $inserted, 'refund_no' => $number];
    }
}
