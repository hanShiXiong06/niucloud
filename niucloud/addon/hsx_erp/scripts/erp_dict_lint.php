<?php
/**
 * ERP 字典漏译护栏（防漂移）。
 *
 * 背景：前端展示多用 `X_text || 原值` 兜底，X_text 由后端字典 map 生成；
 *      字典 map 只要缺一个枚举 key，界面就露英文原文。
 * 作用：扫描 app/ 代码里实际写入的枚举值（action / cost_type / biz_type / source_type），
 *      与对应字典 map 的 key 比对，列出"代码写了但字典没翻译"的值。
 *
 * 用法（在插件根目录 addon/hsx_erp 下）：
 *      php scripts/erp_dict_lint.php
 * 退出码：有缺失=1（可接入 CI），全覆盖=0。
 *
 * 纯文件正则，无需框架，可独立运行。
 */

$root = dirname(__DIR__);

/** 读取某文件中某 map 方法体内的所有 'key' => 的 key 集合 */
function mapKeys(string $file, string $methodSig): array
{
    if (!is_file($file)) return [];
    $src = file_get_contents($file);
    $pos = strpos($src, $methodSig);
    if ($pos === false) return [];
    // 从方法签名起取 return [ ... ]; 段
    $seg = substr($src, $pos);
    if (preg_match('/return\s*\[(.*?)\];/s', $seg, $m)) {
        preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>/", $m[1], $k);
        return array_values(array_unique($k[1]));
    }
    return [];
}

/** 扫描 app/ 下所有 PHP，提取 '<field>' => '<value>' 的 value 集合 */
function writtenValues(string $appDir, string $field): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $src = file_get_contents($f->getPathname());
        if (preg_match_all("/'" . preg_quote($field, '/') . "'\s*=>\s*'([a-zA-Z0-9_]+)'/", $src, $m)) {
            foreach ($m[1] as $v) $out[$v] = true;
        }
    }
    return array_keys($out);
}

// 待校验：字段 => [字典文件, map 方法签名]
$checks = [
    'action'      => [$root . '/app/dict/ErpDict.php', 'function getLedgerActionMap'],
    'cost_type'   => [$root . '/app/dict/ErpDict.php', 'function getCostTypeMap'],
    'biz_type'    => [$root . '/app/service/admin/ErpCapitalAccountService.php', 'function bizTypeMap'],
    'source_type' => [$root . '/app/dict/FinanceDict.php', 'function getSourceTypeMap'],
];

// 这些值是"非枚举的占位/动态"，不参与校验（避免误报）
$ignore = [
    'source_type' => ['unknown', ''],
];

$appDir = $root . '/app';
$hasMissing = false;

foreach ($checks as $field => [$dictFile, $sig]) {
    $written = writtenValues($appDir, $field);
    $keys    = mapKeys($dictFile, $sig);
    $skip    = $ignore[$field] ?? [];
    $missing = array_values(array_diff($written, $keys, $skip));
    sort($missing);
    if ($missing) {
        $hasMissing = true;
        echo "✗ [$field] 字典缺失映射（代码写了、字典没翻译）：\n";
        foreach ($missing as $v) echo "    - $v\n";
        echo "  → 请到 " . basename($dictFile) . " 的 {$sig}() 补上中文。\n\n";
    } else {
        echo "✓ [$field] 字典覆盖完整（写入 " . count($written) . " 个值，全部已翻译）。\n";
    }
}

echo "\n" . ($hasMissing ? "存在漏译，请补齐字典后再上线。\n" : "全部枚举均有中文映射。\n");
exit($hasMissing ? 1 : 0);
