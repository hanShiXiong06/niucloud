<?php
declare(strict_types=1);

use addon\hsx_lead\app\service\cooperation\EntityMembershipService;

/** One-off removal of retired entitlement TEST facts. Never called by an HTTP request. */
if (PHP_SAPI !== 'cli') exit(1);
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--check', '--apply'], true) || ($mode === '--check' && count($argv) !== 2)
    || ($mode === '--apply' && (count($argv) !== 3 || ($argv[2] ?? '') !== '--confirm-all-plugin-data-is-test'))) {
    exit("php addon/hsx_lead/scripts/clean-retired-membership.php --check\nphp addon/hsx_lead/scripts/clean-retired-membership.php --apply --confirm-all-plugin-data-is-test\n");
}
error_reporting(E_ALL & ~E_DEPRECATED);
$pdo = null;
try {
    $backend = dirname(__DIR__, 3);
    require $backend . '/vendor/autoload.php';
    $app = new think\App($backend); $app->loadEnv(); require $app->getThinkPath() . 'helper.php';
    $database = require $backend . '/config/database.php'; $connection = $database['connections'][$database['default']];
    if (($connection['type'] ?? '') !== 'mysql' || !in_array($connection['hostname'] ?? '', ['localhost', '127.0.0.1'], true) || !empty($connection['deploy'])) throw new RuntimeException('仅允许本机测试数据库，不接受远程数据库');
    $prefix = (string)($connection['prefix'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) throw new RuntimeException('表前缀不合法');
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . $connection['hostport'] . ';dbname=' . $connection['database'] . ';charset=utf8mb4', $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $query = static function (string $sql, array $params = []) use ($pdo): array { $statement = $pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll(PDO::FETCH_ASSOC); };
    $table = static fn(string $name): string => '`' . $prefix . $name . '`';
    $isRetired = static function(array $order): bool {
        $rule = json_decode((string)$order['rule_json'], true) ?: [];
        return in_array($order['purpose'], ['publishing', 'certification'], true) || ($order['purpose'] === 'membership' && ($rule['schema_version'] ?? '') !== EntityMembershipService::SNAPSHOT_SCHEMA);
    };
    $canonical = static function(array $rows): array { $unique = []; foreach ($rows as $row) $unique[hash('sha256', json_encode($row, JSON_THROW_ON_ERROR))] = $row; ksort($unique, SORT_STRING); return array_values($unique); };
    $retired = [];
    foreach ($query('SELECT * FROM ' . $table('hsx_lead_coop_order') . ' ORDER BY site_id,id') as $order) {
        if ($isRetired($order)) $retired[] = $order;
    }
    $columns = $query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$prefix . 'hsx_lead_partner']);
    $drops = array_values(array_intersect(['grade_mode', 'grade_id', 'certification_status', 'cert_expires_at', 'publisher_until'], array_column($columns, 'COLUMN_NAME')));
    // Reuse the same complete predicates for backup and locked recheck. Checking only
    // already-known IDs would miss a refund or ledger row inserted after the backup.
    $related = static function(array $orders, bool $lock = false) use ($query, $table, $canonical): array {
        $rows = ['pay'=>[], 'pay_refund'=>[], 'promotion_reward'=>[], 'member_account_log'=>[], 'site_account_log'=>[], 'audits'=>[]];
        $suffix = $lock ? ' FOR UPDATE' : '';
        foreach ($orders as $order) {
            $site = (int)$order['site_id']; $id = (int)$order['id'];
            $rows['pay'] = array_merge($rows['pay'], $query('SELECT * FROM ' . $table('pay') . ' WHERE site_id=? AND trade_type=? AND trade_id=?' . $suffix, [$site, 'hsx_lead_cooperation', $id]));
            $rows['promotion_reward'] = array_merge($rows['promotion_reward'], $query('SELECT * FROM ' . $table('hsx_lead_promotion_reward') . ' WHERE site_id=? AND coop_order_id=?' . $suffix, [$site, $id]));
            $rows['audits'] = array_merge($rows['audits'], $query('SELECT * FROM ' . $table('hsx_lead_audit') . ' WHERE site_id=? AND entity_type=? AND entity_id=?' . $suffix, [$site, 'coop_order', $id]));
        }
        foreach ($rows['pay'] as $pay) {
            $rows['pay_refund'] = array_merge($rows['pay_refund'], $query('SELECT * FROM ' . $table('pay_refund') . ' WHERE site_id=? AND out_trade_no=?' . $suffix, [$pay['site_id'], $pay['out_trade_no']]));
            $rows['site_account_log'] = array_merge($rows['site_account_log'], $query('SELECT * FROM ' . $table('site_account_log') . ' WHERE site_id=? AND trade_no=?' . $suffix, [$pay['site_id'], $pay['out_trade_no']]));
            $rows['member_account_log'] = array_merge($rows['member_account_log'], $query('SELECT * FROM ' . $table('member_account_log') . ' WHERE site_id=? AND related_id=? AND from_type=?' . $suffix, [$pay['site_id'], $pay['out_trade_no'], 'order']));
        }
        foreach ($rows['promotion_reward'] as $reward) $rows['member_account_log'] = array_merge($rows['member_account_log'], $query('SELECT * FROM ' . $table('member_account_log') . ' WHERE site_id=? AND from_type=? AND related_id=?' . $suffix, [$reward['site_id'], 'hsx_lead_promotion_income', $reward['id']]));
        foreach ($rows as $key=>$items) $rows[$key] = $canonical($items);
        return $rows;
    };
    $snapshot = ['orders' => $retired] + $related($retired) + ['config' => [], 'partners' => [], 'members' => [], 'balance_ledgers' => []];
    $snapshot['config'] = $query('SELECT * FROM ' . $table('sys_config') . ' WHERE config_key IN (?,?)', ['HSX_LEAD_COOPERATION', 'HSX_LEAD_PROMOTION']);
    $snapshot['partners'] = $query('SELECT * FROM ' . $table('hsx_lead_partner') . ' ORDER BY site_id,id');
    foreach ($snapshot as $key => $rows) $snapshot[$key] = $canonical($rows);
    $blockers = []; $restore = [];
    $cents = static function ($value): int {
        if (!preg_match('/^(-?)([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D', (string)$value, $match)) throw new RuntimeException('资金精度异常，已停止');
        $amount = (int)$match[2] * 100 + (int)str_pad($match[3] ?? '', 2, '0'); return $match[1] === '-' ? -$amount : $amount;
    };
    $amount = static fn(int $value): string => intdiv($value, 100) . '.' . str_pad((string)($value % 100), 2, '0', STR_PAD_LEFT);
    foreach ($query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()') as $engine) {
        if (in_array($engine['TABLE_NAME'], array_map(static fn(string $name): string => $prefix . $name, ['hsx_lead_coop_order','pay','pay_refund','hsx_lead_promotion_reward','member_account_log','site_account_log','sys_config','hsx_lead_partner','hsx_lead_audit','member','site']), true) && $engine['ENGINE'] !== 'InnoDB') $blockers[] = '有关数据表不支持事务，不能执行可回滚清理';
    }
    // Strictly reversible local balance-only test purchases. Real external funds, refunds,
    // settled rewards or unrelated later ledger entries deliberately require manual review.
    $orderFacts = []; foreach ($retired as $order) $orderFacts[$order['site_id'] . ':' . $order['id']] = $order;
    foreach ($snapshot['pay'] as $pay) {
        $memberLogs = array_values(array_filter($snapshot['member_account_log'], static fn(array $row): bool => (int)$row['site_id'] === (int)$pay['site_id'] && (string)$row['related_id'] === (string)$pay['out_trade_no']));
        $siteLogs = array_values(array_filter($snapshot['site_account_log'], static fn(array $row): bool => (int)$row['site_id'] === (int)$pay['site_id'] && (string)$row['trade_no'] === (string)$pay['out_trade_no']));
        if (in_array((int)$pay['status'], [0, -1], true) && !$memberLogs && !$siteLogs) continue;
        if ((int)$pay['status'] !== 2 || $pay['type'] !== 'balancepay' || $pay['main_type'] !== 'member' || count($memberLogs) !== 1 || count($siteLogs) !== 1) { $blockers[] = '存在非纯余额测试付款或关联流水不唯一'; continue; }
        $original = $orderFacts[$pay['site_id'] . ':' . $pay['trade_id']] ?? [];
        if ($cents($pay['money']) <= 0 || ($original['status'] ?? '') !== 'paid' || ($original['out_trade_no'] ?? '') !== $pay['out_trade_no'] || (int)($original['member_id'] ?? 0) !== (int)$pay['main_id'] || $cents($original['amount'] ?? '') !== $cents($pay['money'])) { $blockers[] = '原业务订单与已收款流水不一致，不能自动清理'; continue; }
        $log = $memberLogs[0]; $siteLog = $siteLogs[0];
        if ($log['account_type'] !== 'balance' || $log['from_type'] !== 'order' || (int)$log['member_id'] !== (int)$pay['main_id'] || $cents($log['account_data']) !== -$cents($pay['money']) || $siteLog['type'] !== 'pay' || $cents($siteLog['money']) !== $cents($pay['money'])) { $blockers[] = '原余额扣款与付款事实不一致'; continue; }
        $key = $pay['site_id'] . ':' . $pay['main_id'];
        if (!isset($restore[$key])) $restore[$key] = ['site_id' => (int)$pay['site_id'], 'member_id' => (int)$pay['main_id'], 'cents' => 0, 'log_ids' => []];
        $restore[$key]['cents'] -= $cents($log['account_data']); $restore[$key]['log_ids'][] = (int)$log['id'];
    }
    foreach ($retired as $order) if ($order['status'] === 'paid') {
        $completed = array_filter($snapshot['pay'], static fn(array $pay): bool => (int)$pay['site_id'] === (int)$order['site_id'] && (int)$pay['trade_id'] === (int)$order['id'] && (int)$pay['status'] === 2);
        if (count($completed) !== 1) $blockers[] = '已付款旧订单缺少唯一完成收款事实，不能自动清理';
    }
    if ($snapshot['pay_refund']) $blockers[] = '存在历史退款；本测试清理器不会猜测退款资金状态';
    foreach ($snapshot['promotion_reward'] as $reward) if ($reward['status'] === 'settled' || (int)$reward['account_log_id'] > 0) $blockers[] = '存在已入账推广奖励，须先核对';
    foreach ($snapshot['member_account_log'] as $log) if ($log['from_type'] !== 'order') $blockers[] = '存在已入账推广流水，不能只凭奖励状态删除';
    foreach ($restore as $key => $change) {
        $members = $query('SELECT member_id,site_id,balance,balance_get FROM ' . $table('member') . ' WHERE site_id=? AND member_id=?', [$change['site_id'], $change['member_id']]);
        $logs = $query('SELECT * FROM ' . $table('member_account_log') . ' WHERE site_id=? AND member_id=? AND account_type=? ORDER BY id', [$change['site_id'], $change['member_id'], 'balance']);
        if (count($members) !== 1 || !$logs || $cents($members[0]['balance']) !== $cents($logs[count($logs) - 1]['account_sum'])) $blockers[] = '会员当前余额与末笔账本不一致';
        if (count($members) === 1 && ($cents($members[0]['balance']) < 0 || $cents($members[0]['balance']) + $change['cents'] > 9999999999)) $blockers[] = '恢复后余额超出框架精度范围';
        foreach ($logs as $log) if ((int)$log['id'] >= min($change['log_ids']) && !in_array((int)$log['id'], $change['log_ids'], true)) $blockers[] = '旧测试扣款后存在无关余额变动，不能自动重写账本';
        foreach ($logs as $index=>$log) if ($index > 0 && (int)$log['id'] >= min($change['log_ids']) && $cents($logs[$index - 1]['account_sum']) + $cents($log['account_data']) !== $cents($log['account_sum'])) $blockers[] = '原余额扣款账本不连续，不能自动恢复';
        $snapshot['members'][$key] = $members[0] ?? []; $snapshot['balance_ledgers'][$key] = $logs;
    }
    $report = ['mode' => $mode, 'scope' => 'all_plugin_test_sites_only', 'retired_orders' => count($retired), 'related_pay_records' => count($snapshot['pay']), 'related_rewards' => count($snapshot['promotion_reward']), 'related_account_logs' => count($snapshot['member_account_log']) + count($snapshot['site_account_log']), 'drop_partner_columns' => $drops, 'blockers' => array_values(array_unique($blockers)), 'external_calls' => 0, 'new_tables_or_columns' => 0];
    if ($mode === '--check') { echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL; exit(0); }
    if ($blockers) throw new RuntimeException(implode('；', array_unique($blockers)) . '，未清理');
    $oldMask = umask(0077); $backup = $backend . '/runtime/hsx_lead_upgrade/retired-membership-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup, 0700, true)) throw new RuntimeException('无法建立备份目录');
    $snapshot['partner_schema'] = $query('SHOW CREATE TABLE ' . $table('hsx_lead_partner'));
    if (file_put_contents($backup . '/before.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('备份失败，未清理');
    chmod($backup . '/before.json', 0600); umask($oldMask);
    // Explicit RR locking reads protect even currently empty matching ranges.
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $sites = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['site_id'], array_merge($retired, $snapshot['partners'], $snapshot['config'])))); sort($sites, SORT_NUMERIC);
    foreach ($sites as $site) $query('SELECT site_id FROM ' . $table('site') . ' WHERE site_id=? FOR UPDATE', [$site]);
    $current = $query('SELECT * FROM ' . $table('hsx_lead_coop_order') . ' ORDER BY site_id,id FOR UPDATE');
    if ($canonical(array_values(array_filter($current, $isRetired))) !== $snapshot['orders']) throw new RuntimeException('清理期间旧权益订单集合发生变化，已停止');
    foreach ($related($retired, true) as $key=>$rows) if ($rows !== $snapshot[$key]) throw new RuntimeException('备份后关联付款、退款、流水或审计集合发生变化，事务已停止');
    if ($canonical($query('SELECT * FROM ' . $table('hsx_lead_partner') . ' ORDER BY site_id,id FOR UPDATE')) !== $snapshot['partners']) throw new RuntimeException('备份后主体权益数据发生变化，事务已停止');
    if ($canonical($query('SELECT * FROM ' . $table('sys_config') . ' WHERE config_key IN (?,?) FOR UPDATE', ['HSX_LEAD_COOPERATION','HSX_LEAD_PROMOTION'])) !== $snapshot['config']) throw new RuntimeException('备份后业务配置发生变化，事务已停止');
    foreach ($restore as $key => $change) {
        $locked = $query('SELECT member_id,site_id,balance,balance_get FROM ' . $table('member') . ' WHERE site_id=? AND member_id=? FOR UPDATE', [$change['site_id'], $change['member_id']]);
        $logs = $query('SELECT * FROM ' . $table('member_account_log') . ' WHERE site_id=? AND member_id=? AND account_type=? ORDER BY id FOR UPDATE', [$change['site_id'], $change['member_id'], 'balance']);
        if (($locked[0] ?? null) !== $snapshot['members'][$key] || $logs !== $snapshot['balance_ledgers'][$key]) throw new RuntimeException('备份后余额账本发生变化，事务已停止');
        $s = $pdo->prepare('UPDATE ' . $table('member') . ' SET balance=balance+? WHERE site_id=? AND member_id=?'); $s->execute([$amount($change['cents']), $change['site_id'], $change['member_id']]);
        if ($s->rowCount() !== 1) throw new RuntimeException('余额恢复行数异常，事务已停止');
    }
    $delete = static function(string $name, array $rows) use ($pdo, $table): void { $column = $name === 'pay' ? 'out_trade_no' : 'id'; foreach ($rows as $row) { $s = $pdo->prepare('DELETE FROM ' . $table($name) . ' WHERE site_id=? AND `' . $column . '`=?'); $s->execute([$row['site_id'], $row[$column]]); if ($s->rowCount() !== 1) throw new RuntimeException('精确删除行数异常，事务已停止'); } };
    $delete('member_account_log', $snapshot['member_account_log']); $delete('site_account_log', $snapshot['site_account_log']);
    $delete('hsx_lead_promotion_reward', $snapshot['promotion_reward']); $delete('pay', $snapshot['pay']); $delete('hsx_lead_audit', $snapshot['audits']); $delete('hsx_lead_coop_order', $retired);
    foreach ($snapshot['partners'] as $partner) if (!preg_match('/^ML[1-9][0-9]{0,9}$/D', (string)$partner['membership_level'])) { $s = $pdo->prepare('UPDATE ' . $table('hsx_lead_partner') . ' SET membership_level=\'\',membership_until=0 WHERE site_id=? AND id=?'); $s->execute([$partner['site_id'], $partner['id']]); }
    foreach ($snapshot['config'] as $config) {
        $value = json_decode((string)$config['value'], true); if (!is_array($value)) throw new RuntimeException('旧配置不是有效JSON，已停止');
        if ($config['config_key'] === 'HSX_LEAD_COOPERATION') foreach (['membership_levels', 'publishing_price', 'publishing_cycle', 'publishing_months', 'certification_price', 'certification_months', 'grades'] as $key) unset($value[$key]);
        else foreach (array_keys($value['sources'] ?? []) as $source) if (!in_array($source, ['membership', 'maintenance'], true)) unset($value['sources'][$source]);
        $s = $pdo->prepare('UPDATE ' . $table('sys_config') . ' SET value=? WHERE site_id=? AND config_key=?'); $s->execute([json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $config['site_id'], $config['config_key']]);
    }
    $pdo->commit();
    // MySQL DDL commits separately; backup is retained and --check identifies any unfinished drops.
    $report['phase'] = 'data_committed_schema_pending'; $report['backup'] = $backup; $report['data_writes'] = true;
    if (file_put_contents($backup . '/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('数据清理已提交，但阶段报告保存失败；请保留备份后核对，勿误认为事务已回滚');
    chmod($backup . '/result.json', 0600);
    if ($query('SHOW CREATE TABLE ' . $table('hsx_lead_partner')) !== $snapshot['partner_schema']) throw new RuntimeException('数据清理已提交，但表结构已变化，未执行旧字段删除，请核对备份');
    if ($drops) $pdo->exec('ALTER TABLE ' . $table('hsx_lead_partner') . ' ' . implode(', ', array_map(static fn(string $name): string => 'DROP COLUMN `' . $name . '`', $drops)));
    $report['mode'] = 'complete'; $report['phase'] = 'complete';
    if (file_put_contents($backup . '/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('清理已提交，但完成报告保存失败，请保留备份并重新只读核对'); chmod($backup . '/result.json', 0600);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $log = sys_get_temp_dir() . '/hsx-lead-membership-cleanup-private.log';
    $mask = umask(0077); file_put_contents($log, get_class($error) . ': ' . $error->getMessage() . ' @ ' . basename($error->getFile()) . ':' . $error->getLine() . PHP_EOL, FILE_APPEND | LOCK_EX); chmod($log, 0600); umask($mask);
    fwrite(STDERR, '[未完成清理] ' . ($error instanceof RuntimeException && !$error instanceof PDOException ? $error->getMessage() : '数据库检查或清理失败，请查看本机私有日志；未调用任何外部服务') . PHP_EOL); exit(1);
}
