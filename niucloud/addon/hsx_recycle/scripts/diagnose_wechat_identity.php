<?php
declare(strict_types=1);

// CLI-only, read-only diagnosis. No user binding, fan sync, token refresh or messages.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

class HsxIdentityDiagnosticError extends RuntimeException {}

const HSX_IDENTITY_DIAGNOSTIC_VERSION = '1.1';

final class HsxWechatIdentityDiagnostic
{
    private PDO $pdo;
    private string $prefix;
    private array $schema = [];
    private bool $truncated = false;
    private const LIMIT = 50;
    private const MEMBER_FIELDS = ['member_id', 'site_id', 'wx_unionid', 'wx_openid', 'weapp_openid', 'status', 'is_del', 'delete_time'];
    private const FAN_FIELDS = ['fans_id', 'site_id', 'unionid', 'openid', 'app_id', 'is_subscribe', 'subscribe', 'update_time', 'subscribe_time'];

    public function __construct(PDO $pdo, string $prefix)
    {
        if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) throw new HsxIdentityDiagnosticError('数据库表前缀不符合预期，已停止。');
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    public static function mask($value): string
    {
        $value = (string)($value ?? '');
        if ($value === '') return '(空)';
        $label = strlen($value) > 10 ? substr($value, 0, 4) . '***' . substr($value, -4) : '***';
        return $label . ' [指纹:' . substr(hash('sha256', $value), 0, 10) . ']';
    }

    private function table(string $name): string { return '`' . $this->prefix . $name . '`'; }

    private function rows(string $sql, array $params = []): array
    {
        $query = $this->pdo->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function columns(string $table): array
    {
        if (isset($this->schema[$table])) return $this->schema[$table];
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $this->rows('PRAGMA table_info(' . $this->table($table) . ')');
            return $this->schema[$table] = array_column($rows, 'type', 'name');
        }
        $rows = $this->rows('SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$this->prefix . $table]);
        return $this->schema[$table] = array_column($rows, 'DATA_TYPE', 'COLUMN_NAME');
    }

    public function sites(): array
    {
        $columns = $this->columns('site');
        if (!isset($columns['site_id'], $columns['site_name'])) throw new HsxIdentityDiagnosticError('无法读取站点列表，请检查数据库配置或手动指定 --site-id。');
        return $this->rows('SELECT site_id, site_name FROM ' . $this->table('site') . ' ORDER BY site_id LIMIT 100');
    }

    private function config(int $siteId, string $key): array
    {
        $columns = $this->columns('sys_config');
        if (!isset($columns['site_id'], $columns['config_key'], $columns['value'])) return ['state' => 'table_missing', 'data' => []];
        $rows = $this->rows('SELECT `value` FROM ' . $this->table('sys_config') . ' WHERE site_id = ? AND config_key = ? LIMIT 2', [$siteId, $key]);
        if (count($rows) !== 1) return ['state' => $rows ? 'duplicate' : 'missing', 'data' => []];
        $data = json_decode((string)$rows[0]['value'], true);
        return ['state' => is_array($data) ? 'present' : 'invalid_json', 'data' => is_array($data) ? $data : []];
    }

    private function safeConfig(array $config): array
    {
        $data = $config['data'];
        return [
            '配置状态' => $config['state'], 'AppID' => self::mask($data['app_id'] ?? ''),
            '第三方代授权' => (int)($data['is_authorization'] ?? 0) === 1,
            'AppSecret已配置' => !empty($data['app_secret']), '回调Token已配置' => !empty($data['token']),
            '说明' => '这里只检查配置是否存在，不验证凭证有效性；代授权模式不以本站AppSecret为空判断失败。',
        ];
    }

    private function stats(string $table, int $siteId, array $fields): array
    {
        $columns = $this->columns($table);
        if (!isset($columns['site_id'])) return ['状态' => '表或site_id字段缺失'];
        $select = ['COUNT(*) AS total'];
        foreach ($fields as $field) {
            if (isset($columns[$field])) $select[] = "SUM(CASE WHEN `{$field}` IS NOT NULL AND `{$field}` <> '' THEN 1 ELSE 0 END) AS `{$field}`";
        }
        $row = $this->rows('SELECT ' . implode(',', $select) . ' FROM ' . $this->table($table) . ' WHERE site_id = ?', [$siteId])[0];
        $result = ['总记录数_含历史或停用记录' => (int)$row['total']];
        foreach ($fields as $field) $result[$field . '_有值'] = isset($columns[$field]) ? (int)($row[$field] ?? 0) : '字段缺失';
        return $result;
    }

    private function matchRows(string $table, int $siteId, array $criteria, array $fields, string $pk): array
    {
        $columns = $this->columns($table);
        if (!isset($columns['site_id'], $columns[$pk])) return [];
        $parts = [];
        $params = [$siteId];
        foreach ($criteria as $field => $values) {
            if (!isset($columns[$field])) continue;
            foreach (array_unique($values) as $value) {
                if ((string)$value === '') continue;
                // MySQL's usual case-insensitive collation must not merge distinct WeChat IDs.
                $part = "`{$field}` = ?";
                $params[] = $value;
                if ($field !== 'member_id' && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    $part .= " AND CAST(`{$field}` AS BINARY) = CAST(? AS BINARY)";
                    $params[] = $value;
                } elseif ($field !== 'member_id') {
                    $part = "`{$field}` COLLATE BINARY = ?";
                }
                $parts[] = '(' . $part . ')';
            }
        }
        if (!$parts) return [];
        $fields = array_values(array_intersect($fields, array_keys($columns)));
        $rows = $this->rows('SELECT `' . implode('`,`', $fields) . '` FROM ' . $this->table($table)
            . ' WHERE site_id = ? AND (' . implode(' OR ', $parts) . ') ORDER BY `' . $pk . '` LIMIT ' . (self::LIMIT + 1), $params);
        if (count($rows) > self::LIMIT) $this->truncated = true;
        return array_slice($rows, 0, self::LIMIT);
    }

    private function values(array $rows, string $key): array
    {
        return array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string)($row[$key] ?? ''), $rows), static fn(string $value): bool => $value !== '')));
    }

    private function safeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach (['wx_unionid', 'wx_openid', 'weapp_openid', 'unionid', 'openid', 'app_id'] as $field) {
                if (array_key_exists($field, $row)) $row[$field] = self::mask($row[$field]);
            }
        }
        unset($row);
        return $rows;
    }

    public function report(int $siteId, string $selector, string $value): array
    {
        if ($siteId <= 0 || !in_array($selector, ['unionid', 'member-id', 'wx-openid', 'weapp-openid'], true) || $value === '') {
            throw new HsxIdentityDiagnosticError('必须指定有效站点和一个查询条件。');
        }
        $this->truncated = false;
        $wechat = $this->config($siteId, 'WECHAT');
        $weapp = $this->config($siteId, 'weapp');
        $submit = $this->config($siteId, 'recycle_order_submit_config');
        $unions = $selector === 'unionid' ? [$value] : [];
        $wx = $selector === 'wx-openid' ? [$value] : [];
        $mini = $selector === 'weapp-openid' ? [$value] : [];
        $ids = $selector === 'member-id' ? [(int)$value] : [];
        $members = $fans = [];
        // Expand only the selected identity's neighbours, always inside the selected tenant.
        for ($round = 0; $round < 3; $round++) {
            $members = $this->matchRows('member', $siteId, ['member_id' => $ids, 'wx_unionid' => $unions, 'wx_openid' => $wx, 'weapp_openid' => $mini], self::MEMBER_FIELDS, 'member_id');
            $unions = array_values(array_unique(array_merge($unions, $this->values($members, 'wx_unionid'))));
            $wx = array_values(array_unique(array_merge($wx, $this->values($members, 'wx_openid'))));
            $mini = array_values(array_unique(array_merge($mini, $this->values($members, 'weapp_openid'))));
            $fans = $this->matchRows('wechat_fans', $siteId, ['unionid' => $unions, 'openid' => $wx], self::FAN_FIELDS, 'fans_id');
            $unions = array_values(array_unique(array_merge($unions, $this->values($fans, 'unionid'))));
            $wx = array_values(array_unique(array_merge($wx, $this->values($fans, 'openid'))));
            if ($this->truncated) break;
        }

        $findings = [];
        $add = static function (string $code, string $message) use (&$findings): void { $findings[] = ['code' => $code, '说明' => $message]; };
        $memberColumns = $this->columns('member');
        $fanColumns = $this->columns('wechat_fans');
        $missing = array_merge(array_diff(['member_id', 'site_id', 'wx_unionid', 'wx_openid', 'weapp_openid'], array_keys($memberColumns)), array_diff(['fans_id', 'site_id', 'unionid', 'openid'], array_keys($fanColumns)));
        if ($missing) $add('SCHEMA_INCOMPLETE', '身份相关表或字段缺失，请先核对实际表结构，不能把查询不到当成用户不存在。');
        if (!$members) $add('MEMBER_NOT_FOUND', '当前站点未找到对应会员；检查站点、查询字段，以及用户是否完成小程序登录。');
        if (!$fans) $add('FAN_NOT_FOUND_LOCALLY', '本地未找到对应公众号粉丝，不代表用户一定未关注；需核对关注回调和粉丝信息采集。');
        if (count($members) > 1) $add('MULTIPLE_MEMBERS', '同一关联范围命中多个会员，禁止直接合并或批量回填，需核对重复账号及原有订单归属。');
        if (count($unions) > 1) $add('UNIONID_CONFLICT', '关联记录出现不同UnionID，可能有旧关联或错误映射；不能直接覆盖。');
        if (count($this->values($fans, 'openid')) > 1 || count($fans) > 1) $add('MULTIPLE_FANS', '命中多个粉丝记录或公众号OpenID，需先确认公众号归属和重复记录。');
        if ($this->truncated) $add('RESULT_TRUNCATED', '关联记录超过50条，结果被截断，不能据此自动关联。');
        $appId = (string)($wechat['data']['app_id'] ?? '');
        if ($appId === '' || $wechat['state'] !== 'present') $add('WECHAT_CONFIG_UNCONFIRMED', '公众号配置缺失、重复或无AppID，无法确认粉丝属于当前公众号。');
        if (empty($weapp['data']['app_id'])) $add('WEAPP_CONFIG_UNCONFIRMED', '小程序AppID未找到，请核对站点配置。');
        foreach ($fans as $fan) {
            $fanAppId = (string)($fan['app_id'] ?? '');
            if ($fanAppId === '' || $fanAppId === '0') $add('FAN_APPID_UNKNOWN', '粉丝记录未保存有效AppID；本地资料不足以证明它属于当前公众号。');
            elseif ($appId !== '' && $fanAppId !== $appId) $add('FAN_APPID_MISMATCH', '粉丝AppID与当前公众号不一致，或旧数据保存了公众号原始ID，需核对来源。');
            if ((string)($fan['openid'] ?? '') === '') $add('FAN_OPENID_MISSING', '粉丝有UnionID但没有OpenID，检查粉丝入库是否遗漏openid字段。');
            if (array_key_exists('is_subscribe', $fan) && array_key_exists('subscribe', $fan) && (string)$fan['is_subscribe'] !== (string)$fan['subscribe']) $add('SUBSCRIBE_FIELDS_CONFLICT', '两种关注状态字段不一致，不能判断真实关注状态。');
            $subscribe = $fan['is_subscribe'] ?? $fan['subscribe'] ?? null;
            if ($subscribe === null) $add('SUBSCRIBE_UNKNOWN', '本地缺少关注状态字段。');
            elseif ((string)$subscribe === '0') $add('LOCALLY_UNSUBSCRIBED', '本地标记未关注，仅为本地记录，未向微信实时核验。');
        }
        foreach ($members as $member) {
            if (empty($member['wx_unionid'])) $add('MEMBER_UNIONID_MISSING', '会员没有UnionID，需要核对小程序登录返回值及保存流程。');
            if (empty($member['weapp_openid'])) $add('MEMBER_WEAPP_OPENID_MISSING', '会员没有小程序OpenID。');
            if (!empty($member['is_del']) || !empty($member['delete_time']) || (isset($member['status']) && (int)$member['status'] !== 1)) $add('MEMBER_NOT_ACTIVE', '命中已删除或停用会员，不应自动绑定。');
            if (!empty($member['wx_openid']) && $member['wx_openid'] === ($member['weapp_openid'] ?? '')) $add('OPENID_CHANNELS_EQUAL', '公众号与小程序OpenID完全相同，需排查是否把小程序OpenID误写入公众号字段。');
        }
        if (count($members) === 1 && count($fans) === 1 && !$missing && !$this->truncated) {
            $member = $members[0]; $fan = $fans[0];
            $sameUnion = !empty($member['wx_unionid']) && $member['wx_unionid'] === ($fan['unionid'] ?? '');
            if ($sameUnion && !empty($fan['openid'])) {
                if (empty($member['wx_openid'])) $add('LOCAL_LINK_CANDIDATE', '两表UnionID一致，粉丝有OpenID，会员缺公众号OpenID：发现待关联候选；本脚本未回填，仍需确认公众号归属及身份来源。');
                elseif ($member['wx_openid'] === $fan['openid']) $add('LOCAL_LINK_MATCHES', '会员与粉丝的UnionID、公众号OpenID在本地一致；不代表微信已允许推送或自动关联流程已正常。');
                else $add('OPENID_CONFLICT', '两表UnionID一致，但公众号OpenID不同，不能直接覆盖会员记录。');
            }
        }
        if ($selector === 'unionid' && !$members && !$fans && !$missing) {
            $other = $this->matchRows('member', $siteId, ['wx_openid' => [$value], 'weapp_openid' => [$value]], self::MEMBER_FIELDS, 'member_id');
            $otherFans = $this->matchRows('wechat_fans', $siteId, ['openid' => [$value]], self::FAN_FIELDS, 'fans_id');
            if ($other || $otherFans) $add('INPUT_MAY_BE_OPENID', '输入值出现在OpenID字段而非UnionID字段，请改用 --wx-openid 或 --weapp-openid 查询。');
        }

        return [
            '报告版本' => HSX_IDENTITY_DIAGNOSTIC_VERSION, '模式' => '只读诊断', '时间_UTC' => gmdate('c'), '站点ID' => $siteId,
            '查询' => ['字段' => $selector, '值' => $selector === 'member-id' ? $value : self::mask($value)],
            '配置检查' => ['公众号' => $this->safeConfig($wechat), '小程序' => $this->safeConfig($weapp), '关注提示配置状态' => $submit['state'], '关注提示开关' => (bool)($submit['data']['follow_official_account']['enabled'] ?? false)],
            '表结构' => ['member' => array_intersect_key($memberColumns, array_flip(self::MEMBER_FIELDS)), 'wechat_fans' => array_intersect_key($fanColumns, array_flip(self::FAN_FIELDS))],
            '站点统计' => ['会员' => $this->stats('member', $siteId, ['weapp_openid', 'wx_unionid', 'wx_openid']), '公众号粉丝' => $this->stats('wechat_fans', $siteId, ['openid', 'unionid'])],
            '关联会员_脱敏' => $this->safeRows($members), '关联粉丝_脱敏' => $this->safeRows($fans),
            '检查结果' => array_values(array_unique($findings, SORT_REGULAR)),
            '未验证事项' => ['未调用微信接口，关注状态仅为本地记录。', '未验证同一开放平台绑定、回调可达性、消息权限及模板配置。', '发现相同UnionID只代表本地数据匹配，不证明它不是过去手工写入。'],
            '数据保护' => '未修改数据库，未刷新Token，未推送消息；未读取姓名、手机号、订单或余额。',
        ];
    }
}

function hsxIdentityOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $arg) {
        if (in_array($arg, ['--help', '--list-sites'], true)) { $options[substr($arg, 2)] = true; continue; }
        if (!preg_match('/^--(site-id|unionid|member-id|wx-openid|weapp-openid|root|env-name)=(.+)$/D', $arg, $match)) throw new HsxIdentityDiagnosticError('参数不支持；使用 --help 查看用法。此脚本没有更新、同步或覆盖开关。');
        if (isset($options[$match[1]])) throw new HsxIdentityDiagnosticError('同一参数不能重复填写。');
        $options[$match[1]] = $match[2];
    }
    foreach (['site-id', 'member-id'] as $field) {
        if (isset($options[$field]) && (!ctype_digit($options[$field]) || (int)$options[$field] <= 0)) throw new HsxIdentityDiagnosticError('站点ID和会员ID必须为正整数。');
    }
    foreach (['unionid', 'wx-openid', 'weapp-openid'] as $field) {
        if (isset($options[$field]) && !preg_match('/^[a-zA-Z0-9_-]{1,255}$/D', $options[$field])) throw new HsxIdentityDiagnosticError('微信身份标识含非预期字符，请检查复制内容。');
    }
    if (isset($options['env-name']) && !preg_match('/^[a-zA-Z0-9_-]+$/D', $options['env-name'])) throw new HsxIdentityDiagnosticError('环境名称不合法。');
    if (!isset($options['help']) && !isset($options['list-sites'])) {
        $selectors = array_intersect(['unionid', 'member-id', 'wx-openid', 'weapp-openid'], array_keys($options));
        if (!isset($options['site-id']) || count($selectors) !== 1) throw new HsxIdentityDiagnosticError('请指定 --site-id 和一个查询条件；不知道站点ID时先运行 --list-sites。');
    }
    return $options;
}

function hsxIdentityReadDatabaseConfig(\think\App $app): array
{
    // Composer does not load ThinkPHP's env() helper. Load it without app initialization.
    $helper = $app->getThinkPath() . 'helper.php';
    if (!is_file($helper)) throw new HsxIdentityDiagnosticError('ThinkPHP辅助函数文件缺失，请检查服务器vendor依赖是否完整。');
    require_once $helper;
    if (!function_exists('env')) throw new HsxIdentityDiagnosticError('ThinkPHP的env函数未加载，无法读取数据库配置。');
    return require $app->getConfigPath() . 'database.php';
}

function hsxIdentityErrorReport(Throwable $error, string $stage): array
{
    // Report only known-safe messages and code location, never raw SQL, credentials or arguments.
    $message = $error instanceof HsxIdentityDiagnosticError ? $error->getMessage() : '诊断未完成，请反馈本报告中的失败阶段和错误位置；不要发送密码或.env。';
    if ($error instanceof Error && $error->getMessage() === 'Call to undefined function env()') {
        $message = 'ThinkPHP的env辅助函数未加载，读取数据库配置失败。';
    }
    return [
        '报告版本' => HSX_IDENTITY_DIAGNOSTIC_VERSION, '模式' => '只读诊断', '完成' => false,
        '失败阶段' => $stage, '说明' => $message,
        '错误类型' => get_class($error), '错误代码' => (string)$error->getCode(),
        '错误位置' => ['文件' => basename($error->getFile()), '行号' => $error->getLine()],
        'PHP版本' => PHP_VERSION, 'PDO已加载' => extension_loaded('pdo'), 'PDO_MySQL已加载' => extension_loaded('pdo_mysql'),
    ];
}

function hsxIdentityConnect(array $options, string &$stage): array
{
    $stage = '检查PHP运行环境';
    if (PHP_VERSION_ID < 80000) throw new HsxIdentityDiagnosticError('需要PHP 8.0或以上，请在终端使用网站对应的PHP版本。');
    if (!class_exists('PDO') || !in_array('mysql', PDO::getAvailableDrivers(), true)) throw new HsxIdentityDiagnosticError('当前终端PHP未启用PDO MySQL扩展，请使用网站对应的PHP版本并检查扩展。');
    $stage = '定位PHP项目';
    $root = isset($options['root']) ? realpath($options['root']) : __DIR__;
    while ($root && (!is_file($root . '/vendor/autoload.php') || !is_file($root . '/config/database.php'))) {
        if (isset($options['root']) || dirname($root) === $root) throw new HsxIdentityDiagnosticError('未找到PHP项目根目录，请用 --root 指向包含think和.env的目录。');
        $root = dirname($root);
    }
    if (!$root) throw new HsxIdentityDiagnosticError('项目根目录不存在。');
    if (isset($options['env-name']) && !is_file($root . '/.env.' . $options['env-name'])) throw new HsxIdentityDiagnosticError('指定的环境文件不存在，已停止，未回退到其他环境。');
    $stage = '加载Composer依赖';
    require_once $root . '/vendor/autoload.php';
    $stage = '加载ThinkPHP环境配置';
    $app = new \think\App($root);
    // Do not initialize the app: providers, listeners, jobs and framework DB logging stay unbooted.
    $app->loadEnv((string)($options['env-name'] ?? ''));
    $stage = '读取数据库配置';
    $database = hsxIdentityReadDatabaseConfig($app);
    $config = $database['connections'][$database['default'] ?? 'mysql'] ?? [];
    if (($config['type'] ?? '') !== 'mysql' || !empty($config['deploy']) || empty($config['database'])) throw new HsxIdentityDiagnosticError('此脚本仅支持项目现有的单连接MySQL配置；当前配置不完整或为其他模式，已停止。');
    foreach (['hostname', 'hostport', 'database', 'charset', 'socket'] as $key) {
        if (strpbrk((string)($config[$key] ?? ''), ";\r\n") !== false) throw new HsxIdentityDiagnosticError('数据库连接格式不符合预期，已停止。');
    }
    $endpoint = !empty($config['socket']) ? 'unix_socket=' . $config['socket'] : 'host=' . ($config['hostname'] ?? '127.0.0.1') . ';port=' . ($config['hostport'] ?? '3306');
    $stage = '连接数据库';
    $pdo = new PDO('mysql:' . $endpoint . ';dbname=' . $config['database'] . ';charset=' . ($config['charset'] ?? 'utf8mb4'), (string)($config['username'] ?? ''), (string)($config['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]);
    $stage = '开启只读事务';
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    return [$pdo, (string)($config['prefix'] ?? '')];
}

function hsxIdentityMain(array $arguments): int
{
    ini_set('display_errors', '0');
    $pdo = null;
    $stage = '检查命令参数';
    try {
        $options = hsxIdentityOptions($arguments ?: ['--help']);
        if (isset($options['help'])) {
            echo '微信身份只读诊断 v' . HSX_IDENTITY_DIAGNOSTIC_VERSION . "（PHP 8+，服务器终端运行，不通过浏览器访问）\n\n";
            echo "在包含 think 和 .env 的PHP项目目录运行：\n";
            echo "php addon/hsx_recycle/scripts/diagnose_wechat_identity.php --list-sites\n";
            echo "php addon/hsx_recycle/scripts/diagnose_wechat_identity.php --site-id=站点ID --unionid=用户UnionID\n";
            echo "也可将 --unionid 换成 --member-id、--wx-openid 或 --weapp-openid。\n";
            echo "可选 --root=/PHP项目绝对路径；特殊环境可指定 --env-name=production。\n";
            echo "报告已脱敏，可直接反馈。没有更新开关，不同步粉丝，不发送消息，不刷新Token。\n";
            return 0;
        }
        [$pdo, $prefix] = hsxIdentityConnect($options, $stage);
        $stage = isset($options['list-sites']) ? '读取站点列表' : '读取身份关联及表结构';
        $diagnostic = new HsxWechatIdentityDiagnostic($pdo, $prefix);
        if (isset($options['list-sites'])) $report = ['报告版本' => HSX_IDENTITY_DIAGNOSTIC_VERSION, '模式' => '只读站点列表_最多100条', '站点' => $diagnostic->sites()];
        else {
            $selector = array_values(array_intersect(['unionid', 'member-id', 'wx-openid', 'weapp-openid'], array_keys($options)))[0];
            $report = $diagnostic->report((int)$options['site-id'], $selector, $options[$selector]);
        }
        $stage = '生成脱敏报告';
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        return 0;
    } catch (Throwable $error) {
        echo json_encode(hsxIdentityErrorReport($error, $stage), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
        return 1;
    } finally {
        try {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        } catch (Throwable $cleanupError) {
            // Closing the read-only connection releases the transaction without exposing PDO details.
            fwrite(STDERR, "只读连接清理未完成，连接关闭后事务将自动释放；没有执行数据写入。\n");
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) exit(hsxIdentityMain(array_slice($argv, 1)));
