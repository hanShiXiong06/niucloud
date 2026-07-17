<?php
declare(strict_types=1);

if (getenv('HSX_MEMBER_CARD_INTEGRATION') !== '1') {
    fwrite(STDERR, "Set HSX_MEMBER_CARD_INTEGRATION=1 to run the rollback-only database integration test.\n");
    exit(2);
}

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_member_card\app\listener\MemberCardBusinessSources;
use addon\hsx_member_card\app\listener\MemberCardFinanceCategories;
use addon\hsx_member_card\app\service\admin\MemberCardOrderService;
use addon\hsx_member_card\app\service\admin\MemberCardMemberService;
use addon\hsx_member_card\app\service\admin\MemberCardProductService;
use addon\hsx_member_card\app\service\admin\MemberCardRedemptionService;
use think\facade\Db;
use think\facade\Event;

$app = new think\App();
$app->initialize();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

// 本测试始终运行在外层事务内并最终回滚，不污染会员、ERP 往来或财务事实。
$member = Db::name('member')->alias('m')
    ->join('site s', 's.site_id = m.site_id')
    ->whereLike('m.mobile', '1__________')
    ->where('m.nickname', '<>', '')
    ->field('m.site_id,m.member_id,m.nickname,m.mobile')
    ->order('m.site_id desc,m.member_id desc')
    ->find();
$assert(is_array($member) && (int)($member['site_id'] ?? 0) > 0, '没有可用于集成测试的实名手机号会员');

request()->siteId((int)$member['site_id']);
request()->uid(1);
request()->username('member-card-integration');
Event::listen('HsxErpFinanceCategories', MemberCardFinanceCategories::class);
Event::listen('HsxErpBusinessSourceOptions', MemberCardBusinessSources::class);

Db::startTrans();
$result = null;
$failure = null;
try {
    $productService = new MemberCardProductService();
    $productId = $productService->save([
        'product_name' => '集成测试贴膜10次卡',
        'sale_price' => '100.00',
        'market_price' => '199.00',
        'effective_mode' => 'immediate',
        'validity_mode' => 'duration',
        'duration_value' => 30,
        'duration_unit' => 'day',
        'usage_notice' => '仅用于事务回滚集成测试',
        'item' => [
            'item_code' => 'film_service',
            'item_name' => '贴膜服务',
            'usage_mode' => 'limited',
            'total_times' => 10,
            'recognition_mode' => 'average',
        ],
    ]);
    $productService->setStatus($productId, 'enabled');

    $testMember = (new MemberCardMemberService())->quickCreate([
        'request_id' => 'integration:member:' . bin2hex(random_bytes(8)),
        'name' => '会员卡集成测试客户',
        'mobile' => '199' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
    ]);
    $assert(($testMember['created'] ?? false) === true, '快速创建客户未创建新会员');
    $initialPasswordHash = (string)Db::name('member')->where('site_id', (int)$member['site_id'])
        ->where('member_id', (int)$testMember['member_id'])->value('password');
    $assert(check_password(substr((string)$testMember['mobile'], -6), $initialPasswordHash), '快速创建客户默认密码必须为手机号后六位');

    $order = (new MemberCardOrderService())->create([
        'request_id' => 'integration:issue:' . bin2hex(random_bytes(8)),
        'member_id' => (int)$testMember['member_id'],
        'product_id' => $productId,
        'settlement_mode' => 'receivable',
        'remark' => 'rollback-only integration',
    ]);
    $assert(($order['success'] ?? false) === true, '挂账开卡未成功：' . (string)($order['last_error'] ?? ''));
    $assert((string)($order['finance_status'] ?? '') === 'pending', '挂账开卡应生成待收 ERP 应收');

    $redemptionService = new MemberCardRedemptionService();
    $search = $redemptionService->search([
        'mobile_keyword' => substr((string)$testMember['mobile'], -4),
        'name' => (string)$testMember['member_name'],
    ]);
    $candidate = (array)($search['candidates'][0] ?? []);
    $card = (array)($candidate['cards'][0] ?? []);
    $assert((int)($card['card_id'] ?? 0) === (int)$order['card_id'], '手机号后四位与姓名未查到刚开出的卡');
    $assert(($card['available'] ?? false) === true, '默认允许未结清卡核销时，新卡应可用');

    $redeem = $redemptionService->redeem((int)$card['card_id'], [
        'request_id' => 'integration:redeem:' . bin2hex(random_bytes(8)),
        'card_item_id' => (int)$card['item_id'],
        'verification_confirmed' => 1,
        'remark' => 'integration redeem',
    ]);
    $assert((int)$redeem['before_remaining'] === 10 && (int)$redeem['after_remaining'] === 9, '核销必须固定扣减1次');

    $reverse = $redemptionService->reverse((int)$redeem['redeem_id'], [
        'request_id' => 'integration:reverse:' . bin2hex(random_bytes(8)),
        'reason' => '集成测试核销冲正',
    ]);
    $assert((string)$reverse['redemption_status'] === 'reversed', '核销冲正失败');

    $cancel = (new MemberCardOrderService())->cancel((int)$order['order_id'], '集成测试取消开卡');
    $assert((string)$cancel['business_status'] === 'cancelled', '未收款且核销已冲正的开卡单应允许取消');
    $result = [
        'site_id' => (int)$member['site_id'],
        'member_quick_created' => true,
        'product_created' => true,
        'receivable_created' => true,
        'mobile_last4_search' => true,
        'redeem_once' => true,
        'reverse_once' => true,
        'cancel_and_finance_void' => true,
        'rolled_back' => true,
    ];
} catch (\Throwable $e) {
    $failure = $e;
} finally {
    Db::rollback();
}

if ($failure !== null) {
    fwrite(STDERR, $failure::class . ': ' . $failure->getMessage() . "\n");
    exit(1);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
