<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict,RefundDict};
use app\service\core\pay\CoreRefundService;
use app\service\core\paytype\CoreOfflineService;
use core\exception\CommonException;
use think\facade\Db;

/** Records a refund already made by a permitted operator. Never transfers or credits money. */
final class ManualRefundService
{
    public function __construct(private Context $context)
    {
    }

    public function confirm(int $id, array $input): array
    {
        Store::permission($this->context, 'finance');
        $this->context->permission('hsx_lead_cooperation_order_manual_refund');
        if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('确认线下退款需要全站财务管理权限');
        if (Db::connect()->getPdo()->inTransaction())throw new CommonException('实际退款登记不能嵌套在其他事务，请先提交原业务操作');
        if (array_diff(array_keys($input), ['refund_no', 'amount', 'request_id', 'method', 'account_label', 'external_no', 'received_at', 'reason', 'confirm']))throw new CommonException('线下退款包含不支持的字段');
        if (!in_array($input['confirm'] ?? null, [true, 1, '1'], true))throw new CommonException('请确认已经实际退款；本操作仅登记，不会转账');
        $request = Store::requestId($input);
        $number = Data::text($input, 'refund_no', 64, true);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $number))throw new CommonException('原退款编号无效，请重新读取原退款');
        $method = Data::text($input, 'method', 20, true);
        if (!isset(ManualPaymentService::METHODS[$method]))throw new CommonException('请选择实际退款方式');
        $account = self::text($input, 'account_label', 100);
        $external = self::text($input, 'external_no', 100);
        $reason = self::text($input, 'reason', 500);
        if (mb_strlen($reason) < 4)throw new CommonException('请填写至少4个字的退款确认说明');
        $amount = Rules::amount(Rules::cents($input['amount'] ?? null));
        if (Rules::cents($amount) <= 0)throw new CommonException('实际退款金额必须大于零');
        $at = $input['received_at'] ?? time();
        if (!is_int($at) && !(is_string($at) && preg_match('/^[1-9][0-9]{0,9}$/D', $at)))throw new CommonException('实际退款时间格式不正确');
        $at = (int) $at;
        if ($at <= 0 || $at > time() + 60)throw new CommonException('实际退款时间不能晚于当前时间');
        $hash = ManualPaymentService::referenceHash('refund', $method, $account, $external);
        $site = $this->context->site;
        $explicitTime = array_key_exists('received_at', $input);
        $receipt = Db::transaction(function () use ($site, $id, $request, $number, $method, $account, $external, $reason, $amount, $at, $hash, $explicitTime): array {
            PromotionService::lockSite($site);
            $order = Store::row('coop_order', $site, $id, true);
            $refund = self::refund($site, $order, $number, true);
            $pay = self::payment($site, $order, $refund, true);
            $source = ManualPaymentService::assertReceipt($site, $order, $pay, true);
            $existing = Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('kind', 'refund')->where(function ($q) use ($request, $number): void {$q->where('request_id', $request)->whereOr('refund_no', $number);})->lock(true)->select()->toArray();
            if ($existing) {
                if (count($existing) !== 1)throw new CommonException('原退款确认记录不唯一，请核对，未重复登记');
                $old = $existing[0];
                if ((int) $old['target_order_id'] !== $id || $old['refund_no'] !== $number || $old['reference_hash'] !== $hash || Rules::cents((string) $old['amount']) !== Rules::cents($amount) || $old['reason'] !== $reason
                    || ($explicitTime && (int) $old['received_at'] !== $at))throw new CommonException('本笔退款已有不同的确认记录，请核对原凭据，不能重复退款');
                return $old;
            }
            if (Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('reference_hash', $hash)->lock(true)->find())throw new CommonException('这个退款流水已登记到其他业务，请勿重复使用');
            if ($refund['status'] !== RefundDict::WAIT || $refund['refund_type'] !== '')throw new CommonException('原退款状态已变化，请先核对，未重复确认');
            if (Rules::cents((string) $refund['money']) !== Rules::cents($amount) || (int) $pay['pay_time'] > $at)throw new CommonException('实际退款金额或时间与原收款不一致');
            self::approved($site, $order, $refund, false);
            $snapshot = ['schema' => 'hsx.lead.manual_refund.v1', 'order_id' => $id, 'merchant_id' => (int) $order['merchant_id'], 'refund_record_id' => (int) $refund['id'],
                'refund_no' => $number, 'out_trade_no' => $order['out_trade_no'], 'member_id' => (int) $order['member_id'], 'source_receipt_id' => (int) $source['id'],
                'amount' => $amount, 'reference_hash' => $hash, 'received_at' => $at, 'confirmed' => true];
            $rid = (int) Db::name('hsx_lead_manual_receipt')->insertGetId(['site_id' => $site, 'kind' => 'refund', 'order_id' => $id, 'target_order_id' => $id, 'member_id' => $order['member_id'],
                'source_receipt_id' => $source['id'], 'out_trade_no' => $order['out_trade_no'], 'refund_no' => $number, 'request_id' => $request, 'method' => $method, 'account_label' => $account, 'external_no' => $external,
                'reference_hash' => $hash, 'amount' => $amount, 'received_at' => $at, 'operator_uid' => $this->context->uid, 'status' => 'recorded', 'reason' => $reason,
                'message' => '实际退款凭据已登记，正在更新业务结果', 'snapshot_json' => Data::json($snapshot), 'create_time' => time(), 'update_time' => time()]);
            $this->context->audit('coop_order', $id, 'manual_refund_recorded', ['manual_receipt_id' => $rid, 'refund_no' => $number, 'amount' => $amount]);
            return Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('id', $rid)->find();
        });
        self::post($site, (int) $receipt['id']);
        return self::result($site, (int) $receipt['id']);
    }

    private static function text(array $input, string $key, int $limit): string
    {
        $value = Data::text($input, $key, $limit, true);
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[<>\x00-\x1F\x7F]/u', $value))throw new CommonException('退款资料请使用普通文字，不能包含HTML或控制字符');
        return $value;
    }
    private static function refund(int $site, array $order, string $number, bool $lock = false): array
    {
        $q = Db::name('pay_refund')->where('site_id', $site)->where('refund_no', $number);
        if ($lock)$q->lock(true);
        $rows = $q->limit(2)->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('原退款记录不存在或不唯一，请核对');
        $r = $rows[0];
        if ($r['trade_type'] !== PaymentService::TRADE_TYPE || (string) $r['trade_id'] !== (string) $order['id'] || $r['out_trade_no'] !== $order['out_trade_no'] || $r['type'] !== PayDict::OFFLINEPAY)throw new CommonException('该退款不属于本订单的线下收款');
        return $r;
    }
    private static function payment(int $site, array $order, array $refund, bool $lock = false): array
    {
        $pay = OrderPaymentReadService::payment($order, $lock);
        if (!$pay || (int) $pay['site_id'] !== $site || $pay['type'] !== PayDict::OFFLINEPAY || (int) $pay['status'] !== (int) PayDict::STATUS_FINISH
            || $pay['main_type'] !== PayDict::MEMBER || (int) $pay['main_id'] !== (int) $order['member_id'] || $pay['out_trade_no'] !== $refund['out_trade_no'] || $pay['channel'] !== $refund['channel']
            || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']))throw new CommonException('原线下收款身份、金额或渠道不一致');
        return $pay;
    }
    private static function approved(int $site, array $order, array $refund, bool $mark): void
    {
        if (PaymentRefundService::manualConfirmation($site, $order, $refund, $mark))return;
        if (MembershipDepositService::manualConfirmation($site, $order, $refund, $mark))return;
        throw new CommonException('原退款尚未审核通过或已失效，未确认退款');
    }
    /** A typed, privileged actual-transfer record is mandatory before replaying successful offline hooks. */
    public static function assertRefundReceipt(int $site, array $order, array $refund): array
    {
        if ((int) ($refund['site_id'] ?? 0) !== $site || ($refund['type'] ?? '') !== PayDict::OFFLINEPAY || ($refund['trade_type'] ?? '') !== PaymentService::TRADE_TYPE || (string) ($refund['trade_id'] ?? '') !== (string) $order['id'] || ($refund['out_trade_no'] ?? '') !== $order['out_trade_no'])throw new CommonException('实际线下退款不属于当前订单');
        $pay = self::payment($site, $order, $refund, true);
        $source = ManualPaymentService::assertReceipt($site, $order, $pay, true);
        $rows = Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('kind', 'refund')->where('target_order_id', $order['id'])->where('refund_no', $refund['refund_no'])->lock(true)->limit(2)->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('尚无唯一的实际线下退款凭据，不能确认业务退款');
        $r = $rows[0];
        $s = Data::decode($r['snapshot_json']);
        if ((int) $r['order_id'] !== (int) $order['id'] || (int) $r['member_id'] !== (int) $order['member_id'] || (int) $r['source_receipt_id'] !== (int) $source['id'] || $r['out_trade_no'] !== $order['out_trade_no']
            || Rules::cents((string) $r['amount']) !== Rules::cents((string) $refund['money']) || (int) $r['operator_uid'] <= 0 || (int) $r['received_at'] <= 0 || (int) $r['received_at'] > time() + 60 || (int) $r['received_at'] < (int) $pay['pay_time'] || $r['external_no'] === '' || $r['account_label'] === ''
            || !in_array($r['status'], ['recorded', 'processing', 'posted', 'attention'], true) || !isset(ManualPaymentService::METHODS[$r['method']])
            || !hash_equals((string) $r['reference_hash'], ManualPaymentService::referenceHash('refund', $r['method'], $r['account_label'], $r['external_no']))
            || ($s['schema'] ?? '') !== 'hsx.lead.manual_refund.v1' || ($s['confirmed'] ?? false) !== true || (int) ($s['order_id'] ?? 0) !== (int) $order['id'] || (int) ($s['refund_record_id'] ?? 0) !== (int) $refund['id']
            || (int) ($s['merchant_id'] ?? 0) !== (int) $order['merchant_id'] || ($s['refund_no'] ?? '') !== $refund['refund_no'] || ($s['out_trade_no'] ?? '') !== $order['out_trade_no']
            || (int) ($s['member_id'] ?? 0) !== (int) $order['member_id'] || (int) ($s['source_receipt_id'] ?? 0) !== (int) $source['id'] || ($s['amount'] ?? '') !== (string) $r['amount']
            || ($s['reference_hash'] ?? '') !== $r['reference_hash'] || (int) ($s['received_at'] ?? 0) !== (int) $r['received_at']
            || ($refund['status'] === RefundDict::SUCCESS && ($refund['refund_type'] !== RefundDict::OFFLINE || $refund['voucher'] !== $r['external_no'] || (int) $refund['refund_time'] !== (int) $r['received_at'])))throw new CommonException('实际线下退款凭据与原单不一致，请平台核对');
        return $r;
    }

    private static function post(int $site, int $rid): void
    {
        try {
            Db::transaction(function () use ($site, $rid): void {
                PromotionService::lockSite($site);
                $r = Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('kind', 'refund')->where('id', $rid)->lock(true)->find();
                if (!$r)throw new CommonException('原人工退款凭据不存在');
                $order = Store::row('coop_order', $site, (int) $r['target_order_id'], true);
                $refund = self::refund($site, $order, $r['refund_no'], true);
                self::assertRefundReceipt($site, $order, $refund);
                if ($r['status'] === 'posted') {
                    if ($refund['status'] !== RefundDict::SUCCESS)throw new CommonException('已登记退款与业务结果不一致');
                    return;
                }
                self::approved($site, $order, $refund, true);
                Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('id', $rid)->update(['status' => 'processing', 'update_time' => time()]);
                if ($refund['status'] !== RefundDict::SUCCESS) {
                    $result = (new CoreOfflineService())->refund(['site_id' => $site, 'refund_no' => $refund['refund_no'], 'voucher' => $r['external_no']]);
                    if (($result['status'] ?? '') !== RefundDict::SUCCESS || ($result['refund_no'] ?? '') !== $refund['refund_no'])throw new CommonException('线下退款凭据未确认');
                    Db::name('pay_refund')->where('site_id', $site)->where('id', $refund['id'])->update(['refund_type' => RefundDict::OFFLINE, 'refund_time' => (int) $r['received_at']]);
                }
                $event = ['out_trade_no' => $refund['out_trade_no'], 'refund_no' => $refund['refund_no'], 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => (string) $order['id'], 'site_id' => $site];
                if ((new CoreRefundService())->refundSuccess($site, $event) !== true)throw new CommonException('原退款业务处理尚未完成');
                // Event caches may still lack this plugin's newly installed
                // listeners. Replay the same idempotent business handlers, never a
                // transfer, so a successful voucher cannot leave old rights active.
                (new \addon\hsx_lead\app\listener\pay\MembershipRefundSuccess())->handle($event);
                PaymentRefundService::confirmed($event);
                PromotionService::refund($event);
                Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('id', $rid)->update(['status' => 'posted', 'message' => '已登记实际退款，业务结果已更新', 'update_time' => time()]);
                Data::audit($site, (int) $r['operator_uid'], 0, 'coop_order', (int) $order['id'], 'manual_refund_posted', ['manual_receipt_id' => $rid, 'refund_no' => $refund['refund_no'], 'amount' => $r['amount']]);
            });
        } catch (\Throwable $error) {
            $message = $error instanceof CommonException ? mb_substr($error->getMessage().'；实际退款已登记，请勿再次转账', 0, 500) : '实际退款已登记，业务结果待核对；请刷新原退款，不要再次转账';
            Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('kind', 'refund')->where('id', $rid)->where('status', '<>', 'posted')->update(['status' => 'attention', 'message' => $message, 'update_time' => time()]);
        }
    }
    /** Compensation only for already recorded real transfers; never creates a receipt or sends money. */
    public static function resume(int $site, array $order, array $refund): bool
    {
        if (($refund['type'] ?? '') !== PayDict::OFFLINEPAY)return false;
        $r = Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('kind', 'refund')->where('target_order_id', $order['id'])->where('refund_no', $refund['refund_no'])->find();
        if ($r)self::post($site, (int) $r['id']);
        return true;
    }
    public static function tick(int $limit = 20): void
    {
        $table = (string) Db::name('hsx_lead_manual_receipt')->getTable();
        if (!(int) (Db::query('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table])[0]['n'] ?? 0))return;
        $rows = Db::name('hsx_lead_manual_receipt')->where('kind', 'refund')->whereIn('status', ['recorded', 'processing', 'attention'])->order('update_time,id')->limit(max(1, min(50, $limit)))->field('id,site_id')->select()->toArray();
        foreach ($rows as $r)try {
            self::post((int) $r['site_id'], (int) $r['id']);
        } catch (\Throwable$e) {
            \think\facade\Log::warning('人工退款原凭据补偿待核对：site='.(int) $r['site_id'].', receipt='.(int) $r['id'].', type='.get_class($e));
        }
    }
    public static function metadata(array $order, ?array $refund, bool $admin = false): array
    {
        $out = ['manual_refund_required' => false, 'can_confirm_manual_refund' => false, 'manual_refund_receipt_id' => 0, 'manual_refund_message' => ''];
        if (!$refund || ($refund['type'] ?? '') !== PayDict::OFFLINEPAY)return $out;
        $out['manual_refund_required'] = true;
        try {
            $r = Db::name('hsx_lead_manual_receipt')->where('site_id', $order['site_id'])->where('kind', 'refund')->where('target_order_id', $order['id'])->where('refund_no', $refund['refund_no'])->find();
            $out['manual_refund_receipt_id'] = (int) ($r['id'] ?? 0);
            $out['manual_refund_message'] = $r ? (string) $r['message'] : ($refund['status'] === RefundDict::SUCCESS ? '线下退款已确认' : '待平台实际退款后确认');
            $out['can_confirm_manual_refund'] = $admin && !$r && $refund['status'] === RefundDict::WAIT && $refund['refund_type'] === '';
        } catch (\Throwable$e) {
            if (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), '1146'))$out['manual_refund_message'] = '人工退款记录尚未就绪，请先执行插件更新';
            else throw new CommonException('人工退款信息读取失败，请刷新核对，不要重复转账');
        }
        return $out;
    }
    private static function result(int $site, int $id): array
    {
        $r = Db::name('hsx_lead_manual_receipt')->where('site_id', $site)->where('kind', 'refund')->where('id', $id)->find();
        if (!$r)throw new CommonException('原人工退款记录不存在');
        $refund = Db::name('pay_refund')->where('site_id', $site)->where('refund_no', $r['refund_no'])->find();
        return ['order_id' => (int) $r['target_order_id'], 'refund_no' => $r['refund_no'], 'amount' => $r['amount'], 'manual_receipt_id' => (int) $r['id'], 'status' => $r['status'] === 'posted' ? 'posted' : 'attention', 'refund_status' => (string) ($refund['status'] ?? 'wait'), 'message' => $r['message']];
    }
}
