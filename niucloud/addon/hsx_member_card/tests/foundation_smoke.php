<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = new think\App();
$app->initialize();

use addon\hsx_member_card\app\listener\MemberCardBusinessSources;
use addon\hsx_member_card\app\listener\MemberCardFinanceCategories;
use addon\hsx_member_card\app\support\MemberCardIdempotency;
use addon\hsx_member_card\app\support\MemberCardMigrationCipher;
use addon\hsx_member_card\app\support\MemberCardMoney;
use addon\hsx_member_card\app\support\MemberCardValidity;
use core\exception\CommonException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$assert(MemberCardMoney::normalize('9.9') === '9.90', '金额必须规范为两位小数');
$assert(MemberCardMoney::divide('100.00', 10) === '10.00', '平均耗卡金额必须使用定点数计算');
$assert(MemberCardIdempotency::child('mc-issue-001', 'finance') === 'mc-issue-001:finance', '子幂等键必须稳定可重放');
$invalidRequestRejected = false;
try { MemberCardIdempotency::normalize('bad request id'); } catch (CommonException) { $invalidRequestRejected = true; }
$assert($invalidRequestRejected, '含空格的request_id必须拒绝');

$permanent = MemberCardValidity::calculate(['effective_mode' => 'immediate', 'validity_mode' => 'permanent'], 1784160000);
$assert($permanent['valid_start_at'] === 1784160000 && $permanent['valid_end_at'] === 0, '立即生效永久卡有效期计算错误');
$firstUsePending = MemberCardValidity::calculate([
    'effective_mode' => 'first_use', 'validity_mode' => 'duration', 'duration_value' => 30, 'duration_unit' => 'day',
], 1784160000);
$assert($firstUsePending === ['valid_start_at' => 0, 'valid_end_at' => 0, 'activated_at' => 0], '首次使用生效卡开卡时不能提前计时');
$firstUse = MemberCardValidity::calculate([
    'effective_mode' => 'first_use', 'validity_mode' => 'duration', 'duration_value' => 30, 'duration_unit' => 'day',
], 1784160000, 1784246400);
$assert($firstUse['valid_end_at'] === 1784246400 + 30 * 86400, '首次核销后的固定天数有效期计算错误');

$categories = (new MemberCardFinanceCategories())->handle();
$categoryMap = array_column($categories, null, 'key');
$assert(($categoryMap['hsx_member_card.card_sale']['statement_group'] ?? '') === 'advance_receipt', '售卡必须进入会员卡预收分组');
$assert(($categoryMap['hsx_member_card.card_refund']['statement_group'] ?? '') === 'advance_receipt_reversal', '退款必须进入预收冲回分组');
$sources = (new MemberCardBusinessSources())->handle();
$assert(($sources[0]['source_plugin'] ?? '') === 'hsx_member_card', '业务来源必须固化插件归属');

$installSql = (string)file_get_contents(dirname(__DIR__) . '/sql/install.sql');
$tables = [
    'member_card_product', 'member_card_product_item', 'member_card_order', 'member_card_card',
    'member_card_card_item', 'member_card_redemption', 'member_card_refund', 'member_card_finance_link',
    'member_card_staff_fact', 'member_card_operation_log', 'member_card_inbox_event', 'member_card_outbox_event',
    'member_card_migration_task', 'member_card_migration_item', 'member_card_external_binding',
];
foreach ($tables as $table) $assert(str_contains($installSql, '{{prefix}}' . $table), "安装SQL缺少{$table}");
$assert(substr_count($installSql, '`site_id` int NOT NULL DEFAULT 0') >= count($tables), '所有业务表必须具备站点隔离字段');
$assert(!str_contains($installSql, '`request_id` varchar(80) NOT NULL DEFAULT \'\''), '幂等请求字段禁止默认空字符串');
$assert(str_contains($installSql, 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)'), '开卡与核销必须使用站点级请求幂等索引');
$assert(str_contains($installSql, 'holder_mobile_last4'), '会员卡必须建立手机号后四位检索字段');
$assert(str_contains($installSql, 'UNIQUE KEY `uk_site_source_entity` (`site_id`,`source_key`,`entity_type`,`external_id`)'), '第三方数据映射必须使用站点级唯一索引');

$credential = '{"token_name":"recovery-token","token_value":"migration-test-token"}';
$encryptedCredential = MemberCardMigrationCipher::encrypt($credential);
$assert($encryptedCredential !== $credential && !str_contains($encryptedCredential, 'migration-test-token'), '旧平台Token必须加密后才能落库');
$assert(MemberCardMigrationCipher::decrypt($encryptedCredential) === $credential, '迁移Token加密后必须能够由队列安全读取');

$eventConfig = require dirname(__DIR__) . '/app/event.php';
$assert(isset($eventConfig['listen']['HsxErpFinanceCategories']), '插件必须向ERP提供动态财务分类');
$assert(isset($eventConfig['listen']['HsxErpBusinessSourceOptions']), '插件必须向ERP提供业务来源');
$assert(isset($eventConfig['listen']['HsxErpFinanceDisplayRows']), '插件必须为升级前财务事实提供只读展示投影');
$assert(isset($eventConfig['listen']['ErpDomainEvent']), '插件必须消费ERP结算完成回调');
$assert(isset($eventConfig['listen']['NoticeData']), '会员卡核销结果必须接入牛云官方通知数据事件');

$serviceDir = dirname(__DIR__) . '/app/service';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($serviceDir));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $source = (string)file_get_contents($file->getPathname());
    $assert(!str_contains($source, 'addon\\hsx_erp\\'), '会员卡插件不能直接引用ERP具体类：' . $file->getFilename());
    $assert(!preg_match('/erp_(receivable|payable|settlement|money_ledger)/', $source), '会员卡插件不能直接读写ERP财务表：' . $file->getFilename());
}

$orderSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/MemberCardOrderService.php');
$assert(str_contains($orderSource, 'runFinance') && str_contains($orderSource, 'createLocalOrder'), '开卡必须由后端Saga一次编排本地订单和ERP财务');
$assert(str_contains($orderSource, "'verification_confirmed'") === false, '开卡服务不应混入核销身份确认');
$redemptionSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/MemberCardRedemptionService.php');
$assert(str_contains($redemptionSource, "->lock(true)") && str_contains($redemptionSource, "'verification_confirmed'"), '核销必须使用行锁并强制姓名人工核验确认');
$assert(str_contains($redemptionSource, "'status' => 'reversed'"), '核销撤销必须冲正原记录，不能删除');
$assert(substr_count($redemptionSource, 'MemberCardMoney::subtract($item->recognized_amount, $redemption->recognized_amount)') === 1, '核销冲正必须且只能在恢复权益时扣回确认收入');
$financeSyncSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/MemberCardFinanceSyncService.php');
$assert(str_contains($financeSyncSource, "':order:' . (int)\$order['id']") && str_contains($financeSyncSource, "':refund:' . (int)\$refund['id']"), '一笔结算关联多张单时，员工事实幂等键必须精确到业务单');
$assert(str_contains($financeSyncSource, "['_event_applied_amount']"), '结算绩效金额必须使用本次分配金额，不能重复使用整笔结算总额');

$legacyClientSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/core/MemberCardLegacyClient.php');
$assert(str_contains($legacyClientSource, "'/member/login'"), '期初迁移必须通过旧平台登录接口换取Token');
$assert(str_contains($legacyClientSource, "'/card/queryPage'"), '期初迁移必须分页读取旧平台卡项');
$assert(str_contains($legacyClientSource, "'/customer/queryPage'"), '期初迁移必须分页读取客户及持卡余额');
$migrationSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/MemberCardMigrationService.php');
$assert(!str_contains($migrationSource, "'password' => create_password(substr(\$mobile, -6))"), '期初迁移新账号密码必须遵循123456约定');
$assert(str_contains($migrationSource, "'password' => create_password('123456')"), '期初迁移必须为新账号设置约定初始密码');
$assert(str_contains($migrationSource, "'settlement_mode' => 'opening'"), '期初持卡余额必须标记为期初单据，不能作为新收款');
$assert(!str_contains($installSql, '`source_password`'), '迁移任务表禁止保存旧平台密码');

$apiRouteSource = (string)file_get_contents(dirname(__DIR__) . '/app/api/route/route.php');
foreach (['overview', 'cards', 'orders', 'redemptions'] as $memberRoute) {
    $assert(str_contains($apiRouteSource, "'{$memberRoute}'"), "用户端会员卡接口缺少{$memberRoute}路由");
}
$assert(str_contains($apiRouteSource, 'ApiCheckToken::class, true'), '用户端会员卡记录必须登录后才能查看');
$portalSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/api/MemberCardPortalService.php');
$assert(str_contains($portalSource, "['member_id', '=', (int)\$this->member_id]"), '用户端会员卡查询必须按当前登录会员隔离');
$assert(!str_contains($portalSource, "->order('sort asc,id asc')"), '持卡权益表没有sort字段，用户端查询不能引用不存在字段');
$noticeServiceSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/core/MemberCardNoticeService.php');
$assert(str_contains($noticeServiceSource, 'NoticeService::send'), '核销通知必须调用牛云官方NoticeService');
$assert(str_contains($noticeServiceSource, 'catch (\\Throwable $e)'), '通知失败不能影响已完成的核销事务');
$memberPages = (string)file_get_contents(dirname(__DIR__) . '/package/uni-app-pages.php');
$assert(str_contains($memberPages, 'pages/member/index') && str_contains($memberPages, 'pages/member/detail'), '用户端必须提供会员卡列表与详情页面');

echo "HSX member card foundation smoke passed.\n";
