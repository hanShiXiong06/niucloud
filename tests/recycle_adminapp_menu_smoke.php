<?php
declare(strict_types=1);

// 执行真实菜单服务及字典；用户权限、插件清单和偏好存储在内存中隔离，不连接数据库。
namespace core\base {
    class BaseAdminService
    {
        public int $uid = 21;
        public int $site_id = 100005;
    }
}

namespace addon\hsx_recycle\app\service\core\adminapp {
    class CoreAppService
    {
        public static array $apps = [];
        public static array $groups = [];
        public function getMobileApp(): array { return self::$apps; }
        public function getMobileAppGroup(): array { return self::$groups; }
    }

    class CoreAdminAppService
    {
        public static array $memory = [];
        public static array $writes = [];

        public function getValue(int $uid, int $site_id, string $type)
        {
            return self::$memory[$site_id][$uid][$type] ?? [];
        }

        public function setValue(int $uid, int $site_id, string $type, array $value): bool
        {
            self::$memory[$site_id][$uid][$type] = $value;
            self::$writes[] = compact('uid', 'site_id', 'type', 'value');
            return true;
        }
    }
}

namespace {
    use addon\hsx_recycle\app\service\admin\adminapp\AppsService;
    use addon\hsx_recycle\app\service\core\adminapp\CoreAdminAppService;
    use addon\hsx_recycle\app\service\core\adminapp\CoreAppService;
    use core\exception\CommonException;

    $root = dirname(__DIR__);
    require $root . '/niucloud/core/exception/CommonException.php';
    require $root . '/niucloud/addon/hsx_recycle/app/service/admin/adminapp/AppsService.php';

    set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    function env(string $name, $default = null)
    {
        return $GLOBALS['test_env'][$name] ?? $default;
    }

    class MenuProbe extends AppsService
    {
        public array $permissions = [];
        protected function getCurrentUserMenuKeys(): array { return $this->permissions; }
    }

    $count = 0;
    $check = static function (bool $condition, string $name) use (&$count): void {
        if (!$condition) {
            throw new \RuntimeException('FAIL ' . $name);
        }
        $count++;
        echo "PASS {$name}\n";
    };
    $keysOf = static fn(array $apps): array => array_column($apps, 'key');
    $flatten = static function (array $menus) use (&$flatten): array {
        $result = [];
        foreach ($menus as $menu) {
            $result[] = $menu;
            $result = array_merge($result, $flatten($menu['children'] ?? []));
        }
        return $result;
    };
    $expected = [
        'hsx_recycle_my_task' => 'recycle_my_task',
        'hsx_recycle_stats' => 'recycle_workbench',
        'hsx_recycle_scan_check' => 'recycle_order_list',
        'hsx_recycle_order' => 'recycle_order_list',
        'hsx_recycle_consignment_order' => 'recycle_consignment_order_list',
        'hsx_recycle_return_order' => 'recycle_return_order_list',
        'hsx_recycle_express_order' => 'express_order_record_list',
    ];
    $recycleApps = require $root . '/niucloud/addon/hsx_recycle/app/dict/adminapp/app.php';
    $recycleGroups = require $root . '/niucloud/addon/hsx_recycle/app/dict/adminapp/app_group.php';
    $pcMenus = $flatten(require $root . '/niucloud/addon/hsx_recycle/app/dict/menu/site.php');
    $pcKeys = array_column($pcMenus, 'menu_key');
    $check($keysOf($recycleApps) === array_keys($expected), '七个移动入口 key 保持不变且无重复');
    $check(count($pcKeys) === count(array_unique($pcKeys)), '后台菜单权限 key 无重复');

    foreach ($recycleApps as $app) {
        $check($app['menu_key'] === $expected[$app['key']] && in_array($app['menu_key'], $pcKeys, true),
            $app['name'] . '对应现有后台权限');
        $check(in_array($app['group'], array_column($recycleGroups, 'key'), true)
            && is_file($root . '/site-uniapp/src' . $app['page'] . '.vue'),
            $app['name'] . '分组及移动页面存在');
    }

    $service = new MenuProbe();
    $allPermissions = array_values(array_unique(array_values($expected)));
    $service->permissions = $allPermissions;
    CoreAppService::$apps = array_reverse($recycleApps);
    CoreAppService::$groups = $recycleGroups;
    $check($keysOf($service->getApps()) === array_keys($expected), '全部入口先按 sort 排序，与字典加载顺序无关');
    $check($keysOf($service->getAppsOfCheck()[0]['childs']) === array_keys($expected), '具备完整权限时全部应用展示七个回收入口');
    $check($keysOf($service->getAppsOfIndex()) === array_keys($expected), '仅安装回收字典时首页完整展示七个入口');

    $service->permissions = [];
    $check($service->getApps() === [] && $service->getAppsOfIndex() === [], '无回收权限时不泄漏回收入口');
    foreach ($allPermissions as $permission) {
        $service->permissions = [$permission];
        $expectedKeys = array_keys(array_filter($expected, static fn(string $key): bool => $key === $permission));
        $check($keysOf($service->getApps()) === $expectedKeys, '单项权限过滤正确：' . $permission);
    }

    $publicApp = ['key' => 'public_entry', 'name' => '公共入口', 'menu_key' => '', 'sort' => 100, 'group' => 'other'];
    $privateApp = ['key' => 'other_entry', 'name' => '其他插件', 'menu_key' => 'other_permission', 'sort' => 101, 'group' => 'other'];
    CoreAppService::$apps = array_merge($recycleApps, [$publicApp, $privateApp]);
    CoreAppService::$groups = array_merge($recycleGroups, [['key' => 'other', 'name' => '其他', 'sort' => 2]]);
    $service->permissions = [];
    $check($keysOf($service->getApps()) === ['public_entry'], '其他插件公共入口保持可见，私有入口仍需权限');
    $service->permissions = ['other_permission'];
    $check($keysOf($service->getApps()) === ['public_entry', 'other_entry'], '其他插件已有权限规则保持不变');

    $extraApps = [];
    for ($i = 0; $i < 12; $i++) {
        $extraApps[] = array_replace($publicApp, ['key' => 'extra_' . $i, 'sort' => 100 + $i]);
    }
    CoreAppService::$apps = array_merge($extraApps, array_reverse($recycleApps));
    $service->permissions = $allPermissions;
    $defaultHome = $service->getAppsOfIndex();
    $check(count($defaultHome) === 9
        && $keysOf(array_slice($defaultHome, 0, 7)) === array_keys($expected), '多插件场景先排序再取默认九个，不丢失后加载的高优先级入口');
    $groups = $service->getAppsOfCheck();
    $check(count($groups[0]['childs']) === 7 && count($groups[1]['childs']) === 12, '全部应用不受首页九个入口限制');
    $GLOBALS['test_env']['system.todo_limit'] = '3';
    $check($keysOf($service->getAppsOfIndex()) === array_slice(array_keys($expected), 0, 3), '沿用既有默认首页数量配置');
    $GLOBALS['test_env']['system.todo_limit'] = 'invalid';
    $check(count($service->getAppsOfIndex()) === 9, '无效首页数量配置回到默认值，避免空白');
    unset($GLOBALS['test_env']);

    $saveFixture = static function ($value) use ($service): void {
        CoreAdminAppService::$memory[$service->site_id][$service->uid]['app'] = $value;
    };
    $selected = ['hsx_recycle_order', 'hsx_recycle_my_task'];
    $saveFixture($selected);
    $check($keysOf($service->getAppsOfIndex()) === $selected, '已保存移动入口按用户选定顺序展示');
    $saveFixture(['removed_entry', ...$selected, 'recycle_workbench']);
    $check($keysOf($service->getAppsOfIndex()) === $selected, '部分失效配置仅移除失效项，不添加未选择的入口');
    $saveFixture(['hsx_recycle_order', 'hsx_recycle_order', 'hsx_recycle_my_task']);
    $check($keysOf($service->getAppsOfIndex()) === $selected, '重复选择不重复渲染');
    $saveFixture([['key' => 'hsx_recycle_stats'], null, 100, 'hsx_recycle_order']);
    $check($keysOf($service->getAppsOfIndex()) === ['hsx_recycle_order'], '存储内混入无效类型时保留有效入口且不报错');

    foreach ([[], ['removed_entry'], ['recycle_order_list'], 'hsx_recycle_order', [new \stdClass()], null] as $badValue) {
        $saveFixture($badValue);
        $before = CoreAdminAppService::$memory;
        $writes = count(CoreAdminAppService::$writes);
        $check($keysOf($service->getAppsOfIndex()) === $keysOf($defaultHome)
            && CoreAdminAppService::$memory === $before && count(CoreAdminAppService::$writes) === $writes,
            '无有效已选入口时只读回退默认：' . get_debug_type($badValue) . '/' . json_encode($badValue));
    }

    $check($service->setAppsOfIndex(['hsx_recycle_order', 'hsx_recycle_order', 'hsx_recycle_my_task']), '保存快捷入口成功');
    $check(CoreAdminAppService::$memory[$service->site_id][$service->uid]['app'] === $selected
        && $keysOf($service->getAppsOfIndex()) === $selected, '保存去重并保留用户顺序，读取与保存一致');

    $reject = static function (array $value, string $name) use ($check, $service): void {
        $before = CoreAdminAppService::$memory;
        $writes = count(CoreAdminAppService::$writes);
        $message = '';
        try {
            $service->setAppsOfIndex($value);
        } catch (CommonException $e) {
            $message = $e->getMessage();
        }
        $check(str_contains($message, '请刷新应用列表后重新选择')
            && CoreAdminAppService::$memory === $before && count(CoreAdminAppService::$writes) === $writes, $name);
    };
    $reject(['missing'], '未知入口拒绝保存，有明确提示且不污染配置');
    $reject(['recycle_order_list'], '不能把后台权限 key 当作移动入口保存');
    foreach ([1, null, true, [], ['key' => 'hsx_recycle_order']] as $invalid) {
        $reject(['hsx_recycle_my_task', $invalid], '非法输入整批拒绝，不部分写入：' . get_debug_type($invalid));
    }
    $service->permissions = ['recycle_my_task'];
    $reject(['hsx_recycle_order'], '没有相应权限的真实入口也不能保存');
    $check($keysOf($service->getAppsOfIndex()) === ['hsx_recycle_my_task'], '权限收回后已保存入口立即过滤，仍保留有效选择');
    $saveFixture(['hsx_recycle_order']);
    $fallbackKeys = $keysOf($service->getAppsOfIndex());
    $check(in_array('hsx_recycle_my_task', $fallbackKeys, true) && !in_array('hsx_recycle_order', $fallbackKeys, true),
        '所有已选入口均无权访问时仅回退有权限的默认入口');

    $service->permissions = $allPermissions;
    CoreAppService::$apps = $extraApps;
    $reject(['hsx_recycle_my_task'], '未安装插件的入口即使残留权限也不能保存');
    CoreAppService::$apps = $recycleApps;
    $service->setAppsOfIndex([]);
    $check(CoreAdminAppService::$memory[$service->site_id][$service->uid]['app'] === []
        && $keysOf($service->getAppsOfIndex()) === array_keys($expected), '空选择恢复默认入口');

    $service->setAppsOfIndex($selected);
    $otherSite = new MenuProbe();
    $otherSite->site_id = 100024;
    $otherSite->permissions = $allPermissions;
    $otherSite->setAppsOfIndex(['hsx_recycle_stats']);
    $otherUser = new MenuProbe();
    $otherUser->uid = 22;
    $otherUser->permissions = $allPermissions;
    $otherUser->setAppsOfIndex(['hsx_recycle_return_order']);
    $check($keysOf($service->getAppsOfIndex()) === $selected
        && $keysOf($otherSite->getAppsOfIndex()) === ['hsx_recycle_stats']
        && $keysOf($otherUser->getAppsOfIndex()) === ['hsx_recycle_return_order'], '快捷入口按站点和管理员独立保存');

    // 框架原有图片/视频空间共享 album key；本次不改框架，也不能用 key 做字典覆盖而丢入口。
    $frameworkApps = require $root . '/niucloud/app/dict/adminapp/app.php';
    CoreAppService::$apps = array_merge($frameworkApps, $recycleApps);
    $albums = array_values(array_filter($frameworkApps, static fn(array $app): bool => $app['key'] === 'album'));
    $service->permissions = array_merge($allPermissions, array_column($frameworkApps, 'menu_key'));
    $service->setAppsOfIndex(['album']);
    $check(count($albums) > 0 && count($service->getAppsOfIndex()) === count($albums), '没有用 key 覆盖框架已有的同 key 不同页面入口');
    $check(count(array_filter($service->getApps(), static fn(array $app): bool => $app['group'] === 'hsx_recycle')) === 7,
        '混合真实框架字典时七个回收入口仍完整');

    restore_error_handler();
    echo "\n{$count} PASS — 真实服务和字典，内存依赖隔离，无数据库及线上写入\n";
}
