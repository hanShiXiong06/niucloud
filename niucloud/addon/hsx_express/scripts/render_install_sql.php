<?php
declare(strict_types=1);

// 只输出 SQL，不读取密钥、不连接数据库、不执行 SQL。
$options = getopt('', ['prefix:']);
$prefix = $options['prefix'] ?? null;
if (!is_string($prefix) || !preg_match('/^[a-zA-Z0-9_]{1,40}$/', $prefix)) {
    fwrite(STDERR, "用法：php render_install_sql.php --prefix=saas_\n请使用目标数据库实际表前缀；本脚本只输出，不执行。\n");
    exit(2);
}
$sql = file_get_contents(dirname(__DIR__) . '/sql/install.sql');
if ($sql === false) {
    fwrite(STDERR, "未找到安装 SQL，未执行任何数据库操作。\n");
    exit(1);
}
echo "-- hsx_express 1.0.0：仅新增物流任务表，不修改商城、回收、库存、财务表。\n";
echo str_replace('{{prefix}}', $prefix, $sql);
