<?php
declare(strict_types=1);

// Explicit CLI upgrade only; never run via an HTTP request.
if (PHP_SAPI !== 'cli') exit('CLI only');
if (!in_array('--apply', $argv, true)) {
    echo "Usage: php addon/phone_shop/scripts/upgrade_agent_reference_sync.php --apply\n";
    echo "Adds missing plugin schema, refreshes phone_shop menus and registers its schedules. No goods/reference rows are rewritten.\n";
    exit(0);
}
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = new \think\App();
$app->initialize();
$schema = new \addon\phone_shop\app\service\core\upgrade\SchemaSyncService();
foreach (['phone_shop_agent', 'phone_shop_goods_category', 'phone_shop_category_mapping',
    'phone_shop_goods_brand', 'phone_shop_goods_attr', 'phone_shop_goods_spec_group',
    'phone_shop_goods_spec_item', 'phone_shop_goods_grade'] as $table) {
    if (!$schema->ensureTable($table)) throw new \RuntimeException('Schema upgrade failed: ' . $table);
    echo "OK schema: {$table}\n";
}
(new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('phone_shop');
(new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('phone_shop');
echo "OK menus and schedules. Restart existing queue/schedule workers and re-login to load permissions.\n";
