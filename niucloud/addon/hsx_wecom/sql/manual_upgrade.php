<?php
declare(strict_types=1);

/**
 * 手动覆盖部署检查。默认只读；只有 --register-runtime 会补缺菜单和计划任务。
 * 不调用 Addon 生命周期、不执行 DDL、不发送通知、不修改角色和授权。
 */
function wecomRuntimeMenuPlan(array $expected, array $existing): array
{
    $byKey = [];
    foreach ($existing as $row) $byKey[$row['app_type'] . ':' . $row['menu_key']][] = $row;
    $missing = $warnings = [];
    foreach ($expected as $menu) {
        $key = $menu['app_type'] . ':' . $menu['menu_key'];
        $rows = $byKey[$key] ?? [];
        if (!$rows) {
            $missing[] = $menu;
            continue;
        }
        if (count($rows) !== 1 || ($rows[0]['addon'] ?? '') !== 'hsx_wecom') {
            throw new RuntimeException('菜单键冲突，未执行覆盖：' . $key);
        }
        $row = $rows[0];
        if ((int)($row['delete_time'] ?? 0) > 0 || (int)($row['status'] ?? 0) !== 1) {
            $warnings[] = $key . ' 已存在但停用/软删除，需管理员确认，不自动恢复';
        }
        foreach (['api_url', 'methods', 'parent_key', 'router_path', 'view_path'] as $field) {
            if ((string)($row[$field] ?? '') !== (string)($menu[$field] ?? '')) {
                $warnings[] = $key . ' 的 ' . $field . ' 与当前字典不同，保留现值，请人工核对';
            }
        }
    }
    return compact('missing', 'warnings');
}

function wecomExpectedStructure(): array
{
    $source = file_get_contents(__DIR__ . '/install.sql');
    if ($source === false) throw new RuntimeException('无法读取插件 install.sql');
    preg_match_all('/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}(\w+)` \((.*?)\) ENGINE=/s', $source, $matches, PREG_SET_ORDER);
    if (count($matches) !== 6) throw new RuntimeException('预期 6 张插件表，请先检查发布包');
    $result = [];
    foreach ($matches as $match) {
        $columns = $indexes = [];
        foreach (explode("\n", trim($match[2])) as $line) {
            $line = rtrim(trim($line), ',');
            if (preg_match('/^`(\w+)` /', $line, $part)) $columns[] = $part[1];
            elseif (preg_match('/^(UNIQUE )?KEY `(\w+)` \((.*?)\)/', $line, $part)) {
                preg_match_all('/`(\w+)`/', $part[3], $fields);
                $indexes[$part[2]] = ['columns' => $fields[1], 'unique' => $part[1] !== ''];
            } elseif (preg_match('/^PRIMARY KEY \((.*?)\)/', $line, $part)) {
                preg_match_all('/`(\w+)`/', $part[1], $fields);
                $indexes['PRIMARY'] = ['columns' => $fields[1], 'unique' => true];
            }
        }
        $result[$match[1]] = compact('columns', 'indexes');
    }
    return $result;
}

function wecomManualUpgradeMain(array $args): int
{
    if (in_array('--help', $args, true)) {
        echo "Usage: php addon/hsx_wecom/sql/manual_upgrade.php [--check | --register-runtime]\n"
            . "--check (default): read-only schema/menu/schedule checks; no secrets printed.\n"
            . "--register-runtime: insert missing hsx_wecom menu keys and retry schedule only.\n"
            . "Never runs DDL, grants roles, resets settings, or sends messages.\n";
        return 0;
    }
    foreach ($args as $arg) {
        if (!in_array($arg, ['--check', '--register-runtime'], true)) throw new RuntimeException('未知参数：' . $arg);
    }
    if (in_array('--check', $args, true) && in_array('--register-runtime', $args, true)) throw new RuntimeException('请分别执行只读检查和注册');
    $register = in_array('--register-runtime', $args, true);
    $root = dirname(__DIR__, 3);
    require $root . '/vendor/autoload.php';
    (new \think\App($root . DIRECTORY_SEPARATOR))->initialize();

    $prefix = (string)config('database.connections.mysql.prefix');
    if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) throw new RuntimeException('表前缀不符合安全格式');
    $database = \think\facade\Db::query('SELECT DATABASE() AS database_name')[0]['database_name'] ?? '';
    echo 'Mode: ' . ($register ? 'REGISTER MISSING RUNTIME ENTRIES' : 'READ ONLY') . "\n";
    echo 'Database: ' . $database . '; prefix: ' . $prefix . "\n";
    if ($database === '') throw new RuntimeException('未选定数据库');
    $problems = [];
    foreach (wecomExpectedStructure() as $name => $expected) {
        $table = $prefix . $name;
        $columns = \think\facade\Db::query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $table]);
        $actualColumns = array_column($columns, 'COLUMN_NAME');
        $missingColumns = array_values(array_diff($expected['columns'], $actualColumns));
        if ($missingColumns) $problems[] = $table . ' 缺表/缺列：' . implode(',', $missingColumns);
        $rows = \think\facade\Db::query('SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$database, $table]);
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
            $indexes[$row['INDEX_NAME']]['unique'] = (int)$row['NON_UNIQUE'] === 0;
        }
        foreach ($expected['indexes'] as $index => $definition) {
            if (($indexes[$index] ?? null) !== $definition) $problems[] = $table . ' 缺失或不一致索引：' . $index;
        }
        echo $table . ': ' . ($missingColumns ? 'INCOMPLETE' : 'columns present') . "\n";
    }
    if ($problems) {
        foreach ($problems as $problem) echo 'FAIL: ' . $problem . "\n";
        echo "先核对前缀并执行增量 SQL；禁止继续覆盖授权或删除记录来绕过错误。\n";
        return 1;
    }

    $addon = (new \app\model\addon\Addon())->where('key', 'hsx_wecom')->findOrEmpty()->toArray();
    if (!$addon || (int)$addon['status'] !== 1) throw new RuntimeException('hsx_wecom 未安装或未启用；手动覆盖工具不会伪造插件安装/租户购买记录');
    $service = new \app\service\core\menu\CoreMenuService();
    $expectedMenus = [];
    foreach (['admin', 'site'] as $type) {
        $tree = require dirname(__DIR__) . '/app/dict/menu/' . $type . '.php';
        $expectedMenus = array_merge($expectedMenus, $service->loadMenu($tree, $type, 'hsx_wecom'));
    }
    $menuName = (new \app\model\sys\SysMenu())->getName();
    $existing = \think\facade\Db::name($menuName)->whereIn('menu_key', array_column($expectedMenus, 'menu_key'))->select()->toArray();
    $plan = wecomRuntimeMenuPlan($expectedMenus, $existing);
    $schedule = (new \app\model\sys\SysSchedule())->where(['addon' => 'hsx_wecom', 'key' => 'hsx_wecom_message_retry'])->findOrEmpty()->toArray();
    echo 'Missing menu keys: ' . count($plan['missing']) . "\n";
    foreach ($plan['missing'] as $menu) echo '  ' . $menu['app_type'] . ':' . $menu['menu_key'] . "\n";
    echo 'Retry schedule: ' . ($schedule ? 'present; status=' . $schedule['status'] : 'MISSING') . "\n";

    if ($register) {
        if ($plan['warnings']) throw new RuntimeException('现有菜单存在差异/停用记录，先只读检查并人工确认，不自动覆盖');
        \think\facade\Db::transaction(static function () use ($plan): void {
            foreach ($plan['missing'] as $menu) \app\model\sys\SysMenu::create($menu);
            // 使用框架现有注册服务：仅补缺记录，保留既有任务状态、执行时间和计数。
            (new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('hsx_wecom');
        });
        \think\facade\Cache::tag(\app\service\admin\sys\MenuService::$cache_tag_name)->clear();
        echo "已补缺菜单和计划任务；未修改角色权限、企业授权、员工绑定和业务记录。\n";
        echo "请重新登录管理员，按需给配置管理员勾选新增权限；重载现有计划任务/队列进程。\n";
    }
    foreach ($plan['warnings'] as $warning) echo 'WARN: ' . $warning . "\n";
    if ($schedule && (int)$schedule['status'] !== \app\dict\schedule\ScheduleDict::ON) echo "WARN: 消息补偿任务停用，保留现状；请负责人确认是否启用。\n";
    echo 'Queue configured: ' . (env('queue.state', false) ? 'ON; verify worker process separately' : 'OFF; verify scheduler delivery separately') . "\n";
    echo "此检查未证明企业微信回调、真实送达或手机跳转成功，仍需人工验收。\n";
    return (!$register && ($plan['missing'] || !$schedule)) || $plan['warnings'] || ($schedule && (int)$schedule['status'] !== \app\dict\schedule\ScheduleDict::ON) ? 1 : 0;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(wecomManualUpgradeMain(array_slice($argv, 1)));
    } catch (Throwable $e) {
        fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
