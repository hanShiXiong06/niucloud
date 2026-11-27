<?php
declare(strict_types=1);

/** Local audit accounts only. No merchant, membership, pay fact, lead, ERP or site-setting seeding. */
if (PHP_SAPI !== 'cli' || count($argv) > 2 || !in_array($argv[1] ?? '--check', ['--check', '--apply'], true)) {
    exit("Usage: php seed-h5-audit-20261106.php [--check|--apply]\n");
}
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable external network functions before local seeding\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before local seeding\n");
error_reporting(E_ALL & ~E_DEPRECATED);
$site = 100005;
$manifest = '/private/tmp/hsx-lead-h5-audit-credentials-20261106.json';
$apply = ($argv[1] ?? '--check') === '--apply';
try {
    $root = dirname(__DIR__, 3);
    require $root.'/vendor/autoload.php';
    $app = new think\App($root);
    $app->loadEnv();
    require $app->getThinkPath().'helper.php';
    require $root.'/app/common.php';
    $database = require $root.'/config/database.php';
    $connection = $database['connections'][$database['default']] ?? [];
    if (($connection['type'] ?? '') !== 'mysql' || !in_array($connection['hostname'] ?? '', ['localhost', '127.0.0.1'], true) || !empty($connection['deploy'])) throw new RuntimeException('LOCALHOST_ONLY');
    $app->env->set('queue.state', false);
    $app->initialize();
    $request = new app\Request();
    $request->siteId($site);
    $request->memberId(0);
    $request->appType('api');
    $request->withHeader(['site-id' => (string) $site, 'channel' => 'h5']);
    $app->instance('request', $request);
    $db = think\facade\Db::connect();
    $status = think\facade\Db::name('site')->where('site_id', $site)->value('status');
    if ((int) $status !== 1) throw new RuntimeException('ACTIVE_TEST_SITE_REQUIRED');
    $notices = think\facade\Db::name('sys_notice')->where('site_id', $site)->whereIn('key', ['hsx_lead_task_assigned', 'hsx_lead_task_changed'])->field('key,is_weapp,is_wechat,is_sms')->select()->toArray();
    if (count($notices) !== 2) throw new RuntimeException('NOTICE_PREFLIGHT_INCOMPLETE');
    foreach ($notices as $row) if ((int) $row['is_weapp'] || (int) $row['is_wechat'] || (int) $row['is_sms']) throw new RuntimeException('NOTICE_CHANNELS_MUST_ALREADY_BE_OFF');
    $enabled = think\facade\Db::name('pay_channel')->where('site_id', $site)->where('channel', 'h5')->where('status', 1)->column('type');
    if ($enabled !== ['balancepay']) throw new RuntimeException('ONLY_LOCAL_TEST_BALANCE_MAY_BE_ENABLED');
    $schedules = think\facade\Db::name('sys_schedule')->where('addon', 'hsx_lead')->field('key,status')->select()->toArray();
    foreach ($schedules as $row) if ((int) $row['status'] !== 2) throw new RuntimeException('PLUGIN_SCHEDULES_MUST_ALREADY_BE_OFF');
    $configuration = think\facade\Db::name('sys_config')->where('site_id', $site)->where('config_key', 'HSX_LEAD_COOPERATION')->value('value');
    $configuration = is_array($configuration) ? $configuration : (json_decode((string) $configuration, true) ?: []);
    $tiers = array_map(static function (array $row): array { return array_intersect_key($row, array_flip(['code', 'name', 'enabled', 'account_limit', 'unlimited', 'month_price', 'year_price'])); }, $configuration['membership_levels'] ?? []);
    if (!$apply) {
        echo json_encode(['checked' => true, 'writes' => 0, 'site_id' => $site, 'existing_private_manifest' => is_file($manifest), 'membership_levels' => $tiers, 'plan' => ['accounts' => 4, 'merchants' => 0, 'balance_each' => '500.00 synthetic / not received', 'no_existing_password_changes' => true, 'no_config_changes' => true, 'no_erp' => true, 'no_notifications' => true, 'no_pay_facts' => true]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }
    if (is_file($manifest)) {
        if (is_link($manifest) || (fileperms($manifest) & 0777) !== 0600) throw new RuntimeException('PRIVATE_MANIFEST_PERMISSIONS_MISMATCH');
        $previous = json_decode((string) file_get_contents($manifest), true, 64, JSON_THROW_ON_ERROR);
        if (($previous['site_id'] ?? 0) !== $site || ($previous['state'] ?? '') !== 'complete' || count($previous['accounts'] ?? []) !== 4) throw new RuntimeException('EXISTING_MANIFEST_REQUIRES_REVIEW_NO_RESEED');
        foreach ($previous['accounts'] as $account) {
            $row = think\facade\Db::name('member')->where('site_id', $site)->where('member_id', (int) $account['member_id'])->field('username,status,mobile,wx_openid,weapp_openid')->find();
            if (!$row || $row['username'] !== $account['username'] || (int) $row['status'] !== 1 || $row['mobile'] !== '' || $row['wx_openid'] !== '' || $row['weapp_openid'] !== '') throw new RuntimeException('OWNED_ACCOUNT_IDENTITY_CHANGED_NO_PASSWORD_RESET');
        }
        echo json_encode(['unchanged' => true, 'site_id' => $site, 'accounts' => array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['role', 'member_id', 'username'])), $previous['accounts']), 'private_manifest' => $manifest], JSON_UNESCAPED_UNICODE),PHP_EOL;
        exit(0);
    }
    $old = umask(0077);
    $handle = fopen($manifest, 'x');
    if (!$handle) throw new RuntimeException('PRIVATE_MANIFEST_CREATION_FAILED');
    chmod($manifest, 0600);
    $state = ['state' => 'planned', 'site_id' => $site, 'run_id' => 'h5-audit-20261106-'.bin2hex(random_bytes(5)), 'created_at' => date(DATE_ATOM), 'accounts' => [], 'synthetic_balance' => true];
    foreach (['publisher' => '发布门店A', 'receiver' => '承接门店B', 'colleague' => '门店同事', 'outsider' => '未入驻账号'] as $role => $title) {
        $state['accounts'][] = ['role' => $role, 'username' => 'qa_lead_20261106_'.$role.'_'.bin2hex(random_bytes(2)), 'nickname' => '【H5走查】'.$title, 'password' => bin2hex(random_bytes(10))];
    }
    fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    fclose($handle);
    umask($old);
    $fingerprint = static function (array $ids) use ($site): array {
        return think\facade\Db::name('member')->where('site_id', $site)->whereIn('member_id', $ids)->field('member_id,balance,balance_get,money,money_get,commission,commission_get,commission_cash_outing')->order('member_id')->select()->toArray();
    };
    $oldIds = think\facade\Db::name('member')->where('site_id', $site)->order('member_id')->column('member_id');
    $before = $fingerprint($oldIds);
    $configHash = hash('sha256', json_encode(think\facade\Db::name('sys_config')->where('site_id', $site)->order('config_key')->select()->toArray()));
    think\facade\Db::transaction(function () use (&$state, $site): void {
        think\facade\Db::name('site')->where('site_id', $site)->lock(true)->find();
        foreach ($state['accounts'] as &$account) {
            if (think\facade\Db::name('member')->where('site_id', $site)->where('username', $account['username'])->count())throw new RuntimeException('OWNED_USERNAME_ALREADY_EXISTS_NO_OVERWRITE');
            $id = (new app\service\api\member\MemberService())->add(['site_id' => $site, 'username' => $account['username'], 'nickname' => $account['nickname'], 'password' => create_password($account['password']), 'mobile' => '', 'wx_openid' => '', 'weapp_openid' => '', 'pid' => 0, 'status' => 1, 'is_del' => 0, 'delete_time' => 0, 'create_time' => time(), 'register_channel' => 'h5', 'register_type' => 'username']);
            if ($id <= 0)throw new RuntimeException('MEMBER_CREATION_FAILED');
            $account['member_id'] = $id;
            $account['balance_log_id'] = (new app\service\core\member\CoreMemberAccountService())->addLog($site, $id, app\dict\member\MemberAccountTypeDict::BALANCE, '500.00', 'adjust', '20261106 H5本地合成走查余额，非实际收款');
        }unset($account);
    });
    $state['existing_member_money_unchanged'] = $fingerprint($oldIds) === $before;
    $state['site_config_unchanged'] = $configHash === hash('sha256', json_encode(think\facade\Db::name('sys_config')->where('site_id', $site)->order('config_key')->select()->toArray()));
    if (!$state['existing_member_money_unchanged'] || !$state['site_config_unchanged'])throw new RuntimeException('UNEXPECTED_EXISTING_STATE_CHANGE_REVIEW_REQUIRED');
    $state['state'] = 'complete';
    $state['finished_at'] = date(DATE_ATOM);
    $handle = fopen($manifest, 'c');
    if (!$handle || !flock($handle, LOCK_EX))throw new RuntimeException('PRIVATE_MANIFEST_LOCK_FAILED');
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    chmod($manifest, 0600);
    echo json_encode(['created' => true, 'site_id' => $site, 'accounts' => array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['role', 'member_id', 'username', 'balance_log_id'])), $state['accounts']), 'private_manifest' => $manifest, 'existing_member_money_unchanged' => true, 'site_config_unchanged' => true, 'no_merchants_precreated' => true, 'no_pay_facts' => true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Local H5 fixture preparation failed: '.get_class($error).' / '.($error instanceof RuntimeException ? $error->getMessage() : 'details suppressed').' @ '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
    exit(1);
}
