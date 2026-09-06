<?php
declare(strict_types=1);

if (getenv('HSX_PROJECT_CENTER_DISTRIBUTION_INTEGRATION') !== '1') {
    fwrite(STDERR, "Set HSX_PROJECT_CENTER_DISTRIBUTION_INTEGRATION=1 to run this rollback-only database integration test.\n");
    exit(2);
}

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDebt;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDetail;
use addon\hsx_project_center\app\model\ProjectCenterDistributionOrder;
use addon\hsx_project_center\app\model\ProjectCenterInvite;
use addon\hsx_project_center\app\service\admin\ProjectCenterDistributionAdminService;
use addon\hsx_project_center\app\service\api\ProjectCenterDistributionPortalService;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionRuleService;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionService;
use addon\hsx_project_center\app\service\core\ProjectCenterInviteService;
use think\facade\Db;

$app = new think\App();
$app->initialize();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertMoney = static function (float $actual, float $expected, string $message): void {
    if (abs($actual - $expected) > 0.001) {
        throw new RuntimeException($message . "，期望 {$expected}，实际 {$actual}");
    }
};
$expectException = static function (callable $callback, string $contains, string $message) use ($assert): void {
    try {
        $callback();
    } catch (Throwable $e) {
        $assert(str_contains($e->getMessage(), $contains), $message . '，实际异常：' . $e->getMessage());
        return;
    }
    throw new RuntimeException($message . '，但没有抛出异常');
};

$siteId = 2_000_000_000 + random_int(1, 100_000);
$suffix = date('YmdHis') . bin2hex(random_bytes(4));
$now = time();
$enabledAt = $now - 120;
$cases = [];
$pass = static function (string $id, string $name) use (&$cases): void {
    $cases[] = ['id' => $id, 'name' => $name, 'status' => 'PASS'];
};

request()->siteId($siteId);
request()->uid(1);
request()->username('project-center-distribution-integration');

Db::startTrans();
$failure = null;
$summary = null;
try {
    $benefitKey = ProjectCenterDistributionRuleService::BENEFIT_KEY;
    $level = static function (string $name, int $status, array $benefit) use ($siteId, $now, $benefitKey): int {
        return (int)Db::name('member_level')->insertGetId([
            'site_id' => $siteId,
            'level_name' => $name,
            'growth' => 0,
            'remark' => '项目分销回滚集成测试',
            'status' => $status,
            'create_time' => $now,
            'update_time' => $now,
            'level_benefits' => json_encode([$benefitKey => $benefit], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'level_gifts' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);
    };
    $levelA = $level('集成测试二级推广等级', 1, [
        'is_use' => 1, 'first_coefficient' => 90,
        'second_enabled' => 1, 'second_coefficient' => 80,
    ]);
    $levelB = $level('集成测试一级推广等级', 1, [
        'is_use' => 1, 'first_coefficient' => 120,
        'second_enabled' => 1, 'second_coefficient' => 100,
    ]);
    $levelStopped = $level('集成测试已停用等级', 0, [
        'is_use' => 1, 'first_coefficient' => 100,
        'second_enabled' => 1, 'second_coefficient' => 100,
    ]);
    $levelNoBenefit = $level('集成测试无推广权益等级', 1, [
        'is_use' => 0, 'first_coefficient' => 100,
        'second_enabled' => 0, 'second_coefficient' => 100,
    ]);

    $member = static function (string $name, int $levelId) use ($siteId, $now, $suffix): int {
        static $index = 0;
        $index++;
        return (int)Db::name('member')->insertGetId([
            'member_no' => 'PCDIST' . $suffix . $index,
            'pid' => 0,
            'site_id' => $siteId,
            'username' => 'pc_dist_' . $suffix . '_' . $index,
            'mobile' => '199' . str_pad((string)(10000000 + $index), 8, '0', STR_PAD_LEFT),
            'nickname' => $name,
            'member_level' => $levelId,
            'member_label' => '[]',
            'status' => 1,
            'commission' => 0,
            'commission_get' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);
    };
    $memberA = $member('二级受益人A', $levelA);
    $memberB = $member('一级受益人B', $levelB);
    $memberC = $member('购买人C', $levelB);
    $memberD = $member('停用等级会员D', $levelStopped);
    $memberE = $member('无权益会员E', $levelNoBenefit);

    $projectId = (int)Db::name('project_center_project')->insertGetId([
        'site_id' => $siteId,
        'project_no' => 'PCDIST-' . $suffix,
        'title' => '项目分销数据库集成测试',
        'subtitle' => '事务回滚测试数据',
        'status' => ProjectCenterDict::PROJECT_ENABLED,
        'payment_amount' => 1000,
        'reviewer_uids' => '[]',
        'reviewer_role_ids' => '[]',
        'distribution_enabled' => 1,
        'config_json' => json_encode([
            'distribution' => [
                'commission_type' => 'fixed',
                'first_value' => 300,
                'second_value' => 100,
                'settle_days' => 7,
                'approval_requires_payment_check' => 1,
                'enabled_at' => $enabledAt,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'create_at' => $now,
        'update_at' => $now,
    ]);

    $ruleService = new ProjectCenterDistributionRuleService();
    $ratioFirst = $ruleService->commission(1000, [
        'commission_type' => 'ratio', 'first_value' => 5, 'second_value' => 2,
    ], 1, 120);
    $ratioSecond = $ruleService->commission(1000, [
        'commission_type' => 'ratio', 'first_value' => 5, 'second_value' => 2,
    ], 2, 80);
    $assertMoney((float)$ratioFirst['commission_amount'], 60, '比例一级佣金公式错误');
    $assertMoney((float)$ratioSecond['commission_amount'], 16, '比例二级佣金公式错误');
    $assert(empty($ruleService->memberCapability($siteId, $memberD, 1)['eligible']), '停用会员等级不能获得推广资格');
    $assert(empty($ruleService->memberCapability($siteId, $memberE, 1)['eligible']), '未开启推广权益的等级不能获得推广资格');
    $assert(empty($ruleService->memberCapability($siteId + 1, $memberA, 1)['eligible']), '会员推广资格必须按站点隔离');
    $pass('PC-DIST-RULE-001', '固定金额、比例金额、会员等级资格和站点隔离');

    $inviteService = new ProjectCenterInviteService();
    $inviteA = $inviteService->create($siteId, $projectId, $memberA);
    $assert((bool)preg_match('/^c_[0-9a-z]+_[0-9a-z]+_[a-f0-9]{10}$/', (string)($inviteA['share_code'] ?? '')), '邀请接口未返回合法的海报短签名凭证');
    $assert(strlen((string)$inviteA['share_code']) <= 30, '海报短签名凭证超过微信小程序码 scene 安全长度');
    $tamperedCode = substr((string)$inviteA['share_code'], 0, -1) . (str_ends_with((string)$inviteA['share_code'], '0') ? '1' : '0');
    $expectException(
        static fn() => $inviteService->bind($siteId, $projectId, $memberB, $tamperedCode),
        '邀请信息无效', '篡改海报短签名凭证必须被服务端拦截'
    );
    $bindB = $inviteService->bind($siteId, $projectId, $memberB, (string)$inviteA['share_code']);
    $assert(($bindB['state'] ?? '') === 'bound', 'A 邀请 B 绑定失败');

    $inviteB = $inviteService->create($siteId, $projectId, $memberB);
    $bindC = $inviteService->bind($siteId, $projectId, $memberC, (string)$inviteB['token']);
    $assert(($bindC['state'] ?? '') === 'bound', 'B 邀请 C 绑定失败');
    $repeatC = $inviteService->bind($siteId, $projectId, $memberC, (string)$inviteB['token']);
    $assert(($repeatC['state'] ?? '') === 'already_bound', '相同邀请重复进入必须保持原关系');
    $keptC = $inviteService->bind($siteId, $projectId, $memberC, (string)$inviteA['token']);
    $assert(($keptC['state'] ?? '') === 'kept_existing', '不同邀请不得覆盖首次有效推荐关系');
    $assert((int)Db::name('member')->where('member_id', $memberC)->value('pid') === $memberB, 'C 的推荐人必须保持为 B');

    $expectException(
        static fn() => $inviteService->bind($siteId, $projectId, $memberA, (string)$inviteA['token']),
        '自己的邀请链接', '自我绑定必须被拦截'
    );
    $inviteC = $inviteService->create($siteId, $projectId, $memberC);
    $expectException(
        static fn() => $inviteService->bind($siteId, $projectId, $memberA, (string)$inviteC['token']),
        '循环推荐关系', 'A 绑定到 C 必须因 A→B→C 环路被拦截'
    );
    $assert((int)Db::name('project_center_relation_log')->where('site_id', $siteId)->where('action', 'bind')->count() === 2, '成功绑定必须各有一条关系审计');
    $assert((int)Db::name('project_center_relation_log')->where('site_id', $siteId)->where('action', 'keep')->count() === 1, '相同关系重复访问必须留下 keep 审计');
    $assert((int)Db::name('project_center_relation_log')->where('site_id', $siteId)->where('action', 'reject')->count() === 3, '覆盖、自绑和循环拒绝必须完整审计');
    $pass('PC-DIST-INVITE-001', '短签名海报邀请、首次关系、重复进入、不覆盖、自绑与循环关系拦截及审计');

    $applicationIndex = 0;
    $application = static function (int $approvedAt, int $paymentConfirmedAt) use (
        $siteId, $projectId, $memberC, $now, $suffix, &$applicationIndex
    ): int {
        $applicationIndex++;
        return (int)Db::name('project_center_application')->insertGetId([
            'site_id' => $siteId,
            'application_no' => 'PCAPP-' . $suffix . '-' . $applicationIndex,
            'project_id' => $projectId,
            'group_id' => 900000 + $applicationIndex,
            'member_id' => $memberC,
            'idempotency_key' => 'pc-dist-integration:' . $suffix . ':' . $applicationIndex,
            'form_id' => 0,
            'form_record_id' => 0,
            'submit_version' => 1,
            'status' => ProjectCenterDict::APPLICATION_APPROVED,
            'payment_declared_at' => $paymentConfirmedAt > 0 ? $paymentConfirmedAt : 0,
            'payment_confirmed_at' => $paymentConfirmedAt,
            'payment_confirmed_uid' => $paymentConfirmedAt > 0 ? 1 : 0,
            'payment_confirmed_name' => $paymentConfirmedAt > 0 ? '集成测试审核员' : '',
            'submitted_at' => $now,
            'reviewed_at' => $approvedAt,
            'approved_at' => $approvedAt,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    };

    $distributionService = new ProjectCenterDistributionService();
    $unpaidApplicationId = $application($now, 0);
    $assert($distributionService->createForApprovedApplication($siteId, $unpaidApplicationId) === 0, '未核对付款的审批工单不得生成佣金单');
    $assert((int)ProjectCenterDistributionOrder::where('application_id', $unpaidApplicationId)->count() === 0, '付款兜底拦截后不能留下空佣金单');
    $pass('PC-DIST-PAY-001', '审核付款核对规则在佣金核心层二次兜底');

    $applicationId = $application($now, $now);
    $orderId = $distributionService->createForApprovedApplication($siteId, $applicationId);
    $assert($orderId > 0, '审核通过后未生成佣金单');
    $repeatOrderId = $distributionService->createForApprovedApplication($siteId, $applicationId);
    $assert($repeatOrderId === $orderId, '同一工单重复触发必须返回同一佣金单');
    $assert((int)ProjectCenterDistributionOrder::where('application_id', $applicationId)->count() === 1, '同一工单只能有一个佣金单');

    $details = ProjectCenterDistributionDetail::where('order_id', $orderId)->order('relation_level asc')->select()->toArray();
    $assert(count($details) === 2, '完整 A→B→C 链路必须生成两级佣金明细');
    $assert((int)$details[0]['beneficiary_member_id'] === $memberB && (int)$details[0]['relation_level'] === 1, '一级受益人必须为 B');
    $assertMoney((float)$details[0]['base_commission'], 300, '一级基础佣金错误');
    $assertMoney((float)$details[0]['coefficient'], 120, '一级等级系数错误');
    $assertMoney((float)$details[0]['commission_amount'], 360, '一级最终佣金错误');
    $assert((int)$details[1]['beneficiary_member_id'] === $memberA && (int)$details[1]['relation_level'] === 2, '二级受益人必须为 A');
    $assertMoney((float)$details[1]['base_commission'], 100, '二级基础佣金错误');
    $assertMoney((float)$details[1]['coefficient'], 80, '二级等级系数错误');
    $assertMoney((float)$details[1]['commission_amount'], 80, '二级最终佣金错误');
    $assert((int)ProjectCenterDistributionDetail::where('order_id', $orderId)->count() === 2, '重复触发不得重复生成佣金明细');
    $pass('PC-DIST-ORDER-001', '两级佣金快照、等级系数、固定金额计算与业务幂等');

    $assert($distributionService->settleOrder($orderId, true), '强制结算佣金单失败');
    $assertMoney((float)Db::name('member')->where('member_id', $memberB)->value('commission'), 360, 'B 结算余额错误');
    $assertMoney((float)Db::name('member')->where('member_id', $memberA)->value('commission'), 80, 'A 结算余额错误');
    $assert((string)ProjectCenterDistributionOrder::where('id', $orderId)->value('status') === 'settled', '佣金单结算状态错误');
    $assert((int)Db::name('member_account_log')->where('site_id', $siteId)->where('from_type', 'project_center_distribution_settle')->count() === 2, '两级结算必须生成两条会员账户流水');
    $assert(!$distributionService->settleOrder($orderId, true), '已结算佣金单重复执行必须幂等返回 false');
    $pass('PC-DIST-SETTLE-001', '两级入账、账户流水与重复结算幂等');

    request()->memberId($memberB);
    $portalService = new ProjectCenterDistributionPortalService();
    $memberOverview = $portalService->overview($projectId);
    $assert(!array_key_exists('member', (array)$memberOverview['capability']), '会员端资格数据不能泄露完整会员资料');
    $assert(!array_key_exists('benefit', (array)$memberOverview['capability']), '会员端资格数据不能泄露完整等级权益配置');
    $assertMoney((float)$memberOverview['commission_balance'], 360, '会员端佣金余额错误');
    $assertMoney((float)$memberOverview['settled_amount'], 360, '会员端累计结算金额错误');
    $assert((int)$memberOverview['direct_count'] === 1 && (int)$memberOverview['second_count'] === 0, '会员端邀请人数统计错误');
    $assert((int)$memberOverview['bound_inviter_member_id'] === $memberA, '会员端绑定推荐人信息错误');
    $memberDetails = $portalService->details(['project_id' => $projectId, 'page' => 1, 'limit' => 15]);
    $memberRows = (array)($memberDetails['data'] ?? $memberDetails['list'] ?? []);
    $assert(count($memberRows) === 1 && (int)$memberRows[0]['beneficiary_member_id'] === $memberB, '会员端只能读取自己的佣金明细');
    $assert(!array_key_exists('level_snapshot', $memberRows[0]), '会员端明细不能返回内部等级快照');

    $adminService = new ProjectCenterDistributionAdminService();
    $adminOverview = $adminService->overview();
    $assert((int)$adminOverview['order_count'] === 1, '管理端概览佣金单数量错误');
    $assertMoney((float)$adminOverview['settled_amount'], 440, '管理端概览两级累计结算金额错误');
    $adminPage = $adminService->page(['page' => 1, 'limit' => 15]);
    $adminRows = (array)($adminPage['data'] ?? $adminPage['list'] ?? []);
    $assert(count($adminRows) === 1 && count((array)$adminRows[0]['details']) === 2, '管理端佣金台账必须返回一张业务单及两级明细');
    $pass('PC-DIST-API-001', '会员端数据最小化、本人范围隔离和管理端可解释台账');

    $distributionService->refundByApplication($siteId, $applicationId, 500);
    $assertMoney((float)Db::name('member')->where('member_id', $memberB)->value('commission'), 180, '50%退款后 B 余额错误');
    $assertMoney((float)Db::name('member')->where('member_id', $memberA)->value('commission'), 40, '50%退款后 A 余额错误');
    $assert((string)ProjectCenterDistributionOrder::where('id', $orderId)->value('status') === 'partial_reversed', '部分退款后佣金单状态错误');
    $assert((int)ProjectCenterDistributionDebt::where('site_id', $siteId)->count() === 0, '余额充足的部分退款不应产生欠款');

    // 模拟会员已经使用/提现部分佣金：退款时只能先扣可用余额，不足部分形成可审计欠款。
    Db::name('member')->where('member_id', $memberB)->update(['commission' => 10]);
    Db::name('member')->where('member_id', $memberA)->update(['commission' => 0]);
    $distributionService->refundByApplication($siteId, $applicationId, 1000);
    $assertMoney((float)Db::name('member')->where('member_id', $memberB)->value('commission'), 0, '全额退款后 B 可用佣金必须扣至 0');
    $assertMoney((float)Db::name('member')->where('member_id', $memberA)->value('commission'), 0, '全额退款后 A 可用佣金必须保持 0');
    $debts = ProjectCenterDistributionDebt::where('site_id', $siteId)->order('member_id asc')->select()->toArray();
    $assert(count($debts) === 2, '余额不足时两级受益人应各生成一条欠款');
    $debtByMember = [];
    foreach ($debts as $debt) $debtByMember[(int)$debt['member_id']] = $debt;
    $assertMoney((float)$debtByMember[$memberB]['amount'], 170, 'B 欠款金额错误');
    $assertMoney((float)$debtByMember[$memberA]['amount'], 40, 'A 欠款金额错误');
    $assert((string)ProjectCenterDistributionOrder::where('id', $orderId)->value('status') === 'reversed', '全额退款后佣金单必须完全冲红');
    $pass('PC-DIST-REFUND-001', '部分退款、全额退款、余额冲红和余额不足欠款');

    $secondApplicationId = $application($now + 1, $now + 1);
    $secondOrderId = $distributionService->createForApprovedApplication($siteId, $secondApplicationId);
    $assert($distributionService->settleOrder($secondOrderId, true), '第二笔佣金结算失败');
    $assertMoney((float)Db::name('member')->where('member_id', $memberB)->value('commission'), 190, 'B 新佣金抵扣欠款后的净入账错误');
    $assertMoney((float)Db::name('member')->where('member_id', $memberA)->value('commission'), 40, 'A 新佣金抵扣欠款后的净入账错误');
    $assert((int)ProjectCenterDistributionDebt::where('site_id', $siteId)->where('status', 'pending')->count() === 0, '历史欠款必须被后续佣金自动抵清');
    $offsets = ProjectCenterDistributionDetail::where('order_id', $secondOrderId)->order('relation_level asc')->column('debt_offset_amount');
    $assertMoney((float)$offsets[0], 170, '一级后续佣金欠款抵扣金额错误');
    $assertMoney((float)$offsets[1], 40, '二级后续佣金欠款抵扣金额错误');
    $pass('PC-DIST-DEBT-001', '退款欠款由后续佣金按时间顺序自动抵扣');

    $refundApplicationId = $application($now + 2, $now + 2);
    $refundOrderId = $distributionService->createForApprovedApplication($siteId, $refundApplicationId);
    $refundId = (int)Db::name('project_center_refund')->insertGetId([
        'site_id' => $siteId,
        'refund_no' => 'PCRF-' . $suffix,
        'project_id' => $projectId,
        'group_id' => 990001,
        'application_id' => $refundApplicationId,
        'member_id' => $memberC,
        'amount' => 1000,
        'status' => 'pending',
        'origin_group_status' => 'active',
        'origin_application_status' => ProjectCenterDict::APPLICATION_APPROVED,
        'reason' => '退款补偿集成测试',
        'requested_at' => $now,
        'create_at' => $now,
        'update_at' => $now,
    ]);
    $assert($distributionService->reconcileRefunds(100, $siteId) === 1, '待退款补偿任务必须处理一条记录');
    $assert((string)ProjectCenterDistributionOrder::where('id', $refundOrderId)->value('status') === 'frozen', '待退款必须冻结佣金单');
    $assert((int)ProjectCenterDistributionDetail::where('order_id', $refundOrderId)->where('status', 'frozen')->count() === 2, '待退款必须冻结两级明细');

    Db::name('project_center_refund')->where('id', $refundId)->update(['status' => 'cancelled', 'update_at' => $now + 1]);
    $assert($distributionService->reconcileRefunds(100, $siteId) === 1, '取消退款补偿任务必须处理一条记录');
    $assert((string)ProjectCenterDistributionOrder::where('id', $refundOrderId)->value('status') === 'pending', '取消退款必须恢复佣金单');

    Db::name('project_center_refund')->where('id', $refundId)->update(['status' => 'refunded', 'update_at' => $now + 2]);
    $assert($distributionService->reconcileRefunds(100, $siteId) === 1, '完成退款补偿任务必须处理一条记录');
    $assert((string)ProjectCenterDistributionOrder::where('id', $refundOrderId)->value('status') === 'cancelled', '未结算佣金遇全额退款必须取消');
    $assertMoney((float)ProjectCenterDistributionOrder::where('id', $refundOrderId)->value('refund_amount'), 1000, '退款补偿必须同步退款金额');
    $pass('PC-DIST-JOB-001', '退款冻结、取消恢复和完成冲红的定时补偿');

    $historicalApplicationId = $application($enabledAt - 1, $enabledAt - 1);
    $historicalOrderId = $distributionService->createForApprovedApplication($siteId, $historicalApplicationId);
    $assert($historicalOrderId > 0, '历史工单应生成可审计的取消佣金单');
    $assert((string)ProjectCenterDistributionOrder::where('id', $historicalOrderId)->value('status') === 'cancelled', '启用分销前的历史工单不能发佣');
    $assert(str_contains((string)ProjectCenterDistributionOrder::where('id', $historicalOrderId)->value('error_message'), '不追溯发佣'), '历史工单必须明确记录不追溯原因');
    $assert((int)ProjectCenterDistributionDetail::where('order_id', $historicalOrderId)->count() === 0, '历史工单不能生成佣金明细');
    $pass('PC-DIST-HISTORY-001', '启用时间快照与历史工单不追溯');

    $reconcileApplicationId = $application($now + 3, $now + 3);
    $assert((int)ProjectCenterDistributionOrder::where('application_id', $reconcileApplicationId)->count() === 0, '补建测试前不应已有佣金单');
    $assert($distributionService->reconcileApproved(100, $siteId + 1) === 0, '补偿任务不得跨站点处理工单');
    $assert($distributionService->reconcileApproved(100, $siteId) === 1, '审核通过佣金单补建任务应成功补建一笔');
    $reconcileOrderId = (int)ProjectCenterDistributionOrder::where('application_id', $reconcileApplicationId)->value('id');
    $assert($reconcileOrderId > 0, '审核通过工单补建后仍未找到佣金单');
    ProjectCenterDistributionOrder::where('id', $reconcileOrderId)->update(['settle_at' => $now - 1, 'update_at' => $now]);
    ProjectCenterDistributionDetail::where('order_id', $reconcileOrderId)->update(['settle_at' => $now - 1, 'update_at' => $now]);
    $assert($distributionService->settleDue(100, $siteId + 1) === 0, '到期结算任务不得跨站点结算');
    $assert($distributionService->settleDue(100, $siteId) === 1, '到期结算任务应结算一笔佣金单');
    $assert((string)ProjectCenterDistributionOrder::where('id', $reconcileOrderId)->value('status') === 'settled', '到期结算后佣金单状态错误');
    $assertMoney((float)Db::name('member')->where('member_id', $memberB)->value('commission'), 550, '补偿结算后 B 余额错误');
    $assertMoney((float)Db::name('member')->where('member_id', $memberA)->value('commission'), 120, '补偿结算后 A 余额错误');
    $pass('PC-DIST-JOB-002', '审核补建、到期结算与任务站点范围隔离');

    // 项目可以完全不接入会员等级权益：只要是当前站点的正常会员，就能分享、绑定关系并按 100% 系数发佣。
    $allMemberProjectId = (int)Db::name('project_center_project')->insertGetId([
        'site_id' => $siteId,
        'project_no' => 'PCDIST-ALL-' . $suffix,
        'title' => '所有会员推广模式集成测试',
        'subtitle' => '不读取会员等级权益',
        'status' => ProjectCenterDict::PROJECT_ENABLED,
        'payment_amount' => 1000,
        'reviewer_uids' => '[]',
        'reviewer_role_ids' => '[]',
        'distribution_enabled' => 1,
        'config_json' => json_encode([
            'distribution' => [
                'eligibility_mode' => ProjectCenterDistributionRuleService::ELIGIBILITY_ALL_MEMBER,
                'commission_type' => 'fixed',
                'first_value' => 50,
                'second_value' => 20,
                'settle_days' => 7,
                'approval_requires_payment_check' => 1,
                'enabled_at' => $enabledAt,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'create_at' => $now,
        'update_at' => $now,
    ]);
    $allMemberProject = (array)Db::name('project_center_project')->where('id', $allMemberProjectId)->find();
    $allMemberCapability = $ruleService->memberCapability($siteId, $memberE, 1, $allMemberProject);
    $assert(!empty($allMemberCapability['eligible']), '所有会员模式不能再依赖会员等级权益');
    $assert((string)$allMemberCapability['eligibility_mode'] === ProjectCenterDistributionRuleService::ELIGIBILITY_ALL_MEMBER, '所有会员模式标识错误');
    $assertMoney((float)$allMemberCapability['coefficient'], 100, '所有会员模式佣金系数必须固定为 100%');

    $allMemberF = $member('所有会员模式推广人F', $levelNoBenefit);
    $allMemberG = $member('所有会员模式参与人G', $levelNoBenefit);
    $inviteE = $inviteService->create($siteId, $allMemberProjectId, $memberE);
    $assert(($inviteService->bind($siteId, $allMemberProjectId, $allMemberF, (string)$inviteE['share_code'])['state'] ?? '') === 'bound', '所有会员模式 E 邀请 F 绑定失败');
    $inviteF = $inviteService->create($siteId, $allMemberProjectId, $allMemberF);
    $assert(($inviteService->bind($siteId, $allMemberProjectId, $allMemberG, (string)$inviteF['share_code'])['state'] ?? '') === 'bound', '所有会员模式 F 邀请 G 绑定失败');

    $allMemberApplicationId = (int)Db::name('project_center_application')->insertGetId([
        'site_id' => $siteId,
        'application_no' => 'PCAPP-ALL-' . $suffix,
        'project_id' => $allMemberProjectId,
        'group_id' => 999991,
        'member_id' => $allMemberG,
        'idempotency_key' => 'pc-dist-all-member:' . $suffix,
        'form_id' => 0,
        'form_record_id' => 0,
        'submit_version' => 1,
        'status' => ProjectCenterDict::APPLICATION_APPROVED,
        'payment_declared_at' => $now,
        'payment_confirmed_at' => $now,
        'payment_confirmed_uid' => 1,
        'payment_confirmed_name' => '集成测试审核员',
        'submitted_at' => $now,
        'reviewed_at' => $now,
        'approved_at' => $now,
        'create_at' => $now,
        'update_at' => $now,
    ]);
    $allMemberOrderId = $distributionService->createForApprovedApplication($siteId, $allMemberApplicationId);
    $assert($allMemberOrderId > 0, '所有会员模式未生成佣金单');
    $allMemberDetails = ProjectCenterDistributionDetail::where('order_id', $allMemberOrderId)->order('relation_level asc')->select()->toArray();
    $assert(count($allMemberDetails) === 2, '所有会员模式完整 E→F→G 链路必须生成两级佣金');
    $assert((int)$allMemberDetails[0]['beneficiary_member_id'] === $allMemberF, '所有会员模式一级受益人必须为 F');
    $assert((int)$allMemberDetails[1]['beneficiary_member_id'] === $memberE, '所有会员模式二级受益人必须为 E');
    $assert((int)$allMemberDetails[0]['beneficiary_level_id'] === 0 && (string)$allMemberDetails[0]['beneficiary_level_name'] === '全部会员', '所有会员模式不能伪造会员等级快照');
    $assertMoney((float)$allMemberDetails[0]['coefficient'], 100, '所有会员模式一级系数错误');
    $assertMoney((float)$allMemberDetails[1]['coefficient'], 100, '所有会员模式二级系数错误');
    $assertMoney((float)$allMemberDetails[0]['commission_amount'], 50, '所有会员模式一级佣金错误');
    $assertMoney((float)$allMemberDetails[1]['commission_amount'], 20, '所有会员模式二级佣金错误');
    $pass('PC-DIST-ALL-MEMBER-001', '不接入会员等级时所有正常会员可分享、绑定并获得两级佣金');

    $summary = [
        'status' => 'PASS',
        'site_id' => $siteId,
        'rollback_only' => true,
        'case_count' => count($cases),
        'cases' => $cases,
        'fixed_commission_example' => ['level_1' => 360, 'level_2' => 80],
        'ratio_commission_example' => ['level_1' => 60, 'level_2' => 16],
    ];
} catch (Throwable $e) {
    $failure = $e;
} finally {
    Db::rollback();
}

// 外层事务回滚后再次核对，不允许测试会员、等级、工单、佣金或账户流水残留。
$cleanupTables = [
    'member', 'member_level', 'member_account_log', 'project_center_project',
    'project_center_application', 'project_center_refund', 'project_center_invite',
    'project_center_relation_log', 'project_center_distribution_order',
    'project_center_distribution_detail', 'project_center_distribution_debt',
];
$remaining = [];
foreach ($cleanupTables as $table) {
    $count = (int)Db::name($table)->where('site_id', $siteId)->count();
    if ($count > 0) $remaining[$table] = $count;
}
if ($remaining !== [] && $failure === null) {
    $failure = new RuntimeException('事务回滚后仍有测试数据残留：' . json_encode($remaining, JSON_UNESCAPED_UNICODE));
}

if ($failure !== null) {
    fwrite(STDERR, $failure::class . ': ' . $failure->getMessage() . "\n");
    exit(1);
}
$summary['cleanup_verified'] = true;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
