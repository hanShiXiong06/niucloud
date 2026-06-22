<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div class="flex items-center">
                    <div class="text-page-title">设备中心</div>
                    <div class="  ml-2 mt-1 text-sm text-gray-500">默认看「在手库存」——你现在手上有哪些货。按状态切换；切到「全部」可看含已售的所有设备。</div>
                </div>
                <div class="flex gap-2">
                    <el-button v-permission="'hsx_erp_asset_manual_inbound'" type="primary" @click="openManualInbound">入库</el-button>
                    <el-button :icon="Refresh" @click="loadList">刷新</el-button>
                    <el-button text @click="showOverview = !showOverview">
                        {{ showOverview ? '收起看板' : '展开看板' }}
                        <el-icon class="ml-1"><ArrowUp v-if="showOverview" /><ArrowDown v-else /></el-icon>
                    </el-button>
                </div>
            </div>

            <el-alert
                v-if="recycleConnected"
                class="mt-3"
                type="success"
                :closable="false"
                show-icon
                title="已与回收系统打通：回收单确认回收后，设备会自动同步到这里（待入库池），无需在此手动入库。手动入库仅用于非回收来源（如自行采购/期初建档）。"
            />

            <!-- 库存概览(可折叠,腾出列表空间) -->
            <div v-show="showOverview" class="mt-3 grid grid-cols-4 gap-3">
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500">在手库存</div>
                    <div class="mt-1 text-xl font-semibold text-gray-800">{{ overview.on_hand.count }} <span class="text-sm font-normal text-gray-400">台</span></div>
                    <div class="text-xs text-gray-400">成本 ¥{{ money(overview.on_hand.cost) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500">可售</div>
                    <div class="mt-1 text-xl font-semibold text-blue-600">{{ overview.sellable.count }} <span class="text-sm font-normal text-gray-400">台</span></div>
                    <div class="text-xs text-gray-400">可售金额 ¥{{ money(overview.sellable.amount) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500">本月已售</div>
                    <div class="mt-1 text-xl font-semibold text-gray-800">{{ overview.sold_month.count }} <span class="text-sm font-normal text-gray-400">台</span></div>
                    <div class="text-xs" :class="Number(overview.sold_month.gross_profit) >= 0 ? 'text-green-600' : 'text-red-600'">毛利 ¥{{ money(overview.sold_month.gross_profit) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500">平均库龄</div>
                    <div class="mt-1 text-xl font-semibold" :class="Number(overview.avg_age_days) >= 30 ? 'text-red-600' : 'text-green-600'">{{ overview.avg_age_days }} <span class="text-sm font-normal text-gray-400">天</span></div>
                    <div class="text-xs text-gray-400">在手平均在库时长</div>
                </div>
            </div>

            <el-tabs v-model="search.inventory_status" class="mt-3" @tab-change="handleSearch">
                <el-tab-pane label="在手库存" name="onhand" />
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待入库" name="pending_in" />
                <el-tab-pane label="待拍照" name="pending_photo" />
                <el-tab-pane label="在库待整备" name="in_stock" />
                <el-tab-pane label="整备中" name="refurbishing" />
                <el-tab-pane :label="integrated ? '已交中台' : '待销售定价'" name="pending_pricing" />
                <el-tab-pane label="可售" name="available_for_sale" />
                <el-tab-pane label="已售/下架" name="sold" />
            </el-tabs>

            <el-form :inline="true" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input
                        v-model.trim="search.keyword"
                        clearable
                        class="!w-[300px]"
                        placeholder="资产号/IMEI/SN/型号，可空格或逗号隔开搜多台"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item label="仓库">
                    <el-select v-model="search.warehouse_id" placeholder="全部仓库" clearable filterable style="width: 160px" @change="onWarehouseFilterChange">
                        <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.warehouse_name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库位">
                    <el-select v-model="search.location_id" placeholder="全部库位" clearable filterable style="width: 150px" :disabled="!search.warehouse_id" @change="handleSearch">
                        <el-option v-for="l in filterLocations" :key="l.id" :label="l.location_name" :value="l.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="归属">
                    <el-select v-model="search.ownership_type" placeholder="全部" clearable style="width: 110px" @change="handleSearch">
                        <el-option label="自有" value="owned" />
                        <el-option label="代卖" value="consign" />
                    </el-select>
                </el-form-item>
                <el-form-item label="成本">
                    <el-input v-model="search.cost_min" placeholder="最低" clearable style="width: 90px" @keyup.enter="handleSearch" />
                    <span class="mx-1 text-gray-400">~</span>
                    <el-input v-model="search.cost_max" placeholder="最高" clearable style="width: 90px" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="库龄">
                    <el-select v-model="search.stock_age" placeholder="全部" clearable style="width: 120px" @change="handleSearch">
                        <el-option label="0-3 天" value="0-3" />
                        <el-option label="3-7 天" value="3-7" />
                        <el-option label="7-15 天" value="7-15" />
                        <el-option label="15-30 天" value="15-30" />
                        <el-option label="30 天以上" value="30+" />
                    </el-select>
                </el-form-item>
                <el-form-item label="入库时间">
                    <el-date-picker v-model="search.stockInRange" type="daterange" value-format="X" range-separator="至"
                        start-placeholder="开始" end-placeholder="结束" style="width: 230px" @change="handleSearch" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button @click="exportCsv" :disabled="!table.data.length">导出当前页</el-button>
                </el-form-item>
            </el-form>

            <div class="mb-3 flex flex-wrap gap-4 rounded-lg bg-gray-50 px-4 py-2 text-sm">
                <span>共 <b class="text-[var(--el-color-primary)]">{{ summary.count }}</b> 台</span>
                <span>成本合计 <b class="text-orange-600">¥{{ money(summary.total_cost) }}</b></span>
                <span>参考售价合计 <b class="text-blue-600">¥{{ money(summary.total_sale) }}</b></span>
                <span v-if="summary.in_stock_count">在库 <b>{{ summary.in_stock_count }}</b> 台 · 平均库龄 <b :class="Number(summary.avg_age_days) >= 30 ? 'text-red-600' : 'text-green-600'">{{ summary.avg_age_days }}</b> 天</span>
            </div>

            <div v-if="selectedAssets.length" class="mb-3 flex items-center justify-between rounded-md bg-blue-50 px-3 py-2">
                <div class="text-sm text-gray-600">已选 <b class="text-blue-600">{{ selectedAssets.length }}</b> 台（跨页累计，翻页/搜索不丢）</div>
                <div class="flex gap-2">
                    <el-button v-if="selectedPendingIn.length" v-permission="'hsx_erp_asset_batch_confirm_inbound'" type="primary" @click="batchConfirmInbound">
                        批量确认入库 ({{ selectedPendingIn.length }})
                    </el-button>
                    <el-button v-if="selectedSellable.length" type="danger" @click="openOutboundBatch">
                        批量卖出 ({{ selectedSellable.length }})
                    </el-button>
                    <el-button text @click="clearAssetSelection">清空已选</el-button>
                </div>
            </div>

            <el-table
                ref="assetTableRef"
                :data="table.data"
                v-loading="table.loading"
                size="large"
                row-key="id"
                @selection-change="handleSelectionChange"
                @sort-change="handleSortChange"
            >
                <el-table-column type="selection" width="52" :selectable="rowSelectable" reserve-selection />
                <el-table-column prop="asset_no" label="资产编号" min-width="180" />
                <el-table-column label="设备" min-width="260">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">IMEI：{{ row.imei || '-' }}</div>
                        <div class="text-xs text-gray-500">SN：{{ row.sn || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="source_device_id" label="来源设备ID" width="120" />
                <el-table-column label="回收单位 / 来源" min-width="160">
                    <template #default="{ row }">
                        <template v-if="row.recycle_party">
                            <div v-if="row.recycle_party.entity_id" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.recycle_party.entity_id)">{{ row.recycle_party.entity_name || row.recycle_party.name }}</div>
                            <div v-else class="font-medium text-gray-800">{{ row.recycle_party.entity_name || '未归属主体' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ row.recycle_party.name || '-' }}<span v-if="row.recycle_party.mobile"> · {{ row.recycle_party.mobile }}</span></div>
                        </template>
                        <span v-else class="text-gray-300">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="销售单位 / 买家" min-width="160">
                    <template #default="{ row }">
                        <template v-if="row.sales_party">
                            <div v-if="row.sales_party.entity_id" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.sales_party.entity_id)">{{ row.sales_party.entity_name || row.sales_party.name }}</div>
                            <div v-else class="font-medium text-gray-800">{{ row.sales_party.entity_name || '未归属主体' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ row.sales_party.name || '-' }}<span v-if="row.sales_party.mobile"> · {{ row.sales_party.mobile }}</span></div>
                        </template>
                        <span v-else class="text-gray-300">未售出</span>
                    </template>
                </el-table-column>
                <el-table-column label="归属" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.ownership_type === 'consign' ? 'warning' : 'success'" effect="plain">
                            {{ row.ownership_type === 'consign' ? '代卖' : '自有' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="成本" width="120" align="right" prop="current_cost" sortable="custom">
                    <template #default="{ row }">¥{{ money(row.current_cost) }}</template>
                </el-table-column>
                <el-table-column label="参考售价" width="140" align="right" prop="current_sale_price" sortable="custom">
                    <template #default="{ row }">
                        <template v-if="Number(row.current_sale_price) > 0">
                            <div class="font-medium text-gray-800">¥{{ money(row.current_sale_price) }}</div>
                            <div class="text-xs text-gray-400">{{ priceSource() }}</div>
                        </template>
                        <span v-else class="text-gray-300">—</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="130">
                    <template #default="{ row }">
                        <el-tooltip
                            :content="nextStepText(row.inventory_status)"
                            :disabled="!nextStepText(row.inventory_status)"
                            placement="top"
                        >
                            <span class="inline-flex cursor-default items-center gap-1">
                                <el-tag :type="displayStatusType(row)">{{ displayStatusText(row) }}</el-tag>
                                <el-icon v-if="nextStepText(row.inventory_status)" class="text-gray-300"><InfoFilled /></el-icon>
                            </span>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="stock_in_at" label="入库时间" width="170" sortable="custom">
                    <template #default="{ row }">{{ formatTime(row.stock_in_at) }}</template>
                </el-table-column>
                <el-table-column label="库龄/周转" width="110" align="right">
                    <template #default="{ row }">
                        <span v-if="row.age_days === null || row.age_days === undefined" class="text-gray-300">-</span>
                        <template v-else>
                            <span v-if="row.age_type === 'turnover'" class="text-gray-500">周转 {{ row.age_days }}天</span>
                            <span v-else :class="Number(row.age_days) >= 30 ? 'text-red-600' : (Number(row.age_days) >= 14 ? 'text-orange-500' : 'text-gray-700')">在库 {{ row.age_days }}天</span>
                        </template>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="200" align="center">
                    <template #default="{ row }">
                        <el-tooltip v-if="row.inventory_status === 'pending_in'" v-permission="'hsx_erp_asset_confirm_inbound'" content="确认入库" placement="top">
                            <el-button type="primary" link :icon="Check" :loading="row._confirming" @click="confirmInbound(row)" />
                        </el-tooltip>
                        <el-tooltip v-if="row.inventory_status === 'pending_photo'" content="完成拍照" placement="top">
                            <el-button type="primary" link :icon="Camera" @click="openPhoto(row)" />
                        </el-tooltip>
                        <el-tooltip v-if="row.inventory_status === 'in_stock'" v-permission="'hsx_erp_refurbishment_create'" content="发起整备" placement="top">
                            <el-button type="primary" link :icon="MagicStick" @click="startRefurbishment(row)" />
                        </el-tooltip>
                        <el-tooltip v-if="row.inventory_status === 'in_stock'" v-permission="'hsx_erp_refurbishment_skip'" content="无需整备" placement="top">
                            <el-button type="success" link :icon="DArrowRight" @click="skipRefurbishment(row)" />
                        </el-tooltip>
                        <!-- ERP 定价/调价：独立模式全显；联合模式下，走过拍照(有图)的、或未真正交中台的本地设备，也由 ERP 定价 -->
                        <el-tooltip
                            v-if="(!integrated || row.has_photo || !row.delegated_to_mid) && ['pending_pricing', 'available_for_sale'].includes(row.inventory_status)"
                            v-permission="'hsx_erp_pricing_save'"
                            :content="row.inventory_status === 'available_for_sale' ? '调价' : '定价'"
                            placement="top"
                        >
                            <el-button type="primary" link :icon="Money" @click="openPricing(row)" />
                        </el-tooltip>
                        <!-- 就地出库/卖出：在库即可直接卖，无需拍照/上架 -->
                        <el-button v-if="canOutbound(row)" v-permission="'hsx_erp_outbound_create'" type="danger" link size="small" :icon="Sell" @click="openOutbound(row)">卖同行</el-button>
                        <el-tooltip v-if="canTransfer(row)" v-permission="'hsx_erp_outbound_transfer'" content="调拨" placement="top">
                            <el-button type="warning" link :icon="Sort" @click="openTransfer(row)" />
                        </el-tooltip>
                        <el-tooltip v-if="canAdjustCost(row)" v-permission="'hsx_erp_asset_adjust_cost'" content="调成本" placement="top">
                            <el-button type="info" link :icon="Edit" @click="openCostAdjust(row)" />
                        </el-tooltip>
                        <el-tooltip content="详情" placement="top">
                            <el-button type="primary" link :icon="View" @click="openDetail(row)" />
                        </el-tooltip>
                        <el-tooltip content="全链路追踪" placement="top">
                            <el-button type="primary" link :icon="Share" @click="openTrace({ assetId: Number(row.id) })" />
                        </el-tooltip>
                    </template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        v-if="search.keyword || search.inventory_status"
                        icon="search"
                        title="没有符合条件的设备"
                        description="换个关键词或库存状态再试试。"
                    />
                    <EmptyState
                        v-else
                        icon="box"
                        title="还没有库存设备"
                        description="点击「入库」录入第一台；或在回收订单确认回收后，设备会自动同步到这里的待入库池。"
                    >
                        <template #action>
                            <el-button v-permission="'hsx_erp_asset_manual_inbound'" type="primary" @click="openManualInbound">入库</el-button>
                        </template>
                    </EmptyState>
                </template>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="table.page"
                    v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next, jumper"
                    :total="table.total"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-dialog v-model="manualDialog.visible" title="入库建档" width="760px" destroy-on-close top="6vh">
            <el-form label-width="84px" label-position="right" class="manual-inbound-form">
                <!-- 设备信息 -->
                <el-divider content-position="left"><span class="text-sm font-medium text-gray-700">设备信息</span></el-divider>
                <div class="grid grid-cols-2 gap-x-6">
                    <el-form-item label="设备型号" required>
                        <el-input v-model.trim="manualForm.model" placeholder="例如 iPhone 15 Pro" />
                    </el-form-item>
                    <el-form-item label="入库类型" required>
                        <el-select v-model="manualForm.business_type" class="w-full" @change="handleBusinessTypeChange">
                            <el-option label="回收客户" value="recycle" />
                            <el-option label="同行/供应商采购" value="purchase" />
                            <el-option label="代卖委托" value="consignment" />
                            <el-option label="期初库存" value="opening" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-form-item v-if="manualForm.business_type !== 'opening'" :label="manualForm.business_type === 'consignment' ? '寄卖人' : '卖方客户'" required>
                    <counterparty-select v-model="manualForm.counterparty_id" role-type="supplier" placeholder="搜索姓名 / 手机号选择卖方（货物来源）" @resolved="onSupplierResolved" />
                </el-form-item>
                <div class="grid grid-cols-2 gap-x-6">
                    <el-form-item label="IMEI">
                        <el-input v-model.trim="manualForm.imei" placeholder="IMEI / SN 至少填一个" />
                    </el-form-item>
                    <el-form-item label="IMEI2">
                        <el-input v-model.trim="manualForm.imei2" />
                    </el-form-item>
                    <el-form-item label="SN">
                        <el-input v-model.trim="manualForm.sn" />
                    </el-form-item>
                    <el-form-item label="容量">
                        <el-input v-model.trim="manualForm.capacity" placeholder="例如 256GB" />
                    </el-form-item>
                    <el-form-item label="颜色">
                        <el-input v-model.trim="manualForm.color" />
                    </el-form-item>
                    <el-form-item label="销售价">
                        <el-input-number v-model="manualForm.suggested_sale_price" :min="0" :precision="2" controls-position="right" class="!w-full" />
                    </el-form-item>
                </div>

                <!-- 结算（钱） -->
                <el-divider content-position="left"><span class="text-sm font-medium text-gray-700">结算（钱）</span></el-divider>
                <div class="grid grid-cols-2 gap-x-6">
                    <el-form-item :label="manualForm.business_type === 'consignment' ? '入库成本' : '应付/成本'">
                        <el-input-number v-model="manualForm.purchase_cost" :min="0" :precision="2" controls-position="right"
                            :disabled="manualForm.business_type === 'consignment'" class="!w-full" />
                    </el-form-item>
                    <el-form-item v-if="!['consignment', 'opening'].includes(manualForm.business_type)" :label="manualForm.settle_mode === 'cash' ? '付款金额' : '订金/已付'">
                        <el-input-number v-model="manualForm.paid_amount" :min="0" :max="manualForm.purchase_cost" :precision="2"
                            controls-position="right" :disabled="manualForm.settle_mode === 'cash' || manualForm.use_prepay" class="!w-full" />
                    </el-form-item>
                </div>
                <el-form-item v-if="manualPrepay.available > 0 && !['consignment', 'opening'].includes(manualForm.business_type)" label="采购预付">
                    <el-checkbox v-model="manualForm.use_prepay" @change="onUsePrepayChange">用预付抵扣本台应付</el-checkbox>
                    <span class="ml-2 text-xs text-green-600">该卖方可用预付 ¥{{ money(manualPrepay.available) }}（货款已提前付过，建档即从预付里核销这台）</span>
                </el-form-item>
                <el-form-item v-if="manualForm.use_prepay && manualPrepayShortfall > 0" :label="`差额补付（¥${money(manualPrepayShortfall)}）`">
                    <el-select v-model="manualForm.paid_account_id" filterable clearable class="w-full" placeholder="预付不够这台，差额从哪个户头补付（不选则挂应付）">
                        <el-option v-for="acc in accountOptions" :key="acc.id" :value="acc.id"
                            :label="`${acc.account_name}（余额 ¥${money(acc.balance)}）`" :disabled="Number(acc.balance) < manualPrepayShortfall" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="!manualForm.use_prepay && !['consignment', 'opening'].includes(manualForm.business_type)" label="结算方式">
                    <el-radio-group v-model="manualForm.settle_mode" @change="handleSettleModeChange">
                        <el-radio-button label="cash">现结（当场付清）</el-radio-button>
                        <el-radio-button label="credit">挂账（应付卖方）</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="showPayAccount" label="付款户头" :required="payNowAmount > 0">
                    <el-select v-model="manualForm.paid_account_id" filterable clearable class="w-full" placeholder="本次付款从哪个户头出账">
                        <el-option v-for="acc in accountOptions" :key="acc.id" :value="acc.id"
                            :label="`${acc.account_name}（余额 ¥${money(acc.balance)}）`" :disabled="payNowAmount > 0 && Number(acc.balance) < payNowAmount" />
                    </el-select>
                </el-form-item>
                <div v-if="!['consignment', 'opening'].includes(manualForm.business_type)"
                    class="mb-4 rounded-md px-3 py-2 text-sm" :class="manualUnpaidAmount > 0 ? 'bg-orange-50 text-orange-600' : 'bg-green-50 text-green-600'">
                    {{ manualSettlementText }}
                </div>

                <!-- 入库（货） -->
                <el-divider content-position="left"><span class="text-sm font-medium text-gray-700">入库（货）</span></el-divider>
                <div class="grid grid-cols-2 gap-x-6">
                    <el-form-item label="入库仓库">
                        <el-select v-model="manualForm.warehouse_id" filterable clearable class="w-full" placeholder="不选=待入库" @change="manualForm.location_id = 0">
                            <el-option v-for="item in warehouseOptions" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="入库库位" :required="Number(manualForm.warehouse_id) > 0">
                        <el-select v-model="manualForm.location_id" filterable clearable class="w-full" :disabled="!Number(manualForm.warehouse_id)" placeholder="选了仓库需选库位">
                            <el-option v-for="item in manualLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-form-item label="是否整备">
                    <el-switch v-model="manualForm.need_refurb" active-text="需要整备" inactive-text="免整备" inline-prompt />
                    <span class="ml-2 text-xs text-gray-400">{{ manualForm.need_refurb ? '入库后自动建整备工单，进入「整备中」' : '入库后直接进「待定价」/「可售」' }}</span>
                </el-form-item>
                <!-- 商城上架(选填)：手工补图片+质检，与售价凑齐三要素即直推商城「待上架货源」，免走拍照工单 -->
                <el-divider content-position="left"><span class="text-sm font-medium text-gray-700">商城上架（选填）</span></el-divider>
                <el-form-item label="商品图片">
                    <div class="w-full">
                        <upload-image v-model="manualForm.images" :limit="9" />
                        <div class="mt-1 text-xs text-gray-400">传了图片 + 填了销售价(上面)即"三要素齐"，入库后自动推送商城「待上架货源」，无需拍照工单；不传则走正常入库。</div>
                    </div>
                </el-form-item>
                <el-form-item label="质检简述">
                    <el-input v-model.trim="manualForm.qc_note" type="textarea" :rows="2" placeholder="简单描述成色/功能/瑕疵，如：99新，功能全好，左下角轻微划痕" maxlength="500" show-word-limit />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="manualForm.remark" type="textarea" :rows="2" placeholder="录入来源、采购说明等" />
                </el-form-item>
                <div class="-mt-1 text-xs leading-5 text-gray-400">
                    付款与入库相互独立：可先付款后入库（不选仓库=待入库），也可先入库后挂账。
                    <template v-if="Number(manualForm.warehouse_id) > 0">已选库位则建档即确认入库{{ Number(manualForm.suggested_sale_price) > 0 ? '并转「可售」' : '，未填销售价进「待定价」' }}。</template>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="manualDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="manualDialog.submitting" @click="submitManualInbound">
                    {{ Number(manualForm.warehouse_id) > 0 ? '建档并入库' : '创建待入库设备' }}
                </el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="counterpartyDialog.visible" title="快速新增往来单位" width="520px">
            <el-form label-width="100px">
                <el-form-item label="单位类型">
                    <el-select v-model="counterpartyDialog.form.counterparty_type" class="w-full">
                        <el-option label="个人" value="individual" />
                        <el-option label="企业" value="company" />
                    </el-select>
                </el-form-item>
                <el-form-item label="名称" required><el-input v-model.trim="counterpartyDialog.form.name" /></el-form-item>
                <el-form-item label="手机号"><el-input v-model.trim="counterpartyDialog.form.mobile" /></el-form-item>
                <el-form-item label="联系人"><el-input v-model.trim="counterpartyDialog.form.contact_name" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="counterpartyDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="counterpartyDialog.loading" @click="submitQuickCounterparty">保存并选择</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" title="ERP 设备详情" size="720px">
            <el-descriptions v-if="detail.asset" :column="2" border>
                <el-descriptions-item label="资产编号">{{ detail.asset.asset_no }}</el-descriptions-item>
                <el-descriptions-item label="库存状态">{{ statusName(detail.asset.inventory_status) }}</el-descriptions-item>
                <el-descriptions-item label="IMEI">{{ detail.asset.imei || '-' }}</el-descriptions-item>
                <el-descriptions-item label="SN">{{ detail.asset.sn || '-' }}</el-descriptions-item>
                <el-descriptions-item label="型号" :span="2">{{ detail.asset.model || '-' }}</el-descriptions-item>
                <el-descriptions-item label="来源主体">{{ detail.contact?.unit_name || '散户/未关联' }}</el-descriptions-item>
                <el-descriptions-item label="主体电话">{{ detail.contact?.unit_mobile || '-' }}</el-descriptions-item>
                <el-descriptions-item label="关联人">{{ detail.contact?.person_name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="关联人电话">{{ detail.contact?.person_mobile || '-' }}</el-descriptions-item>
                <el-descriptions-item label="采购成本">¥{{ money(detail.asset.purchase_cost) }}</el-descriptions-item>
                <el-descriptions-item label="当前总成本">¥{{ money(detail.asset.current_cost) }}</el-descriptions-item>
            </el-descriptions>

            <el-descriptions v-if="detail.outbound_info" class="mt-4" :column="2" border title="出库信息">
                <el-descriptions-item label="出库类型">{{ detail.outbound_info.type_text }}</el-descriptions-item>
                <el-descriptions-item label="出库单号">{{ detail.outbound_info.outbound_no }}</el-descriptions-item>
                <el-descriptions-item label="卖给/出给">{{ detail.outbound_info.buyer_name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="出货价">¥{{ money(detail.outbound_info.sale_price) }}</el-descriptions-item>
                <el-descriptions-item label="操作出库人">{{ detail.outbound_info.operator_name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="出库时间">{{ formatTime(detail.outbound_info.out_at) }}</el-descriptions-item>
            </el-descriptions>

            <el-alert
                v-if="detail.asset"
                class="mt-4"
                :type="detail.asset.inventory_status === 'pending_in' ? 'warning' : 'info'"
                :closable="false"
                :title="nextStepText(detail.asset.inventory_status)"
            />

            <div class="mt-5 font-medium">库存流水</div>
            <el-table class="mt-3" :data="detail.stock_ledger || []" size="small" empty-text="暂无库存流水">
                <el-table-column label="动作" width="130">
                    <template #default="{ row }">{{ row.action_text || row.action }}</template>
                </el-table-column>
                <el-table-column label="状态变化" min-width="160">
                    <template #default="{ row }">{{ row.before_status_text || statusName(row.before_status) }} → {{ row.after_status_text || statusName(row.after_status) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="120" />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
            </el-table>

            <div class="mt-5 font-medium">成本流水</div>
            <el-table class="mt-3" :data="detail.cost_ledger || []" size="small" empty-text="暂无成本流水">
                <el-table-column label="成本类型" width="120">
                    <template #default="{ row }">{{ row.cost_type_text || row.cost_type }}</template>
                </el-table-column>
                <el-table-column label="变动金额" width="120" align="right">
                    <template #default="{ row }">¥{{ money(row.amount_delta) }}</template>
                </el-table-column>
                <el-table-column label="变动后成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.after_cost) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="120" />
                <el-table-column prop="remark" label="说明" min-width="180" show-overflow-tooltip />
            </el-table>

            <div class="mt-5 font-medium">操作时间线</div>
            <el-timeline class="mt-4">
                <el-timeline-item
                    v-for="item in detail.timeline || []"
                    :key="item.id"
                    :timestamp="formatTime(item.occurred_at)"
                >
                    {{ item.action_text || item.action }} · {{ item.operator_name || '系统' }}
                </el-timeline-item>
            </el-timeline>
        </el-drawer>

        <el-dialog v-model="inbound.visible" title="确认入库位置" width="560px">
            <el-form label-width="100px">
                <el-form-item label="入库仓库" required>
                    <el-select v-model="inbound.warehouse_id" class="w-full" @change="inbound.location_id = 0">
                        <el-option v-for="item in warehouseOptions" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="入库库位" required>
                    <el-select v-model="inbound.location_id" class="w-full">
                        <el-option v-for="item in availableLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="inbound.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="inbound.visible = false">取消</el-button>
                <el-button type="primary" :loading="inbound.loading" @click="submitInbound">确认入库</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="transfer.visible" title="调拨" width="560px">
            <el-form label-width="100px">
                <el-form-item label="设备">
                    <span class="text-gray-600">{{ transfer.asset?.model || '-' }}（IMEI {{ transfer.asset?.imei || '-' }}）</span>
                </el-form-item>
                <el-form-item label="当前库位">
                    <el-tag v-if="currentWarehouseName" type="info" effect="plain" size="small">
                        {{ currentWarehouseName }}<template v-if="currentLocationName"> / {{ currentLocationName }}</template>
                    </el-tag>
                    <span v-else class="text-gray-400">未归位</span>
                </el-form-item>
                <el-form-item label="目标库位" required>
                    <el-tree-select
                        v-model="transfer.target_value"
                        :data="transferTreeData"
                        node-key="value"
                        :props="{ label: 'label', children: 'children', disabled: 'disabled' }"
                        :render-after-expand="false"
                        check-strictly
                        default-expand-all
                        class="w-full"
                        placeholder="选择目标仓库 / 库位"
                        @change="onTargetChange"
                    />
                    <div class="text-xs text-gray-400 mt-1">仓库为父节点、库位为子节点；选到库位即归位，选仓库则暂不指定库位。</div>
                </el-form-item>
                <el-form-item v-if="showConsignChoice" label="代卖处理" required>
                    <el-radio-group v-model="transfer.consign_action">
                        <el-radio value="list">上架代卖（卖出时再结寄卖人）</el-radio>
                        <el-radio value="buyout">我方买断（立即应付寄卖人）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="showConsignChoice && transfer.consign_action === 'buyout'" label="买断价" required>
                    <el-input-number v-model="transfer.buyout_price" :min="0" :precision="2" :controls="false" class="!w-[180px]" />
                    <span class="text-xs text-gray-400 ml-2">买断价计入成本，并对寄卖人生成应付</span>
                </el-form-item>
                <el-alert
                    v-if="transferBlocked"
                    class="mb-3"
                    type="error"
                    :closable="false"
                    show-icon
                    title="调拨受限"
                    :description="transferBlockedMsg"
                />
                <el-form-item label="备注"><el-input v-model.trim="transfer.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="transfer.visible = false">取消</el-button>
                <el-button type="primary" :loading="transfer.loading" :disabled="transferBlocked" @click="submitTransfer">确认调拨</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="priceDialog.visible" :title="priceDialog.asset?.inventory_status === 'available_for_sale' ? '调价' : '销售定价'" width="480px">
            <el-form label-width="90px">
                <el-form-item label="设备">
                    <span class="text-gray-600">{{ priceDialog.asset?.model || '-' }}（IMEI {{ priceDialog.asset?.imei || '-' }}）</span>
                </el-form-item>
                <el-form-item label="采购成本">
                    <span class="text-gray-600">¥{{ money(priceDialog.asset?.purchase_cost) }}</span>
                </el-form-item>
                <el-form-item label="销售价" required>
                    <el-input-number v-model="priceDialog.sale_price" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="最低利润">
                    <el-input-number v-model="priceDialog.min_profit" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="priceDialog.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="priceDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="priceDialog.submitting" @click="submitPricing">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="costDialog.visible" title="调整成本" width="460px">
            <el-form label-width="90px">
                <el-form-item label="设备">
                    <span class="text-gray-600">{{ costDialog.asset?.model || '-' }}（IMEI {{ costDialog.asset?.imei || '-' }}）</span>
                </el-form-item>
                <el-form-item label="当前成本">
                    <span class="text-gray-600">¥{{ money(costDialog.asset?.current_cost) }}</span>
                </el-form-item>
                <el-form-item label="新成本" required>
                    <el-input-number v-model="costDialog.cost" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="原因">
                    <el-input v-model.trim="costDialog.reason" type="textarea" :rows="2" placeholder="如：维修加价 / 录入有误修正" maxlength="200" show-word-limit />
                </el-form-item>
                <el-form-item v-if="costDialog.asset?.inventory_status !== 'outbound'" label="计入应付">
                    <el-checkbox v-model="costDialog.sync_payable">此差额计入对该供应商的应付</el-checkbox>
                </el-form-item>
                <div v-if="costDialog.asset?.inventory_status !== 'outbound' && costDialog.sync_payable && costAdjustDelta !== 0" class="mb-2 rounded-md bg-orange-50 px-3 py-2 text-xs text-orange-600">
                    对供应商应付将同步 {{ costAdjustDelta > 0 ? '+' : '' }}¥{{ money(costAdjustDelta) }}（从 ¥{{ money(costDialog.asset?.current_cost) }} 到 ¥{{ money(costDialog.cost) }}）。仅适用于手工建档入库的设备；回收来源请在回收侧处理。
                </div>
                <div v-if="costDialog.asset?.inventory_status === 'outbound'" class="mb-2 rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-600">
                    该设备已售/已出库，此处为财务订正：仅修正库存成本与毛利口径并记成本流水，不改动已生成的应付/应收。
                </div>
                <div class="text-xs text-gray-400">调整会写入成本流水留痕。不勾"计入应付"则只改库存成本（如整备费/运费），不影响欠供应商的钱。</div>
            </el-form>
            <template #footer>
                <el-button @click="costDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="costDialog.submitting" @click="submitCostAdjust">保存</el-button>
            </template>
        </el-dialog>

        <!-- 完成拍照：待拍照 → 入库在库 -->
        <el-dialog v-model="photoDialog.visible" title="完成拍照" width="560px">
            <el-form label-width="80px">
                <el-form-item label="设备">
                    <span class="text-gray-600">{{ photoDialog.asset?.model || '-' }}（IMEI {{ photoDialog.asset?.imei || '-' }}）</span>
                </el-form-item>
                <el-form-item label="商品图片" required>
                    <div class="w-full">
                        <upload-image v-model="photoDialog.images" :limit="9" />
                        <div class="mt-1 text-xs text-gray-400">拍清楚每张图，传完点确认即入库在库、进入「待定价」交销售；若有售价会直推商城待上架货源。</div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="photoDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="photoDialog.submitting" :disabled="!photoDialog.images.length" @click="submitPhoto">确认拍照完成</el-button>
            </template>
        </el-dialog>

        <!-- 卖同行 / 就地出库（单台或批量） -->
        <el-dialog v-model="outbound.visible" :title="`卖给同行 / 出库（${outbound.rows.length} 台）`" width="640px" destroy-on-close>
            <el-form label-width="92px">
                <div class="mb-3 rounded bg-gray-50 px-3 py-2 text-xs text-gray-400">ERP 直接出库 = 同行渠道。零售给客户请走「商城·线下开单」(需安装商城)。</div>
                <el-form-item label="买家" required>
                    <counterparty-select v-model="outbound.counterparty_id" value-field="member_id" role-type="customer" placeholder="搜索姓名/手机号选择买家" class="w-full" />
                </el-form-item>
                <el-form-item label="结算方式">
                    <el-radio-group v-model="outbound.settle_mode">
                        <el-radio label="now">现结（当场填价、立即收款）</el-radio>
                        <el-radio label="later">先出货（不填价，回款时再回填）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="设备清单">
                    <el-table :data="outbound.rows" size="small" max-height="240" border class="w-full">
                        <el-table-column type="index" label="#" width="44" />
                        <el-table-column label="设备" min-width="160">
                            <template #default="{ row }"><span class="font-medium">{{ row.model || '设备' }}</span><span class="ml-1 text-xs text-gray-400">{{ row.imei }}</span></template>
                        </el-table-column>
                        <el-table-column label="成本" width="90" align="right"><template #default="{ row }">¥{{ money(row.current_cost) }}</template></el-table-column>
                        <el-table-column v-if="outbound.settle_mode === 'now'" label="售价" width="130" align="right">
                            <template #default="{ row }"><el-input-number v-model="row.price" :min="0" :controls="false" size="small" class="!w-28" /></template>
                        </el-table-column>
                    </el-table>
                    <div v-if="outbound.settle_mode === 'later'" class="mt-1 text-xs text-gray-400">先出货不填价，回款时到出库单回填价格再生成应收。</div>
                </el-form-item>
                <el-form-item v-if="outbound.settle_mode === 'now'" label="收款户头" required>
                    <el-select v-model="outbound.capital_account_id" filterable class="w-full" placeholder="款项收入哪个账户">
                        <el-option v-for="acc in accountOptions" :key="acc.id" :value="acc.id" :label="`${acc.account_name}（余额 ¥${money(acc.balance)}）`" />
                    </el-select>
                </el-form-item>
                <el-form-item label="快递单号">
                    <el-input v-model.trim="outbound.express_no" placeholder="打包发货填物流单号，便于追踪（选填）" maxlength="64" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="outbound.remark" type="textarea" :rows="2" placeholder="选填" />
                </el-form-item>
            </el-form>
            <div class="text-xs text-gray-400">在库即可直接卖：无需拍照、无需先上架商城。先出货方式会在回填价格后生成对该买家的应收。</div>
            <template #footer>
                <el-button @click="outbound.visible = false">取消</el-button>
                <el-button type="primary" :loading="outbound.submitting" @click="submitOutbound">确认出库</el-button>
            </template>
        </el-dialog>

        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" @changed="loadList" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search, InfoFilled, Check, MagicStick, Money, Sort, Edit, View, Sell, DArrowRight, Camera, Share, ArrowUp, ArrowDown } from '@element-plus/icons-vue'
import { useDeviceTrace } from '@/addon/hsx_erp/composables/useDeviceTrace'

const { openTrace } = useDeviceTrace()
import { useRouter } from 'vue-router'
import {
    batchConfirmErpAssetInbound,
    confirmErpAssetInbound,
    createErpManualInbound,
    getErpAssetInfo,
    getErpAssetList,
    getErpAssetOverview,
    getErpIntegrationStatus,
    adjustErpAssetCost,
    completeErpAssetPhoto
} from '@/addon/hsx_erp/api/asset'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getFinancePrepayBalance } from '@/addon/hsx_erp/api/finance'
import { getErpCounterpartyOptions, saveErpCounterparty } from '@/addon/hsx_erp/api/counterparty'
import { skipErpRefurbishment } from '@/addon/hsx_erp/api/refurbishment'
import { transferErpAsset, createErpOutbound } from '@/addon/hsx_erp/api/outbound'
import { saveErpAssetPrice } from '@/addon/hsx_erp/api/pricing'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import EntityDrawer from '@/addon/hsx_erp/views/finance/entity-drawer.vue'

const router = useRouter()
const search = reactive({ keyword: '', inventory_status: 'onhand', warehouse_id: '' as any, location_id: '' as any, ownership_type: '', cost_min: '' as any, cost_max: '' as any, stock_age: '', stockInRange: [] as any, sort_field: '', sort_order: '' })

// 库存概览（顶部卡片，全局口径，不随 tab 变）
const overview = reactive({
    on_hand: { count: 0, cost: 0 },
    sellable: { count: 0, amount: 0 },
    sold_month: { count: 0, amount: 0, gross_profit: 0 },
    avg_age_days: 0,
})
const filterLocations = computed(() => warehouseOptions.value.find((w: any) => Number(w.id) === Number(search.warehouse_id))?.locations || [])
const onWarehouseFilterChange = () => { search.location_id = ''; handleSearch() }
const summary = reactive({ count: 0, total_cost: 0, total_sale: 0, in_stock_count: 0, avg_age_days: 0 })
// 是否已接入中台(数据中台)：接入后拍照/定价交给中台，ERP 不再自行定价
const integrated = ref(false)
const showOverview = ref(true) // 顶部库存概览看板,可折叠以腾出列表空间
const table = reactive({ data: [] as any[], total: 0, page: 1, limit: 20, loading: false })
const detailVisible = ref(false)
const detail = reactive<any>({ asset: null, timeline: [] })
const selectedAssets = ref<any[]>([])
const assetTableRef = ref<any>(null)
const clearAssetSelection = () => assetTableRef.value?.clearSelection()
const warehouseOptions = ref<any[]>([])
const counterpartyOptions = ref<any[]>([])
const inbound = reactive<any>({
    visible: false, loading: false, warehouse_id: 0, location_id: 0, remark: '', assetIds: []
})
const availableLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(inbound.warehouse_id))?.locations || []
)

// 调拨
const transfer = reactive<any>({
    visible: false, loading: false, asset: null,
    to_warehouse_id: 0, to_location_id: 0, target_value: '', remark: '',
    consign_action: 'list', buyout_price: 0
})
const transferLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(transfer.to_warehouse_id))?.locations || []
)
// 反显设备当前所在仓库/库位（名称从已加载的 warehouseOptions 解析，资产行只带 id）
const currentWarehouseName = computed(() => {
    const wid = Number(transfer.asset?.warehouse_id || 0)
    if (!wid) return ''
    // 优先用后端补的名称，其次从 warehouseOptions 解析，最后兜底显示 id
    if (transfer.asset?.warehouse_name) return transfer.asset.warehouse_name
    const wh = warehouseOptions.value.find((w: any) => Number(w.id) === wid)
    return wh?.warehouse_name || ('仓#' + wid)
})
const currentLocationName = computed(() => {
    const wid = Number(transfer.asset?.warehouse_id || 0)
    const lid = Number(transfer.asset?.location_id || 0)
    if (!lid) return ''
    if (transfer.asset?.location_name) return transfer.asset.location_name
    const wh = warehouseOptions.value.find((w: any) => Number(w.id) === wid)
    const loc = (wh?.locations || []).find((l: any) => Number(l.id) === lid)
    return loc?.location_name || ('库位#' + lid)
})
// 目标仓库/库位树：仓库为父、库位为子。代卖仓锁死：不接受调入；代卖仓设备只能调去二手机仓。
const warehouseTypeLabel = (t: string) =>
    (({ mall: '二手机仓', peer: '同行仓', consignment: '代卖仓', hold: '暂存仓' }) as Record<string, string>)[t] || ''
// 设备当前所在仓的业务类型
const transferFromType = computed(() => {
    const wid = Number(transfer.asset?.warehouse_id || 0)
    return String(warehouseOptions.value.find((w: any) => Number(w.id) === wid)?.business_type || '')
})
const transferTreeData = computed(() => {
    const fromConsign = transferFromType.value === 'consignment'
    return warehouseOptions.value.map((w: any) => {
        const t = String(w.business_type || '')
        // 目标仓未开启「允许调入」则禁用；代卖仓设备只能调去二手机仓
        const blocked = Number(w.allow_inbound ?? 1) !== 1 || (fromConsign && t !== 'mall')
        const tl = warehouseTypeLabel(t)
        return {
            value: 'w:' + w.id,
            label: w.warehouse_name + (tl ? `（${tl}）` : '') + (blocked ? ' · 不可调入' : ''),
            disabled: blocked,
            children: (w.locations || []).map((l: any) => ({
                value: `l:${w.id}:${l.id}`,
                label: l.location_name,
                disabled: blocked
            }))
        }
    })
})
const transferToWarehouse = computed(() =>
    warehouseOptions.value.find((w: any) => Number(w.id) === Number(transfer.to_warehouse_id))
)
const onTargetChange = (val: string) => {
    if (!val) { transfer.to_warehouse_id = 0; transfer.to_location_id = 0; return }
    if (val.startsWith('w:')) {
        transfer.to_warehouse_id = Number(val.slice(2))
        transfer.to_location_id = 0
    } else if (val.startsWith('l:')) {
        const parts = val.split(':')
        transfer.to_warehouse_id = Number(parts[1])
        transfer.to_location_id = Number(parts[2])
    }
}
const transferTargetType = computed(() =>
    String(warehouseOptions.value.find((item: any) => Number(item.id) === Number(transfer.to_warehouse_id))?.business_type || '')
)
// 代卖仓锁死规则 → 禁用确认并提示
const transferBlocked = computed(() => {
    const wh = transferToWarehouse.value
    if (!wh) return false
    if (Number(wh.allow_inbound ?? 1) !== 1) return true                                        // 目标仓未开启允许调入
    if (transferFromType.value === 'consignment' && String(wh.business_type || '') !== 'mall') return true  // 代卖仓设备只能去二手机仓
    return false
})
const transferBlockedMsg = computed(() => {
    const wh = transferToWarehouse.value
    if (!wh) return ''
    if (Number(wh.allow_inbound ?? 1) !== 1) return '目标仓库未开启「允许调入」，请在仓库管理中开启，或改选其它目标仓。'
    if (transferFromType.value === 'consignment' && String(wh.business_type || '') !== 'mall') return '代卖仓设备只能调拨到二手机仓（买断转回收），不能调往其它仓。'
    return ''
})
// 代卖设备调进二手机仓(商城)时，需要选择"上架代卖 or 我方买断"
const showConsignChoice = computed(() =>
    transfer.asset && String(transfer.asset.ownership_type) === 'consign' && transferTargetType.value === 'mall'
)
const canTransfer = (row: any) => !['pending_in', 'outbound', 'locked'].includes(String(row.inventory_status))

// —— 就地出库 / 卖出 ——（在库/待定价/可售即可直接卖）
const canOutbound = (row: any) => ['in_stock', 'pending_pricing', 'available_for_sale'].includes(String(row.inventory_status))
const outbound = reactive<any>({ visible: false, submitting: false, rows: [] as any[], counterparty_id: '', settle_mode: 'now', capital_account_id: '', express_no: '', remark: '' })
const toOutboundRow = (row: any) => ({
    id: Number(row.id), model: row.model, imei: row.imei || row.asset_no, asset_no: row.asset_no,
    current_cost: row.current_cost,
    price: Number(row.current_sale_price) > 0 ? Number(row.current_sale_price) : undefined,
})
const startOutbound = (rows: any[]) => {
    if (!accountOptions.value.length) loadAccountOptions()
    outbound.rows = rows.map(toOutboundRow)
    outbound.counterparty_id = ''
    outbound.settle_mode = 'now'
    outbound.capital_account_id = ''
    outbound.express_no = ''
    outbound.remark = ''
    outbound.visible = true
}
const openOutbound = (row: any) => startOutbound([row])
const openOutboundBatch = () => {
    if (!selectedSellable.value.length) return ElMessage.warning('请先勾选要卖的在库设备')
    startOutbound(selectedSellable.value)
}
const submitOutbound = async () => {
    if (!outbound.rows.length) return ElMessage.warning('没有可出库的设备')
    if (!outbound.counterparty_id) return ElMessage.warning('请选择买家')
    if (outbound.settle_mode === 'now') {
        if (outbound.rows.some((r: any) => !(Number(r.price) > 0))) return ElMessage.warning('现结需为每台填写售价')
        if (!outbound.capital_account_id) return ElMessage.warning('现结需选择收款户头')
    }
    outbound.submitting = true
    try {
        await createErpOutbound({
            outbound_type: 'peer_sale',
            sale_channel: 'peer',
            counterparty_id: outbound.counterparty_id,
            settle_mode: outbound.settle_mode,
            capital_account_id: outbound.capital_account_id || 0,
            express_no: outbound.express_no,
            remark: outbound.remark,
            items: outbound.rows.map((r: any) => ({ asset_id: Number(r.id), sale_price: outbound.settle_mode === 'now' ? Number(r.price) || 0 : 0 })),
        })
        ElMessage.success(outbound.rows.length > 1 ? `已出库 ${outbound.rows.length} 台` : '出库成功')
        outbound.visible = false
        clearAssetSelection()
        loadList()
    } finally {
        outbound.submitting = false
    }
}
const openTransfer = (row: any) => {
    transfer.asset = row
    // 反显：默认选中设备当前所在的仓库/库位
    const wid = Number(row.warehouse_id || 0)
    const lid = Number(row.location_id || 0)
    transfer.to_warehouse_id = wid
    transfer.to_location_id = lid
    transfer.target_value = lid > 0 ? `l:${wid}:${lid}` : (wid > 0 ? `w:${wid}` : '')
    transfer.remark = ''
    transfer.consign_action = 'list'
    transfer.buyout_price = 0
    transfer.visible = true
}
const submitTransfer = async () => {
    if (!transfer.to_warehouse_id) { ElMessage.warning('请选择目标仓库'); return }
    if (showConsignChoice.value && transfer.consign_action === 'buyout' && Number(transfer.buyout_price) <= 0) {
        ElMessage.warning('我方买断必须填写买断价'); return
    }
    transfer.loading = true
    try {
        const payload: any = {
            asset_ids: [Number(transfer.asset.id)],
            to_warehouse_id: Number(transfer.to_warehouse_id),
            to_location_id: Number(transfer.to_location_id),
            remark: transfer.remark,
            consign_action: transfer.consign_action
        }
        if (showConsignChoice.value && transfer.consign_action === 'buyout') {
            payload.buyout_prices = [{ asset_id: Number(transfer.asset.id), amount: Number(transfer.buyout_price) }]
        }
        await transferErpAsset(payload)
        ElMessage.success('调拨成功')
        transfer.visible = false
        loadList()
    } finally {
        transfer.loading = false
    }
}
const manualDialog = reactive({ visible: false, submitting: false })
const manualForm = reactive({
    model: '',
    business_type: 'recycle',
    counterparty_id: 0,
    counterparty_member_id: 0,
    imei: '',
    imei2: '',
    sn: '',
    capacity: '',
    color: '',
    purchase_cost: 0,
    settle_mode: 'cash',
    paid_amount: 0,
    paid_account_id: 0,
    suggested_sale_price: 0,
    warehouse_id: 0,
    location_id: 0,
    need_refurb: false,
    use_prepay: false,
    images: '',
    qc_note: '',
    remark: ''
})
// 该供应商可用采购预付余额(选定供应商后拉取)
const manualPrepay = reactive<any>({ available: 0 })
// 选定供应商时:记会员ID(应付锚到人) + 拉取其可用预付余额，供入库直接抵扣
const onSupplierResolved = async (d: any) => {
    manualForm.counterparty_member_id = Number(d?.member_id || 0)
    manualForm.use_prepay = false
    manualPrepay.available = 0
    const mid = Number(d?.member_id || 0)
    if (mid > 0) {
        try {
            const res: any = await getFinancePrepayBalance(mid)
            manualPrepay.available = Number(res?.data?.available || 0)
        } catch (e) {
            manualPrepay.available = 0
        }
    }
}
// 勾选"用预付抵扣"时:本台不再走现金，置挂账 + 已付0
const onUsePrepayChange = (v: boolean) => {
    if (v) {
        manualForm.settle_mode = 'credit'
        manualForm.paid_amount = 0
        manualForm.paid_account_id = 0
    }
}
// 付款户头选项（已付>0 时建档即从该户头出账）
const accountOptions = ref<any[]>([])
const loadAccountOptions = async () => {
    try {
        const res: any = await getCapitalAccounts()
        accountOptions.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
    } catch (e) {
        accountOptions.value = []
    }
}
// 手工建档可选入库库位（与确认入库共用 warehouseOptions）
const manualLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(manualForm.warehouse_id))?.locations || []
)
const counterpartyDialog = reactive<any>({
    visible: false,
    loading: false,
    form: { counterparty_type: 'individual', name: '', mobile: '', contact_name: '' }
})
const manualUnpaidAmount = computed(() =>
    Math.max(0, Number(manualForm.purchase_cost || 0) - Number(manualForm.paid_amount || 0))
)
// 用预付时,成本超出可用预付的"还欠差额"
const manualPrepayShortfall = computed(() =>
    manualForm.use_prepay ? Math.max(0, Number(manualForm.purchase_cost || 0) - Number(manualPrepay.available || 0)) : 0
)
// 本次实际付款额：用预付时=差额补付额(选了账户才付)，现结=采购成本，挂账=订金
const payNowAmount = computed(() =>
    manualForm.use_prepay
        ? (Number(manualForm.paid_account_id) > 0 ? manualPrepayShortfall.value : 0)
        : (Number(manualForm.settle_mode === 'cash' ? manualForm.purchase_cost : manualForm.paid_amount) || 0)
)
// 付款户头显示：用预付时不需现金户头；否则现结一定显示，挂账填了订金才显示
const showPayAccount = computed(() =>
    !manualForm.use_prepay &&
    !['consignment', 'opening'].includes(manualForm.business_type) &&
    (manualForm.settle_mode === 'cash' || Number(manualForm.paid_amount) > 0)
)
const manualSettlementText = computed(() => {
    if (manualForm.business_type === 'consignment') return '代卖入库：暂不形成采购成本和应付，销售后按代卖结算规则处理。'
    if (manualForm.business_type === 'opening') return `期初成本 ¥${money(manualForm.purchase_cost)}，不自动形成外部应付。`
    if (manualForm.use_prepay) {
        const offset = Math.min(Number(manualPrepay.available || 0), Number(manualForm.purchase_cost || 0))
        const rest = manualPrepayShortfall.value
        if (rest <= 0) return `用预付抵扣 ¥${money(offset)}，本台应付已结清，无需付现金`
        return Number(manualForm.paid_account_id) > 0
            ? `用预付抵扣 ¥${money(offset)}，差额 ¥${money(rest)} 从所选账户当场补付，本台结清`
            : `用预付抵扣 ¥${money(offset)}，差额 ¥${money(rest)} 暂挂应付卖方（选个账户可当场补付）`
    }
    const mode = manualForm.settle_mode === 'cash' ? '现结' : '挂账'
    return `${mode}：成本 ¥${money(manualForm.purchase_cost)}，本次付 ¥${money(manualForm.paid_amount)}，挂账应付卖方 ¥${money(manualUnpaidAmount.value)}`
})

const loadList = async () => {
    table.loading = true
    try {
        const params: any = { ...search, page: table.page, limit: table.limit }
        // 库龄筛选(优先于自定义入库时间区间): 把"在库天数"换算成 stock_in_at 区间
        const ageMap: Record<string, [number | null, number]> = {
            '0-3': [3, 0], '3-7': [7, 3], '7-15': [15, 7], '15-30': [30, 15], '30+': [null, 30],
        }
        if (search.stock_age && ageMap[search.stock_age]) {
            const now = Math.floor(Date.now() / 1000)
            const [maxDays, minDays] = ageMap[search.stock_age]
            params.stock_in_end = now - minDays * 86400
            if (maxDays !== null) params.stock_in_start = now - maxDays * 86400
        } else if (Array.isArray(search.stockInRange) && search.stockInRange.length === 2) {
            params.stock_in_start = search.stockInRange[0]
            params.stock_in_end = search.stockInRange[1]
        }
        delete params.stockInRange
        delete params.stock_age
        const res: any = await getErpAssetList(params)
        table.data = res.data?.data || []
        table.total = Number(res.data?.total || 0)
        const s = res.data?.summary || {}
        summary.count = Number(s.count || 0)
        summary.total_cost = Number(s.total_cost || 0)
        summary.total_sale = Number(s.total_sale || 0)
        summary.in_stock_count = Number(s.in_stock_count || 0)
        summary.avg_age_days = Number(s.avg_age_days || 0)
    } finally {
        table.loading = false
        // 概览随列表刷新（入库/调拨/定价等动作后保持最新）
        loadOverview()
    }
}

const handleSearch = () => {
    table.page = 1
    loadList()
}

const handleReset = () => {
    Object.assign(search, {
        keyword: '', inventory_status: '', warehouse_id: '', location_id: '',
        ownership_type: '', cost_min: '', cost_max: '', stock_age: '', stockInRange: [], sort_field: '', sort_order: '',
    })
    handleSearch()
}

// 导出当前页为 CSV
const exportCsv = () => {
    const head = ['资产编号', 'IMEI', 'SN', '型号', '仓库', '成本', '参考售价', '状态']
    const rows = table.data.map((r: any) => [
        r.asset_no || '', r.imei || '', r.sn || '', r.model || '', r.warehouse_name || '',
        Number(r.current_cost || 0).toFixed(2), Number(r.current_sale_price || 0).toFixed(2), statusName(r.inventory_status),
    ])
    const csv = [head, ...rows].map((line) => line.map((c: any) => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n')
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = '资产清单_' + Date.now() + '.csv'
    a.click()
    URL.revokeObjectURL(a.href)
}

const resetManualForm = () => {
    Object.assign(manualForm, {
        model: '',
        business_type: 'recycle',
        counterparty_id: 0,
        counterparty_member_id: 0,
        imei: '',
        imei2: '',
        sn: '',
        capacity: '',
        color: '',
        purchase_cost: 0,
        settle_mode: 'cash',
        paid_amount: 0,
        paid_account_id: 0,
        suggested_sale_price: 0,
        warehouse_id: 0,
        location_id: 0,
        use_prepay: false,
        images: '',
        qc_note: '',
        remark: ''
    })
    manualPrepay.available = 0
    // 默认带出默认仓库及其首个库位，省一步点选
    const def = warehouseOptions.value.find((item: any) => item.is_default === 1) || warehouseOptions.value[0]
    if (def) {
        manualForm.warehouse_id = Number(def.id || 0)
        manualForm.location_id = Number(def.locations?.[0]?.id || 0)
    }
}

const handleBusinessTypeChange = () => {
    if (['consignment', 'opening'].includes(manualForm.business_type)) {
        manualForm.paid_amount = 0
        manualForm.paid_account_id = 0
    } else if (manualForm.settle_mode === 'cash') {
        manualForm.paid_amount = Number(manualForm.purchase_cost || 0)
    }
    if (manualForm.business_type === 'consignment') manualForm.purchase_cost = 0
}

// 结算方式: 现结=本次付清(已付=采购成本, 锁定), 挂账=应付卖方(已付默认0, 可填订金)
const handleSettleModeChange = () => {
    if (manualForm.settle_mode === 'cash') {
        manualForm.paid_amount = Number(manualForm.purchase_cost || 0)
    } else {
        manualForm.paid_amount = 0
        manualForm.paid_account_id = 0
    }
}

// 现结时, 采购成本变化则已付跟随(始终付清)
watch(() => manualForm.purchase_cost, (v) => {
    if (manualForm.settle_mode === 'cash' && !['consignment', 'opening'].includes(manualForm.business_type)) {
        manualForm.paid_amount = Number(v || 0)
    }
})

const openManualInbound = () => {
    resetManualForm()
    if (!accountOptions.value.length) loadAccountOptions()
    manualDialog.visible = true
}

const submitManualInbound = async () => {
    if (!manualForm.model) {
        ElMessage.warning('请填写设备型号')
        return
    }
    if (!manualForm.imei && !manualForm.sn) {
        ElMessage.warning('IMEI 和 SN 至少填写一个')
        return
    }
    if (manualForm.business_type !== 'opening' && !manualForm.counterparty_id) {
        ElMessage.warning('请选择卖方客户')
        return
    }
    // 现结=本次付清, 提交前确保已付=采购成本
    if (manualForm.settle_mode === 'cash' && !['consignment', 'opening'].includes(manualForm.business_type)) {
        manualForm.paid_amount = Number(manualForm.purchase_cost || 0)
    }
    if (Number(manualForm.paid_amount) > Number(manualForm.purchase_cost)) {
        ElMessage.warning('已付金额不能大于应付金额')
        return
    }
    if (Number(manualForm.paid_amount) > 0 && !['consignment', 'opening'].includes(manualForm.business_type)) {
        if (!Number(manualForm.paid_account_id)) {
            ElMessage.warning('填写了已付金额，请选择付款户头')
            return
        }
        const acc = accountOptions.value.find((a: any) => Number(a.id) === Number(manualForm.paid_account_id))
        if (acc && Number(acc.balance) < Number(manualForm.paid_amount)) {
            ElMessage.warning(`户头「${acc.account_name}」余额不足，请改用其他户头`)
            return
        }
    }
    if (Number(manualForm.warehouse_id) > 0 && !Number(manualForm.location_id)) {
        ElMessage.warning('选择了入库仓库，请同时选择库位')
        return
    }
    manualDialog.submitting = true
    try {
        await createErpManualInbound({ ...manualForm, images: String(manualForm.images || '').split(',').filter(Boolean) })
        const hasLoc = Number(manualForm.warehouse_id) > 0
        ElMessage.success(hasLoc ? '已建档并入库到指定库位' : '已创建待入库设备')
        manualDialog.visible = false
        // 跳到设备实际落到的状态页：未选库位→待入库；选了库位则按是否整备/是否带价分流
        search.inventory_status = !hasLoc
            ? 'pending_in'
            : (manualForm.need_refurb
                ? 'refurbishing'
                : (Number(manualForm.suggested_sale_price) > 0 ? 'available_for_sale' : 'pending_pricing'))
        table.page = 1
        await loadList()
    } finally {
        manualDialog.submitting = false
    }
}

const loadCounterparties = async () => {
    const res: any = await getErpCounterpartyOptions()
    counterpartyOptions.value = res.data || []
}

const openQuickCounterparty = () => {
    Object.assign(counterpartyDialog.form, {
        counterparty_type: 'individual',
        name: '',
        mobile: '',
        contact_name: '',
        role_type: manualForm.business_type === 'consignment' ? 'consignor' : 'supplier'
    })
    counterpartyDialog.visible = true
}

const submitQuickCounterparty = async () => {
    if (!counterpartyDialog.form.name) return ElMessage.warning('请填写往来单位名称')
    counterpartyDialog.loading = true
    try {
        const res: any = await saveErpCounterparty(0, { ...counterpartyDialog.form, status: 1 })
        await loadCounterparties()
        manualForm.counterparty_id = Number(res.data || 0)
        counterpartyDialog.visible = false
        ElMessage.success('往来单位已新增并选中')
    } finally {
        counterpartyDialog.loading = false
    }
}

// 完成拍照
const photoDialog = reactive<any>({ visible: false, submitting: false, asset: null, images: '' })
const openPhoto = (row: any) => {
    photoDialog.asset = row
    photoDialog.images = ''
    photoDialog.visible = true
}
const submitPhoto = async () => {
    if (!photoDialog.asset?.id) return
    const imgs = String(photoDialog.images || '').split(',').filter(Boolean)
    if (!imgs.length) return ElMessage.warning('请至少上传一张照片')
    photoDialog.submitting = true
    try {
        await completeErpAssetPhoto(Number(photoDialog.asset.id), imgs)
        ElMessage.success('拍照完成，已入库进入待定价')
        photoDialog.visible = false
        search.inventory_status = 'pending_pricing'
        loadList()
    } catch (e: any) {
        ElMessage.error(e?.message || '完成拍照失败')
    } finally {
        photoDialog.submitting = false
    }
}

const sellableStatuses = ['in_stock', 'pending_pricing', 'available_for_sale']
const rowSelectable = (row: any) => row.inventory_status === 'pending_in' || sellableStatuses.includes(String(row.inventory_status))
const handleSelectionChange = (rows: any[]) => {
    selectedAssets.value = rows.filter(rowSelectable)
}
const selectedPendingIn = computed(() => selectedAssets.value.filter((r: any) => r.inventory_status === 'pending_in'))
const selectedSellable = computed(() => selectedAssets.value.filter((r: any) => sellableStatuses.includes(String(r.inventory_status))))

const confirmInbound = async (row: any) => {
    inbound.assetIds = [Number(row.id)]
    inbound.remark = ''
    inbound.visible = true
}

const batchConfirmInbound = async () => {
    const assetIds = selectedPendingIn.value.map((item: any) => Number(item.id))
    if (assetIds.length === 0) {
        ElMessage.warning('请先勾选待入库设备')
        return
    }
    inbound.assetIds = assetIds
    inbound.remark = ''
    inbound.visible = true
}

const submitInbound = async () => {
    if (!inbound.warehouse_id || !inbound.location_id) {
        ElMessage.warning('请选择入库仓库和库位')
        return
    }
    inbound.loading = true
    try {
        const data = { warehouse_id: inbound.warehouse_id, location_id: inbound.location_id, remark: inbound.remark }
        const res: any = inbound.assetIds.length === 1
            ? await confirmErpAssetInbound(inbound.assetIds[0], data)
            : await batchConfirmErpAssetInbound(inbound.assetIds, data)
        ElMessage.success(`已确认入库 ${Number(res.data?.confirmed_count || inbound.assetIds.length)} 台`)
        inbound.visible = false
        selectedAssets.value = []
        await loadList()
    } finally {
        inbound.loading = false
    }
}

const openDetail = async (row: any) => {
    const res: any = await getErpAssetInfo(row.id)
    Object.assign(detail, res.data || {})
    detailVisible.value = true
}

const startRefurbishment = (row: any) => {
    router.push({ path: '/hsx_erp/refurbishment', query: { asset_id: String(row.id) } })
}

// 改为内联定价/调价弹框（不再跳转到独立的"销售定价"页）
const priceDialog = reactive<any>({
    visible: false, submitting: false, asset: null, sale_price: 0, min_profit: 0, remark: ''
})
const openPricing = (row: any) => {
    priceDialog.asset = row
    priceDialog.sale_price = Number(row.current_sale_price || 0)
    priceDialog.min_profit = 0
    priceDialog.remark = ''
    priceDialog.visible = true
}
const submitPricing = async () => {
    if (!priceDialog.asset?.id) return
    if (Number(priceDialog.sale_price) <= 0) {
        ElMessage.warning('请填写销售价')
        return
    }
    priceDialog.submitting = true
    try {
        await saveErpAssetPrice(Number(priceDialog.asset.id), {
            sale_price: Number(priceDialog.sale_price),
            min_profit: Number(priceDialog.min_profit || 0),
            remark: priceDialog.remark || ''
        })
        ElMessage.success('已保存销售定价')
        priceDialog.visible = false
        loadList()
    } catch (error: any) {
        ElMessage.error(error?.message || '定价失败')
    } finally {
        priceDialog.submitting = false
    }
}

// 调成本（实时调整在库设备成本，写成本流水）
// outbound(已售/已出库)允许财务订正成本: 卖后才发现成本=0/填错时的兜底, 后端对已售只改成本+记流水、不动应付
const COST_ADJUSTABLE = ['in_stock', 'refurbishing', 'pending_pricing', 'available_for_sale', 'locked', 'outbound']
const canAdjustCost = (row: any) => COST_ADJUSTABLE.includes(String(row.inventory_status))
const costDialog = reactive<any>({ visible: false, submitting: false, asset: null, cost: 0, reason: '', sync_payable: false })
const costAdjustDelta = computed(() => Math.round((Number(costDialog.cost || 0) - Number(costDialog.asset?.current_cost || 0)) * 100) / 100)
const openCostAdjust = (row: any) => {
    costDialog.asset = row
    costDialog.cost = Number(row.current_cost || 0)
    costDialog.reason = ''
    costDialog.sync_payable = false
    costDialog.visible = true
}
const submitCostAdjust = async () => {
    if (!costDialog.asset?.id) return
    if (costAdjustDelta.value === 0) { ElMessage.warning('成本未变化'); return }
    const deltaTxt = `成本 ${costAdjustDelta.value > 0 ? '+' : ''}¥${money(costAdjustDelta.value)}（¥${money(costDialog.asset?.current_cost)} → ¥${money(costDialog.cost)}）`
    const payTxt = costDialog.sync_payable ? '，并同步调整对供应商的应付' : ''
    try {
        await ElMessageBox.confirm(`确认调整成本？${deltaTxt}${payTxt}。`, '调成本', { type: 'warning' })
    } catch { return }
    costDialog.submitting = true
    try {
        await adjustErpAssetCost(Number(costDialog.asset.id), { cost: Number(costDialog.cost), reason: costDialog.reason || '', sync_payable: costDialog.sync_payable ? 1 : 0 })
        ElMessage.success('成本已调整')
        costDialog.visible = false
        loadList()
    } catch (error: any) {
        ElMessage.error(error?.message || '调成本失败')
    } finally {
        costDialog.submitting = false
    }
}

const skipRefurbishment = async (row: any) => {
    await ElMessageBox.confirm(
        `确认 ${row.model || row.asset_no} 无需整备，直接进入待销售定价吗？`,
        '无需整备',
        { type: 'warning', confirmButtonText: '进入待销售定价', cancelButtonText: '取消' }
    )
    await skipErpRefurbishment(Number(row.id), { remark: '库存工作台确认无需整备' })
    ElMessage.success('设备已进入待销售定价')
    await loadList()
}

const money = (value: any) => Number(value || 0).toFixed(2)
const statusName = (status: string) => ({
    pending_in: '待入库',
    inbound_rejected: '入库驳回',
    in_stock: '在库',
    refurbishing: '整备中',
    pending_pricing: '待销售定价',
    available_for_sale: '在售',
    locked: '销售锁定',
    outbound: '已出库',
    lost: '丢失'
}[status] || status || '-')
const statusType = (status: string) => ({
    pending_in: 'warning',
    inbound_rejected: 'danger',
    in_stock: 'success',
    refurbishing: 'warning',
    pending_pricing: 'primary',
    available_for_sale: 'success',
    locked: 'success',
    outbound: 'success'
}[status] || 'info')
const nextStepText = (status: string) => ({
    pending_in: '下一步：核对串号、型号和成本后确认入库',
    inbound_rejected: '下一步：修正驳回信息后重新提交入库',
    in_stock: '下一步：发起整备，或确认无需整备后直接进入待销售定价',
    refurbishing: '下一步：完成整备验收并确认实际费用',
    pending_pricing: '下一步：创建销售定价单，确认销售价和最低利润',
    available_for_sale: '下一步：客户锁定后创建销售单并出库'
}[status] || '请根据设备当前状态继续处理')
const formatTime = (value: any) => {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('zh-CN')
}

const loadWarehouses = async () => {
    const res: any = await getErpWarehouseOptions()
    warehouseOptions.value = res.data || []
    const defaultWarehouse = warehouseOptions.value.find((item: any) => item.is_default === 1) || warehouseOptions.value[0]
    inbound.warehouse_id = Number(defaultWarehouse?.id || 0)
    inbound.location_id = Number(defaultWarehouse?.locations?.[0]?.id || 0)
}

// 往来主体抽屉(复用财务中心同一组件: 查看/编辑主体+联系人)
const entityDrawer = reactive<any>({ visible: false, id: 0 })
const openEntity = (id: number) => {
    if (!id) return
    entityDrawer.id = id
    entityDrawer.visible = true
}

const loadOverview = async () => {
    try {
        const res: any = await getErpAssetOverview()
        Object.assign(overview, res.data || {})
    } catch (e) { /* ignore */ }
}

const recycleConnected = ref(false)
const loadIntegration = async () => {
    try {
        const res: any = await getErpIntegrationStatus()
        integrated.value = !!res.data?.device_asset_connected
        recycleConnected.value = !!res.data?.recycle_connected
    } catch (e) {
        integrated.value = false
        recycleConnected.value = false
    }
}

// 列表与状态标签的展示名：联合模式下"待销售定价"语义其实是"已交中台·处理中"
const flowStatusName = (status: string) =>
    integrated.value && status === 'pending_pricing' ? '已交中台·处理中' : statusName(status)

// 细化状态展示：只有"真的交给了中台"(delegated_to_mid)的待定价才显示"已交中台·处理中"；
// 本地/手工入库的待定价仍显示"待销售定价"，由 ERP 自行定价。
const displayStatusText = (row: any) =>
    integrated.value && row.inventory_status === 'pending_pricing' && row.delegated_to_mid
        ? '已交中台·处理中'
        : (row.status_text || statusName(row.inventory_status))
const displayStatusType = (row: any) => row.status_type || statusType(row.inventory_status)

// 列排序 → 服务端排序
const handleSortChange = ({ prop, order }: { prop: string; order: string | null }) => {
    search.sort_field = order ? prop : ''
    search.sort_order = order === 'ascending' ? 'asc' : order === 'descending' ? 'desc' : ''
    table.page = 1
    loadList()
}

// 参考售价来源标注：均为"参考价"，真实成交价在销售环节产生
const priceSource = () => (integrated.value ? '中台参考价' : '门店参考价')

onMounted(() => Promise.all([loadIntegration(), loadOverview(), loadList(), loadWarehouses(), loadCounterparties()]))
</script>
