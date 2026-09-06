<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
require_once $root . '/app/dict/ErpDict.php';
require_once $root . '/app/support/ErpPurchaseReturnPolicy.php';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$service = (string)file_get_contents($root . '/app/service/admin/ErpPurchaseService.php');
$assert(str_contains($service, "\$item['warehouse_id']"), '采购服务必须读取单台设备warehouse_id');
$assert(str_contains($service, "\$item['location_id']"), '采购服务必须读取单台设备location_id');
$assert(str_contains($service, '项商品请选择入库仓库和库位'), '采购服务必须逐项校验入库位置');
$assert(!str_contains($service, "\$itemWarehouseId = (int)(\$data['warehouse_id']"), '后端不得用采购单仓库静默替代设备仓库');
$assert(str_contains($service, 'appendPurchasePaymentSummary'), '采购列表必须返回单台设备付款摘要');
$assert(str_contains($service, "['return_flow']"), '采购详情必须返回后端退货流程决策');
foreach (['order_effective_amount', 'asset_payable_amount', 'asset_paid_amount', 'asset_unpaid_amount', 'order_business_status_label'] as $field) {
    $assert(str_contains($service, "['{$field}']"), '采购列表缺少退货后有效账务字段：' . $field);
}
$assert(str_contains($service, "ep.status <> 'void'"), '采购付款状态必须排除已退设备的作废应付');
$assert(str_contains($service, 'EXISTS(SELECT 1') && str_contains($service, 'hasAssetPayableExpr'), '全部退货时必须识别设备级应付事实，不能回退旧整单金额');
$assert(str_contains($service, 'effective_purchase_amount') && str_contains($service, 'original_total_cost'), '采购详情必须同时返回原订单金额和退货后的有效采购本金');
$assert(str_contains($service, 'ErpListingWorkflow::statusFromAsset'), '采购入库必须复用统一商城状态机');
$assert(str_contains($service, "'retail_price' => \$retailPrice"), '采购入库商城就绪判断必须使用真实零售价');
$assert(!str_contains($service, "'retail_price' => \$estimateSalePrice"), '采购估售价不能冒充商城零售价');

$direct = \addon\hsx_erp\app\dict\ErpDict::purchaseReturnFlow(3000, 0);
$refund = \addon\hsx_erp\app\dict\ErpDict::purchaseReturnFlow(3000, 3000);
$mixed = \addon\hsx_erp\app\dict\ErpDict::purchaseReturnFlow(3000, 1200);
$discounted = \addon\hsx_erp\app\dict\ErpDict::purchaseReturnFlow(3000, 0, 2500);
$partialRefund = \addon\hsx_erp\app\dict\ErpDict::purchaseReturnFlow(3000, 1200, 2500);
$assert($direct['value'] === 'direct_void' && $direct['requires_refund'] === false, '未付款必须直接退货且不生成退款');
$assert($refund['value'] === 'refund_receivable' && $refund['requires_refund'] === true, '全额付款必须进入退款应收');
$assert($mixed['value'] === 'mixed' && $mixed['requires_refund'] === true, '部分付款必须拆分冲销与退款应收');
$assert((float)$discounted['offset_amount'] === 3000.0 && (float)$discounted['retained_payable_amount'] === 0.0, '完全未结账退货必须整台作废应付且不保留尾款');
$assert($partialRefund['offset_amount'] === 1800.0 && $partialRefund['refund_amount'] === 700.0, '部分付款退货必须先冲未付再生成退款应收');

$normalPolicy = \addon\hsx_erp\app\support\ErpPurchaseReturnPolicy::assess([
    'purchase_cost' => 5500,
    'refurbish_cost' => 0,
    'total_cost' => 5500,
    'refurbish_status' => 'none',
], 5500, 5500);
$refurbishedPolicy = \addon\hsx_erp\app\support\ErpPurchaseReturnPolicy::assess([
    'purchase_cost' => 5500,
    'refurbish_cost' => 100,
    'total_cost' => 5600,
    'refurbish_status' => 'done',
], 5500, 5500);
$unclassifiedPolicy = \addon\hsx_erp\app\support\ErpPurchaseReturnPolicy::assess([
    'purchase_cost' => 5500,
    'refurbish_cost' => 0,
    'total_cost' => 5600,
    'refurbish_status' => 'none',
], 5500, 5500);
$assert($normalPolicy['returnable'] === true && $normalPolicy['default_return_amount'] === 5500.0, '无内部成本设备应按供应商本金退货');
$assert($refurbishedPolicy['returnable'] === false && str_contains($refurbishedPolicy['block_reason'], '销售出库'), '已整备设备必须禁止标准采购退货并引导销售出库');
$assert($unclassifiedPolicy['returnable'] === false && $unclassifiedPolicy['unclassified_cost'] === 100.0, '未分类成本必须先归类再决定业务路径');

$purchaseView = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/purchase/list.vue');
foreach (['入库位置', 'applyDefaultLocationToAll', 'purchaseRowClassName', '本页同批', 'ErpDeviceIdentity', 'ErpRoleFocus'] as $needle) {
    $assert(str_contains($purchaseView, $needle), '采购页缺少产品优化：' . $needle);
}
foreach (['search.imei', 'search.party_id', 'search.purchase_no', 'ErpPartySelect', 'label="当前状态"', 'assetStatusMeta'] as $needle) {
    $assert(str_contains($purchaseView, $needle), '采购列表缺少精确筛选或状态信息收敛：' . $needle);
}
$assert(!str_contains($purchaseView, 'label="关键词"'), '采购列表不得继续使用含义混杂的综合关键词筛选');
$assert(str_contains($purchaseView, 'missingLocationIndex'), 'PC采购开单必须逐台校验位置');
$assert(!str_contains($purchaseView, '撤销批次'), '正常采购列表不得并列展示撤销批次与采购退货');
$assert(str_contains($purchaseView, "return '采购退货'"), '采购列表退货入口必须使用统一业务文案');
foreach (['purchase-return-disabled-wrap', 'disabled>{{ purchaseReturnActionLabel(row) }}', 'row.return_flow?.block_reason'] as $needle) {
    $assert(str_contains($purchaseView, $needle), 'PC采购列表缺少禁用退货按钮或悬停原因：' . $needle);
}
foreach (['有效采购本金', '已结采购款', '待结采购款', 'row.asset_paid_amount', 'row.purchase_cost', '整备支出', 'order_business_status_label'] as $needle) {
    $assert(str_contains($purchaseView, $needle), 'PC采购页缺少有效本金、独立整备或订单业务状态：' . $needle);
}
$assert(!str_contains($purchaseView, 'allocatedPaid'), '采购付款不得再按原订单比例二次分摊');

$mobileView = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/purchase/create.vue');
$assert(str_contains($mobileView, 'openItemWarehouse'), '移动端采购必须支持逐台选择仓库');
$assert(str_contains($mobileView, 'item.location_id'), '移动端采购必须保存逐台库位');

$mobileList = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/purchase/list.vue');
$assert(str_contains($mobileList, 'groupedBatches'), '移动采购列表必须按采购批次分组');
$assert(str_contains($mobileList, 'purchase_finance_status'), '移动采购列表必须使用后端采购财务字典');
foreach (['batchMachineSummary', 'isBatchExpanded', 'orderStatusLabel', '采购单号', '已付 ¥'] as $needle) {
    $assert(str_contains($mobileList, $needle), '移动采购摘要卡缺少信息密度优化：' . $needle);
}
$assert(!str_contains($mobileList, '<text class="batch-status-label">付款状态</text>'), '移动采购批次不得并列展示付款和单据两个状态标签');
$assert(str_contains($mobileList, '@click.stop="goReturn(row)"'), '移动采购列表必须提供可直接操作的退货入口');
foreach (['asset_paid_amount', 'asset_unpaid_amount', 'order_business_status_label', '整备 +'] as $needle) {
    $assert(str_contains($mobileList, $needle), '移动采购列表必须同步有效采购账务与独立整备信息：' . $needle);
}
$mobilePurchaseDetail = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/purchase/detail.vue');
foreach (['original_total_cost', 'effective_purchase_amount', 'effective_asset_count', '整备费用（独立应付）'] as $needle) {
    $assert(str_contains($mobilePurchaseDetail, $needle), '移动采购详情必须同步有效本金和整备费用口径：' . $needle);
}

$mobileReturn = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/purchase_return/create.vue');
$assert(str_contains($mobileReturn, 'selectedRequiresRefund'), '移动退货页必须按实际付款决定退款流程');
$assert(str_contains($mobileReturn, 'selectedOffsetTotal'), '移动退货页必须展示冲销未付款金额');
$assert(str_contains($mobileReturn, 'selectedRefundTotal'), '移动退货页必须展示退款应收金额');
$assert(str_contains($mobileReturn, 'block_reason'), '移动退货页必须解释设备不可采购退货的原因');
$assert(str_contains($mobileReturn, "refund_mode: selectedRequiresRefund.value ? form.value.refund_mode : 'none'"), '未付款退货必须提交无需退款模式，已付款设备使用用户选择的收款路径');
$assert(str_contains($mobileReturn, '当场收款') && str_contains($mobileReturn, '记账待收') && str_contains($mobileReturn, 'capital_account_id'), '移动采购退货必须明确区分当场到账与记账待收，并在现收时选择账户');
foreach (['业务管理员确认退机', '系统处理结果', '我已核对设备，并确认机器已经交还供货方', 'showSubmitResult', '去确认退款'] as $needle) {
    $assert(str_contains($mobileReturn, $needle), '移动退货页缺少流程引导：' . $needle);
}
$mobilePurchaseReturnList = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/purchase_return/list.vue');
$mobileSaleReturnList = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/sale_return/list.vue');
$assert(str_contains($mobilePurchaseReturnList, '新建采退'), '移动采购退货列表必须提供明确可发现的新建入口');
$assert(str_contains($mobileSaleReturnList, '新建销退'), '移动销售退货列表必须提供明确可发现的新建入口');

$returnService = (string)file_get_contents($root . '/app/service/admin/ErpPurchaseReturnService.php');
$assert(str_contains($returnService, 'ErpPurchaseReturnPolicy::assess'), '退货服务必须执行统一商业退货策略');
$assert(str_contains($returnService, 'createBatch') && str_contains($returnService, "'purchase_order_id'"), '采购退货必须支持按设备来源自动拆单');
$assert(str_contains($returnService, ": 'none';"), '未付款退货单必须记录为无需退款');
$assert(str_contains($returnService, "'purchase-return-cash:'") && str_contains($returnService, 'confirmReceivableItemsInTransaction'), '采购退货当场收款必须在同一事务内生成并核销应收事实');
$assert(str_contains($returnService, '当场收款必须选择实际到账账户'), '采购退货现场收款必须强制选择资金账户');
$assert(str_contains($returnService, "['refund_receivable']"), '采购退货详情必须返回退款应收进度');
foreach (['supplier_amount', 'unpaid_offset_amount', 'refund_receivable_amount', 'retained_payable_amount', 'policy_json'] as $field) {
    $assert(str_contains($returnService, "'{$field}'"), '退货明细缺少商业决策审计字段：' . $field);
}

$costAdjust = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/cost_adjust/detail.vue');
foreach (['purchase_adjust', 'refurbish', 'internal_adjust', '供应商调价', '整备费用', '内部成本修正'] as $needle) {
    $assert(str_contains($costAdjust, $needle), '成本调整页缺少成本分类：' . $needle);
}
foreach (['costBreakdown', '采购本金', '历史成本调整', 'before_cost', "v-else-if=\"isOutbound\""] as $needle) {
    $assert(str_contains($costAdjust, $needle), '成本调整页缺少成本解释或状态修复：' . $needle);
}
$assetApi = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/api/asset.ts');
$assert(str_contains($assetApi, 'row.before_total_cost'), '成本流水必须把后端before_total_cost映射到页面');
$assert(str_contains($assetApi, 'row.after_total_cost'), '成本流水必须把后端after_total_cost映射到页面');
$stockService = (string)file_get_contents($root . '/app/service/admin/ErpStockService.php');
$assert(str_contains($stockService, 'orderPayableMap') && str_contains($stockService, "['source_type', '=', 'purchase']"), '库存中心必须兼容整单应付折账的设备分摊');
$costPresentation = (string)file_get_contents($root . '/app/support/ErpStockCostPresentation.php');
$assert(substr_count($stockService, 'ErpStockCostPresentation::summarize(') === 2 && str_contains($stockService, "'cost_summary'"), '库存列表和详情必须复用同一成本拆分');
foreach (['supplier_adjust_cost', 'internal_adjust_cost'] as $field) {
    $assert(str_contains($costPresentation, "'{$field}'"), '库存详情缺少成本拆分字段：' . $field);
}
$assert(str_contains($stockService, "['return_flow'] = ErpPurchaseReturnPolicy::assess"), '库存详情必须返回统一退货决策');
$stockDetail = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue');
foreach (['returnActionLabel(asset)', "asset.return_flow?.returnable === false", 'return-disabled-reason', 'purchase_return/create'] as $needle) {
    $assert(str_contains($stockDetail, $needle), '移动库存详情缺少禁用退货入口或原因：' . $needle);
}
$assert(str_contains($service, "'source_type' => \$costType"), '成本流水必须保存真实成本类型');

$financeService = (string)file_get_contents($root . '/app/service/admin/ErpFinanceService.php');
foreach (['business_reason', 'settlement_explanation', 'purchaseReturnItemRefundAmount', 'refund_receivable_amount'] as $needle) {
    $assert(str_contains($financeService, $needle), '采购退货应收详情必须解释设备级退款原因：' . $needle);
}
$assert(str_contains($financeService, "WHEN p.source_type = 'refurbish' THEN 'refurbish'"), '整备应付不得再归入采购批次');
foreach (['source_meta', 'category_statement_group', 'settlementLinkMeta', 'combinedCategoryMeta', 'business_source_key', 'finance_type_key'] as $needle) {
    $assert(str_contains($financeService, $needle), '应收应付缺少来源/分类快照传递：' . $needle);
}
$financeSourceService = (string)file_get_contents($root . '/app/service/admin/ErpFinanceSourceService.php');
foreach (['inventory_purchase', 'sale_revenue', 'purchase_refund', 'sale_refund', 'after_sale_compensation', 'findFinanceCategory', 'findBusinessSource'] as $needle) {
    $assert(str_contains($financeSourceService, $needle), '财务事实来源服务缺少标准分类或动态解析：' . $needle);
}
$receivableView = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/receivable/list.vue');
foreach (['财务确认供货商退款', 'ErpFinanceSourceMeta', '按退货设备确认退款到账', 'asset_id: item.asset_id', '确认退款到账'] as $needle) {
    $assert(str_contains($receivableView, $needle), 'PC应收款必须为财务展示采购退货退款闭环：' . $needle);
}
foreach (['ErpPartySelect', 'search.imei', 'search.party_id', 'search.source_type', 'search.operator_uid', 'getErpStaffOptions'] as $needle) {
    $assert(str_contains($receivableView, $needle), 'PC应收款筛选必须优先使用通用组件和结构化条件：' . $needle);
}
$financeController = (string)file_get_contents($root . '/app/adminapi/controller/ErpFinance.php');
foreach (["['source_type', '']", "['imei', '']", "['operator_uid', 0]"] as $needle) {
    $assert(str_contains($financeController, $needle), '应收款接口缺少结构化筛选参数：' . $needle);
}
foreach (["where['source_type']", "where['imei']", "where['operator_uid']"] as $needle) {
    $assert(str_contains($financeService, $needle), '应收款服务缺少结构化筛选逻辑：' . $needle);
}
$financeMetaComponent = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/components/ErpFinanceSourceMeta.vue');
foreach (['finance_type_name', 'business_source_name', 'channel_name', '应收原因', '应付原因'] as $needle) {
    $assert(str_contains($financeMetaComponent, $needle), 'PC财务来源通用组件缺少关键信息：' . $needle);
}
$partySelectComponent = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/components/ErpPartySelect.vue');
$assert(
    str_contains($partySelectComponent, '<script lang="ts">')
        && str_contains($partySelectComponent, 'inheritAttrs: false')
        && str_contains($partySelectComponent, 'useAttrs')
        && str_contains($partySelectComponent, 'v-bind="attrs"')
        && !str_contains($partySelectComponent, 'defineOptions('),
    '往来主体通用组件必须显式透传 class/style，避免 Fragment 属性告警'
);
$payableView = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/payable/list.vue');
foreach (['finance_type_key', 'business_source_key', 'channel_code', 'ErpFinanceSourceMeta'] as $needle) {
    $assert(str_contains($payableView, $needle), 'PC应付款缺少动态类型、来源或渠道筛选：' . $needle);
}
$mobileFinanceSource = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpFinanceSourceSummary.vue');
foreach (['finance_type_name', 'business_source_name', 'channel_name', 'business_reason'] as $needle) {
    $assert(str_contains($mobileFinanceSource, $needle), '移动财务来源组件缺少关键信息：' . $needle);
}
// 展示降噪：插件元数据仍由接口返回，但不再属于客户核账页面的展示契约。
$assert(str_contains($financeSourceService, "'source_plugin_name'"), '接口必须保留来源插件元数据，不能因界面隐藏而删除');
foreach (['PC' => $financeMetaComponent, '移动端' => $mobileFinanceSource] as $platform => $component) {
    $template = strstr($component, '<script', true) ?: $component;
    $assert(!preg_match('/source_plugin|pluginName|来源插件/', $template), $platform . '财务页面不得展示内部插件信息');
}
$configService = (string)file_get_contents($root . '/app/service/admin/ErpConfigService.php');
foreach (['HsxErpSaleChannelOptions', 'HsxErpFinanceCategories', 'HsxErpBusinessSourceOptions', 'getBusinessSourceOptions', 'findBusinessSource', 'statement_group'] as $needle) {
    $assert(str_contains($configService, $needle), 'ERP动态渠道/分类/来源 Hook 契约不完整：' . $needle);
}
$inboundListener = (string)file_get_contents($root . '/app/listener/ErpDeviceInboundRequested.php');
foreach (['ErpDeviceInboundRequested', 'hsx_recycle.recycle_purchase', 'origin_event_id', 'request_id', 'warehouse_id', 'location_id'] as $needle) {
    $assert(str_contains($inboundListener, $needle), '回收插件入库 Hook 缺少来源或幂等字段：' . $needle);
}

foreach ([
    '/niucloud/addon/hsx_erp/admin/views/erp/purchase/list.vue' => ['确认采购开单', '确认供应商调价'],
    '/niucloud/addon/hsx_erp/admin/views/erp/sale/list.vue' => ['确认销售出库'],
    '/niucloud/addon/hsx_erp/admin/views/erp/sale_return/list.vue' => ['确认发起销售退货'],
    '/niucloud/addon/hsx_erp/admin/views/erp/payable/list.vue' => ['确认设备付款', '确认应付应收折账'],
    '/niucloud/addon/hsx_erp/admin/views/erp/receivable/list.vue' => ['确认供货商退款到账', '确认应收应付折账'],
    '/niucloud/addon/hsx_erp/admin/views/erp/stock/list.vue' => ['确认更新设备流转'],
] as $relative => $needles) {
    $view = (string)file_get_contents($repo . $relative);
    foreach ($needles as $needle) {
        $assert(str_contains($view, $needle), 'ERP敏感操作缺少明确二次确认：' . $needle);
    }
}
$mobileSensitiveConfirm = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/hooks/useErpSensitiveConfirm.ts');
$assert(str_contains($mobileSensitiveConfirm, 'uni.showModal'), '移动端敏感操作必须使用统一二次确认');
$mobilePopupConfirm = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/hooks/useErpPopupConfirm.ts');
$assert(
    str_contains($mobilePopupConfirm, 'confirmErpSensitiveAction') && str_contains($mobilePopupConfirm, 'reopenPopup'),
    '移动端弹层敏感操作必须关闭业务弹层后调用统一二次确认，并在取消后恢复现场'
);
foreach ([
    '/site-uniapp/src/addon/hsx_erp/pages/purchase/create.vue',
    '/site-uniapp/src/addon/hsx_erp/pages/sale/create.vue',
    '/site-uniapp/src/addon/hsx_erp/pages/sale_return/create.vue',
    '/site-uniapp/src/addon/hsx_erp/pages/receivable/list.vue',
    '/site-uniapp/src/addon/hsx_erp/pages/cost_adjust/detail.vue',
] as $relative) {
    $view = (string)file_get_contents($repo . $relative);
    $assert(
        str_contains($view, 'confirmErpSensitiveAction') || str_contains($view, 'confirmErpPopupAction'),
        '移动端敏感操作缺少统一二次确认：' . $relative
    );
}
$mobilePayableList = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/payable/list.vue');
$mobilePayableModal = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpPayConfirmModal.vue');
$assert(
    str_contains($mobilePayableList, 'ErpPayConfirmModal') && str_contains($mobilePayableModal, 'confirmErpPopupAction'),
    '移动端应付款列表必须复用设备级打款弹窗并执行统一二次确认'
);
$mobileOffset = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpOffsetConfirmModal.vue');
$assert(
    str_contains($mobileOffset, 'confirmErpSensitiveAction') || str_contains($mobileOffset, 'confirmErpPopupAction'),
    '移动端应收应付折账必须使用统一二次确认'
);

$returnController = (string)file_get_contents($root . '/app/adminapi/controller/ErpPurchaseReturn.php');
$assert(str_contains($returnController, "'refund_receivable'"), '采购退货创建结果必须返回后续财务入口数据');

$pcReturn = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/purchase_return/list.vue');
foreach (['handoverConfirmed', 'process_status_label', '当前登录管理员', 'refundProgressText', 'goRefundReceivable', 'function purchaseRefundModeTip'] as $needle) {
    $assert(str_contains($pcReturn, $needle), 'PC采购退货页缺少退款闭环：' . $needle);
}
foreach (['ErpPartySelect', 'onSourcePartyChange', 'party_id: form.party_id', 'purchase_order_id: a.purchase_order_id'] as $needle) {
    $assert(str_contains($pcReturn, $needle), 'PC采购退货必须按供货方自动加载设备并保留来源关系：' . $needle);
}
foreach (['normalizeReturnAsset', 'return_flow?.paid_amount', 'assetSettlementText', '已结清 ¥'] as $needle) {
    $assert(str_contains($pcReturn, $needle), 'PC采购退货页必须按设备应付显示真实结算状态：' . $needle);
}
$assert(str_contains($pcReturn, 'create-return-grid'), 'PC采购退货页必须使用设备核验与处理结论双栏工作台');
$assert(str_contains($pcReturn, 'return-device-card'), 'PC采购退货页必须使用设备卡片降低单台设备信息空洞');
$assert(str_contains($pcReturn, 'return-decision-panel'), 'PC采购退货页必须集中展示库存和账务处理结论');
$assert(str_contains($pcReturn, 'repeat(auto-fit'), 'PC采购退货设备卡片必须根据可用宽度自动调整列数');
$assert(str_contains($pcReturn, '自动冲销应付'), '未结算设备必须展示明确的应付处理结论');

$pcSaleReturn = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/sale_return/list.vue');
$saleReturnService = (string)file_get_contents($root . '/app/service/admin/ErpSaleReturnService.php');
$assert(str_contains($saleReturnService, 'createBatch') && str_contains($saleReturnService, "'sale_order_id'"), '销售退货必须支持按设备来源自动拆单');
$assert(str_contains($saleReturnService, '$originalWarehouseId') && str_contains($saleReturnService, '$originalLocationId'), '销售退货必须由后端按设备原仓位自动回库');
$assert(!str_contains($pcSaleReturn, 'label="退回仓库 / 库位"'), '销售退货不能要求业务员人工指定回库位置');
$assert(str_contains($pcReturn, '选择供货方后自动加载可退设备') && str_contains($pcReturn, 'purchase_order_id: 0'), '采购退货应围绕供货方和设备操作');
$assert(str_contains($pcSaleReturn, '选择客户后自动加载可退设备') && str_contains($pcSaleReturn, 'sale_order_id: 0'), '销售退货应围绕客户和设备操作');
foreach (['sale-device-card', 'device-card-title', 'device-card-spec', 'device-imei', '原销售价', '本次退货价', 'sale-decision-panel'] as $needle) {
    $assert(str_contains($pcSaleReturn, $needle), '销售退货必须使用以设备关键信息为核心的卡片工作台：' . $needle);
}
foreach ([$pcReturn, $pcSaleReturn] as $returnWorkbench) {
    foreach (['<el-tabs', 'erp-status-tabs', '<el-form :inline="true"', '<el-table', 'text-page-title', "mode === 'create'", 'return-flow-guide', 'ErpPartySelect', 'ErpReturnDialog'] as $needle) {
        $assert(str_contains($returnWorkbench, $needle), '采购/销售退货必须使用统一的全宽工作台体验：' . $needle);
    }
    $assert(str_contains($returnWorkbench, 'ErpPartySelect'), '采购/销售退货筛选必须复用往来主体组件');
}
$saleReturnDetail = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/sale_return/detail.vue');
$menuDict = (string)file_get_contents($root . '/app/dict/menu/site.php');
foreach (['销售退货详情', '退货设备', '处理结论', '确认收到退货设备', '预计退款应付', 'embedded'] as $needle) {
    $assert(str_contains($saleReturnDetail, $needle), '销售退货必须提供可嵌入抽屉的详情组件：' . $needle);
}
$assert(str_contains($pcSaleReturn, '<el-drawer') && str_contains($pcSaleReturn, '<SaleReturnDetail'), '销售退货列表查看必须使用详情抽屉');
$assert(!str_contains($pcSaleReturn, '/site/hsx_erp/sale_return/detail'), '销售退货列表不能再跳转独立详情页');
$assert(!str_contains($menuDict, "'router_path' => 'hsx_erp/sale_return/detail'"), '销售退货详情不应再注册独立页面路由');
$assert(str_contains($pcSaleReturn, '售后补差') && str_contains($saleReturnService, 'createCompensation'), '销售退货工作台必须提供设备级售后补差入口');
$assert(str_contains($pcSaleReturn, '出款账户') && !str_contains($pcSaleReturn, '<el-option label="往来折抵"'), '业务端销售退货只能现场退款或转财务，现场退款必须选择出款账户');
$assert(str_contains($saleReturnDetail, '公司待向客户付款') && str_contains($saleReturnDetail, '公司已向客户支付补差款'), '售后补差详情必须从公司付款视角展示真实进度');
$assert(str_contains($saleReturnService, 'saleItemSettledMap') && str_contains($saleReturnService, '$refundPayable'), '销售退货必须按设备重新核算已收款并生成退款应付');
$assert(str_contains($saleReturnService, "'asset_id'       => (int)\$item->asset_id"), '销售退货应付必须关联具体设备');
$assert(str_contains($financeService, 'saleReturnPayableItems') && str_contains($financeService, "p.source_type = 'sale_return'"), '应付款必须支持销售退货设备明细');
$saleReturnService = (string)file_get_contents($root . '/app/service/admin/ErpSaleReturnService.php');
foreach (["(string)\$return->status === 'confirmed'", "['source_type', '=', 'sale_return']", "['asset_id', '=', (int)\$item->asset_id]", '金额不一致的退款应付'] as $needle) {
    $assert(str_contains($saleReturnService, $needle), '销售退货确认必须按设备幂等生成客户退款应付：' . $needle);
}
foreach (['createAndConfirm', 'sale_return_cancel', '退款应付已发生付款或折账'] as $needle) {
    $assert(str_contains($saleReturnService, $needle), '销售退货一站式处理或安全撤销缺失：' . $needle);
}
$voucherHook = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/hooks/useErpVoucher.ts');
$mobilePay = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpPayConfirmModal.vue');
$mobileReceipt = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpReceiptConfirmModal.vue');
foreach (['uploadImage', 'previewImage', 'serializeErpVoucherUrls'] as $needle) {
    $assert(str_contains($voucherHook, $needle), '移动端统一收付款凭证 Hook 缺少能力：' . $needle);
}
foreach ([$mobilePay, $mobileReceipt] as $component) {
    $assert(str_contains($component, 'ErpVoucherUploader') && str_contains($component, 'voucher_urls'), '设备级收付款必须上传并提交凭证');
}
$mobileSaleCreate = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/sale/create.vue');
$pcSaleCreate = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/sale/list.vue');
$assert(str_contains($mobileSaleCreate, 'ErpVoucherUploader') && str_contains($mobileSaleCreate, 'voucher_urls: form.value.voucher_urls'), '移动端销售现结必须支持收款凭证');
$assert(str_contains($pcSaleCreate, 'ErpFinanceVoucherUpload') && str_contains($pcSaleCreate, 'voucher_urls: create.form.voucher_urls'), 'PC 销售现结必须支持收款凭证');
$pcPurchaseCreate = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/purchase/list.vue');
$mobilePurchaseCreate = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/purchase/create.vue');
$assert(str_contains($service, 'confirmPayableItemsInTransaction') && str_contains($service, 'purchase_cash_settled') && str_contains($service, 'flushPendingSettlementDomainEvents'), '采购现结必须在采购事务内核销设备应付、写入资金流水，并在提交后派发事件');
$assert(str_contains($pcPurchaseCreate, '无需再次到财务确认') && str_contains($pcPurchaseCreate, 'ErpFinanceVoucherUpload') && !str_contains($pcPurchaseCreate, '付款等待财务确认'), 'PC采购现结必须即时付款并支持付款凭证，不能提示再次财务确认');
$assert(str_contains($mobilePurchaseCreate, '无需再次到财务确认') && str_contains($mobilePurchaseCreate, 'ErpVoucherUploader') && !str_contains($mobilePurchaseCreate, '待财务付款'), '移动采购现结必须即时付款并支持付款凭证，不能提示再次财务确认');
$saleChannels = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/hooks/useErpSaleChannels.ts');
$channelPopup = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpSaleChannelPopup.vue');
$assert(str_contains($saleChannels, "platform: '平台'"), '移动端 platform 渠道类型必须显示中文');
$assert(!str_contains($channelPopup, "'circle'"), '渠道未选图标不得使用不存在的 circle 图标名');
$serialTrace = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/serial_trace/list.vue');
$assert(str_contains($serialTrace, 'getMobileSerialTraceList') && str_contains($serialTrace, '供货商') && str_contains($serialTrace, '最新记录排在最上面'), '移动端必须提供多次入库串号追踪');
$saleReturnCreate = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/sale_return/create.vue');
foreach (['createAndConfirmErpSaleReturn', 'createMobileSaleCompensation', '确认退货并处理退款'] as $needle) {
    $assert(str_contains($saleReturnCreate, $needle), '移动端销售退货/售后补差流程未统一：' . $needle);
}
$stockDetailView = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/stock/list.vue');
$assert(str_contains($stockDetailView, 'ErpImageGallery') && str_contains($stockDetailView, 'assetStatusMeta(row.before_status)'), '设备档案必须渲染图片并将库存状态翻译为中文');
$assert(str_contains($stockDetailView, "sale_compensation: '售后补差应付'") && str_contains($stockDetailView, 'accountLedgerRemark'), '设备账目流水必须解释售后补差等业务事实');
$assert(str_contains($stockDetailView, '设备账务轨迹') && str_contains($stockDetailView, 'accountTimelineRows') && str_contains($stockDetailView, '_merged_compensation'), '设备档案必须保留采购、销售和售后账务轨迹，并合并同一笔售后补差的应付形成与实际付款');
$assert(str_contains($stockDetailView, 'timelineSettlementText') && str_contains($stockDetailView, '折账结清'), '设备账务轨迹必须明确展示实际收付款或折账结算结果');
$assert(str_contains($stockDetailView, "sale_cancel: '整单销售撤销'") && str_contains($stockDetailView, "sale_item_cancel: '单台销售撤销'") && !str_contains($stockDetailView, "|| '其他业务'"), '设备账务轨迹必须将销售撤销完整翻译为中文，不能退化成“其他业务”');
$assert(str_contains($stockDetailView, '实际销售收入') && str_contains($stockDetailView, 'sale_compensation_amount') && str_contains($stockDetailView, 'net_sale_amount'), '售后补差后必须同时展示原成交价、补差金额和扣除补差后的实际销售收入');
$stockService = (string)file_get_contents($root . '/app/service/admin/ErpStockService.php');
$assert(str_contains($stockService, 'enrichAssetAccountLedgers') && str_contains($stockService, "['实际付款']") && str_contains($stockService, "['实际收款']") && str_contains($stockService, 'lifecycle_key'), '设备账务接口必须按真实资金类型返回结算方式，并按业务单关联补差应付和付款，不能仅按金额猜测或把付款误标为折账');
$assert(str_contains($stockService, 'markReversedAccountLedgers') && str_contains($stockService, "['last_sale_item']['sale_order_id']") && str_contains($stockService, "['已冲销']"), '设备账务接口必须在撤销后找回原销售关系，并将原销售应收标记为已冲销');
$mobileStockDetail = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue');
$assert(str_contains($mobileStockDetail, '设备账务轨迹') && str_contains($mobileStockDetail, 'accountBizLabel') && str_contains($mobileStockDetail, 'sale_compensation'), '移动管理端设备档案必须同步展示中文化的设备级账务轨迹');
$voucherComponent = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/components/ErpFinanceVoucherUpload.vue');
$assert(str_contains($voucherComponent, '<upload-image') && str_contains($financeService, 'voucher_urls'), '真实收付款必须支持选填图片凭证并写入资金流水');
$settlementCards = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/components/ErpSettlementCards.vue');
$assert(str_contains($settlementCards, 'resultSentence') && str_contains($settlementCards, '<el-collapse-item') && str_contains($settlementCards, '真实资金与凭证'), '结算明细必须先展示业务结论，设备账款与资金凭证按需展开');
$pcPayable = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/payable/list.vue');
$assert(str_contains($financeService, "row['money_ledgers']") && str_contains($pcPayable, 'ErpSettlementCards'), '全局结算抽屉必须返回真实资金流水并复用结算卡片组件');
$assert(str_contains($financeService, 'payableDirectAssetIds') && str_contains($financeService, "'asset_id'"), '结算摘要必须通过设备级应付关联资产，不能把售后补差付款显示为0台设备');
$assert(str_contains($pcPayable, 'ledger-event-list') && str_contains($pcPayable, 'ledgerAmountMeta') && str_contains($pcPayable, '这不是资金入账') && str_contains($pcPayable, "sale_compensation: '售后补差应付'") && !str_contains($pcPayable, 'signedLedgerMoney'), '账目流水必须使用可折叠业务事件卡片，并明确区分新增应付与真实资金进出，不能使用脱离科目的裸正负金额');
$refreshHook = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/hooks/useErpPageRefresh.ts');
foreach (['onMounted', 'onActivated', 'pending', 'refresh'] as $needle) {
    $assert(str_contains($refreshHook, $needle), 'ERP页面恢复刷新机制缺少：' . $needle);
}
foreach (['purchase/list.vue', 'sale/list.vue', 'purchase_return/list.vue', 'sale_return/list.vue', 'sale_return/detail.vue', 'payable/list.vue', 'receivable/list.vue', 'workbench/index.vue'] as $relative) {
    $page = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/' . $relative);
    $assert(str_contains($page, 'useErpPageRefresh'), 'ERP核心页面返回时必须刷新接口：' . $relative);
}

$purchaseList = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/purchase/list.vue');
foreach (['canAdjustSupplierPrice', 'supplierAdjustBlockedReason', '设备已完成采购退货，不能再调整供应商采购价'] as $needle) {
    $assert(str_contains($purchaseList, $needle), 'PC采购列表必须禁止已退货设备供应商调价：' . $needle);
}
foreach (['本页有效采购汇总', '已退货、已作废货品不计入', "assetStatus === 'returned'", 'Number(row.is_returned || 0) === 1'] as $needle) {
    $assert(str_contains($purchaseList, $needle), 'PC采购汇总必须排除已退货和已作废设备：' . $needle);
}
$assert(str_contains($service, "status === ErpDict::ASSET_RETURNED") || str_contains($service, "status) === ErpDict::ASSET_RETURNED"), '后端必须拒绝已退货设备成本调整');

$identity = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/components/ErpDeviceIdentity.vue');
$identityTemplate = strstr($identity, '<script', true) ?: $identity;
$assert(!preg_match('/系统资产号|assetNo|asset_no/', $identityTemplate), '内部资产号不应在设备身份组件中展示');
$assert(str_contains($identityTemplate, 'IMEI') && str_contains($identityTemplate, 'SN'), '设备身份组件必须保留 IMEI / SN 展示');
$assert(str_contains($identity, 'assetNo?:'), '内部资产号组件参数应保留，避免破坏调用契约');

$roleDoc = (string)file_get_contents($root . '/docs/menu-role-information-priority.md');
foreach (['工作台', '采购管理', '销售出库', '库存中心', '应付款', '应收款', '基础配置'] as $menu) {
    $assert(str_contains($roleDoc, $menu), '菜单角色基线缺少：' . $menu);
}
$assert(str_contains($roleDoc, '敏感操作二次确认'), '角色与信息规范必须包含敏感操作二次确认基线');

$policyDoc = (string)file_get_contents($root . '/docs/purchase-return-cost-policy.md');
foreach (['供应商结算本金', '整备成本', '当前总成本', '优先冲销未付款', '退款应收', '售价 5500：毛利 -100'] as $rule) {
    $assert(str_contains($policyDoc, $rule), '采购退货商业规范缺少：' . $rule);
}

$saleService = (string)file_get_contents($root . '/app/service/admin/ErpSaleService.php');
$assert(str_contains($saleService, ': round((float)$asset->total_cost, 2);')
    && str_contains($saleService, "\$ownershipType === 'consigned'"), '自有设备必须使用当前总成本，代卖设备必须使用客户结算金额计算毛利');
$saleList = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/sale/list.vue');
foreach (['本页有效销售汇总', "row.status !== 'sold'", '已销售退货', '已取消销售', '本页同批', 'saleRowClassName', '取消销售'] as $needle) {
    $assert(str_contains($saleList, $needle), 'PC销售列表必须按有效设备展示批次、状态和汇总：' . $needle);
}
foreach (['appendReturnContext', "['status', '=', ErpDict::ASSET_SOLD]", "where('i.status', '=', ErpDict::ASSET_SOLD)"] as $needle) {
    $assert(str_contains($saleService, $needle), '销售服务必须统一退货上下文和有效销售口径：' . $needle);
}
$assert(str_contains($saleList, '实际销售收入') && str_contains($saleList, 'net_sale_amount'), '销售列表和汇总必须按扣除售后补差后的实际销售收入展示');
$assert(str_contains($saleService, 'after_sale_compensation') && str_contains($saleService, 'sale_compensation_amount') && str_contains($saleService, 'net_sale_amount'), '销售接口必须区分真正退货与售后补差，并返回设备级实际销售收入');
$stockList = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/stock/list.vue');
$assert(str_contains($stockList, 'onActivated') && str_contains($stockList, 'activatedOnce'), '库存中心从财务页面返回后必须自动刷新结算状态');
foreach (['label="入库"', 'label="出库"', '尚未销售出库', 'outboundStatusMeta', 'hasEffectiveOutbound', '当前库存 / 流转', '订单结算', '采购款', '销售款', 'financeStatusMeta'] as $needle) {
    $assert(str_contains($stockList, $needle), '库存中心必须同时展示设备入库、出库和缺省状态：' . $needle);
}
$stockService = (string)file_get_contents($root . '/app/service/admin/ErpStockService.php');
foreach (['appendLifecycleContext', 'inbound_purchase_no', 'outbound_sale_no', 'outbound_sale_item_id', 'inbound_finance_status', 'outbound_finance_status', 'outbound_settled_amount'] as $needle) {
    $assert(str_contains($stockService, $needle), '库存服务必须提供稳定的设备生命周期字段：' . $needle);
}
$serialTracePage = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/serial_trace/detail.vue');
$serialTimeline = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpAssetLifecycleTimeline.vue');
foreach (['serialTraceDetail', "whereOr('sn', '=', \$serial)", "'cycles' => \$assets", "'timeline' => \$timeline"] as $needle) {
    $assert(str_contains($stockService, $needle), '串号追踪必须聚合同一 IMEI/SN 的全部入库周期：' . $needle);
}
foreach (['入库周期', '完整流转时间轴', '每次重新入库都是一段独立业务'] as $needle) {
    $assert(str_contains($serialTracePage, $needle), '移动端串号详情缺少生命周期信息：' . $needle);
}
foreach (['采购入库', '销售出库', '客户退货入库', '退还供应商', '设备现在'] as $needle) {
    $assert(str_contains($serialTimeline, $needle), '串号生命周期可视化缺少关键节点：' . $needle);
}
$payModal = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpPayConfirmModal.vue');
$receiptModal = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpReceiptConfirmModal.vue');
$offsetModal = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpOffsetConfirmModal.vue');
foreach ([$payModal, $receiptModal, $offsetModal] as $modal) {
    $assert(str_contains($modal, 'scroll-y') && str_contains($modal, 'min-height:0'), '财务弹窗必须提供独立的纵向滚动区域');
}

$configService = (string)file_get_contents($root . '/app/service/admin/ErpConfigService.php');
foreach (['HsxErpSaleChannelOptions', 'HsxErpFinanceCategories', 'HsxErpBusinessSourceOptions', 'baseSaleChannelOptions', 'baseFinanceCategories', 'baseBusinessSourceOptions', 'getBusinessSourceOptions', 'findBusinessSource', "'erp_peer'", "'refurbish_labor'", "'hsx_erp.manual_purchase'", "'hsx_erp.manual_sale'", "'hsx_erp.manual_refurbish'"] as $needle) {
    $assert(str_contains($configService, $needle), 'ERP动态字典缺少基础项或插件Hook：' . $needle);
}
foreach (['sale_revenue', 'inventory_purchase', 'sale_refund', 'purchase_refund', 'after_sale_compensation', 'statement_group', 'revenue_reversal', 'purchase_reversal'] as $needle) {
    $assert(str_contains($configService, $needle), 'ERP财务分类缺少商业报表基础项：' . $needle);
}
$assert(str_contains($configService, '业务来源 key 冲突，保留先注册项'), '业务来源插件 key 冲突不得静默覆盖');
$assert(!str_contains($configService, "getConfigValue(\$this->site_id, self::SALE_CHANNEL_KEY)"), '动态销售渠道不得继续读取旧版自由输入配置');
$phoneShopEvent = (string)file_get_contents(dirname($root) . '/phone_shop/app/event.php');
$phoneShopListener = (string)file_get_contents(dirname($root) . '/phone_shop/app/listener/erp/ErpSaleChannelOptionsListener.php');
$assert(str_contains($phoneShopEvent, 'HsxErpSaleChannelOptions') && str_contains($phoneShopListener, 'phone_shop_mini_program'), '商城插件必须通过牛云事件机制贡献小程序销售渠道');
$phoneShopSourceListener = (string)file_get_contents(dirname($root) . '/phone_shop/app/listener/erp/ErpBusinessSourceOptionsListener.php');
$recycleEvent = (string)file_get_contents(dirname($root) . '/hsx_recycle/app/event.php');
$recycleSourceListener = (string)file_get_contents(dirname($root) . '/hsx_recycle/app/listener/erp/ErpBusinessSourceOptionsListener.php');
$assert(str_contains($phoneShopEvent, 'HsxErpBusinessSourceOptions') && str_contains($phoneShopSourceListener, 'phone_shop.mini_program_sale'), '商城插件必须通过Hook贡献小程序销售业务来源');
$assert(str_contains($recycleEvent, 'HsxErpBusinessSourceOptions') && str_contains($recycleSourceListener, 'hsx_recycle.recycle_purchase'), '回收插件必须通过Hook贡献回收采购业务来源');
$configController = (string)file_get_contents($root . '/app/adminapi/controller/ErpConfig.php');
$configRoute = (string)file_get_contents($root . '/app/adminapi/route/route.php');
$assert(str_contains($configController, 'businessSourceOptions') && str_contains($configRoute, 'config/business_source_options'), '业务来源字典必须提供站点管理端查询接口');
$assert(str_contains($saleService, "'sale_channel_key'") && str_contains($saleService, "'channel_source_plugin'"), '销售单必须保存渠道稳定编码和来源插件快照');
$assert(str_contains($saleList, 'getErpSaleChannelOptions') && str_contains($saleList, '<el-select v-model="create.form.sale_channel_key"'), 'PC销售开单必须使用动态渠道下拉，不能让用户自由输入');
foreach (["'source_type' => 'refurbish'", '\'asset_id\' => $id', '请为每一项整备项目选择服务商', "'amount' => \$itemAmount", "'party_id' => \$itemPartyId"] as $needle) {
    $assert(str_contains($stockService, $needle), '设备整备费用必须按设备和服务商生成应付：' . $needle);
}
$inboundListener = (string)file_get_contents($root . '/app/listener/ErpDeviceInboundRequested.php');
foreach (["\$device['refurbishment']", "'refurbish_required'", "'refurbishment_suggestion'", "'estimated_cost'"] as $needle) {
    $assert(str_contains($inboundListener, $needle), '回收插件的整备决定必须透传到ERP入库，不得丢失：' . $needle);
}
foreach (['sendRefurbish', 'completeRefurbish', 'refurbish_complete', '整备费用必须在设备“整备完成”时登记', "['pending', 'processing', 'failed']"] as $needle) {
    $assert(str_contains($stockService, $needle), '整备必须走待整备、开始、完工及异常闭环：' . $needle);
}
$capitalService = (string)file_get_contents($root . '/app/service/admin/ErpCapitalAccountService.php');
$capitalView = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/capital_account/list.vue');
$assert(str_contains($capitalService, 'findFinanceCategory') && str_contains($capitalService, "'category_source_plugin'"), '手工收付款必须校验动态收支类型并保存插件来源快照');
$assert(str_contains($capitalView, 'getErpFinanceCategories') && str_contains($capitalView, 'entryCategoryOptions'), '资金账户记账必须使用动态收入/支出类型');
$hookDoc = (string)file_get_contents($root . '/docs/ERP动态字典与插件Hook契约.md');
$assert(str_contains($hookDoc, 'ERP 基础项 + 商家自定义项 + 当前站点套餐已安装插件的 Hook 返回项'), '动态字典Hook必须有稳定的插件开发契约');

$integrationService = (string)file_get_contents($root . '/app/service/admin/ErpIntegrationService.php');
foreach (['enqueueDomainEvent', 'dispatchDomainEvent', 'retryPendingDomainEvents', 'required_consumers'] as $needle) {
    $assert(str_contains($integrationService, $needle), '跨插件领域事件缺少事务Outbox或自动重试能力：' . $needle);
}
$assert(str_contains($saleService, 'erp.asset.sold.v1') && str_contains($saleService, 'erp.asset.returned.v1'), '销售完成和撤销必须发布设备级领域事件');
$assert(str_contains($saleReturnService, 'erp.asset.returned.v1'), '销售退货确认必须发布设备退回事件');
$deviceEventService = (string)file_get_contents(dirname($root) . '/hsx_device_asset/app/service/core/DeviceAssetErpEventService.php');
$assert(str_contains($deviceEventService, "'erp_asset_id' => \$erpAssetId"), '设备中台已有记录也必须回填ERP资产关联');
$addon = (string)file_get_contents($root . '/Addon.php');
$assert(str_contains($addon, "installAddonSchedule('hsx_erp')") && str_contains($addon, "uninstallAddonSchedule('hsx_erp')"), '领域事件失败重试任务必须随插件安装和卸载');

echo "[PASS] ERP purchase granularity and role UI smoke test\n";
