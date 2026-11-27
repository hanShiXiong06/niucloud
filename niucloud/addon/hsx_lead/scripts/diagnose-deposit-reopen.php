<?php
declare(strict_types=1);
namespace addon\hsx_lead\scripts;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{EntityMembershipService,ManualRefundService,MembershipCostService,MembershipDepositService,OrderPaymentReadService,PaymentService,Store,TeamService};
use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use RuntimeException;
use Throwable;
use think\facade\Db;

final class DepositReopenDiagnosticStatus
{
    public static string $stage = 'arguments';
}

/** Whitelisted location/codes only: never return raw SQL, credentials,
 * DSNs, message traces or business request parameters for unknown errors.
 */
function depositReopenSafeError(Throwable $error): array
{
    $details = ['stage' => DepositReopenDiagnosticStatus::$stage, 'error_type' => get_class($error), 'file' => basename($error->getFile()), 'line' => $error->getLine()];
    $cause = $error;
    for ($i = 0;$cause !== null && $i < 5;$i++,$cause = $cause->getPrevious()) {
        if (!$cause instanceof \PDOException)continue;
        $info = $cause->errorInfo ?? [];
        $state = $info[0] ?? $cause->getCode();
        if (is_string($state) && preg_match('/^[0-9A-Z]{5}$/D', $state))$details['sqlstate'] = $state;
        if (isset($info[1]) && (is_int($info[1]) || ctype_digit((string) $info[1])))$details['driver_code'] = (int) $info[1];
        break;
    }
    // Only this file's fixed, developer-authored validation messages are
    // public. A dependency may wrap SQL/credentials in RuntimeException or a
    // business exception; its class alone never makes that message safe.
    $known = get_class($error) === RuntimeException::class && $error->getFile() === __FILE__;
    return ['diagnostic_version' => '20261112.2', 'read_only' => true, 'state_writes' => false,
        'error' => $known ? $error->getMessage() : '诊断未完成，安全定位信息见 diagnostics；未执行状态修复', 'diagnostics' => $details];
}

/** One explicitly selected tenant/member only. No apply mode, refund calls,
 * account operations, cache flush, schema changes or state repair.
 */
function depositReopenOptions(array $args): array
{
    $out = [];
    foreach ($args as $arg) {
        if ($arg === '--check')continue;
        if (!preg_match('/^--(site|member-id|merchant-id|mobile|level|cycle)=(.+)$/D', $arg, $m) || isset($out[$m[1]]))throw new RuntimeException('仅支持 --check --site=站点ID，并选择 --mobile=手机号、--member-id=用户ID 或 --merchant-id=门店ID；不支持 --apply');
        $out[$m[1]] = $m[2];
    }
    if (!isset($out['site']) || !preg_match('/^[1-9][0-9]{0,9}$/D', $out['site']))throw new RuntimeException('请明确填写有效的 --site=站点ID，不会默认选择站点');
    if (count(array_intersect(array_keys($out), ['member-id', 'merchant-id', 'mobile'])) !== 1)throw new RuntimeException('请且仅请提供一种定位信息：--mobile、--member-id 或 --merchant-id');
    foreach (['member-id', 'merchant-id'] as $key)if (isset($out[$key]) && !preg_match('/^[1-9][0-9]{0,9}$/D', $out[$key]))throw new RuntimeException('用户ID或门店ID格式不正确');
    if (isset($out['mobile']) && !preg_match('/^1[3-9][0-9]{9}$/D', $out['mobile']))throw new RuntimeException('请填写11位手机号');
    if (isset($out['level']) && !preg_match('/^ML[1-9][0-9]{0,9}$/D', $out['level']))throw new RuntimeException('等级标识格式应为 ML 加等级ID');
    if (isset($out['cycle']) && !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,23}$/D', $out['cycle']))throw new RuntimeException('购买周期标识格式不正确');
    return $out;
}

function depositReopenCodeReport(): array
{
    $classes = [MembershipDepositService::class, EntityMembershipService::class, MembershipCostService::class];
    $files = [];
    foreach ($classes as $class) {
        $reflection = new \ReflectionClass($class);
        $file = (string) $reflection->getFileName();
        $files[] = ['file' => basename($file), 'sha256' => hash_file('sha256', $file)];
    }
    $ready = method_exists(MembershipDepositService::class, 'purchaseState') && method_exists(MembershipDepositService::class, 'membershipEpoch');
    foreach ($classes as $class) {
        $source = (string) file_get_contents((new \ReflectionClass($class))->getFileName());
        if ($class === EntityMembershipService::class)$ready = $ready && str_contains($source, 'deposit_purchase_blocked') && str_contains($source, 'assertDepositEpoch');
    }
    return ['cli_patch_present' => $ready, 'php_version' => PHP_VERSION, 'cli_opcache' => (bool) ini_get('opcache.enable_cli'), 'files' => $files,
        'scope' => '只证明本命令加载的PHP文件；不能替代HTTP进程、小程序API地址和实际客户端版本核对'];
}

function depositReopenDiagnostic(array $options): array
{
    DepositReopenDiagnosticStatus::$stage = 'code_check';
    $site = (int) $options['site'];
    $report = ['diagnostic_version' => '20261112.2', 'read_only' => true, 'state_writes' => false, 'external_payment_calls' => false, 'site_id' => $site, 'code' => depositReopenCodeReport()];
    if (!$report['code']['cli_patch_present'])return $report + ['next_action' => 'UPDATE_BACKEND', 'message' => '当前CLI加载的后端未包含退押金再开通修复；先完整覆盖配套PHP文件，不要改业务状态'];
    // Use the exact same connection as the services. Database-enforced read-only
    // protection also blocks accidental lazy creation by a future service edit.
    DepositReopenDiagnosticStatus::$stage = 'read_only_transaction';
    // ThinkORM getPdo() returns false before its first SQL statement. A cold
    // CLI must not call inTransaction() on that false value. SET below lazily
    // connects using the framework and still precedes every business read.
    $pdo = Db::connect()->getPdo();
    if ($pdo instanceof \PDO && $pdo->inTransaction())throw new RuntimeException('诊断必须在独立只读事务中运行，拒绝嵌入已有事务');
    Db::execute('SET TRANSACTION READ ONLY');
    Db::startTrans();
    try {
        DepositReopenDiagnosticStatus::$stage = 'member_lookup';
        if (isset($options['merchant-id'])) {
            $merchants = Data::query('merchant', $site)->where('id', (int) $options['merchant-id'])->select()->toArray();
            if (count($merchants) !== 1)throw new RuntimeException('本站没有唯一匹配的门店；不会跨站查找');
            $memberId = (int) $merchants[0]['member_id'];
        } else {
            $query = Db::name('member')->where('site_id', $site);
            if (isset($options['member-id']))$query->where('member_id', (int) $options['member-id']);
            else $query->where('mobile', $options['mobile']);
            $members = $query->field('member_id')->limit(2)->select()->toArray();
            if (count($members) !== 1)throw new RuntimeException('本站未找到唯一匹配会员，请改用 --member-id 精确定位');
            $memberId = (int) $members[0]['member_id'];
        }
        DepositReopenDiagnosticStatus::$stage = 'merchant_resolution';
        $ctx = new Context($site, 0, $memberId);
        $merchant = TeamService::resolve($ctx);
        $mid = (int) $merchant['id'];
        if (isset($options['merchant-id']) && $mid !== (int) $options['merchant-id'])throw new RuntimeException('门店负责人实际关联与指定门店不一致，未继续诊断');
        $report['target'] = ['merchant_id' => $mid, 'member_id' => $memberId, 'owner_member_id' => (int) $merchant['owner_member_id'], 'is_owner' => $merchant['is_owner']];
        if (!Store::query('partner', $site)->where('merchant_id', $mid)->find())return $report + ['next_action' => 'PARTNER_RECORD_MISSING', 'message' => '缺少门店协作记录；本脚本不会自动创建或补写'];
        DepositReopenDiagnosticStatus::$stage = 'membership_summary';
        $membership = new EntityMembershipService($ctx);
        $summary = $membership->summary();
        DepositReopenDiagnosticStatus::$stage = 'deposit_summary';
        $deposit = (new MembershipDepositService($ctx))->summary();
        $report['summary'] = array_intersect_key($summary, array_flip(['merchant_id', 'can_manage', 'active', 'level_code', 'deposit_balance', 'deposit_purchase_blocked', 'deposit_request_status', 'cooperation_exited', 'cost_review_required', 'cost_review_message', 'display_status', 'display_status_text']));
        $report['deposit'] = ['balance' => $deposit['balance'], 'can_apply' => $deposit['can_apply'], 'blocking_reasons' => $deposit['blocking_reasons'], 'request' => null, 'sources' => []];
        $request = $deposit['request'] ?? null;
        $complete = true;
        DepositReopenDiagnosticStatus::$stage = 'refund_source_check';
        if ($request) {
            $anchor = Store::row('coop_order', $site, (int) $request['id']);
            $raw = Data::decode($anchor['rule_json'])['membership_deposit_return'] ?? [];
            $report['deposit']['request'] = array_intersect_key($request, array_flip(['id', 'status', 'status_name', 'amount']));
            $sources = $raw['sources'] ?? [];
            $complete = is_array($sources) && count($sources) > 0;
            foreach (is_array($sources) ? $sources : [] as $source) {
                $item = ['source_order_id' => (int) ($source['order_id'] ?? 0), 'source_status' => (string) ($source['status'] ?? ''), 'amount' => (string) ($source['amount'] ?? ''), 'refund_record_id' => (int) ($source['refund_record_id'] ?? 0), 'native_refund_status' => 'missing', 'source_matches' => false, 'original_payment_matches' => false];
                $original = Store::query('coop_order', $site)->where('id', $item['source_order_id'])->where('merchant_id', $mid)->find();
                $refunds = empty($source['refund_no']) ? [] : Db::name('pay_refund')->where('site_id', $site)->where('refund_no', $source['refund_no'])->limit(2)->select()->toArray();
                if ($original && count($refunds) === 1) {
                    $refund = $refunds[0];
                    $item['native_refund_status'] = (string) $refund['status'];
                    $rule = Data::decode($original['rule_json']);
                    $allocation = $rule['membership_refund_allocations'][$refund['refund_no']] ?? [];
                    $item['source_matches'] = ($original['purpose'] ?? '') === 'membership' && $refund['trade_type'] === PaymentService::TRADE_TYPE && (int) $refund['trade_id'] === (int) $original['id'] && $refund['out_trade_no'] === $original['out_trade_no'] && $item['refund_record_id'] === (int) $refund['id'] && RulesForDepositDiagnostic::sameAmount($refund['money'], $source['amount'] ?? null) && RulesForDepositDiagnostic::sameAmount($allocation['deposit'] ?? null, $source['amount'] ?? null) && ($allocation['service'] ?? '') === '0.00' && ($allocation['experience'] ?? '') === '0.00';
                    try {
                        $pay = OrderPaymentReadService::payment($original);
                        if ($pay && (int) $pay['status'] === (int) PayDict::STATUS_FINISH && $pay['main_type'] === PayDict::MEMBER && (int) $pay['main_id'] === (int) $original['member_id'] && RulesForDepositDiagnostic::sameAmount($pay['money'], $original['amount'])) {
                            OrderPaymentReadService::assertManualSource($original, $pay);
                            if ($refund['type'] === PayDict::OFFLINEPAY && $refund['status'] === RefundDict::SUCCESS)ManualRefundService::assertRefundReceipt($site, $original, $refund);
                            $item['original_payment_matches'] = true;
                        }
                    } catch (CommonException$ignored) {
                        $item['original_payment_matches'] = false;
                    }
                }
                $complete = $complete && $item['source_matches'] && $item['original_payment_matches'] && $item['native_refund_status'] === RefundDict::SUCCESS;
                $report['deposit']['sources'][] = $item;
            }
        }
        $report['deposit']['all_source_refunds_confirmed'] = $complete && $request !== null;
        DepositReopenDiagnosticStatus::$stage = 'membership_quote';
        $tiers = $summary['tiers'] ?? [];
        $selected = null;
        $latest = Store::query('coop_order', $site)->where('merchant_id', $mid)->where('purpose', 'membership')->where('status', 'paid')->order('id desc')->find();
        $last = $latest ? Data::decode($latest['rule_json']) : [];
        $level = $options['level'] ?? ($last['level_code'] ?? '');
        foreach ($tiers as $tier)if (($tier['code'] ?? '') === $level)$selected = $tier;
        if (!$selected && !isset($options['level']))$selected = $tiers[0] ?? null;
        $report['available_levels'] = array_map(static fn (array $tier): array => ['code' => $tier['code'], 'name' => $tier['name'], 'cycles' => array_map(static fn (array $c): array => array_intersect_key($c, array_flip(['key', 'name', 'enabled', 'price'])), $tier['cycles'] ?? [])], $tiers);
        if ($selected) {
            $cycle = $options['cycle'] ?? ($last['cycle'] ?? '');
            $period = null;
            foreach ($selected['cycles'] ?? [] as $p)if (($p['key'] ?? '') === $cycle && !empty($p['enabled']))$period = $p;
            if (!$period && !isset($options['cycle']))foreach ($selected['cycles'] ?? [] as $p)if (!empty($p['enabled'])) {
                $period = $p;
                break;
            }
            if ($period && $merchant['is_owner']) {
                $quote = $membership->quote($selected['code'], $period['key']);
                $report['quote'] = array_intersect_key($quote, array_flip(['level_code', 'cycle', 'can_purchase', 'reason', 'existing_order_id', 'deposit_amount', 'service_amount', 'experience_amount', 'payable_amount']));
            }
        }
        if (!empty($report['summary']['deposit_purchase_blocked'])) {
            $localCompletion = $report['deposit']['all_source_refunds_confirmed'] && in_array($request['status'] ?? '', ['approved', 'refunding', 'attention'], true);
            $report['next_action'] = $localCompletion ? 'REFRESH_EXISTING_DEPOSIT_REQUEST' : 'WAIT_OR_REVIEW_ORIGINAL_REFUND';
            $report['message'] = $localCompletion ? '原退款凭据均已成功，但退出申请尚未完成；可在后台只刷新这笔原申请以补偿业务回调，不应新增退款或SQL强改状态' : '原申请仍在审核、退款或核对中；先处理原记录，不可直接改成成功';
        } elseif (!empty($summary['cost_review_required'])) {
            $report['next_action'] = 'REVIEW_ORIGINAL_FINANCIAL_RECORDS';
            $report['message'] = '退出不再拦截，但仍有付款或退款凭据待核对，不能通过改退出状态绕过';
        } elseif (isset($report['quote']) && !empty($report['quote']['can_purchase'])) {
            $report['next_action'] = 'CHECK_HTTP_RESPONSE_AND_CLIENT';
            $report['message'] = '本CLI真实业务报价已允许购买，无需改退出状态；继续比较小程序实际HTTP会员/报价响应、所连站点及前端不确定状态';
        } else {
            $report['next_action'] = 'CHECK_QUOTE_REASON_OR_OWNER';
            $report['message'] = '请按quote.reason、负责人身份和开放的套餐周期核对；本脚本没有改变任何状态';
        }
        DepositReopenDiagnosticStatus::$stage = 'complete';
        return $report;
    } finally {
        Db::rollback();
    }
}

/** Never display SQL, credentials, payment references or raw rule JSON. */
final class RulesForDepositDiagnostic
{
    public static function sameAmount($a, $b): bool
    {
        try {
            return \addon\hsx_lead\app\service\cooperation\Rules::cents($a) === \addon\hsx_lead\app\service\cooperation\Rules::cents($b);
        } catch (Throwable$error) {
            return false;
        }
    }
}

if (!defined('HSX_LEAD_DEPOSIT_REOPEN_DIAG_LIBRARY_ONLY')) {
    if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000)exit("仅支持 PHP 8.0+ 命令行执行\n");
    try {
        $options = depositReopenOptions(array_slice($argv, 1));
        DepositReopenDiagnosticStatus::$stage = 'bootstrap';
        define('HSX_LEAD_UPGRADE_LIBRARY_ONLY', true);
        require_once __DIR__.'/upgrade.php';
        bootstrap(dirname(__DIR__, 3));
        echo json_encode(depositReopenDiagnostic($options), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable$error) {
        fwrite(STDERR, json_encode(depositReopenSafeError($error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        exit(1);
    }
}
