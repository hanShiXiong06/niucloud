<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use addon\hsx_lead\app\service\NoticeConfigService;
use app\dict\schedule\ScheduleDict;
use app\service\admin\sys\MenuService;
use app\service\core\addon\CoreAddonInstallService;
use app\service\core\menu\CoreMenuService;
use app\service\core\schedule\CoreScheduleInstallService;
use RuntimeException;
use think\facade\Cache;
use think\facade\Db;
use Throwable;

/** Explicit, non-DDL refresh for an already installed plugin. Not an installer. */
final class UpgradeCommand
{
    private string $plugin;
    private string $backend;
    private string $project;
    private array $mounts;
    private array $generated;
    private string $backup = '';

    // Paths are injectable only for the isolated test; CLI never accepts path overrides.
    public function __construct(string $plugin, string $backend, string $project)
    {
        $this->plugin = rtrim($plugin, '/');
        $this->backend = rtrim($backend, '/');
        $this->project = rtrim($project, '/');
        $this->mounts = [
            'admin' => $this->project.'/admin/src/addon/hsx_lead',
            'uni-app' => $this->project.'/uni-app/src/addon/hsx_lead',
            'resource' => $this->backend.'/public/addon/hsx_lead',
        ];
        $wap = $this->project.'/uni-app/src/';
        $this->generated = [$wap.'pages.json', $wap.'addon/components/diy/group/index.vue'];
        foreach ($this->files($wap.'locale') as $file) {
            if (substr($file, -5) === '.json') $this->generated[] = $file;
        }
    }

    /** SELECTs and filesystem reads only: no lock file, backup, DDL or cache mutation. */
    public function check(): array
    {
        foreach ([$this->plugin, $this->backend, $this->project] as $root) {
            if (!is_dir($root) || realpath($root) !== $root) throw new RuntimeException('项目根目录必须为真实绝对路径，不支持符号链接根目录');
        }
        $ddl = file_get_contents($this->plugin.'/sql/install.sql');
        if (!is_string($ddl) || !preg_match_all('/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}([a-z_]+)`\s*\((.*?)\)\s*ENGINE\s*=/s', $ddl, $tables, PREG_SET_ORDER)
            || count($tables) !== 17) throw new RuntimeException('安装SQL声明不完整；请完整上传本插件，未执行升级');
        $database = Db::getConfig();
        $prefix = (string) ($database['connections'][$database['default']]['prefix'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) throw new RuntimeException('数据库表前缀格式异常');
        $missing = [];
        foreach ($tables as $table) {
            $columns = Db::query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$prefix.$table[1]]);
            $names = array_column($columns, 'COLUMN_NAME');
            if (!$names) {
                $missing[] = $table[1].'（整表缺失）';
                continue;
            }
            preg_match_all('/^\s*`([a-z_]+)`\s/m', $table[2], $fields);
            foreach (array_diff($fields[1], $names) as $field) $missing[] = $table[1].'.'.$field;
        }
        if ($missing) throw new RuntimeException("基础表结构未齐全，升级已阻止，不自动建表/加字段：\n- ".implode("\n- ", $missing)
            ."\n主体会员更新请先执行 php addon/hsx_lead/scripts/upgrade-entity-membership.php --check；通知任务表请先执行 php addon/hsx_lead/scripts/upgrade-notice-runtime.php --check；人工收款凭据表请先执行 php addon/hsx_lead/scripts/upgrade-manual-receipts.php --check；虚拟支付表请先执行 php addon/hsx_lead/scripts/upgrade-virtual-payment.php --check；推广层级字段/唯一索引请先执行 php addon/hsx_lead/scripts/upgrade-promotion-levels.php --check，再按文档备份并执行 --apply；其他缺失字段需单独核对，勿卸载重装。");
        if (!defined('HSX_LEAD_PROMOTION_LEVELS_LIBRARY_ONLY')) define('HSX_LEAD_PROMOTION_LEVELS_LIBRARY_ONLY', true);
        require_once $this->plugin.'/scripts/upgrade-promotion-levels.php';
        if ((new PromotionLevelsUpgrade($this->backend))->check()['pending_statements'] > 0)throw new RuntimeException('推广层级字段或唯一索引尚未升级；先执行 php addon/hsx_lead/scripts/upgrade-promotion-levels.php --check，再备份并执行 --apply，勿卸载重装');
        if (!Db::name('addon')->where('key', 'hsx_lead')->find()) throw new RuntimeException('插件尚未安装；本命令不执行安装，请先在平台正常安装');
        // A native menu refresh does not infer parent-directory grants for old roles.
        // Never silently migrate permissions from this general-purpose updater.
        if (Db::name('sys_menu')->where('addon', 'hsx_lead')->where('app_type', 'site')->where('menu_key', 'hsx_lead')->find()) {
            if (!defined('HSX_LEAD_MENU_GROUPS_LIBRARY_ONLY')) define('HSX_LEAD_MENU_GROUPS_LIBRARY_ONLY', true);
            require_once $this->plugin.'/scripts/upgrade-menu-groups.php';
            $menuGroups = (new MenuGroupsUpgrade($this->plugin, $this->backend))->check();
            if ($menuGroups['affected_roles'] > 0) throw new RuntimeException("原角色尚缺少本次新增的菜单目录，已阻止普通刷新，避免页面消失。请先执行：\nphp addon/hsx_lead/scripts/upgrade-menu-groups.php --check\nphp addon/hsx_lead/scripts/upgrade-menu-groups.php --apply\n再重新执行 upgrade.php --check / --apply；本命令不会静默修改角色权限。");
        }
        // A global menu key must never REPLACE another addon's or a customized menu.
        $menu = new CoreMenuService();
        foreach (['admin', 'site'] as $type) {
            $path = $this->plugin.'/app/dict/menu/'.$type.'.php';
            if (!is_file($path)) continue;
            $tree = require $path;
            unset($tree['delete']);
            foreach ($menu->loadMenu($tree, $type, 'hsx_lead') as $item) {
                $rows = Db::name('sys_menu')->where('menu_key', $item['menu_key'])->where('app_type', $type)->select()->toArray();
                foreach ($rows as $row) {
                    if ($row['addon'] !== 'hsx_lead' || $row['source'] !== 'system') throw new RuntimeException('菜单标识与其他插件或自定义菜单冲突：'.$item['menu_key']);
                }
            }
        }
        foreach ($this->scheduleTemplates() as $item) {
            $rows = Db::name('sys_schedule')->where('addon', 'hsx_lead')->where('key', $item['key'])->select()->toArray();
            if (count($rows) > 1 || (!empty($rows[0]['delete_time']))) throw new RuntimeException('计划任务存在重复或已删除记录，请先核对：'.$item['key']);
        }
        foreach ($this->mounts as $source => $target) {
            if (!is_dir($this->plugin.'/'.$source)) throw new RuntimeException('缺少插件源码目录：'.$source);
            $this->files($this->plugin.'/'.$source);
            $this->assertWritable($target);
            foreach ($this->files($target) as $file) $this->assertWritable($file);
        }
        foreach ($this->generated as $path) {
            if (!is_file($path)) throw new RuntimeException('缺少框架前端生成入口，请上传完整项目源码：'.$path);
            $this->assertWritable($path);
            if (substr($path, -5) === '.json' && basename($path) !== 'pages.json'
                && !is_array(json_decode((string) file_get_contents($path), true))) throw new RuntimeException('框架语言包JSON不合法：'.$path);
        }
        foreach ($this->retiredFiles() as $path) $this->assertWritable($path);
        $pages = (string) file_get_contents($this->generated[0]);
        foreach (['// {{ PAGE_BEGAIN }}', '// {{ PAGE_END }}'] as $marker) {
            if (substr_count($pages, $marker) !== 1) throw new RuntimeException('pages.json 缺少唯一框架注册标记：'.$marker);
        }
        $this->assertWritable($this->backend.'/runtime/hsx_lead_upgrade');
        return ['tables' => count($tables), 'source_directories' => array_keys($this->mounts), 'generated_files' => $this->generated,
            'ddl' => false, 'compile_or_publish' => false];
    }

    public function apply(): array
    {
        $this->check();
        $state = $this->backend.'/runtime/hsx_lead_upgrade';
        $this->privateDirectory($state);
        $lock = fopen($state.'/upgrade.lock', 'c');
        if (!$lock) throw new RuntimeException('无法创建插件升级锁');
        chmod($state.'/upgrade.lock', 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('本插件升级正在执行，请勿重复运行');
        }
        try {
            $this->check();
            $this->backup = $state.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
            $this->privateDirectory($this->backup);
            $this->createBackup();
            $this->writeJson($this->backup.'/result.json', ['status' => 'started', 'ddl' => false]);
            foreach ($this->mounts as $source => $target) {
                $copied = [];
                if (!dir_copy($this->plugin.'/'.$source, $target, $copied)) throw new RuntimeException('插件源码复制失败：'.$source);
                foreach ($this->files($this->plugin.'/'.$source) as $file) {
                    $relative = substr($file, strlen($this->plugin.'/'.$source));
                    if (hash_file('sha256', $file) !== hash_file('sha256', $target.$relative)) throw new RuntimeException('源码复制校验失败：'.$source.$relative);
                }
            }
            // Exact audited retired plugin files only, already captured in the private backup.
            // A directory copy alone would otherwise leave the removed implementation callable.
            foreach ($this->retiredFiles() as $path) if (is_file($path) && !unlink($path)) throw new RuntimeException('无法移除已退役的插件文件：'.$path);
            $installer = new CoreAddonInstallService('hsx_lead');
            $wap = $this->project.'/uni-app/src/';
            if ($installer->compileDiyComponentsCode($wap, ['hsx_lead']) === false
                || $installer->installPageCode($wap, ['hsx_lead']) === false) throw new RuntimeException('框架前端注册文件生成失败');
            $installer->compileLocale($wap, ['hsx_lead']);
            $this->verifyPages();
            Db::transaction(function (): void {
                // Lock the installed addon row: this script does not update its version/status.
                if (!Db::name('addon')->where('key', 'hsx_lead')->lock(true)->find()) throw new RuntimeException('插件安装状态已改变，停止更新');
                $before = Db::name('sys_schedule')->where('addon', 'hsx_lead')->select()->toArray();
                $known = array_column($before, null, 'key');
                (new CoreScheduleInstallService())->install($this->scheduleTemplates(), 'hsx_lead');
                foreach ($this->scheduleTemplates() as $item) {
                    if (!isset($known[$item['key']])) Db::name('sys_schedule')->where('addon', 'hsx_lead')->where('key', $item['key'])->update(['status' => ScheduleDict::OFF]);
                }
                NoticeConfigService::syncAssignedSites();
                (new CoreMenuService())->refreshAddonMenu('hsx_lead');
                foreach ($before as $row) {
                    $now = Db::name('sys_schedule')->where('id', $row['id'])->find();
                    if ($now !== $row) throw new RuntimeException('已有计划任务被意外修改，数据库更新已回滚');
                }
            });
            Cache::tag(MenuService::$cache_tag_name)->clear();
            $result = ['status' => 'complete', 'backup' => $this->backup, 'ddl' => false, 'source_sync' => true,
                'menu_count' => Db::name('sys_menu')->where('addon', 'hsx_lead')->count(),
                'schedules' => Db::name('sys_schedule')->where('addon', 'hsx_lead')->field('key,status,time')->select()->toArray(),
                'compile_or_publish' => false];
            $this->writeJson($this->backup.'/result.json', $result);
            return $result;
        } catch (Throwable $error) {
            if ($this->backup !== '') $this->writeJson($this->backup.'/result.json', ['status' => 'incomplete', 'requires_review' => true]);
            throw $error;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function backupPath(): string
    {
        return $this->backup;
    }

    private function scheduleTemplates(): array
    {
        $rows = require $this->plugin.'/app/dict/schedule/schedule.php';
        if (!is_array($rows) || !$rows) throw new RuntimeException('本插件计划任务声明缺失');
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['key'], $row['time']) || strpos($row['key'], 'hsx_lead_') !== 0) throw new RuntimeException('本插件计划任务声明异常');
        }
        return $rows;
    }

    private function createBackup(): void
    {
        $data = [
            'sys_menu' => Db::name('sys_menu')->where('addon', 'hsx_lead')->select()->toArray(),
            'sys_schedule' => Db::name('sys_schedule')->where('addon', 'hsx_lead')->select()->toArray(),
            'sys_notice' => Db::name('sys_notice')->whereIn('key', array_values(NoticeConfigService::PHASES))->select()->toArray(),
            // Explicit non-secret keys only. Never serialize connections, captcha keys, users or .env.
            'sys_config' => Db::name('sys_config')->whereIn('config_key', ['HSX_LEAD_COOPERATION', 'HSX_LEAD_PROMOTION', 'HSX_LEAD_NOTICE_WEAPP'])->select()->toArray(),
        ];
        $this->writeJson($this->backup.'/metadata.json', $data);
        $manifest = [];
        $paths = array_merge(array_values($this->mounts), $this->generated, array_values(array_filter($this->retiredFiles(), 'is_file')));
        foreach ($paths as $path) {
            $label = strpos($path, $this->backend.'/') === 0 ? 'backend/'.substr($path, strlen($this->backend) + 1) : 'project/'.substr($path, strlen($this->project) + 1);
            $manifest[] = ['path' => $path, 'backup' => 'files/'.$label, 'existed' => file_exists($path), 'directory' => is_dir($path)];
            foreach (is_file($path) ? [$path] : $this->files($path) as $file) {
                $relative = is_file($path) ? '' : substr($file, strlen($path));
                $destination = $this->backup.'/files/'.$label.$relative;
                $this->privateDirectory(dirname($destination));
                if (!copy($file, $destination) || hash_file('sha256', $file) !== hash_file('sha256', $destination)) throw new RuntimeException('文件备份失败，未开始更新');
                chmod($destination, 0600);
            }
        }
        $this->writeJson($this->backup.'/manifest.json', $manifest);
    }

    private function verifyPages(): void
    {
        $content = (string) file_get_contents($this->generated[0]);
        if (substr_count($content, 'HSX_LEAD_PAGE_BEGIN') !== 1 || substr_count($content, 'HSX_LEAD_PAGE_END') !== 1
            || substr_count($content, '"root": "addon/hsx_lead"') !== 1) throw new RuntimeException('商户协作页面注册校验失败');
        $declaration = require $this->plugin.'/package/uni-app-pages.php';
        preg_match_all('/"path"\s*:\s*"([^"]+)"/', $declaration['pages'], $matches);
        foreach ($matches[1] as $path) {
            if (strpos($content, '"'.$path.'"') === false || !is_file($this->mounts['uni-app'].'/'.$path.'.vue')) throw new RuntimeException('商户协作页面源码或注册缺失：'.$path);
        }
    }

    private function retiredFiles(): array
    {
        return [
            $this->backend.'/addon/hsx_lead/app/service/cooperation/MembershipPlanService.php',
            $this->mounts['uni-app'].'/components/cooperation/CoopPlanComparison.vue',
            $this->mounts['uni-app'].'/components/cooperation/CoopPlansEntryCard.vue',
            $this->mounts['uni-app'].'/utils/cooperation-plans.ts',
            $this->mounts['uni-app'].'/static/cooperation/levels/level-v1.png',
            $this->mounts['uni-app'].'/static/cooperation/levels/level-v2.png',
            $this->mounts['uni-app'].'/static/cooperation/levels/level-v3.png',
            $this->mounts['uni-app'].'/static/cooperation/levels/level-v4.png',
        ];
    }

    private function assertWritable(string $path): void
    {
        $current = $path;
        $checkedTarget = false;
        while ($current !== '/' && $current !== '.') {
            if (is_link($current)) throw new RuntimeException('升级涉及的路径含符号链接，未执行：'.$current);
            // Ancestors such as /www need traversal, not write permission. Only
            // the existing target (or nearest existing parent of a new path) is written.
            if (!$checkedTarget && file_exists($current)) {
                if (!is_writable($current)) throw new RuntimeException('升级路径不可写：'.$current);
                $checkedTarget = true;
            }
            $current = dirname($current);
        }
    }

    private function files(string $path): array
    {
        if (is_link($path)) throw new RuntimeException('升级目录不接受符号链接：'.$path);
        if (!file_exists($path)) return [];
        if (!is_dir($path)) throw new RuntimeException('预期为目录：'.$path);
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) throw new RuntimeException('升级目录含符号链接：'.$file->getPathname());
            if ($file->isFile()) $files[] = $file->getPathname();
        }
        sort($files);
        return $files;
    }

    private function privateDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('无法创建私有备份目录');
        chmod($path, 0700);
    }

    private function writeJson(string $path, array $data): void
    {
        if (file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('升级备份/结果文件写入失败');
        chmod($path, 0600);
    }
}

/** Minimal CLI bootstrap: do not initialize HTTP middleware, jobs or plugin lifecycle. */
function bootstrap(string $backend): void
{
    require_once $backend.'/vendor/autoload.php';
    $app = new \think\App($backend);
    $app->loadEnv();
    require_once $app->getThinkPath().'helper.php';
    require_once $backend.'/app/common.php';
    foreach (['app', 'database', 'cache', 'log'] as $name) {
        $path = $backend.'/config/'.$name.'.php';
        if (is_file($path)) $app->config->set(require $path, $name);
    }
    $database = $app->config->get('database');
    $connection = $database['default'];
    $database['connections'][$connection]['fields_cache'] = false;
    $database['connections'][$connection]['trigger_sql'] = false;
    $app->config->set($database, 'database');
    $manager = new \think\DbManager();
    $manager->setConfig($database);
    $app->instance('think\DbManager', $manager);
    $app->instance('request', new \app\Request());
    date_default_timezone_set((string) $app->config->get('app.default_timezone', 'Asia/Shanghai'));
}

function main(array $arguments): int
{
    if (PHP_SAPI !== 'cli') return 1;
    if (PHP_VERSION_ID < 80000) {
        fwrite(STDERR, "需要 PHP 8.0 或以上，请使用网站相同版本的PHP命令。\n");
        return 1;
    }
    if (count($arguments) === 1 && in_array($arguments[0], ['--help', '-h'], true)) {
        echo "已安装 hsx_lead 安全刷新（不建表、不编译、不发布）\nphp addon/hsx_lead/scripts/upgrade.php --check\nphp addon/hsx_lead/scripts/upgrade.php --apply\n";
        return 0;
    }
    if (count($arguments) !== 1 || !in_array($arguments[0], ['--check', '--apply'], true)) {
        fwrite(STDERR, "必须明确指定 --check（只读预检）或 --apply（备份后更新）；--help 查看说明。\n");
        return 2;
    }
    $command = null;
    try {
        $plugin = dirname(__DIR__);
        $backend = dirname($plugin, 2);
        bootstrap($backend);
        $command = new UpgradeCommand($plugin, $backend, dirname($backend));
        $result = $arguments[0] === '--check' ? $command->check() : $command->apply();
        if ($arguments[0] === '--check') echo "[通过] 14张基础表及字段、菜单冲突、前端目录已预检；未写入数据库/文件/缓存。\n";
        else echo "[完成] hsx_lead菜单、通知记录、任务注册与源码挂载已更新。\n备份：".$result['backup']."\n已有任务状态保持；新增任务关闭。未编译/上传/发布前端，请按原流程打包。\n";
        return 0;
    } catch (Throwable $error) {
        // Do not print a vendor/PDO message or stack trace: it may contain credentials.
        $message = get_class($error) === RuntimeException::class ? $error->getMessage() : '框架或数据库操作失败（已隐藏连接信息），请核对运行环境、权限与官方日志';
        fwrite(STDERR, "[失败] ".$message."\n");
        if ($command && $command->backupPath() !== '') fwrite(STDERR, '更新未完整结束，请先核对私有备份：'.$command->backupPath()."；勿卸载重装。\n");
        return 1;
    }
}

if (!defined('HSX_LEAD_UPGRADE_LIBRARY_ONLY')) exit(main(array_slice($_SERVER['argv'] ?? [], 1)));
