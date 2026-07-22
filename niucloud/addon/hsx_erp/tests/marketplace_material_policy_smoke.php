<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpConfig.php');
$config = (string)file_get_contents($root . '/app/service/admin/ErpConfigService.php');
$policy = (string)file_get_contents($root . '/app/listener/marketplace/ListingMaterialPolicy.php');
$stock = (string)file_get_contents($root . '/app/service/admin/ErpStockService.php');
$completion = (string)file_get_contents($root . '/app/listener/marketplace/PhoneShopListingMaterialCompleted.php');
$mapping = (string)file_get_contents($root . '/app/service/admin/ErpChannelMappingService.php');
$intakeController = (string)file_get_contents(dirname($root) . '/phone_shop/app/adminapi/controller/intake/DeviceIntake.php');

$assert(str_contains($controller, "['marketplace', []]"), '业务规则保存接口必须接收商城资料协作配置');
$assert(str_contains($config, "'category_mode'") && str_contains($config, "'spec_mode'") && str_contains($config, "'publish_mode'"), '配置服务必须拆分分类、规格与发布方式');
$assert(str_contains($config, "'channel_can_write_erp_master' => 0"), '渠道策略必须明确禁止商城覆盖 ERP 主数据');
$assert(str_contains($policy, "can_phone_shop_operate") && str_contains($policy, 'requires_mapping'), '商城必须能通过 Hook 读取资料协作与映射策略');
$assert(str_contains($intakeController, "isset(\$result['can_phone_shop_operate'])") && !str_contains($intakeController, "(string)\$result['owner'] !== 'phone_shop'"), '首次独立映射待办必须按可操作策略放行，不能继续使用旧负责人判断');
$assert(str_contains($stock, "'listing_status' => 'pending_shop'") && str_contains($stock, 'ErpChannelMappingService'), '商城运营模式必须交接待办并记录渠道关联');
$assert(str_contains($stock, "(string)\$channelPolicy['category_mode'] === 'erp'"), 'ERP分类模式必须走ERP目录投影');
$assert(str_contains($completion, 'recordManualCompletion') && !str_contains($completion, "'retail_price'"), '商城完成事件只能记录映射，不得覆盖ERP售价');
$assert(!str_contains($completion, "'spec_json' =>") && !str_contains($completion, "'image_urls' =>") && !str_contains($completion, "'qc_report' =>"), '商城完成事件不得覆盖ERP规格、图片和质检');
$assert(str_contains($mapping, 'erp_channel_category_mapping') && str_contains($mapping, 'erp_channel_attribute_value_mapping'), '映射桥必须同时承接分类和规格值');

echo "ERP marketplace material policy smoke passed.\n";
