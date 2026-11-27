<?php
declare(strict_types=1);
// Static plugin-boundary contract; does not boot the framework or touch a database/network.
$plugin = dirname(__DIR__);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok)throw new RuntimeException($label);
    $checks++;
};
$srcHas = static fn (string $source, string $needle): bool => str_contains(preg_replace('/\s+/', '', $source), preg_replace('/\s+/', '', $needle));
$api = file_get_contents($plugin.'/app/api/route/promotion.php');
$admin = file_get_contents($plugin.'/app/adminapi/route/promotion.php');
$service = file_get_contents($plugin.'/app/service/cooperation/PromotionService.php');
$payment = file_get_contents($plugin.'/app/service/cooperation/PaymentService.php');
$check($srcHas($api, 'ApiCheckToken::class,true') && str_contains($api, 'ApiChannel::class'), 'member endpoints require authenticated same-channel identity');
$check(str_contains($admin, 'PermissionGate::class') && str_contains($admin, 'AdminCheckRole::class'), 'admin endpoints use both framework and plugin API permissions');
$check(str_contains($service, "ownership()->scope()['all_data']"), 'promotion administration requires all-data authority');
foreach (['config_save', 'providers_save', 'provider_review', 'hold', 'resume', 'settle'] as $key)$check(str_contains($service, "admin('".$key."')"), 'write service checks independent permission '.$key);
$check(str_contains($api, "Route::post('bind'") && !str_contains($api, "Route::get('bind'"), 'relationship writes are not hidden behind GET');
$check($srcHas($service, "->where('pid',0)->update(['pid'=>\$id])"), 'binding uses existing framework member.pid only when empty');
$check(str_contains($service, '循环') && str_contains($service, 'can_bind'), 'binding protects confirmation and cycles');
$check($srcHas($service, "$"."source==='membership'?MembershipCostService::promotionBase(\$order):Rules::cents((string)\$order['fee_amount'])"), 'commission maintenance rewards use only fee; membership rewards delegate to service-only cost base');
$cost = file_get_contents($plugin.'/app/service/cooperation/MembershipCostService.php');
$check(str_contains($cost, "return Rules::cents(\$rule['service_amount'])"), 'membership deposit and purchased experience cannot become referral reward base');
$check(str_contains($payment, 'PromotionService::recordPaid') && str_contains($payment, 'PayDict::STATUS_FINISH'), 'source callback integrates after verified framework payment');
$check(str_contains($service, 'CoreMemberAccountService())->addLog') && !preg_match('/function\s+withdraw\s*\(/', $service), 'reward credit uses framework commission and adds no independent withdrawal API');
$check(str_contains($service, 'MemberAccountTypeDict::COMMISSION') && str_contains($service, 'account_log_id'), 'settlement retains framework ledger reference');
$check($srcHas($service, 'RefundDict::WAIT,RefundDict::DEALING,RefundDict::SUCCESS'), 'pending and successful refunds prevent new award credits');
$check($srcHas($service, "\$row['status']!=='held'") && str_contains($service, '已有账本记录'), 'resume rejects already-settled/recorded credits');
$check($srcHas($service, "'status'=>'pending','review_required'=>0") && str_contains($service, "'resumed'"), 'resume is an auditable state change, not another account credit');
$check(str_contains(file_get_contents($plugin.'/app/event.php'), 'PromotionRefundSuccess'), 'refund listener registered through framework hook');
$addonSource = file_get_contents($plugin.'/Addon.php');
$check(str_contains($addonSource, "'hsx_lead_promotion_settle'") && str_contains($addonSource, 'ScheduleDict::OFF') && str_contains(file_get_contents($plugin.'/app/dict/schedule/schedule.php'), 'hsx_lead_promotion_settle'), 'separate scheduled settlement is registered and preserved/default-off');
$sql = file_get_contents($plugin.'/sql/install.sql');
$check(substr_count($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_lead_promotion_reward`') === 1, 'one new reward audit table only');
$check(str_contains($sql, 'UNIQUE KEY `uk_site_order_level` (`site_id`,`coop_order_id`,`commission_level`)'), 'database enforces per-site per-order per-level source idempotency');
$check(!str_contains($sql, 'hsx_lead_wallet') && !str_contains($sql, 'hsx_lead_withdrawal'), 'no new wallet or withdrawal table');
$menus = require $plugin.'/app/dict/menu/site.php';
$keys = [];
$walk = function (array $rows) use (&$walk, &$keys): void {
    foreach ($rows as $row) {
        $keys[$row['menu_key']] = $row;
        if (!empty($row['children']))$walk($row['children']);
    }
};
$walk($menus);
foreach (['manage', 'rewards', 'config', 'config_save', 'providers', 'providers_save', 'provider_review', 'settle', 'retry', 'hold', 'resume'] as $key)$check(isset($keys['hsx_lead_promotion_'.$key]), 'explicit menu/API permission '.$key);
$check(str_contains($api, "Route::get('application'") && str_contains($api, "Route::post('application'"), 'application status is readable and submission is an explicit POST');
$check(str_contains($admin, "Route::post('providers/:id/review'") && str_contains($admin, "Route::post('orders/:id/retry'"), 'approval and recovery are separate authenticated writes');
$check(str_contains($service, 'promotion_snapshot') && str_contains($service, 'promoter_role') && str_contains($service, 'assertProviderVersion'), 'paid role/rate and reviewer versions are explicit snapshots');
$check($srcHas($service, "if (\$admin) \$page['payment_reviews']"), 'payment recovery details are absent from the ordinary member reward response');
$check(($keys['hsx_lead_promotion_manage']['view_path'] ?? '') === 'promotion/index', 'platform navigation points to the actual promoted UI');
echo 'PASS '.$checks." promotion API/permission/schema contracts; no application boot or network.\n";
