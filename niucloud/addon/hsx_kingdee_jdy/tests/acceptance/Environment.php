<?php
declare(strict_types=1);

namespace HsxJdyAcceptance;

use addon\hsx_erp\app\listener\ErpMasterDataLookupRequested;
use addon\hsx_erp\app\service\admin\ErpFinanceService;
use addon\hsx_erp\app\service\admin\ErpIntegrationService;
use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use addon\hsx_erp\app\service\admin\ErpSaleService;
use addon\hsx_erp\app\service\admin\ErpPurchaseReturnService;
use addon\hsx_erp\app\service\admin\ErpSaleReturnService;
use addon\hsx_kingdee_jdy\app\listener\LedgerRecorded;
use addon\hsx_kingdee_jdy\app\service\ConfigService;
use addon\hsx_kingdee_jdy\app\service\TaskService;
use addon\hsx_kingdee_jdy\app\support\Problem;
use think\facade\Db;

// This harness is CLI-only and never loaded by the plugin's production routes.
final class Environment
{
    public const SITE = 900000003;
    public const STEPS = ['采购入库', '支付采购款', '销售出库', '收取销售款', '销售退货', '退还客户款', '采购退货', '收取供应商退款'];
    public \think\App $app;
    public array $state;

    public static function path(): string
    {
        $path = (string)getenv('HSX_JDY_ACCEPTANCE_STATE');
        if (!preg_match('#^/tmp/hsx-jdy-acceptance-[0-9]+/state\.json$#D', $path)) throw new \RuntimeException('Acceptance state path required');
        return $path;
    }

    public static function boot(bool $create = false, array $input = []): self
    {
        if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) throw new \RuntimeException('CLI only');
        foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'stream_socket_client'] as $fn) {
            if (function_exists($fn)) throw new \RuntimeException('External HTTP must be disabled');
        }
        if (ini_get('allow_url_fopen')) throw new \RuntimeException('URL streams must be disabled');
        $path = self::path();
        $root = dirname(__DIR__, 4);
        require_once $root . '/vendor/autoload.php';
        $self = new self();
        $self->app = new \think\App($root);
        $self->app->loadEnv();
        require_once $self->app->getThinkPath() . 'helper.php';
        require_once $root . '/app/common.php';
        $database = require $root . '/config/database.php';
        $original = $database['connections'][$database['default']];
        if (($original['type'] ?? '') !== 'mysql' || !in_array($original['hostname'], ['localhost', '127.0.0.1'], true)
            || !empty($original['deploy']) || !ctype_digit((string)$original['hostport'])) throw new \RuntimeException('Local MySQL only');
        $new = !is_file($path);
        if ($new && !$create) throw new \RuntimeException('Run setup first');
        $self->state = $new ? ['schema' => 'hsx_jdy_acceptance_' . bin2hex(random_bytes(8)), 'key' => bin2hex(random_bytes(32)),
            'auth_key' => bin2hex(random_bytes(32)), 'created_at' => time(), 'ready' => false] : json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $schema = $self->state['schema'];
        if (!preg_match('/^hsx_jdy_acceptance_[a-f0-9]{16}$/D', $schema) || $schema === $original['database']) throw new \RuntimeException('Isolation guard failed');
        if (!$new && empty($self->state['ready']) && !$create) throw new \RuntimeException('Run setup to finish initialization');
        $pdo = new \PDO('mysql:host=127.0.0.1;port=' . $original['hostport'] . ';charset=utf8mb4', $original['username'], $original['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false]);
        $prefix = $original['prefix'];
        if ($new) {
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
            chmod(dirname($path), 0700);
            $self->persist();
            $pdo->exec('CREATE DATABASE ' . self::quote($schema) . ' CHARACTER SET utf8mb4');
            $pdo->exec('USE ' . self::quote($schema));
            $q = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
            $q->execute([$original['database']]);
            foreach ($q->fetchAll(\PDO::FETCH_COLUMN) as $table) {
                $short = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : '';
                if (!str_starts_with($short, 'erp_') && !in_array($short, ['site', 'site_group', 'sys_config', 'sys_user', 'sys_user_role', 'sys_schedule', 'member', 'addon'], true)) continue;
                $pdo->exec('CREATE TABLE ' . self::quote($table) . ' LIKE ' . self::quote($original['database']) . '.' . self::quote($table));
            }
            $pdo->exec(str_replace('{{prefix}}', $prefix, file_get_contents(dirname(__DIR__, 2) . '/sql/install.sql')));
            $pdo->exec('CREATE TABLE ' . self::quote($prefix . 'jdy_acceptance_meta') . ' (`key` varchar(64) PRIMARY KEY, `value` text NOT NULL) ENGINE=InnoDB');
            $pdo->exec('CREATE TABLE ' . self::quote($prefix . 'jdy_acceptance_case') . ' (id int PRIMARY KEY AUTO_INCREMENT, label varchar(100) NOT NULL, step int NOT NULL DEFAULT 0, data_json text NOT NULL, history_json text NOT NULL, create_at int NOT NULL) ENGINE=InnoDB');
            $pdo->exec('CREATE TABLE ' . self::quote($prefix . 'jdy_acceptance_notice') . ' (id int PRIMARY KEY AUTO_INCREMENT, event_id varchar(150) NOT NULL UNIQUE, task_id int NOT NULL, title varchar(100) NOT NULL, business_no varchar(100) NOT NULL, create_at int NOT NULL) ENGINE=InnoDB');
        }
        // Replace all ORM connections before constructing any business service.
        $database['default'] = 'mysql';
        $database['connections'] = ['mysql' => array_replace($original, ['hostname' => '127.0.0.1', 'database' => $schema, 'trigger_sql' => false, 'fields_cache' => false, 'break_reconnect' => false])];
        $self->app->config->set($database, 'database');
        $manager = new \think\DbManager();
        $manager->setConfig($database);
        $self->app->instance('think\DbManager', $manager);
        $self->app->env->set('app.auth_key', $self->state['auth_key']);
        foreach (['cache', 'log'] as $key) {
            $self->app->config->set(['default' => 'file', $key === 'cache' ? 'stores' : 'channels' => ['file' => ['type' => 'File', 'path' => dirname($path) . '/' . $schema . '/' . $key . '/']]], $key);
        }
        $request = new \app\Request();
        $request->withServer($_SERVER)->withGet($_GET)->withPost($input)->setMethod($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $self->app->instance('request', $request);
        $request->siteId(self::SITE); $request->uid(1); $request->username('本地验收员'); $request->appType('adminapi');
        if (Db::query('SELECT DATABASE() AS db')[0]['db'] !== $schema) throw new \RuntimeException('Wrong database');
        $self->app->event->listen('ErpDomainEvent', LedgerRecorded::class);
        $self->app->event->listen('ErpMasterDataLookupRequested', ErpMasterDataLookupRequested::class);
        $self->app->event->listen('HsxBusinessTaskAssigned', static function (array $event): array {
            if (Db::name('jdy_acceptance_notice')->where('event_id', $event['event_id'])->count()) return ['duplicate' => true];
            Db::name('jdy_acceptance_notice')->insert(['event_id' => $event['event_id'], 'task_id' => $event['source_id'],
                'title' => $event['title'], 'business_no' => $event['business_no'], 'create_at' => time()]);
            return ['queued' => true, 'status' => 'pending'];
        });
        if ($new || empty($self->state['ready'])) {
            if (!$new && $self->meta('owner') !== hash('sha256', $self->state['key'])) throw new \RuntimeException('Cannot resume unowned schema');
            $self->seed();
            $self->state['ready'] = true;
            $self->persist();
        }
        if ($self->meta('owner') !== hash('sha256', $self->state['key'])) throw new \RuntimeException('Schema ownership mismatch');
        if ($create) {
            // The framework language loader needs the installed-addon table even when empty.
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::quote($schema) . '.' . self::quote($prefix . 'addon')
                . ' LIKE ' . self::quote($original['database']) . '.' . self::quote($prefix . 'addon'));
        }
        return $self;
    }

    private function persist(): void
    {
        $path = self::path();
        $old = umask(0077);
        try { file_put_contents($path, json_encode($this->state, JSON_THROW_ON_ERROR), LOCK_EX); chmod($path, 0600); }
        finally { umask($old); }
    }

    private static function quote(string $id): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $id)) throw new \RuntimeException('Invalid identifier');
        return '`' . $id . '`';
    }

    public function meta(string $key) { return json_decode((string)Db::name('jdy_acceptance_meta')->where('key', $key)->value('value'), true); }
    public function setMeta(string $key, $value): void { Db::name('jdy_acceptance_meta')->replace()->insert(['key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR)]); }
    private function add(string $table, array $data): int { return (int)Db::name($table)->insertGetId(['site_id' => self::SITE] + $data); }

    private function seed(): void
    {
        if (!Db::name('site')->where('site_id', self::SITE)->count()) {
        $this->setMeta('owner', hash('sha256', $this->state['key']));
        $this->setMeta('fault', 'none');
        Db::name('site')->insert(['site_id' => self::SITE, 'site_name' => '精斗云隔离验收', 'app_type' => 'site', 'addons' => json_encode(['hsx_erp', 'hsx_kingdee_jdy']), 'group_id' => 0]);
        Db::name('sys_user')->insert(['uid' => 1, 'username' => 'jdy-local-acceptance', 'real_name' => '本地验收员', 'status' => 1]);
        Db::name('sys_user_role')->insert(['uid' => 1, 'site_id' => self::SITE, 'status' => 1, 'is_admin' => 1]);
        (new \addon\hsx_kingdee_jdy\Addon())->install();
        $config = (new ConfigService())->save(self::SITE, 1, ['revision' => 0, 'client_id' => '999001', 'client_secret' => 'local-fixture-client',
            'app_key' => 'LOCAL-FIXTURE', 'app_secret' => 'local-fixture-app', 'account_id' => '999000001', 'domain' => 'https://tf.jdy.com', 'mode' => 'preview', 'assignee_uid' => 1]);
        $row = \app\model\sys\SysConfig::where('site_id', self::SITE)->where('config_key', ConfigService::KEY)->find();
        $v = $row->value; $v['collect_from'] = time() - 1; $row->save(['value' => $v]);
        $supplier = $this->add('erp_party', ['party_no' => 'LOCAL-S', 'party_name' => '验收供应商', 'party_type' => 'supplier', 'status' => 1]);
        $customer = $this->add('erp_party', ['party_no' => 'LOCAL-C', 'party_name' => '验收客户', 'party_type' => 'customer', 'status' => 1]);
        $warehouse = $this->add('erp_warehouse', ['warehouse_name' => '验收测试仓', 'warehouse_type' => 'peer', 'status' => 1, 'allow_direct_sale' => 1]);
        $location = $this->add('erp_warehouse_location', ['warehouse_id' => $warehouse, 'location_name' => '验收库位', 'status' => 1]);
        $this->setMeta('masters', compact('supplier', 'customer', 'warehouse', 'location'));
        foreach ([['supplier', $supplier, 'LOCAL-S'], ['customer', $customer, 'LOCAL-C'], ['warehouse', $warehouse, 'LOCAL-W']] as [$kind, $id, $number]) {
            $this->add('hsx_kingdee_jdy_mapping', ['connection_key' => $config['connection_key'], 'kind' => $kind, 'local_id' => $id, 'external_number' => $number, 'operator_uid' => 1, 'create_at' => time(), 'update_at' => time()]);
        }
        }
        $complete = (int)Db::name('jdy_acceptance_case')->where('label', '已跑完的闭环样例')->value('id') ?: $this->newCase('已跑完的闭环样例');
        for ($step = 0; $step < 8; $step++) $this->advance($complete, $step);
        $missing = (int)Db::name('jdy_acceptance_case')->where('label', '缺商品映射待处理样例')->value('id') ?: $this->newCase('缺商品映射待处理样例');
        $this->advance($missing, 0);
        if (!Db::name('jdy_acceptance_case')->where('label', '我的首次验收')->count()) $this->newCase('我的首次验收');
        $this->pump();
    }

    public function newCase(string $label = '新一轮验收'): int
    {
        if (Db::name('jdy_acceptance_case')->count() >= 100) throw new Problem('LIMIT', '验收轮次已达100，请保留数据并联系开发人员');
        return Db::transaction(function () use ($label): int {
            $account = $this->add('erp_capital_account', ['account_name' => $label . '资金账户', 'account_type' => 'bank', 'balance' => 20000, 'status' => 1]);
            return (int)Db::name('jdy_acceptance_case')->insertGetId(['label' => mb_substr($label, 0, 80), 'step' => 0,
                'data_json' => json_encode(['account' => $account]), 'history_json' => '[]', 'create_at' => time()]);
        });
    }

    public function advance(int $id, int $expected): array
    {
        $result = Db::transaction(function () use ($id, $expected): array {
            $row = Db::name('jdy_acceptance_case')->where('id', $id)->lock(true)->find();
            if (!$row || $expected < 0 || $expected > 7) throw new Problem('CASE_INVALID', '验收轮次或步骤不存在');
            if ((int)$row['step'] > $expected) return ['duplicate' => true, 'message' => '此步骤已完成，未重复执行'];
            if ((int)$row['step'] !== $expected) throw new Problem('STEP_ORDER', '请按顺序执行，先刷新当前轮次');
            $data = json_decode($row['data_json'], true);
            $master = $this->meta('masters');
            $rid = 'JDY-LOCAL-' . $id . '-' . $expected;
            $finance = ErpFinanceService::forSite(self::SITE, 1, '本地验收员');
            if ($expected === 0) {
                $data['po'] = ErpPurchaseService::forSite(self::SITE, 1, '本地验收员')->create(['party_id' => $master['supplier'], 'party_name' => '验收供应商', 'request_id' => $rid, 'settle_method' => '挂账',
                    'items' => [['imei' => 'LOCAL-TEST-' . $id, 'model' => '验收手机', 'spec' => '256GB', 'purchase_cost' => 4500, 'retail_price' => 5000,
                        'warehouse_id' => $master['warehouse'], 'location_id' => $master['location'], 'image_urls' => '["/static/fixture.png"]']]]);
                $data['asset'] = (int)Db::name('erp_asset')->where('site_id', self::SITE)->where('purchase_order_id', $data['po'])->value('id');
            } elseif ($expected === 1) {
                $payable = Db::name('erp_payable')->where('site_id', self::SITE)->where('source_type', 'purchase_asset')->where('source_id', $data['asset'])->value('id');
                $finance->confirmPaymentInTransaction((int)$payable, 4500, ['capital_account_id' => $data['account'], 'request_id' => $rid]);
            } elseif ($expected === 2) {
                $data['so'] = ErpSaleService::forSite(self::SITE, 1, '本地验收员')->create(['party_id' => $master['customer'], 'party_name' => '验收客户', 'request_id' => $rid, 'settle_method' => '挂账', 'items' => [['asset_id' => $data['asset'], 'sale_price' => 5000]]]);
            } elseif ($expected === 3 || $expected === 7) {
                $type = $expected === 3 ? 'sale' : 'purchase_return';
                $source = $expected === 3 ? $data['so'] : $data['pret'];
                $party = $expected === 3 ? $master['customer'] : $master['supplier'];
                $amount = $expected === 3 ? 5000 : 4500;
                $receivable = Db::name('erp_receivable')->where('site_id', self::SITE)->where('source_type', $type)->where('source_id', $source)->value('id');
                $finance->confirmReceivableItemsInTransaction($party, [['receivable_id' => $receivable, 'amount' => $amount]], ['capital_account_id' => $data['account'], 'request_id' => $rid]);
            } elseif ($expected === 4) {
                $returns = new ErpSaleReturnService();
                $data['ret'] = $returns->create(['sale_order_id' => $data['so'], 'request_id' => $rid, 'refund_mode' => 'payable', 'items' => [['asset_id' => $data['asset'], 'return_price' => 5000, 'reason' => '本地验收退货']]]);
                $returns->confirm($data['ret']);
            } elseif ($expected === 5) {
                $payable = Db::name('erp_payable')->where('site_id', self::SITE)->where('source_type', 'sale_return')->where('source_id', $data['ret'])->value('id');
                $finance->confirmPaymentInTransaction((int)$payable, 5000, ['capital_account_id' => $data['account'], 'request_id' => $rid]);
            } elseif ($expected === 6) {
                $data['pret'] = (new ErpPurchaseReturnService())->create(['purchase_order_id' => $data['po'], 'request_id' => $rid, 'refund_mode' => 'receivable', 'items' => [['asset_id' => $data['asset'], 'return_cost' => 4500, 'reason' => '本地验收退货']]]);
            }
            $history = json_decode($row['history_json'], true);
            $history[] = ['step' => $expected, 'name' => self::STEPS[$expected], 'at' => time(), 'balance' => Db::name('erp_capital_account')->where('site_id', self::SITE)->where('id', $data['account'])->value('balance')];
            Db::name('jdy_acceptance_case')->where('id', $id)->update(['step' => $expected + 1, 'data_json' => json_encode($data), 'history_json' => json_encode($history)]);
            return ['duplicate' => false, 'message' => self::STEPS[$expected] . '已完成'];
        });
        $this->pump();
        return $result;
    }

    public function pump(): void
    {
        $integration = ErpIntegrationService::forSite(self::SITE, 1);
        foreach (Db::name('erp_outbox_event')->where('site_id', self::SITE)->order('id')->limit(1000)->column('id') as $id) $integration->dispatchDomainEvent((int)$id);
        foreach (Db::name('hsx_kingdee_jdy_task')->where('site_id', self::SITE)->whereIn('status', ['pending', 'retry', 'processing'])->column('id') as $id) (new TaskService())->process(self::SITE, (int)$id);
    }

    public function summary(): array
    {
        $cases = Db::name('jdy_acceptance_case')->order('id desc')->select()->toArray();
        foreach ($cases as &$row) {
            $row['data'] = json_decode($row['data_json'], true);
            $row['history'] = json_decode($row['history_json'], true);
            $row['balance'] = Db::name('erp_capital_account')->where('site_id', self::SITE)->where('id', $row['data']['account'])->value('balance');
            $row['asset_status'] = !empty($row['data']['asset']) ? Db::name('erp_asset')->where('site_id', self::SITE)->where('id', $row['data']['asset'])->value('status') : '';
            $row['purchase_no'] = !empty($row['data']['po']) ? Db::name('erp_purchase_order')->where('id', $row['data']['po'])->value('purchase_no') : '';
            $row['sale_no'] = !empty($row['data']['so']) ? Db::name('erp_sale_order')->where('id', $row['data']['so'])->value('sale_no') : '';
            unset($row['data_json'], $row['history_json']);
        }
        return ['site_name' => '精斗云隔离验收', 'database' => $this->state['schema'], 'cases' => $cases, 'steps' => self::STEPS,
            'task_count' => Db::name('hsx_kingdee_jdy_task')->where('site_id', self::SITE)->count(),
            'notice_count' => Db::name('jdy_acceptance_notice')->count(), 'fault' => $this->meta('fault'), 'external_http_enabled' => false,
            'schedule_count' => Db::name('sys_schedule')->where('addon', 'hsx_kingdee_jdy')->count()];
    }
}
