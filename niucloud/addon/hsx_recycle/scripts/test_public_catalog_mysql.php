<?php
declare(strict_types=1);

// 真实 MySQL + ThinkORM 回归。仅接受专用临时 socket，绝不读取项目 .env。
namespace core\base {
    class BaseAdminService {
        public static int $testSite = 0;
        public static string $testApp = 'admin';
        public $site_id; public $app_type; public $uid = 7; public $username = 'catalog-test';
        public function __construct() { $this->site_id = self::$testSite; $this->app_type = self::$testApp; }
        protected function pageQuery($query): array { return ['data' => $query->select()->toArray(), 'total' => $query->count()]; }
    }
    class BaseCoreService { public function __construct() {} }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static string $cache_tag_name = 'catalog_test_config';
        public static bool $failNextWrite = false;
        public function getConfigValue(int $siteId, string $key): array {
            return json_decode((string)\think\facade\Db::name('catalog_test_config')->where('site_id', $siteId)->where('name', $key)->value('value'), true) ?: [];
        }
        public function setConfig(int $siteId, string $key, array $value): void {
            if (self::$failNextWrite) { self::$failNextWrite = false; throw new \core\exception\CommonException('injected transaction failure'); }
            $query = \think\facade\Db::name('catalog_test_config')->where('site_id', $siteId)->where('name', $key);
            if ($query->count()) $query->update(['value' => json_encode($value)]);
            else \think\facade\Db::name('catalog_test_config')->insert(['site_id' => $siteId, 'name' => $key, 'value' => json_encode($value)]);
        }
    }
}
namespace {
    use think\facade\Db;
    use think\facade\Cache;
    use core\base\BaseAdminService as Context;
    use addon\hsx_recycle\app\service\admin\device\DeviceCatalogConfigService;
    use addon\hsx_recycle\app\service\admin\device\DeviceCatalogPrintRecoveryService;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrinterTemplateService;
    use addon\hsx_recycle\app\service\core\printer\PrinterTemplateLibrary;
    use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
    use addon\hsx_recycle\app\service\admin\check\RecycleCheckTemplateService;
    use addon\hsx_recycle\app\service\admin\template\RecycleTemplateBindingService;
    use addon\hsx_recycle\app\service\core\device\CoreRecycleDeviceModelDictService;
    use addon\hsx_recycle\app\service\core\device\DeviceCatalogPolicy;
    use addon\hsx_recycle\app\service\core\recycle_order\DeviceCheckDisplayService;

    $socket = getenv('HSX_CATALOG_TEST_SOCKET') ?: '';
    $directory = realpath(dirname($socket)) ?: '';
    if (!str_starts_with($directory, '/private/tmp/hsx-catalog-mysql.') || !is_file($directory . '/init2.log')) {
        throw new \RuntimeException('Only a dedicated temporary catalog test MySQL socket is allowed');
    }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    $database = 'hsx_catalog_test_' . bin2hex(random_bytes(6));
    $pdo = new \PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
    $pdo->exec('USE `' . $database . '`');
    $count = 0;
    $check = static function (bool $condition, string $message) use (&$count): void { if (!$condition) throw new \RuntimeException('FAIL: ' . $message); $count++; echo "PASS: {$message}\n"; };
    $reject = static function (callable $action, string $message) use ($check): void {
        try { $action(); } catch (\core\exception\CommonException $e) { $check(true, $message . ' / ' . $e->getMessage()); return; }
        $check(false, $message);
    };
    $context = static function (int $site): void { Context::$testSite = $site; Context::$testApp = $site ? 'site' : 'admin'; };
    try {
        $app = new \think\App($directory . '/isolated-app/'); // 不调用 initialize，不加载项目配置或 .env。
        \think\Container::setInstance($app);
        $app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => $directory . '/cache/']]], 'cache');
        $app->config->set(['default' => 'file', 'channels' => ['file' => ['type' => 'File', 'path' => $directory . '/logs/']]], 'log');
        $app->config->set(['default' => 'catalog_test', 'datetime_format' => 'Y-m-d H:i:s', 'auto_timestamp' => true, 'connections' => ['catalog_test' => [
            'type' => 'mysql', 'socket' => $socket, 'database' => $database, 'username' => 'root', 'password' => '',
            'prefix' => 'test_', 'charset' => 'utf8mb4', 'fields_strict' => true, 'trigger_sql' => false,
        ]]], 'database');
        (new \think\service\ModelService($app))->boot();
        $sql = file_get_contents(dirname(__DIR__) . '/sql/install.sql');
        $tables = array_merge(DeviceCatalogConfigService::TABLES, ['recycle_template_binding', 'recycle_device_model_import_task', 'recycle_check_import_batch', 'recycle_printer_template', 'recycle_printer']);
        foreach ($tables as $table) {
            if (!preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `\{\{prefix\}\}' . preg_quote($table, '/') . '`\s*\(.*?;/s', $sql, $matches)) throw new \RuntimeException('Missing DDL: ' . $table);
            $pdo->exec(str_replace('{{prefix}}', 'test_', $matches[0]));
        }
        $pdo->exec('CREATE TABLE test_catalog_test_config (site_id INT NOT NULL, name VARCHAR(80) NOT NULL, value JSON, PRIMARY KEY(site_id,name))');
        $pdo->exec('CREATE TABLE test_recycle_device (id INT PRIMARY KEY, site_id INT NOT NULL, category_id INT, check_template_id INT, info JSON)');
        $pdo->exec('CREATE TABLE test_site (site_id INT PRIMARY KEY, site_name VARCHAR(100), status INT DEFAULT 1)');
        Db::name('site')->insertAll([['site_id'=>100005,'site_name'=>'生产站'],['site_id'=>100006,'site_name'=>'测试站']]);
        Db::name('recycle_device_model_dict')->insertAll([
            ['id'=>11,'site_id'=>100005,'pid'=>0,'level'=>1,'node_name'=>'手机','model_full_name'=>'手机'],
            ['id'=>12,'site_id'=>100005,'pid'=>11,'level'=>2,'node_name'=>'苹果','model_full_name'=>'手机/苹果'],
            ['id'=>13,'site_id'=>100005,'pid'=>12,'level'=>3,'node_name'=>'iPhone 16','model_full_name'=>'手机/苹果/iPhone 16'],
            ['id'=>21,'site_id'=>100006,'pid'=>0,'level'=>1,'node_name'=>'本地','model_full_name'=>'本地'],
            ['id'=>22,'site_id'=>100006,'pid'=>21,'level'=>2,'node_name'=>'本地设备','model_full_name'=>'本地/本地设备'],
        ]);
        Db::name('recycle_device_model_alias')->insert(['id'=>1,'site_id'=>100005,'category_id'=>13,'alias'=>'16','normalized_alias'=>'16']);
        Db::name('recycle_check_template')->insertAll([
            ['id'=>51,'site_id'=>100005,'template_key'=>'iphone','template_name'=>'苹果质检','is_default'=>1],
            ['id'=>61,'site_id'=>100006,'template_key'=>'local','template_name'=>'本站质检','is_default'=>1],
        ]);
        Db::name('recycle_check_group')->insert(['id'=>71,'site_id'=>100005,'template_id'=>51,'group_key'=>'base','group_name'=>'基本信息']);
        Db::name('recycle_check_field')->insert(['id'=>81,'site_id'=>100005,'template_id'=>51,'group_id'=>71,'field_key'=>'capacity','field_name'=>'容量','component'=>'radio']);
        Db::name('recycle_check_option')->insert(['id'=>91,'site_id'=>100005,'field_id'=>81,'option_value'=>'2','option_label'=>'256GB']);
        Db::name('recycle_check_dict')->insert(['id'=>101,'site_id'=>100005,'dict_type'=>'option','text'=>'256GB']);
        Db::name('recycle_template_binding')->insert(['id'=>111,'site_id'=>100005,'target_type'=>'model_dict','target_id'=>13,'scene_key'=>'manual_device_label','check_template_id'=>51,'print_template_id'=>121,'inherit_enabled'=>1,'status'=>1]);
        Db::name('recycle_printer_template')->insert(['template_id'=>121,'site_id'=>100005,'template_name'=>'本站标签','content'=>'{}','html_content'=>'','instruction_content'=>'','variables'=>'[]']);
        Db::name('recycle_device')->insert(['id'=>131,'site_id'=>100005,'category_id'=>13,'check_template_id'=>51,'info'=>json_encode(['capacity'=>'2'])]);
        $deviceBefore = Db::name('recycle_device')->where('id',131)->find();
        $check(!empty($deviceBefore), 'business device fixture exists');
        $ownBefore = Db::name('recycle_device_model_dict')->where('site_id',100006)->select()->toArray();
        $platform = new DeviceCatalogConfigService();
        $preview = $platform->preview();
        $check(!$preview['blockers'] && $preview['counts']['recycle_device_model_dict'] === 3, 'only 100005 enters preview');
        $reject(fn()=> $platform->promote('stale'), 'stale preview rejected without mutation');
        Db::name('recycle_check_field')->where('id',81)->update(['group_id'=>999]);
        $reject(fn()=> $platform->promote($platform->preview()['fingerprint']), 'broken parent rejected before promotion');
        Db::name('recycle_check_field')->where('id',81)->update(['group_id'=>71]);
        $context(100006);
        $reject(fn()=> (new DeviceCatalogConfigService())->promote($preview['fingerprint']), 'tenant cannot promote');
        $reject(fn()=> (new DeviceCatalogConfigService())->grant(100006,true), 'tenant cannot grant itself');
        $reject(fn()=> (new DeviceCatalogConfigService())->choose(true), 'uninitialized public library unavailable');
        $context(0);
        \app\service\core\sys\CoreConfigService::$failNextWrite = true;
        $reject(fn()=> $platform->promote($platform->preview()['fingerprint']), 'failed config write rolls promotion back');
        $check((int)Db::name('recycle_check_option')->where('id',91)->value('site_id')===100005
            && !Db::name('recycle_template_binding')->where('site_id',0)->count(), 'transaction rollback restores catalog ownership and bindings');
        $platform->promote($platform->preview()['fingerprint']);
        $check((int)Db::name('recycle_device_model_dict')->where('id',13)->value('site_id')===0, 'original model ID promoted in place');
        foreach (['recycle_check_template'=>51,'recycle_check_group'=>71,'recycle_check_field'=>81,'recycle_check_option'=>91] as $table=>$id) $check((int)Db::name($table)->where('id',$id)->value('site_id')===0, $table . ' ID unchanged');
        $check(Db::name('recycle_device')->where('id',131)->find()===$deviceBefore, 'existing device and inspection data unchanged');
        $check(Db::name('recycle_device_model_dict')->where('site_id',100006)->select()->toArray()===$ownBefore, 'other site data not copied or deleted');
        $check((int)Db::name('recycle_template_binding')->where('site_id',0)->value('print_template_id')===0, 'printer binding never shared');
        $check((int)Db::name('recycle_template_binding')->where('id',111)->value('print_template_id')===121, 'original printer binding retained');
        $check((int)Db::name('recycle_template_binding')->where('id',111)->value('check_template_id')===0, 'local binding no longer selects a public template when disabled');
        $check($platform->promote('')['already_initialized'], 'promotion is idempotent');
        $check((new DeviceCatalogPolicy())->forSite(100005)['effective'], '100005 automatically uses public library');
        $context(100005);
        $schema=(new RecycleCheckTemplateService())->schema(['device_id'=>131]);
        $check((int)$schema['template']['id']===51 && $schema['groups'][0]['fields'][0]['options'][0]['label']==='256GB', 'original device resolves public template and option label');
        $check((int)(new RecycleTemplateBindingService())->resolveByTarget('model_dict',13)['print_template_id']===121, 'public inspection with original site printing');
        $display=(new DeviceCheckDisplayService())->enrichDevices([$deviceBefore],100005);
        $check(($display[0]['check_summary'][0]['label'] ?? '')==='256GB', 'read-only device summary shows text not option key');
        $apiClass = new \ReflectionClass(\addon\hsx_recycle\app\service\api\recycle_order\RecycleOrderService::class);
        $api = $apiClass->newInstanceWithoutConstructor();
        $siteProperty = $apiClass->getProperty('site_id'); $siteProperty->setAccessible(true); $siteProperty->setValue($api,100005);
        $optionMethod = $apiClass->getMethod('getTemplateOptionMap'); $optionMethod->setAccessible(true);
        $check($optionMethod->invoke($api,[51])[51]['capacity']['2']['label']==='256GB', 'customer order option label resolves public template');
        $severityMethod = $apiClass->getMethod('getOptionSeverityMap'); $severityMethod->setAccessible(true);
        $check(array_key_exists('256gb',$severityMethod->invoke($api)), 'customer order severity dictionary follows selected library');
        $check((new \addon\hsx_recycle\app\model\RecycleDevice())->getCheckTemplateNameAttr(null,$deviceBefore)==='苹果质检', 'customer device template name remains visible');
        $reject(fn()=> (new RecycleDeviceModelDictService())->quickAdd('手机/新型号'), 'shared consumer cannot write');
        $context(100006);
        $core = new CoreRecycleDeviceModelDictService();
        $check(array_column($core->searchLeafOptions(100006),'id')===[22], 'switch off selects only local data');
        (new DeviceCatalogConfigService())->choose(true);
        $check(array_column($core->searchLeafOptions(100006),'id')===[13], 'switch on selects only public data');
        $check(array_column((new RecycleCheckTemplateService())->getPage([])['data'],'id')===[51], 'public template list excludes local template');
        $reject(fn()=> (new RecycleDeviceModelDictService())->selectableLeaf(22), 'hidden local ID cannot be selected by API');
        $reject(fn()=> (new RecycleCheckTemplateService())->editTemplate(51,['template_name'=>'unauthorized']), 'shared templates reject writes');
        $context(0); $platform->grant(100006,true);
        $context(100006);
        (new RecycleDeviceModelDictService())->quickAdd('手机/测试新增');
        $check((int)Db::name('recycle_device_model_dict')->where('node_name','测试新增')->value('site_id')===0, 'authorized site creates in public library only');
        (new RecycleCheckTemplateService())->saveField(['id'=>81,'template_id'=>51,'group_id'=>71,'field_key'=>'capacity','field_name'=>'容量','component'=>'radio','extra_config'=>['summary_visible'=>1]]);
        $check((int)Db::name('recycle_check_field')->where('id',81)->value('template_id')===51, 'shared template summary configuration preserves IDs');
        (new RecycleDeviceModelDictService())->edit(13, ['path'=>['手机','苹果','iPhone 16 更新'], 'node_name'=>'iPhone 16 更新']);
        $check((int)Db::name('recycle_device')->where('id',131)->value('category_id')===13 && Db::name('recycle_device_model_dict')->where('id',13)->count()===1, 'public model editing preserves referenced ID');
        $context(0); $platform->grant(100006,false);
        $context(100006);
        $reject(fn()=> (new RecycleDeviceModelDictService())->quickAdd('手机/撤权后新增'), 'revoked grant enforced by backend');
        (new DeviceCatalogConfigService())->choose(false);
        $check(array_column($core->searchLeafOptions(100006),'id')===[22], 'switch back restores original local list');
        $check((int)(new RecycleCheckTemplateService())->schema(['template_id'=>61])['template']['id']===61, 'local template still available after switch off');
        $context(0);
        $reject(fn()=> (new RecycleDeviceModelDictService())->delete(13), 'public model referenced by a device cannot be deleted');
        $reject(fn()=> (new RecycleCheckTemplateService())->deleteTemplate(51), 'public template referenced by a device cannot be deleted');
        Db::name('recycle_check_template')->insert(['id'=>151,'site_id'=>0,'template_key'=>'bound_only','template_name'=>'已绑定未使用']);
        Db::name('recycle_device_model_dict')->insert(['id'=>152,'site_id'=>0,'pid'=>0,'node_name'=>'绑定型号','model_full_name'=>'绑定型号','level'=>1]);
        Db::name('recycle_template_binding')->insert(['site_id'=>0,'target_type'=>'model_dict','target_id'=>152,'scene_key'=>'manual_device_label','check_template_id'=>151]);
        $reject(fn()=> (new RecycleCheckTemplateService())->deleteTemplate(151), 'bound public template cannot be deleted');
        $reject(fn()=> (new RecycleDeviceModelDictService())->delete(152), 'bound public model cannot be deleted');
        Db::name('recycle_device_model_dict')->insert(['id'=>153,'site_id'=>0,'pid'=>0,'node_name'=>'未用型号','model_full_name'=>'未用型号','level'=>1]);
        Db::name('recycle_device_model_alias')->insert(['site_id'=>0,'category_id'=>153,'alias'=>'测试别名','normalized_alias'=>'test-delete']);
        (new RecycleDeviceModelDictService())->delete(153);
        $check(!Db::name('recycle_device_model_dict')->where('id',153)->count() && !Db::name('recycle_device_model_alias')->where('category_id',153)->count(), 'unused model deletion removes dangling aliases');
        // 模拟手工 SQL 把打印绑定也转到 0；只在本脚本创建的隔离库操作。
        $sharedQuery = static fn()=> Db::name('recycle_template_binding')->where('site_id',0)->where('target_id',13)->where('scene_key','manual_device_label');
        $localQuery = static fn()=> Db::name('recycle_template_binding')->where('site_id',100005)->where('target_id',13)->where('scene_key','manual_device_label');
        $localQuery()->delete();
        $sharedQuery()->update(['print_template_id'=>121]);
        $context(0);
        $reject(fn()=> (new DeviceCatalogPrintRecoveryService())->preview(), 'platform cannot recover tenant printing implicitly');
        $context(100006);
        $reject(fn()=> (new DeviceCatalogPrintRecoveryService())->preview(), 'local-library site cannot run public print recovery');
        (new DeviceCatalogConfigService())->choose(true);
        $otherPreview = (new DeviceCatalogPrintRecoveryService())->preview();
        $check($otherPreview['items']===[], 'other site cannot see someone else\'s print references');
        $context(100005);
        $recovery = new DeviceCatalogPrintRecoveryService();
        $preview = $recovery->preview();
        $check($preview['restore']===1 && $preview['blocked']===0, 'manual migration detected from actual print-template ownership');
        $check(!$localQuery()->count() && (int)$sharedQuery()->value('print_template_id')===121, 'preview is read only');
        $reject(fn()=> $recovery->restore($otherPreview['fingerprint']), 'fingerprint cannot be reused across sites');
        $sharedQuery()->update(['remark'=>'changed since preview']);
        $reject(fn()=> $recovery->restore($preview['fingerprint']), 'print recovery rejects a changed preview');
        $beforeRecovery = $sharedQuery()->find();
        $pdo->exec("CREATE TRIGGER reject_print_cleanup BEFORE UPDATE ON test_recycle_template_binding FOR EACH ROW BEGIN IF OLD.site_id = 0 AND OLD.print_template_id = 121 AND NEW.print_template_id = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected print cleanup failure'; END IF; END");
        $failed = false;
        try { $recovery->restore($recovery->preview()['fingerprint']); }
        catch (\Throwable $e) { $failed = true; }
        finally { $pdo->exec('DROP TRIGGER reject_print_cleanup'); }
        $check($failed && !$localQuery()->count() && $sharedQuery()->find()===$beforeRecovery, 'cleanup failure rolls back inserted site binding and preserves public binding');
        $result = $recovery->restore($recovery->preview()['fingerprint']);
        $check($result['restored']===1 && $result['cleaned']===1 && $result['skipped']===0, 'recovery reports exact restored and cleaned counts');
        $check((int)$localQuery()->value('print_template_id')===121 && (int)$localQuery()->value('check_template_id')===0, 'restored site binding contains printing only');
        $check((int)$sharedQuery()->value('check_template_id')===51 && (int)$sharedQuery()->value('print_template_id')===0, 'public inspection binding remains public');
        $resolved = (new RecycleTemplateBindingService())->resolveByTarget('model_dict',13);
        $check((int)$resolved['check_template_id']===51 && (int)$resolved['print_template_id']===121, 'business resolver combines public inspection and recovered site label');
        $result = $recovery->restore($recovery->preview()['fingerprint']);
        $check($result['restored']===0 && $localQuery()->count()===1, 'repeat recovery creates no duplicates');
        $sharedQuery()->update(['print_template_id'=>121]);
        $localQuery()->update(['print_template_id'=>0,'remark'=>'keep local decision']);
        $preview = $recovery->preview();
        $check($preview['blocked']===1 && $preview['restore']===0, 'existing different site configuration is a conflict');
        $recovery->restore($preview['fingerprint']);
        $check((int)$localQuery()->value('print_template_id')===0 && (int)$sharedQuery()->value('print_template_id')===121, 'conflict preserves both references for manual review');
        $localQuery()->update(['print_template_id'=>121]);
        $preview = $recovery->preview();
        $check($preview['clean']===1 && $preview['restore']===0, 'matching site configuration needs cleanup only');
        $recovery->restore($preview['fingerprint']);
        $check($localQuery()->value('remark')==='keep local decision' && $localQuery()->count()===1, 'matching cleanup preserves existing site metadata');
        $sharedQuery()->update(['print_template_id'=>121]);
        Db::name('recycle_printer_template')->where('template_id',121)->update(['delete_time'=>time()]);
        $check($recovery->preview()['blocked']===1, 'deleted original print template is not restored');
        Db::name('recycle_printer_template')->where('template_id',121)->update(['delete_time'=>0]);
        Db::name('recycle_template_binding')->insert(['site_id'=>0,'target_type'=>'model_dict','target_id'=>99999,'scene_key'=>'manual_device_label','print_template_id'=>121]);
        $check($recovery->preview()['blocked']===1, 'unknown shared target cannot be guessed');
        Db::name('recycle_template_binding')->where('target_id',99999)->delete();
        Db::name('recycle_printer_template')->insert(['template_id'=>122,'site_id'=>100006,'template_name'=>'别站标签','content'=>'{}','html_content'=>'','instruction_content'=>'','variables'=>'[]']);
        Db::name('recycle_template_binding')->insert(['site_id'=>0,'target_type'=>'global','target_id'=>0,'scene_key'=>'other_scene','print_template_id'=>122]);
        $recovery->restore($recovery->preview()['fingerprint']);
        $check((int)Db::name('recycle_template_binding')->where('scene_key','other_scene')->value('print_template_id')===122, 'recovery never cleans another site\'s print binding');

        // 同一份交付 SQL 在隔离库执行：只替换库选择和表前缀，不能访问真实数据库。
        $repairSql = file_get_contents(dirname(__DIR__) . '/sql/repair_public_print_bindings_100005_ns.sql');
        $repairSql = preg_replace('/^--.*$/m', '', $repairSql);
        $repairSql = str_replace(['USE `saas_`;', 'ns_'], ['', 'test_'], $repairSql);
        $runRepairSql = static function() use($pdo,$repairSql): void {
            try {
                foreach (explode(';', $repairSql) as $statement) {
                    if (trim($statement)==='') continue;
                    $cursor = $pdo->query($statement); $cursor->closeCursor();
                }
            } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        };
        $localQuery()->delete(); $sharedQuery()->update(['print_template_id'=>121]);
        $runRepairSql();
        $check((int)$localQuery()->value('print_template_id')===121 && (int)$localQuery()->value('check_template_id')===0, 'delivered SQL restores site printing without copying inspection');
        $check((int)$sharedQuery()->value('print_template_id')===0 && (int)$sharedQuery()->value('check_template_id')===51, 'delivered SQL cleans only public print reference');
        $runRepairSql();
        $check($localQuery()->count()===1, 'delivered SQL is idempotent');
        $sharedQuery()->update(['print_template_id'=>121]); $localQuery()->update(['print_template_id'=>0]);
        $runRepairSql();
        $check((int)$localQuery()->value('print_template_id')===0 && (int)$sharedQuery()->value('print_template_id')===121, 'delivered SQL does not overwrite or clear conflicts');
        $check(Db::name('recycle_device')->where('id',131)->find()===$deviceBefore, 'both recovery paths leave all business device data unchanged');
        $check((int)Db::name('recycle_template_binding')->where('scene_key','other_scene')->value('print_template_id')===122, 'delivered SQL preserves other sites');
        // 公共标签直接消费；修改必须另存本站。新站不依赖型号库开关或新建站点时复制数据。
        $context(0);
        $printService = new RecyclePrinterTemplateService();
        $layout = ['width'=>58,'height'=>40,'elements'=>[['id'=>'t1','type'=>'text','x'=>8,'y'=>8,'font'=>9,'content'=>'{{model}}']]];
        $publicId = (int)$printService->add(['template_name'=>'公共设备标签','template_type'=>'device_label','width'=>58,'height'=>40,'template_data'=>$layout,'is_default'=>1,'printer_id'=>999,'trigger_event'=>'complete']);
        $publicRow = Db::name('recycle_printer_template')->where('template_id',$publicId)->find();
        $check((int)$publicRow['site_id']===0 && (int)$publicRow['printer_id']===0 && $publicRow['trigger_event']==='', 'platform public template cannot bind a site printer or auto trigger');
        $secondPublicId = (int)$printService->add(['template_name'=>'另一公共标签','template_type'=>'device_label','width'=>58,'height'=>40,'template_data'=>$layout]);
        $check($secondPublicId!==$publicId && (int)Db::name('recycle_printer_template')->where('template_id',$publicId)->value('is_default')===1, 'creating another template cannot replace an earlier model instance');
        $printService->edit($publicId,['template_name'=>'公共标签更新']);
        $check((int)$printService->getDefaultTemplate('device_label')['template_id']===$publicId, 'editing public contents preserves public default');
        $check(empty($printService->getInfo(121)), 'platform template access does not expose tenant private template');
        $context(100007);
        $freshService = new RecyclePrinterTemplateService();
        $check((int)$freshService->getDefaultTemplate('device_label')['template_id']===$publicId, 'brand new site immediately uses public default without copying data');
        $check(Db::name('recycle_printer_template')->where('site_id',100007)->count()===0, 'reading public default creates no site rows');
        $beforeRead = Db::name('recycle_printer_template')->where('template_id',$publicId)->find();
        $info = $freshService->getInfo($publicId);
        $check($info['is_public']===true && $info['can_edit']===false && (int)$info['printer_id']===0, 'shared info exposes origin and read only status');
        $check(Db::name('recycle_printer_template')->where('template_id',$publicId)->find()===$beforeRead, 'reading shared print template performs no migration writes');
        $ids = array_column($freshService->getPage([])['data'],'template_id');
        $check(in_array($publicId,$ids) && !in_array(121,$ids) && !in_array(122,$ids), 'fresh site sees public templates but no other sites private labels');
        $reject(fn()=> $freshService->edit($publicId,['template_name'=>'bad','is_default'=>1,'template_type'=>'device_label']), 'direct public edit rejected before any default changes');
        $reject(fn()=> $freshService->del($publicId), 'site cannot delete public template');
        $reject(fn()=> $freshService->modifyStatus($publicId,0), 'site cannot disable public template');
        $reject(fn()=> $freshService->copyToSite(121), 'site cannot copy another site private label');
        $freshService->setDefault($secondPublicId);
        $check((int)$freshService->getDefaultTemplate('device_label')['template_id']===$secondPublicId, 'site can choose another public default without duplicating it');
        $check((int)PrinterTemplateLibrary::defaultTemplate(100008,'device_label')['template_id']===$publicId, 'site public-default preference cannot alter another site');
        $copyId = $freshService->copyToSite($publicId);
        $copy = Db::name('recycle_printer_template')->where('template_id',$copyId)->find();
        $check((int)$copy['site_id']===100007 && (int)$copy['printer_id']===0 && (int)$copy['is_default']===0 && $copy['trigger_event']==='', 'site copy has new ID and no inherited printer, trigger or default');
        $freshService->edit($copyId,['template_name'=>'本站定制','template_data'=>array_merge($layout,['width'=>60])]);
        $check(Db::name('recycle_printer_template')->where('template_id',$publicId)->find()===$beforeRead, 'editing copy leaves public original completely unchanged');
        $freshService->setDefault($copyId);
        $check((int)$freshService->getDefaultTemplate('device_label')['template_id']===$copyId, 'site custom template can become its default');
        $freshService->modifyStatus($copyId,0);
        $check($freshService->getDefaultTemplate('device_label')===[], 'disabled explicit default does not silently print another layout');
        $freshService->modifyStatus($copyId,1);
        $freshService->del($copyId);
        $check((int)$freshService->getDefaultTemplate('device_label')['template_id']===$publicId, 'removing site default permits documented public fallback');
        $context(0);
        $printService->modifyStatus($secondPublicId,0);
        $context(100007);
        $check($freshService->getInfo($secondPublicId)===[], 'disabled public template cannot be selected or printed by site');
        $reject(fn()=> $freshService->setDefault($secondPublicId), 'cannot set disabled public template as site default');
        $bindingService = new RecycleTemplateBindingService();
        $bindingService->save(['target_type'=>'global','target_id'=>0,'scene_key'=>'manual_device_label','print_template_id'=>$publicId,'inherit_enabled'=>1,'status'=>1]);
        $resolved = $bindingService->resolveByTarget('global',0);
        $check((int)$resolved['print_template_id']===$publicId && $resolved['print_template_name']==='公共标签更新', 'site binding can directly select shared label and resolve its name');
        $options = $bindingService->getInfo('global',0)['print_template_options'];
        $check(in_array($publicId,array_column($options,'template_id')) && !in_array(121,array_column($options,'template_id')), 'business selector exposes public labels but never another site labels');
        $context(0);
        (new RecycleTemplateBindingService())->save(['target_type'=>'model_dict','target_id'=>13,'scene_key'=>'manual_device_label','check_template_id'=>51,'print_template_id'=>$publicId,'inherit_enabled'=>1,'status'=>1]);
        $context(100006);
        $resolved = (new RecycleTemplateBindingService())->resolveByTarget('model_dict',13);
        $check((int)$resolved['print_template_id']===$publicId, 'public model binding can use a genuinely public print template');
        $context(100005);
        $localQuery()->update(['print_template_id'=>121]);
        $check((int)(new RecycleTemplateBindingService())->resolveByTarget('model_dict',13)['print_template_id']===121, 'existing site label binding takes priority over public label');
        $context(0);
        $printService->edit($publicId,['template_name'=>'公共新版本']);
        $context(100007);
        $check($freshService->getInfo($publicId)['template_name']==='公共新版本', 'public original updates are visible to shared consumers');
        Db::name('recycle_printer')->insert(['printer_id'=>501,'site_id'=>100005,'printer_name'=>'别站打印机','brand'=>'xpyun','sn'=>'test','uid'=>7]);
        Db::name('recycle_printer_template')->where('template_id',$publicId)->update(['printer_id'=>501]);
        $check((int)$freshService->getInfo($publicId)['printer_id']===0 && $freshService->getDefaultPrinter(501)===[], 'even polluted public printer ID cannot route work to another site printer');
        $check(Db::name('recycle_device')->where('id',131)->find()===$deviceBefore, 'shared printing changes no business devices or orders');
        $context(0);
        foreach (['admin', 'site'] as $type) {
            $menus=require dirname(__DIR__) . '/app/dict/menu/' . $type . '.php';
            $apis=[]; $keys=[];
            $walk=function(array $rows)use(&$walk,&$apis,&$keys){foreach($rows as $row){$keys[]=$row['menu_key'];$apis[strtolower($row['methods']??'').' '.($row['api_url']??'')]=true;if(!empty($row['children']))$walk($row['children']);}};
            $walk($menus);
            foreach ([['get','printer_template/lists'],['get','printer_template/<id>'],['post','printer_template'],['put','printer_template/<id>'],['post','printer_template/default/<id>'],['get','printer_template/variables']] as [$method,$path]) {
                $check(isset($apis[$method.' recycle/'.$path]), $type.' role covers '.$method.' '.$path);
            }
            if ($type === 'admin') {
                $check(in_array('hsx_recycle_platform_print',$keys) && in_array('hsx_recycle_platform_print_edit',$keys), 'platform has public template list and editor menus');
                $check(!isset($apis['post recycle/printer_template/test_print/<id>']), 'platform template menu does not grant real printing');
            }
            if ($type === 'site') $check(isset($apis['post recycle/printer_template/copy/<id>']), 'site role covers copy-to-site operation');
            if ($type === 'site') foreach (['get','post'] as $method) $check(isset($apis[$method.' recycle/catalog/print_recovery']), 'site role covers '.$method.' print recovery');
            preg_match_all("/Route::(get|post|put|delete)\\(\x27((?:recycle_device_model_dict|template_binding|check_template|check_catalog)[^\x27]*)\x27/", file_get_contents(dirname(__DIR__) . '/app/adminapi/route/route.php'), $routes, PREG_SET_ORDER);
            foreach($routes as $route){$path=preg_replace('/:(\\w+)/','<$1>',$route[2]);$check(isset($apis[$route[1].' recycle/'.$path]), $type . ' role covers ' . $route[1] . ' ' . $path);}
        }
        // 真实问题：导入模板仍有名称和绑定，但 schema_json / 明细字段均为空。
        $context(0);
        $catalog = new \addon\hsx_recycle\app\service\admin\check\RecycleCheckCatalogService();
        $processModel = new \ReflectionMethod($catalog, 'processModel');
        $processModel->setAccessible(true);
        $append = static function (array $rows) use ($catalog, $processModel): array {
            $options=[]; $nodes=[]; $created=0; $bound=0; $skipped=0;
            Db::transaction(function () use ($catalog,$processModel,$rows,&$options,&$nodes,&$created,&$bound,&$skipped) {
                $args=[$rows,time(),&$options,&$nodes,&$created,&$bound,&$skipped,'append'];
                $processModel->invokeArgs($catalog,$args);
            });
            return compact('created','bound','skipped');
        };
        Db::name('recycle_device_model_dict')->insert(['id'=>800,'site_id'=>0,'node_name'=>'iPhone Air','model_full_name'=>'手机/iPhone Air','product_source_id'=>'225366','source'=>'recycle_spider','node_type'=>'model']);
        Db::name('recycle_check_template')->insert(['id'=>801,'site_id'=>0,'template_key'=>'pjt_empty_air','template_name'=>'拍机堂·iPhone Air','scene'=>'pjt']);
        Db::name('recycle_template_binding')->insert(['site_id'=>0,'target_type'=>'model_dict','target_id'=>800,'scene_key'=>'manual_device_label','check_template_id'=>801,'print_template_id'=>$publicId]);
        $empty = (new RecycleCheckTemplateService())->schema(['template_id'=>801]);
        $check($empty['groups']===[] && $empty['schema_status']==='missing' && $empty['schema_message']!=='', 'empty imported schema explains missing content instead of pretending success');
        $rows=[['苹果_iPhone_Air','225366','存储容量','基本问题','256G','256G | 512G | 1T'],['苹果_iPhone_Air','225366','机身颜色','基本问题','天蓝色','深空黑色 | 天蓝色 | 浅金色 | 云白色']];
        $result=$append($rows);
        $check($result===['created'=>0,'bound'=>1,'skipped'=>0], 'append repairs bound but empty imported template');
        $bindingAfter=Db::name('recycle_template_binding')->where('site_id',0)->where('target_id',800)->find();
        $check((int)$bindingAfter['check_template_id']===801 && (int)$bindingAfter['print_template_id']===$publicId, 'repair retains template ID and print binding');
        $context(100005);
        $air=(new RecycleCheckTemplateService())->schema(['category_id'=>800]);
        $airFields=array_merge(...array_column($air['groups'],'fields'));
        $check(count($airFields)===5 && $airFields[0]['options'][0]['label']==='256G' && $airFields[1]['options'][1]['label']==='天蓝色', 'public consumer sees exact capacity/color plus standard information fields');
        $context(0);
        $field=$airFields[0]; $field['template_id']=801; $field['extra_config']=['summary_visible'=>1];
        (new RecycleCheckTemplateService())->saveField($field);
        $snapshot=Db::name('recycle_check_template')->where('id',801)->find();
        $check($append($rows)['skipped']===1 && Db::name('recycle_check_template')->where('id',801)->find()===$snapshot, 'repeat append preserves restored schema and custom summary settings');
        $check((new RecycleCheckTemplateService())->schema(['template_id'=>801])['groups'][0]['fields'][0]['extra_config']['summary_visible']===1, 'compact summary is saved and reloadable');
        Db::name('recycle_device_model_dict')->insertAll([
            ['id'=>810,'site_id'=>0,'node_name'=>'A','product_source_id'=>'810','source'=>'recycle_spider','node_type'=>'model'],
            ['id'=>811,'site_id'=>0,'node_name'=>'B','product_source_id'=>'811','source'=>'recycle_spider','node_type'=>'model'],
        ]);
        Db::name('recycle_check_template')->insert(['id'=>812,'site_id'=>0,'template_key'=>'pjt_shared_empty','template_name'=>'共享空模板','scene'=>'pjt']);
        foreach([810,811] as $id) Db::name('recycle_template_binding')->insert(['site_id'=>0,'target_type'=>'model_dict','target_id'=>$id,'scene_key'=>'manual_device_label','check_template_id'=>812]);
        $append([['A','810','容量','基本问题','128G','128G']]);
        $check((int)Db::name('recycle_template_binding')->where('target_id',810)->value('check_template_id')!==812
            && (int)Db::name('recycle_template_binding')->where('target_id',811)->value('check_template_id')===812
            && !Db::name('recycle_check_template')->where('id',812)->value('schema_json'), 'shared empty template is not overwritten with one model data');
        Db::name('recycle_check_template')->where('id',812)->update(['scene'=>'phone']);
        $check($append([['B','811','容量','基本问题','128G','128G']])['skipped']===1, 'manual empty template remains untouched by append import');
        Db::name('recycle_check_template')->where('id',812)->update(['scene'=>'pjt','schema_json'=>'{broken']);
        $check($append([['B','811','容量','基本问题','128G','128G']])['skipped']===1, 'malformed nonempty source is not silently overwritten');
        $check((new RecycleCheckTemplateService())->schema(['template_id'=>812])['schema_status']==='invalid', 'malformed schema is identified rather than reported as a new empty template');
        Db::name('recycle_check_template')->where('id',812)->update(['schema_json'=>'{"groups":"broken"}']);
        $check((new RecycleCheckTemplateService())->schema(['template_id'=>812])['schema_status']==='invalid', 'invalid group shape returns a clear diagnostic without PHP warnings');
        Db::name('recycle_check_template')->where('id',812)->update(['schema_json'=>json_encode(['groups'=>[['status'=>0,'fields'=>[['field_key'=>'capacity','field_name'=>'容量']]]]])]);
        $check((new RecycleCheckTemplateService())->schema(['template_id'=>812])['schema_status']==='empty'
            && $append([['B','811','容量','基本问题','128G','128G']])['skipped']===1, 'hidden groups are diagnosed separately and never overwritten by append');
        $check(Db::name('recycle_device')->where('id',131)->find()===$deviceBefore, 'empty template repair never alters business devices');
        // 一个来源产品对应多个型号节点：已有正常绑定不覆盖，其余缺失逐个补齐。
        $originalBinding = Db::name('recycle_template_binding')->where('site_id',0)->where('target_id',800)->find();
        $originalSchema = Db::name('recycle_check_template')->where('id',801)->find();
        Db::name('recycle_device_model_dict')->insertAll([
            ['id'=>820,'site_id'=>0,'node_name'=>'Air 分类二','product_source_id'=>'225366','source'=>'recycle_spider','node_type'=>'model','status'=>1],
            ['id'=>821,'site_id'=>0,'node_name'=>'Air 分类三','product_source_id'=>'225366','source'=>'recycle_spider','node_type'=>'model','status'=>1],
            ['id'=>822,'site_id'=>0,'node_name'=>'Air 手工','product_source_id'=>'225366','source'=>'manual','node_type'=>'model','status'=>1],
            ['id'=>823,'site_id'=>0,'node_name'=>'Air 品牌','product_source_id'=>'225366','source'=>'recycle_spider','node_type'=>'brand','status'=>1],
            ['id'=>824,'site_id'=>0,'node_name'=>'Air 停用','product_source_id'=>'225366','source'=>'recycle_spider','node_type'=>'model','status'=>0],
            ['id'=>825,'site_id'=>100006,'node_name'=>'Air 私有','product_source_id'=>'225366','source'=>'recycle_spider','node_type'=>'model','status'=>1],
        ]);
        Db::name('recycle_template_binding')->insert(['site_id'=>0,'target_type'=>'model_dict','target_id'=>821,'scene_key'=>'manual_device_label','check_template_id'=>0,'print_template_id'=>$publicId]);
        $result = $append($rows);
        $check($result['bound']===2 && $result['skipped']===0, 'one product import repairs every missing enabled model, even if the first model is already bound');
        $check(Db::name('recycle_template_binding')->where('site_id',0)->where('target_id',800)->find()===$originalBinding
            && Db::name('recycle_check_template')->where('id',801)->find()===$originalSchema, 'existing bound model and its customized template remain byte-for-byte unchanged');
        $check((int)Db::name('recycle_template_binding')->where('target_id',821)->value('print_template_id')===$publicId, 'duplicate product repair preserves independent printer association');
        $check(Db::name('recycle_template_binding')->whereIn('target_id',[822,823,824,825])->count()===0, 'same numeric product ID never binds another source, brand, disabled model or private site');
        $check((int)Db::name('recycle_template_binding')->where('target_id',820)->value('check_template_id')
            ===(int)Db::name('recycle_template_binding')->where('target_id',821)->value('check_template_id'), 'same source product uses one shared imported schema');
        $beforeBindings = Db::name('recycle_template_binding')->count();
        $check($append($rows)['skipped']===1 && Db::name('recycle_template_binding')->count()===$beforeBindings, 'repeated multi-model append is idempotent');
        $context(100005);
        $check(!empty((new RecycleCheckTemplateService())->schema(['category_id'=>820])['groups']), 'shared-library consuming site resolves newly repaired model');
        $check(Db::name('recycle_device')->where('id',131)->find()===$deviceBefore, 'multi-model import leaves all existing device facts untouched');
        echo "PASS {$count} checks; isolated database only\n";
    } finally { $pdo->exec('DROP DATABASE `' . $database . '`'); }
}
