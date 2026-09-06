<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$installSql = file_get_contents($pluginRoot . '/sql/install.sql');
$schemaSource = file_get_contents($pluginRoot . '/app/support/WecomSchema.php');
if ($installSql === false || $schemaSource === false) {
    throw new RuntimeException('无法读取企业微信安装结构');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$extractFields = static function (string $body): array {
    preg_match_all('/^\s*`([^`]+)`\s+/m', $body, $matches);
    return array_values(array_map('strval', $matches[1] ?? []));
};
$assertNoDuplicates = static function (string $table, array $fields, string $source) use ($assert): void {
    $counts = array_count_values($fields);
    $duplicates = array_keys(array_filter($counts, static fn(int $count): bool => $count > 1));
    $assert($duplicates === [], $source . ' 的表 ' . $table . ' 存在重复字段：' . implode(', ', $duplicates));
};

preg_match_all(
    '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`\{\{prefix\}\}([^`]+)`\s*\((.*?)\)\s*ENGINE=/si',
    $installSql,
    $installMatches,
    PREG_SET_ORDER
);
$installTables = [];
foreach ($installMatches as $match) {
    $name = (string)$match[1];
    $assert(!isset($installTables[$name]), 'install.sql 重复创建表：' . $name);
    $fields = $extractFields((string)$match[2]);
    $assertNoDuplicates($name, $fields, 'install.sql');
    $installTables[$name] = $fields;
}

preg_match_all(
    '/\$(\w+)\s*=\s*\$prefix\s*\.\s*\'([^\']+)\';/',
    $schemaSource,
    $variableMatches,
    PREG_SET_ORDER
);
$tableVariables = [];
foreach ($variableMatches as $match) $tableVariables[(string)$match[1]] = (string)$match[2];

preg_match_all(
    '/Db::execute\("CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`\{\$(\w+)\}`\s*\((.*?)\)\s*ENGINE=/si',
    $schemaSource,
    $schemaMatches,
    PREG_SET_ORDER
);
$schemaTables = [];
foreach ($schemaMatches as $match) {
    $variable = (string)$match[1];
    $assert(isset($tableVariables[$variable]), 'WecomSchema 未声明表变量：' . $variable);
    $name = $tableVariables[$variable];
    $assert(!isset($schemaTables[$name]), 'WecomSchema 重复创建表：' . $name);
    $fields = $extractFields((string)$match[2]);
    $assertNoDuplicates($name, $fields, 'WecomSchema');
    $schemaTables[$name] = $fields;
}

$requiredFields = [
    'wecom_provider_suite' => [
        'id', 'channel_code', 'provider_corp_id', 'suite_id', 'suite_secret_cipher',
        'callback_token', 'encoding_aes_key_cipher', 'admin_miniapp_appid', 'web_base_url',
        'event_callback_url', 'auth_callback_url', 'suite_ticket', 'status',
    ],
    'wecom_corp_authorization' => [
        'id', 'site_id', 'provider_suite_id', 'auth_corpid', 'permanent_code_cipher',
        'agent_id', 'corp_name', 'status', 'authorized_at', 'cancelled_at',
    ],
    'wecom_authorization_intent' => [
        'id', 'state', 'site_id', 'uid', 'provider_suite_id', 'purpose', 'pre_auth_code',
        'return_url', 'expires_at', 'used_at',
    ],
    'wecom_callback_event' => [
        'id', 'event_key', 'provider_suite_id', 'auth_corpid', 'info_type', 'payload_hash',
        'status', 'processed_at',
    ],
    'wecom_staff_binding' => [
        'id', 'site_id', 'corp_authorization_id', 'uid', 'wecom_userid', 'open_userid',
        'id_scope', 'bind_source', 'status',
    ],
    'wecom_message_log' => [
        'id', 'site_id', 'corp_authorization_id', 'channel_code', 'auth_corpid', 'agent_id',
        'event_id', 'receiver_uid', 'wecom_userid', 'payload_json', 'response_json',
        'provider_msgid', 'status',
    ],
];

foreach ($requiredFields as $table => $fields) {
    $assert(isset($installTables[$table]), 'install.sql 缺少关键表：' . $table);
    $assert(isset($schemaTables[$table]), 'WecomSchema 缺少关键表：' . $table);
    foreach ($fields as $field) {
        $assert(in_array($field, $installTables[$table], true), 'install.sql 的 ' . $table . ' 缺少字段 ' . $field);
        $assert(in_array($field, $schemaTables[$table], true), 'WecomSchema 的 ' . $table . ' 缺少字段 ' . $field);
    }
    $assert(
        $installTables[$table] === $schemaTables[$table],
        $table . ' 在 install.sql 与 WecomSchema 的字段顺序或结构不一致'
    );
}

$assert(count($installTables) === count($requiredFields), 'install.sql 出现未纳入服务商结构检查的表');
$assert(count($schemaTables) === count($requiredFields), 'WecomSchema 出现未纳入服务商结构检查的表');

echo "hsx_wecom provider schema contract smoke passed\n";
