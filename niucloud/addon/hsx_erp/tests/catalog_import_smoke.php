<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$install = (string)file_get_contents($root . '/sql/install.sql');
$schema = (string)file_get_contents($root . '/app/support/ErpSchema.php');
$service = (string)file_get_contents($root . '/app/service/admin/ErpGoodsCatalogService.php');
$taskService = (string)file_get_contents($root . '/app/service/admin/ErpGoodsCatalogImportTaskService.php');
$taskModel = (string)file_get_contents($root . '/app/model/ErpCatalogImportTask.php');
$job = (string)file_get_contents($root . '/app/job/GoodsCatalogImport.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpGoodsCatalog.php');
$routes = (string)file_get_contents($root . '/app/adminapi/route/route.php');
$events = (string)file_get_contents($root . '/app/event.php');
$listener = (string)file_get_contents($root . '/app/listener/catalog/ErpCatalogProducts.php');
$pc = (string)file_get_contents($root . '/admin/views/erp/goods/meta.vue');
$pcPicker = (string)file_get_contents($root . '/admin/components/ErpCatalogProductSelect.vue');
$mobilePicker = (string)file_get_contents(dirname($root, 3) . '/site-uniapp/src/addon/hsx_erp/components/ErpCatalogProductPopup.vue');

$assert(str_contains($install, 'erp_catalog_product_master') && str_contains($schema, 'erp_catalog_product_master'), '标准产品模板表必须进入新装和升级结构');
$assert(str_contains($install, 'erp_site_catalog_product') && str_contains($schema, 'erp_site_catalog_product'), '站点目录绑定表必须进入新装和升级结构');
$assert(str_contains($install, 'uk_source_product') && str_contains($install, 'uk_site_master'), '标准产品和站点绑定必须具备幂等唯一键');
$assert(str_contains($install, 'erp_catalog_import_task') && str_contains($schema, 'erp_catalog_import_task'), '异步导入任务表必须进入新装和升级结构');
$assert(str_contains($service, "where('sp.site_id', '=', \$this->site_id)") && str_contains($service, "['site_id', '=', \$siteId]"), '目录查询必须由登录站点隔离，后台写入必须显式携带站点');
$assert(str_contains($service, 'importRowsForSite') && str_contains($service, 'int $siteId') && !str_contains($service, '单次最多导入10000条'), '后台分批导入必须显式携带站点且不再限制10000条');
$assert(str_contains($service, 'duplicate_skipped') && str_contains($service, '$same') && str_contains($service, "\$stats['skipped']++"), '本站已存在且内容一致的产品必须幂等跳过');
$assert(str_contains($service, 'source_key') && str_contains($service, 'source_product_id') && str_contains($service, 'master_conflicts'), '外部产品ID必须使用来源命名空间并检测跨站标准差异');
$assert(str_contains($service, 'normalizeCatalogPath') && str_contains($service, 'catalogPathSegments') && str_contains($service, 'count($segments) > 2'), '品类列必须支持“一级品类/可选子分类”两层路径');
$assert(str_contains($service, "~[/\\\\\\\\／＞>,，|｜]+~u") && str_contains($service, "\\x{3000}"), '品类路径必须按 Unicode 解析并兼容全角分隔符和空白');
$assert(str_contains($controller, 'ErpGoodsCatalogImportTaskService') && str_contains($routes, 'goods/catalog/import/upload') && str_contains($routes, 'goods/catalog/import/tasks'), '商品目录异步任务接口必须注册');
$assert(str_contains($taskModel, "erp_catalog_import_task") && str_contains($job, 'runTask') && str_contains($taskService, 'GoodsCatalogImport::dispatch'), '商品目录必须通过框架队列任务执行');
$assert(str_contains($taskService, 'CHUNK_SIZE = 500') && str_contains($taskService, 'IReadFilter') && str_contains($taskService, 'processed_rows'), 'Excel 必须按500行分批读取并记录进度');
$assert(str_contains($taskService, "env('queue.state', false)") && str_contains($taskService, '点击“重试”即可执行'), '队列未启用时必须明确保留待执行任务，不得伪装成功');
$assert(str_contains($events, 'HsxErpCatalogProducts') && str_contains($listener, "['sp.site_id', '=', \$siteId]") && str_contains($listener, "['sp.is_enabled', '=', 1]"), '商城和业务插件必须通过按站点隔离的只读Hook消费目录');
$assert(str_contains($service, "event('HsxErpCatalogChanged'") && str_contains($service, "'site_id' => \$siteId"), '目录变更后必须发布带显式站点ID的领域事件');
$assert(str_contains($pc, '异步导入商品目录') && str_contains($pc, 'el-progress') && str_contains($pc, 'uploadErpGoodsCatalogImport'), 'PC端必须支持异步上传、进度与导入记录');
$assert(str_contains($service, 'public function hierarchy') && str_contains($routes, 'goods/catalog/hierarchy'), '产品目录必须提供独立的懒加载层级接口');
$assert(str_contains($service, "['brand', 'series']") && str_contains($service, "node_type' => 'category'"), '品类、品牌、系列节点必须保留父级过滤条件');
$assert(str_contains($controller, "['include_filters', 0]") && str_contains($service, "(int)(\$where['include_filters'] ?? 0) === 1"), '级联懒加载不得在每次展开时重复统计品牌和系列筛选');
$assert(str_contains($pc, '产品目录树') && str_contains($pc, 'loadCatalogTreeNode') && str_contains($pc, 'getErpGoodsCatalogHierarchy') && str_contains($pc, 'lazy'), '产品目录必须以品类、子分类、品牌、系列、型号层级按需展开');
$assert(str_contains($pcPicker, '<el-cascader') && str_contains($pcPicker, 'loadCascaderChildren') && str_contains($pcPicker, 'branchCache'), 'PC商品目录选择器必须使用标准 Cascader、级联懒加载和短时分支缓存');
$assert(str_contains($pcPicker, 'beforeRemoteFilter') && str_contains($pcPicker, 'site_product_id: value') && str_contains($pcPicker, 'searchSequence'), 'PC商品目录选择器必须支持服务端模糊搜索、ID反显和竞态保护');
$assert(str_contains($service, 'public function add') && str_contains($service, 'public function edit') && str_contains($service, 'public function delete'), '商品目录元组件必须具备站点级型号 CRUD');
$assert(str_contains($service, 'min(sp.sort) as sort') && str_contains($service, 'sp.sort asc,sp.site_product_id asc'), '目录树必须按排序值升序，并在排序值相同时保持创建顺序');
$assert(str_contains($routes, 'goods/catalog/product/save/:id') && str_contains($routes, 'goods/catalog/product/:id'), '商品型号 CRUD 接口必须注册');
$assert(str_contains($pc, '新增型号') && str_contains($pc, 'submitCatalogProduct') && str_contains($pc, 'removeCatalogProduct'), '商品目录元组件必须提供型号新增、编辑、排序、启停和删除入口');
$assert(str_contains($mobilePicker, 'branchCache') && str_contains($mobilePicker, 'scheduleSearch') && str_contains($mobilePicker, 'resolveSelected'), '移动商品目录选择器必须支持懒加载缓存、模糊搜索和ID反显');
$assert(str_contains($mobilePicker, 'category-tabs') && str_contains($mobilePicker, 'brand-rail') && str_contains($mobilePicker, 'series-tabs'), '移动商品目录必须以品类、品牌、系列和型号工作台呈现');
$assert(str_contains($mobilePicker, 'workspaceReady') && str_contains($mobilePicker, 'navigationSequence'), '移动商品目录必须能从中断加载恢复并防止异步竞态覆盖');

echo "[PASS] ERP multi-site product catalog import smoke test\n";
