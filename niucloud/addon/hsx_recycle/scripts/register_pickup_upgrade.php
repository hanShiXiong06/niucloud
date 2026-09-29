<?php
declare(strict_types=1);

// 手工覆盖升级辅助：只补本次3个权限和1个计划任务，不改表、不刷新整个插件菜单。
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array('--help', $argv, true)) {
    echo "用法：php addon/hsx_recycle/scripts/register_pickup_upgrade.php [--help|--apply]\n";
    echo "默认连接当前配置数据库只读预览；--help 不初始化框架、不连接数据库。\n";
    echo "--apply 仅补缺失的3个权限和1个计划任务，不改表，保留已有配置。\n";
    echo "本脚本不直接预约/查询/通知；新增任务默认启用（每5分钟），运行中的计划任务进程后续可能自动查询并触发状态通知。\n";
    echo "请勿并发运行；员工授权、计划任务进程、真实数据库结构及渠道验收需另行核实。\n";
    exit(0);
}
$apply = in_array('--apply', $argv, true);
require dirname(__DIR__, 3) . '/vendor/autoload.php';
(new \think\App(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR))->initialize();

$menuService = new \app\service\core\menu\CoreMenuService();
$keys = ['pickup_notice_config_info', 'pickup_notice_config_save', 'express_pickup_resolve_unbooked'];
$rows = array_values(array_filter($menuService->loadMenu(require dirname(__DIR__) . '/app/dict/menu/site.php', 'site', 'hsx_recycle'),
    static fn(array $row): bool => in_array($row['menu_key'], $keys, true)));
if (count($rows) !== count($keys)) throw new \RuntimeException('菜单字典不完整，请先上传本次插件文件');
$schedules = array_values(array_filter(require dirname(__DIR__) . '/app/dict/schedule/schedule.php',
    static fn(array $row): bool => $row['key'] === 'hsx_recycle_pickup_reconcile'));
if (count($schedules) !== 1) throw new \RuntimeException('计划任务字典不完整');

echo $apply ? "应用本次配置注册（本进程不外呼；新增任务启用后由计划任务进程自动核实）\n" : "只读预览；确认后加 --apply 执行\n";
\think\facade\Db::transaction(function () use ($rows, $apply, $schedules) {
    foreach ($rows as $row) {
        $query = \think\facade\Db::name('sys_menu')->where('addon', 'hsx_recycle')->where('app_type', 'site')->where('menu_key', $row['menu_key']);
        $exists = $query->find();
        echo $row['menu_key'] . ($exists ? '：已存在，保留' : '：待注册') . "\n";
        if (!$exists && $apply) \think\facade\Db::name('sys_menu')->insert($row);
    }
    $exists = \think\facade\Db::name('sys_schedule')->where('addon', 'hsx_recycle')->where('key', 'hsx_recycle_pickup_reconcile')->find();
    echo 'hsx_recycle_pickup_reconcile' . ($exists ? '：已存在，保留原启停配置' : '：待注册，默认启用、每5分钟执行') . "\n";
    if (!$exists && $apply) (new \app\service\core\schedule\CoreScheduleInstallService())->install($schedules, 'hsx_recycle');
});
if ($apply) \think\facade\Cache::tag(\app\service\admin\sys\MenuService::$cache_tag_name)->clear();
echo "注册不代表渠道已验收。请检查员工权限、计划任务进程、公网回调及真实消息模板。\n";
