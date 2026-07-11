<?php
declare(strict_types=1);

$addonRoot = dirname(__DIR__);
$menu = require $addonRoot . '/app/dict/menu/site.php';
$routeSource = (string)file_get_contents($addonRoot . '/app/adminapi/route/route.php');

$normalize = static function (string $path): string {
    $path = strtolower(trim($path, '/'));
    $path = (string)preg_replace('/(?:<|:)[a-z_][a-z0-9_]*(?:>)?/i', '{param}', $path);
    return $path;
};

$permissions = [];
$walk = static function (array $items) use (&$walk, &$permissions, $normalize): void {
    foreach ($items as $item) {
        $apiUrl = trim((string)($item['api_url'] ?? ''));
        $method = strtolower(trim((string)($item['methods'] ?? '')));
        if ($apiUrl !== '' && $method !== '') {
            $permissions[$method . ' ' . $normalize($apiUrl)] = true;
        }
        $children = (array)($item['children'] ?? []);
        if ($children !== []) {
            $walk($children);
        }
    }
};
$walk($menu);

preg_match_all("/Route::(get|post|delete)\\('([^']+)'/", $routeSource, $matches, PREG_SET_ORDER);
$missing = [];
foreach ($matches as $match) {
    $key = strtolower($match[1]) . ' ' . $normalize('erp/' . $match[2]);
    if (!isset($permissions[$key])) {
        $missing[] = $key;
    }
}

if ($missing !== []) {
    throw new RuntimeException("ERP路由缺少菜单权限登记：\n- " . implode("\n- ", $missing));
}

echo "ERP permission smoke test passed.\n";
