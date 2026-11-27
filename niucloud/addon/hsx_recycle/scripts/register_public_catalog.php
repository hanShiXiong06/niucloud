<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array('--help', $argv, true)) {
    echo "公共资料库菜单注册：php addon/hsx_recycle/scripts/register_public_catalog.php [--apply]\n";
    echo "默认只读预览；--apply 仅补齐本功能菜单和权限，不改表结构，不转换资料，不清空数据，不自动授权员工。\n";
    exit;
}
$apply = in_array('--apply', $argv, true);
require dirname(__DIR__, 3) . '/vendor/autoload.php';
(new \think\App(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR))->initialize();
$loader = new \app\service\core\menu\CoreMenuService();
$rows = [];
foreach (['admin', 'site'] as $type) {
    $menus = require dirname(__DIR__) . '/app/dict/menu/' . $type . '.php';
    foreach ($loader->loadMenu($menus, $type, 'hsx_recycle') as $row) {
        $key = $row['menu_key'];
        if (str_starts_with($key, 'hsx_recycle_platform_catalog') || str_starts_with($key, 'hsx_recycle_platform_print') || str_starts_with($key, 'platform_recycle_')
            || str_starts_with($key, 'recycle_catalog_config') || str_starts_with($key, 'recycle_catalog_extra_')) $rows[] = $row;
    }
}
echo $apply ? "注册公共资料库菜单（不转换数据）\n" : "只读预览；确认后添加 --apply 执行\n";
\think\facade\Db::transaction(function () use ($rows, $apply) {
    foreach ($rows as $row) {
        $exists = \think\facade\Db::name('sys_menu')->where('addon', 'hsx_recycle')->where('app_type', $row['app_type'])->where('menu_key', $row['menu_key'])->count();
        echo $row['app_type'] . ' / ' . $row['menu_name'] . ($exists ? '：已存在，保留' : '：待注册') . "\n";
        if (!$exists && $apply) \think\facade\Db::name('sys_menu')->insert($row);
    }
});
if ($apply) \think\facade\Cache::tag(\app\service\admin\sys\MenuService::$cache_tag_name)->clear();
echo "下一步：平台设置 → 公共型号与模板库。先备份数据库，再预检并确认转换 100005。其他站自行选择来源；员工新增操作权限需重新授权。\n";
