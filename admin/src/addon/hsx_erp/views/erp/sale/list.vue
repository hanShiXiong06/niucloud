<template>
    <ErpDesktopPage class="main-container">
        <el-alert v-if="lookup.sessionChanged.value" type="warning" :closable="false" show-icon role="alert">
            <template #title>当前登录账号或店铺已切换</template>
            <p>本页已停止开单。请刷新并核对店铺，原草稿不会带入其他店铺。 <el-button type="primary" link @click="lookup.reopen">刷新并核对店铺</el-button></p>
        </el-alert>
        <section v-else class="erp-list-surface">
            <ErpTradeHeader page="sale" title="销售出库" subtitle="同行直接选在库设备开单，无需在商城上架。" :advanced-count="advancedFilterCount" @search="handleSearch" @reset="handleReset">
                <template #actions>
                    <el-button :icon="Refresh" :loading="table.loading" @click="refreshWorkspace">刷新</el-button>
                    <el-button @click="profitReportVisible = true">经营台账</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate()">新建销售单</el-button>
                    <ErpRoleFocus :items="saleRoleFocus" />
                </template>
                <template #quick>
                    <el-form-item label="关键词">
                        <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="型号 / IMEI / 资产号 / 销售单 / 客户" />
                    </el-form-item>
                    <el-form-item label="仓库">
                        <el-select v-model="search.warehouse_id" clearable class="!w-[160px]" placeholder="全部仓库" @change="onSearchWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </template>
                <template #advanced>
                    <el-form-item label="库位">
                        <el-select v-model="search.location_id" clearable class="!w-[160px]" placeholder="全部库位" :disabled="!search.warehouse_id">
                            <el-option v-for="item in searchLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="商品型号">
                        <ErpCatalogProductSelect v-model="search.catalog_product_id" class="!w-[280px]" placeholder="搜索品牌、系列或型号" />
                    </el-form-item>
                    <el-form-item label="开单人">
                            <el-select v-model="search.salesman_uid" clearable filterable class="!w-[150px]" placeholder="全部">
                                <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                            </el-select>
                        </el-form-item>
                    <el-form-item label="销售时间">
                            <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" start-placeholder="开始" end-placeholder="结束" class="!w-[260px]" />
                        </el-form-item>
                    <el-form-item label="售价">
                            <el-input-number v-model="search.min_amount" :min="0" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                            <span class="mx-1 text-[color:var(--el-text-color-secondary)]">-</span>
                            <el-input-number v-model="search.max_amount" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                        </el-form-item>
                    <el-form-item label="毛利">
                            <el-input-number v-model="search.min_profit" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                            <span class="mx-1 text-[color:var(--el-text-color-secondary)]">-</span>
                            <el-input-number v-model="search.max_profit" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                        </el-form-item>
                </template>
                <template #summary>
                    <p v-if="table.loading || listError" role="status">{{ table.loading ? '正在读取本页数据…' : '读取成功后显示汇总' }}</p>
                    <template v-else><div class="mt-5 flex flex-wrap items-center justify-between gap-2 erp-summary-heading">
                <div class="text-sm font-medium text-[color:var(--el-text-color-regular)]">本页有效销售汇总</div>
                <div class="text-xs text-[color:var(--el-text-color-secondary)]">已取消、已全部退款的明细不计入；毛利为实际销售收入减有效成本</div>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-4 erp-metrics">
                <div class="summary-tile">
                    <div class="summary-label">有效销售件数</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">实际销售收入</div>
                    <div class="summary-value">{{ money(summary.amount) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">有效商品成本</div>
                    <div class="summary-value">{{ money(summary.cost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">实际毛利</div>
                    <div class="summary-value" :class="summary.profit >= 0 ? 'text-[color:var(--el-color-success)]' : 'text-[color:var(--el-color-danger)]'">{{ money(summary.profit) }}</div>
                </div>
            </div></template>
                </template>
            </ErpTradeHeader>
            <el-alert v-if="listError" type="warning" :closable="false" show-icon>
                <template #title>未能读取销售单。筛选条件已保留。 <el-button link type="primary" @click="loadList">重新读取</el-button></template>
            </el-alert>
            <el-tabs v-model="activeTab" class="mt-4 erp-status-tabs" @tab-change="onTabChange">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待收款" name="pending" />
                <el-tab-pane label="部分收款" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
            </el-tabs>

            <ErpDataTable v-show="!listError" :data="table.data" v-loading="table.loading" :row-class-name="saleRowClassName">
                <template #empty><span>{{ table.loading ? '正在读取…' : '没有符合当前条件的销售记录' }}</span></template>
                <el-table-column label="销售商品" min-width="240">
                    <template #default="{ row }">
                        <div v-if="isStandardGoods(row)">
                            <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ row.model || '-' }}</div>
                            <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ row.product_code || '未设置商品编码' }} · {{ quantityText(itemQuantity(row)) }} {{ row.unit || '件' }}</div>
                        </div>
                        <ErpDeviceIdentity v-else compact :model="row.model" :spec="row.spec" :imei="row.imei" :sn="row.sn" :asset-no="row.asset_no" />
                        <div v-if="isStandardGoods(row)" class="mt-2">
                            <el-tag type="primary" effect="plain" size="small">标品销售</el-tag>
                        </div>
                        <div v-if="isExternalGoods(row)" class="mt-2 flex items-center gap-2">
                            <el-tag type="success" effect="plain" size="small">商城商品</el-tag>
                            <span class="text-xs text-[color:var(--el-text-color-secondary)]">{{ itemQuantity(row) }} 件 · 商城退款自动同步</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="客户 / 渠道" min-width="170">
                    <template #default="{ row }">
                        <div>{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">渠道：{{ row.sale_channel || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="实际成交" min-width="120" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.net_sale_amount) }}</div>
                        <div v-if="Number(row.sale_compensation_amount || 0)" class="mt-1 text-xs text-[color:var(--el-color-warning)]">原成交 {{ money(row.sale_price) }} · 补差 -{{ money(row.sale_compensation_amount) }}</div>
                        <div v-if="Number(row.external_refunded_amount || 0)" class="mt-1 text-xs text-[color:var(--el-color-warning)]">商城累计退款 -{{ money(row.external_refunded_amount) }}</div>
                        <div v-if="isConsigned(row)" class="mt-1 text-xs text-[color:var(--el-color-warning)]">代卖 · {{ row.owner_party_name || '货主未登记' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="销售单 / 时间" min-width="215">
                    <template #default="{ row }">
                        <div class="flex items-center gap-2">
                            <span class="batch-dot" :class="`batch-dot--${batchTone(row)}`"></span>
                            <span class="font-medium">{{ row.sale_no || '-' }}</span>
                        </div>
                        <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ formatTime(row.sale_at || row.create_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="当前状态" min-width="180">
                    <template #default="{ row }">
                        <div class="sale-status-stack">
                            <el-tag :type="saleStateMeta(row).type" effect="plain">{{ saleStateMeta(row).label }}</el-tag>
                            <span v-if="showFinanceStatus(row)" class="sale-status-stack__finance">{{ row.finance_status === 'settled' ? '本站已结清' : '本单剩余应收 ' + recordedMoney(row.receivable_amount) }}</span>
                            <span v-if="saleStateHint(row)" class="sale-status-stack__hint">{{ saleStateHint(row) }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="150" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">查看原单</el-button>
                        <el-dropdown trigger="click" class="ml-2"><el-button link aria-label="更多销售操作">更多</el-button>
                            <template #dropdown><el-dropdown-menu>
                                <el-dropdown-item @click="printSaleReceipt(row)">打印小票</el-dropdown-item>
                                <el-dropdown-item v-if="canReturnSale(row)" @click="goSaleReturn(row)">销售退货</el-dropdown-item>
                                <el-dropdown-item v-if="canCancelSaleItemFromList(row)" @click="cancelSaleItem(row)">取消销售</el-dropdown-item>
                            </el-dropdown-menu></template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </ErpDataTable>

            <div v-show="!listError" class="mt-4 flex justify-end erp-pagination">
                <el-pagination
                    v-model:current-page="table.page"
                    v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next, jumper"
                    :total="table.total"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </section>

        <HsxDialog class="erp-desktop-overlay" :confirm-loading="create.saving" v-model="create.visible" title="销售出库" :width="entryStep === 1 ? 'min(94vw, 1040px)' : 'min(90vw, 820px)'" top="5vh" destroy-on-close>
            <el-form label-width="96px">
                <ErpTradeEntryGuide :step="entryStep" :labels="['选客户', '选库存货品', '核对结算']" :busy="create.saving" @choose="chooseStep" />
                <p v-if="entryError" role="alert" class="trade-entry-error">{{ entryError }}</p>
                <p v-if="lookup.errors.value.length" class="trade-entry-error" role="alert">{{ lookup.errors.value.join('、') }}未读取。<el-button link type="primary" @click="refreshWorkspace">重新读取资料</el-button></p>
                <section v-show="entryStep === 0" aria-label="选择销售客户">
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item class="md:col-span-2" label="销售客户" required>
                        <counterparty-select v-model="create.form.party_id" role-type="customer" placeholder="搜索或新建销售客户" @resolving-change="partyResolving = $event" @resolved="onPartyResolved" />
                    </el-form-item>
                    <el-form-item label="销售渠道">
                        <el-select v-model="create.form.sale_channel_key" filterable class="w-full" placeholder="选择销售渠道" @change="onSaleChannelChange">
                            <el-option v-for="item in saleChannelOptions" :key="item.key" :label="item.name" :value="item.key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="制单员" required>
                        <el-select v-model="create.form.salesman_uid" filterable class="w-full" placeholder="选择制单员">
                            <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                        </el-select>
                    </el-form-item>
                </div>
                <HsxNotice default-expanded v-if="creditNotice" class="mb-4" :type="creditProfile?.severity || 'warning'" :closable="false" show-icon :title="creditNotice" />

                </section>
                <section v-show="entryStep === 1" aria-label="选择可售库存">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <el-radio-group v-model="stock.item_type" @change="onSaleItemTypeChange">
                        <el-radio-button label="device">二手机 / 一机一码</el-radio-button>
                        <el-radio-button label="standard">标品 / 数量库存</el-radio-button>
                    </el-radio-group>
                    <div class="flex flex-wrap gap-2">
                        <el-input v-model.trim="stock.keyword" clearable class="!w-[220px]" :placeholder="stock.item_type === 'standard' ? '商品名称 / 编码' : '型号 / IMEI / 采购来源'" @keyup.enter="reloadStock" />
                        <el-select v-model="stock.warehouse_id" clearable class="!w-[130px]" placeholder="仓库" @change="onStockWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                        <el-select v-model="stock.location_id" clearable class="!w-[130px]" placeholder="库位" :disabled="!stock.warehouse_id" @change="reloadStock">
                            <el-option v-for="item in stockLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                        <ErpCatalogProductSelect v-if="stock.item_type === 'device'" v-model="stock.catalog_product_id" class="!w-[230px]" placeholder="商品型号" @change="reloadStock" />
                        <el-button :icon="Search" @click="loadStock">查询</el-button>
                    </div>
                </div>
                <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm" aria-live="polite">
                    <span>已选 <b>{{ selectedQuantityText }}</b></span>
                    <span>销售总额 <b>{{ money(selectedAmount) }}</b></span>
                    <el-button v-if="selectedAssets.length" link @click="stockTableRef?.clearSelection()">清空已选</el-button>
                </div>
                <p v-if="stockError" class="trade-entry-error" role="alert">{{ stockError }} <el-button link type="primary" @click="loadStock">重新读取库存</el-button></p>
                <el-table :key="stock.item_type" ref="stockTableRef" row-key="id" class="sale-select-table" :data="stock.data" v-loading="stock.loading" size="small" max-height="360" @row-click="onStockRowClick" @selection-change="onStockSelection">
                    <el-table-column type="selection" reserve-selection width="48" />
                    <el-table-column :label="stock.item_type === 'standard' ? '标品' : '设备'" min-width="280">
                        <template #default="{ row }">
                            <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ row.model || '-' }}</div>
                            <div class="mt-0.5 text-xs text-[color:var(--el-text-color-secondary)]">{{ stock.item_type === 'standard' ? `${row.product_code || '-'} · 可售 ${quantityText(row.available_quantity)}${row.unit || '件'}` : compactDeviceInfo(row) }}</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <el-tag v-if="stock.item_type === 'device' && (row.catalog_product_name || row.category_name)" size="small" effect="plain" type="info">{{ row.catalog_product_name || row.category_name }}</el-tag>
                                <el-tag v-if="isConsigned(row)" size="small" effect="plain" type="warning">客户代卖</el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="来源 / 位置" min-width="170">
                        <template #default="{ row }">
                            <div>{{ stock.item_type === 'standard' ? (row.warehouse_name || '-') : (isConsigned(row) ? `货主：${row.owner_party_name || '-'}` : (row.party_name || '-')) }}</div>
                            <div class="mt-0.5 text-xs text-[color:var(--el-text-color-secondary)]">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="成本 / 结算" width="130" align="right">
                        <template #default="{ row }">
                            <div>{{ money(rowSelectedCost(row)) }}</div>
                            <div v-if="isConsigned(row)" class="mt-0.5 text-xs text-[color:var(--el-color-warning)]">应付货主</div>
                        </template>
                    </el-table-column>
                    <el-table-column v-if="stock.item_type === 'standard'" label="销售数量" width="130" align="right">
                        <template #default="{ row }"><el-input-number v-model="saleQuantities[row.id]" :min="0.001" :max="Number(row.available_quantity || 0)" :precision="3" :controls="false" class="!w-[105px]" /></template>
                    </el-table-column>
                    <el-table-column :label="stock.item_type === 'standard' ? '销售总价' : '销售价'" width="150" align="right">
                        <template #default="{ row }"><el-input-number v-model="salePrices[row.id]" :min="0" :precision="2" :controls="false" class="!w-[120px]" /></template>
                    </el-table-column>
                </el-table>
                <div class="mt-3 flex items-center justify-between">
                    <div class="text-sm text-[color:var(--el-text-color-secondary)]">成本 / 代卖结算 {{ money(selectedCost) }} · 预计毛利 {{ money(selectedProfit) }}</div>
                    <el-pagination v-model:current-page="stock.page" v-model:page-size="stock.limit" layout="total, prev, pager, next" :total="stock.total" @current-change="loadStock" />
                </div>

                </section>
                <section v-show="entryStep === 2" aria-label="核对结算">
                    <div class="trade-checkout"><div><small>销售客户</small><b>{{ create.form.party_name || '已选客户' }}</b></div><div><small>货品</small><b>{{ selectedQuantityText }}</b></div><div><small>销售总额</small><b>{{ money(selectedAmount) }}</b></div></div>
                    <ErpTradeReviewLines :lines="reviewLines" />
                <p class="trade-entry-hint">{{ create.form.settle_mode === 'credit' ? '挂账出库：客户暂不付款，由财务在应收款中跟进。' : '现结只登记已经收到的金额，这里不会向客户扣款。' }}</p>
                    <p v-if="creditNotice" role="status" class="trade-entry-hint">{{ creditNotice }}</p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <el-form-item class="trade-settlement-mode" label="结算方式">
                        <el-select v-model="create.form.settle_mode" class="w-full">
                            <el-option label="挂账，稍后收款" value="credit" :disabled="!creditAllowedForOrder" />
                            <el-option label="现结，本次收款" value="cash" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="本次收款" required>
                        <el-input-number v-model="create.form.received_amount" :min="0" :precision="2" :controls="false" :disabled="creditProfile?.policy === 'cash_only'" class="!w-full" />
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="收款账户" required>
                        <el-select v-model="create.form.capital_account_id" class="w-full" :loading="accountLoading" placeholder="选择账户">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-form-item v-if="create.form.settle_mode === 'cash'" label="收款凭证"><ErpFinanceVoucherUpload v-model="create.form.voucher_urls" /></el-form-item>
                <p v-if="create.form.settle_mode === 'cash' && accountError" role="alert" class="trade-entry-error">资金账户未能读取。<el-button link type="primary" @click="reloadAccounts">重新读取账户</el-button></p>
                <p v-else-if="create.form.settle_mode === 'cash' && !accountLoading && !accounts.length" class="trade-entry-hint">没有可用的资金账户，请财务先在「资金账户」启用账户。<el-button link @click="reloadAccounts">刷新账户</el-button></p>
                <HsxFold title="备注（选填）" :summary="create.form.remark ? '已填写' : ''"><el-form-item label="备注">
                    <el-input v-model.trim="create.form.remark" type="textarea" :rows="2" />
                </el-form-item></HsxFold>
            </section>
            </el-form>

            <template #footer>
                <el-button :disabled="create.saving" @click="create.visible = false">取消</el-button>
                <el-button v-if="entryStep > 0" :disabled="create.saving" @click="chooseStep(entryStep - 1)">上一步</el-button>
                <el-button v-if="entryStep < 2" type="primary" :loading="entryStep === 0 && partyResolving" @click="chooseStep(entryStep + 1)">{{ partyResolving ? '正在匹配…' : entryStep === 0 ? '下一步：选库存' : '下一步：核对结算' }}</el-button>
                <el-button v-else type="primary" :loading="create.saving" :disabled="(creditProfile?.can_sale === false) || (create.saving)" @click="submitCreate">确认出库</el-button>
            </template>
        </HsxDialog>

        <HsxDrawer class="erp-desktop-overlay" v-model="detail.visible" title="销售单详情" size="72%" destroy-on-close>
            <div v-loading="detail.loading">
                <div v-if="detail.error" class="mb-4 flex items-center gap-3" role="alert">
                    <span>{{ detail.error }}</span><el-button link type="primary" @click="loadDetail(detail.orderId)">重新读取</el-button>
                </div>
                <section v-if="detail.data" class="sale-overview" aria-label="本地销售概览">
                    <div class="sale-overview__title"><div><small>销售原单</small><h3>{{ detail.data.party_name || '客户未登记' }}</h3><span>{{ detail.data.sale_no }}</span></div>
                        <el-tag :type="statusMeta(detail.data.finance_status).type" effect="plain">本站 · {{ statusMeta(detail.data.finance_status).label }}</el-tag></div>
                    <dl><div><dt>实际销售收入</dt><dd>{{ recordedMoney(detail.data.net_total_amount) }}</dd></div>
                        <div><dt>本站已收</dt><dd>{{ recordedMoney(detail.data.received_amount) }}</dd></div>
                        <div><dt>整单剩余应收</dt><dd>{{ recordedMoney(detail.data.receivable_amount) }}</dd></div></dl>
                    <p>{{ localNextStep(detail.data) }}</p>
                </section>
                <el-table v-if="detail.data?.items?.length && !detail.data?.integration_sale_evidence?.length" class="mb-4" :data="detail.data.items" size="small" max-height="300" aria-label="本单商品">
                    <el-table-column prop="model" label="商品" min-width="160" /><el-table-column label="串号 / 数量" min-width="155"><template #default="{row}">{{ row.imei || row.sn || quantityText(itemQuantity(row))+' 件' }}</template></el-table-column>
                    <el-table-column label="销售收入" align="right" width="115"><template #default="{row}">{{ recordedMoney(row.net_sale_amount) }}</template></el-table-column>
                </el-table>
                <section v-for="proof in detail.data?.integration_sale_evidence || []" :key="proof.provider_label" class="mb-4 rounded-lg border border-[var(--el-border-color)] px-4 py-3">
                    <div class="mb-2"><strong>按串号核对 · {{ proof.provider_label }}</strong></div>
                    <el-table :data="proof.devices" size="small" max-height="240">
                        <el-table-column prop="imei" label="设备串号" min-width="160" />
                        <el-table-column v-if="proof.show_purchase" label="已核实采购单号" min-width="135"><template #default="{ row }">{{ row.purchase_no || '待核对' }}</template></el-table-column>
                        <el-table-column label="已核实销售单号" min-width="135"><template #default="{ row }">{{ row.sale_no || '待核对' }}</template></el-table-column>
                    </el-table>
                    <p v-if="proof.receipt_label" class="mt-2 text-sm">{{ proof.provider_label }}收款：{{ proof.receipt_label }} · 原销售单核销：{{ proof.allocation_label || '待核对' }}</p>
                </section>
                <ErpDocumentSyncResult :results="detail.data?.integration_results" operator-view="sale" :original-sale-id="detail.orderId" :has-serial-evidence="!!detail.data?.integration_sale_evidence?.length" @refresh="loadDetail(detail.orderId)" />
                <div v-if="integrationLinks.length" class="mb-4 flex flex-wrap gap-2">
                    <el-button v-for="action in integrationLinks" :key="action.key" type="primary" plain @click="router.push(action.target)">{{ action.label }}</el-button>
                </div>
                <details v-if="detail.data" :key="detail.orderId" class="sale-original"><summary>整单资料、商品与售后操作 <small>{{ detail.data.items?.length || 0 }} 条明细</small></summary>
                <el-descriptions :column="4" border>
                    <el-descriptions-item label="销售单号">{{ detail.data.sale_no }}</el-descriptions-item>
                    <el-descriptions-item label="客户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="业务来源">{{ erpSourceLabel(detail.data.origin_name, 'ERP销售') }}</el-descriptions-item>
                    <el-descriptions-item label="销售渠道">{{ detail.data.sale_channel || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="原业务单号">{{ detail.data.origin_no || detail.data.sale_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="制单员">{{ detail.data.salesman_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="收款状态">
                        <el-tag :type="statusMeta(detail.data.finance_status).type">{{ statusMeta(detail.data.finance_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="原成交金额">{{ money(detail.data.gross_total_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="售后补差">-{{ money(detail.data.sale_compensation_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="实际销售收入">{{ money(detail.data.net_total_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="成本">{{ money(detail.data.total_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="毛利">{{ money(detail.data.profit) }}</el-descriptions-item>
                    <el-descriptions-item label="剩余应收">{{ money(detail.data.receivable_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="4">{{ detail.data.remark || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div v-if="canCancelSaleOrder" class="mt-4 flex items-center justify-between rounded-lg bg-[var(--el-color-warning-light-9)] px-4 py-3">
                    <span class="text-sm text-[color:var(--el-color-warning)]">本单尚未收款，撤销后商品会按原数量和原成本退回库存。</span>
                    <el-button type="warning" plain @click="cancelSaleOrder">整单撤销</el-button>
                </div>
                <div class="mt-5 font-medium">商品明细</div>
                <el-table class="mt-3" :data="detail.data?.items || []" size="large">
                    <el-table-column prop="model" label="型号" min-width="180" />
                    <el-table-column label="商品类型" width="130"><template #default="{ row }"><el-tag v-if="isStandardGoods(row)" type="primary" effect="plain">标品</el-tag><el-tag v-else-if="isExternalGoods(row)" type="success" effect="plain">商城商品</el-tag><span v-else>设备</span></template></el-table-column>
                    <el-table-column label="IMEI / 数量" min-width="170"><template #default="{ row }"><span v-if="isStandardGoods(row)">{{ quantityText(itemQuantity(row)) }} {{ row.unit || '件' }}</span><span v-else-if="!isExternalGoods(row)">{{ row.imei || '-' }}</span><span v-else>{{ itemQuantity(row) }} 件</span></template></el-table-column>
                    <el-table-column label="归属" min-width="150"><template #default="{ row }"><el-tag v-if="isConsigned(row)" type="warning" effect="plain">客户代卖</el-tag><span v-else>自有</span><div v-if="isConsigned(row)" class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ row.owner_party_name || '-' }}</div></template></el-table-column>
                    <el-table-column label="成本 / 结算" width="140" align="right"><template #default="{ row }">{{ money(row.cost) }}<div v-if="isConsigned(row)" class="text-xs text-[color:var(--el-color-warning)]">应付货主</div></template></el-table-column>
                    <el-table-column label="出库位置" min-width="150"><template #default="{ row }">{{ [row.warehouse_name,row.location_name].filter(Boolean).join(' / ') || '—' }}</template></el-table-column>
                    <el-table-column label="销售收入" width="190" align="right"><template #default="{ row }"><div>{{ money(row.net_sale_amount) }}</div><div v-if="Number(row.sale_compensation_amount || 0)" class="text-xs text-[color:var(--el-color-warning)]">原价 {{ money(row.sale_price) }} · 补差 -{{ money(row.sale_compensation_amount) }}</div><div v-if="Number(row.external_refunded_amount || 0)" class="text-xs text-[color:var(--el-color-warning)]">退款 -{{ money(row.external_refunded_amount) }}</div></template></el-table-column>
                    <el-table-column label="毛利" width="130" align="right"><template #default="{ row }">{{ money(row.profit) }}</template></el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'sold' ? 'success' : row.status === 'void' ? 'info' : 'warning'" effect="plain">{{ saleItemStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="remark" label="备注" min-width="180" />
                    <el-table-column label="操作" width="120" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="canCancelSaleItem(row)" type="danger" link @click="cancelSaleItem(row)">取消销售</el-button>
                            <span v-else class="text-xs text-[color:var(--el-text-color-secondary)]">-</span>
                        </template>
                    </el-table-column>
                </el-table>
                </details>
            </div>
        </HsxDrawer>
        <ErpSaleProfitReport v-model="profitReportVisible" />
    </ErpDesktopPage>
</template>

<script setup lang="ts">
import ErpDesktopPage from '@/addon/hsx_erp/components/ErpDesktopPage.vue'
import ErpDocumentSyncResult from '@/addon/hsx_erp/components/ErpDocumentSyncResult.vue'
import { saleActionLinks, useSourceDocument } from '@/addon/hsx_erp/hooks/useSourceDocument'
import ErpTradeHeader from '@/addon/hsx_erp/components/ErpTradeHeader.vue'
import ErpTradeReviewLines from '@/addon/hsx_erp/components/ErpTradeReviewLines.vue'
import ErpTradeEntryGuide from '@/addon/hsx_erp/components/ErpTradeEntryGuide.vue'
import { useTradeDirectory, useTradeAccounts } from '@/addon/hsx_erp/hooks/useTradeDirectory'
import ErpDataTable from '@/addon/hsx_erp/components/ErpDataTable.vue'
import { HsxDialog, HsxDrawer, HsxNotice, useFeedback, HsxFold } from '@/addon/hsx_components/core'
import { erpEnumLabel, erpSourceLabel } from '@/addon/hsx_erp/utils/display'
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search, TrendCharts } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpSaleChannelOptions } from '@/addon/hsx_erp/api/config'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { cancelErpSale, cancelErpSaleItem, createErpSale, getErpSaleInfo, getErpSaleList, getErpSaleStock, getErpStaffOptions, printErpSaleReceipt } from '@/addon/hsx_erp/api/erp'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpDeviceIdentity from '@/addon/hsx_erp/components/ErpDeviceIdentity.vue'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
import ErpSaleProfitReport from '@/addon/hsx_erp/components/ErpSaleProfitReport.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import { firstPositiveErpAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
const hsxFeedback = useFeedback()
const lookup = useTradeDirectory()
const partyResolving = ref(false)
const entryStep = ref(0), entryError = ref(''), listError = ref(false)
let listVersion = 0
function refreshWorkspace(withList: boolean | Event = true) {
    if (!lookup.sessionOK()) return
    lookup.clear(); void loadWarehouses(); void loadStaffOptions(); void loadSaleChannels()
    if (withList !== false) void loadList()
}

const search = reactive<any>({ keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', catalog_product_id: '', salesman_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined, min_profit: undefined, max_profit: undefined })
const advancedFilterCount = computed(() => [search.location_id, search.catalog_product_id, search.salesman_uid, search.dateRange?.length || undefined, search.min_amount, search.max_amount, search.min_profit, search.max_profit].filter(v => v != null && v !== '').length)
const activeTab = ref('')
const profitReportVisible = ref(false)
const router = useRouter()
const route = useRoute()
const saleRoleFocus = [
    { role: '销售', focus: '客户、成交价、毛利与设备批次' },
    { role: '仓管', focus: '出库设备身份、原位置与资产状态' },
    { role: '财务', focus: '收款状态、已收事实与剩余应收' },
]

function onTabChange(tab: string) {
    if (tab === 'void') {
        search.finance_status = ''
        search.status = 'void'
    } else {
        search.finance_status = tab
        search.status = ''
    }
    table.page = 1
    loadList()
}
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const stock = reactive({ loading: false, data: [] as any[], item_type: 'device', keyword: '', warehouse_id: '', location_id: '', catalog_product_id: '', page: 1, limit: 8, total: 0 })
const staffOptions = ref<any[]>([])
const warehouses = ref<any[]>([])
const saleChannelOptions = ref<any[]>([])
const currentUid = ref(0)
const creditProfile = ref<any>(null)
const selectedAssets = ref<any[]>([])
const pendingAssetIds = ref<number[]>([])
const stockTableRef = ref<any>()
const salePrices = reactive<Record<number, number>>({})
const saleQuantities = reactive<Record<number, number>>({})
const create = reactive({ visible: false, saving: false, form: defaultForm() })
const { detail, loadDetail } = useSourceDocument(getErpSaleInfo)
const integrationLinks = computed(() => saleActionLinks(detail.data))

const summary = computed(() => table.data.reduce((acc, row: any) => {
    if (row.order_status === 'void' || row.status !== 'sold') return acc
    acc.count += itemQuantity(row)
    acc.amount += Number(row.net_sale_amount || 0)
    acc.cost += Number(row.cost || 0)
    acc.profit += Number(row.profit || 0)
    return acc
}, { count: 0, amount: 0, cost: 0, profit: 0 }))
const selectedCost = computed(() => selectedAssets.value.reduce((sum, row) => sum + rowSelectedCost(row), 0))
const selectedAmount = computed(() => selectedAssets.value.reduce((sum, row) => sum + Number(salePrices[row.id] || 0), 0))
const selectedProfit = computed(() => selectedAmount.value - selectedCost.value)
const selectedQuantityText = computed(() => stock.item_type === 'standard'
    ? `${quantityText(selectedAssets.value.reduce((sum, row) => sum + Number(saleQuantities[row.id] || 0), 0))} 件`
    : `${selectedAssets.value.length} 台`)
const creditAllowedForOrder = computed(() => {
    const profile = creditProfile.value
    if (!profile) return true
    if (profile.can_credit === false) return false
    const limit = Number(profile.credit_limit || 0)
    return limit <= 0 || Number(profile.outstanding_amount || 0) + selectedAmount.value <= limit + 0.0001
})
const creditNotice = computed(() => {
    if (creditProfile.value?.message) return creditProfile.value.message
    if (!creditAllowedForOrder.value && Number(creditProfile.value?.credit_limit || 0) > 0) {
        return `本单挂账后将超过客户信用额度 ${money(creditProfile.value.credit_limit)}，请改为现结。`
    }
    return ''
})
const searchWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(search.warehouse_id)) || null)
const searchLocations = computed(() => searchWarehouse.value?.locations || [])
const stockWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(stock.warehouse_id)) || null)
const stockLocations = computed(() => stockWarehouse.value?.locations || [])

watch(() => create.form.settle_mode, () => {
    if (create.form.settle_mode === 'cash') create.form.received_amount = selectedAmount.value
})
watch(selectedAmount, amount => {
    if (create.form.settle_mode === 'cash') create.form.received_amount = amount
})
watch(creditAllowedForOrder, allowed => {
    if (!allowed && creditProfile.value?.can_sale !== false) create.form.settle_mode = 'cash'
})

onMounted(() => {
    if (route.query.sale_id) loadDetail(route.query.sale_id)
    loadStaffOptions()
    loadWarehouses()
    loadSaleChannels()
    const assetIds = String(route.query.asset_ids || '').split(',').map(Number).filter(id => id > 0)
    if (assetIds.length && !route.query.sale_id) openCreate(assetIds)
})
useErpPageRefresh(loadList)
const { accounts, accountError, accountLoading, reloadAccounts } = useTradeAccounts(
    () => create.visible && entryStep.value === 2 && create.form.settle_mode === 'cash', () => lookup.scope.value, () => lookup.sessionOK() ? getCapitalAccounts() : Promise.reject(new Error('店铺已切换'))
)
watch(lookup.sessionChanged, changed => {
    if (!changed) return
    create.visible = false; detail.visible = false; warehouses.value = []; staffOptions.value = []; currentUid.value = 0
    ++stockVersion; stock.data = []; selectedAssets.value = []; creditProfile.value = null; saleChannelOptions.value = []; profitReportVisible.value = false
    ++listVersion; table.data = []; table.total = 0; table.loading = false
})
watch(lookup.scope, (next, previous) => { if (next && !previous && lookup.sessionOK()) refreshWorkspace() })
function defaultForm() {
    return { party_id: 0, party_name: '', sale_channel: '', sale_channel_key: '', channel_source_plugin: '', channel_source_key: '', salesman_uid: 0, settle_method: '', settle_mode: 'credit', received_amount: 0, capital_account_id: 0, voucher_urls: '', remark: '' }
}

async function loadSaleChannels() {
    const res: any = await lookup.live('销售渠道', getErpSaleChannelOptions, Array.isArray)
    if (!res) return
    saleChannelOptions.value = (Array.isArray(res?.data) ? res.data : []).filter((row: any) => Number(row.enabled ?? 1) === 1)
    if (!create.form.sale_channel_key) {
        const preferred = saleChannelOptions.value.find((row: any) => Number(row.is_default || 0) === 1) || saleChannelOptions.value[0]
        if (preferred) onSaleChannelChange(preferred.key)
    }
}

function onSaleChannelChange(key: string) {
    const channel = saleChannelOptions.value.find((row: any) => row.key === key)
    create.form.sale_channel_key = channel?.key || ''
    create.form.sale_channel = channel?.name || ''
    create.form.channel_source_plugin = channel?.source_plugin || ''
    create.form.channel_source_key = channel?.source_key || ''
}

async function loadList() {
    if (!lookup.sessionOK()) return
    const v = ++listVersion
    table.loading = true; listError.value = false
    try {
        const res: any = await getErpSaleList({ ...buildSearchParams(), page: table.page, limit: table.limit })
        if (!lookup.sessionOK() || v !== listVersion) return
        if (!Array.isArray(res?.data?.data)) throw new Error('incomplete list')
        table.data = res.data.data
        table.total = Number(res.data.total || 0)
    } catch {
        if (v === listVersion) { table.data = []; table.total = 0; listError.value = true }
    } finally { if (v === listVersion) table.loading = false }
}

const stockError = ref('')
let stockRead = false, stockVersion = 0
async function loadStock() {
    if (!lookup.sessionOK()) return
    const v = ++stockVersion
    stockError.value = ''; stock.loading = true
    try {
        const res: any = await getErpSaleStock({
            item_type: stock.item_type,
            keyword: stock.keyword,
            warehouse_id: stock.warehouse_id,
            location_id: stock.location_id,
            catalog_product_id: stock.catalog_product_id,
            asset_ids: pendingAssetIds.value,
            stock_ids: [],
            page: stock.page,
            limit: stock.limit
        })
        if (!lookup.sessionOK() || v !== stockVersion) return
        if (!Array.isArray(res?.data?.data)) throw new Error('incomplete inventory')
        stock.data = res.data.data
        stock.total = res?.data?.total || 0
        stock.data.forEach((row: any) => {
            if (!(row.id in salePrices)) salePrices[row.id] = firstPositiveErpAmount(row.retail_price, row.estimate_sale_price, saleCostBasis(row))
            saleQuantities[row.id] = saleQuantities[row.id] || 1
        })
        if (pendingAssetIds.value.length) {
            selectedAssets.value = stock.data.filter((row: any) => pendingAssetIds.value.includes(Number(row.id)))
            const preselected = selectedAssets.value.slice()
            await nextTick()
            if (!lookup.sessionOK() || v !== stockVersion) return
            preselected.forEach(row => stockTableRef.value?.toggleRowSelection(row, true))
            pendingAssetIds.value = []
        }
    } catch {
        if (v === stockVersion) { stockError.value = '未能读取可售库存。'; stock.data = []; stock.total = 0; stockTableRef.value?.clearSelection(); selectedAssets.value = [] }
    } finally {
        if (v === stockVersion) stock.loading = false
    }
}

function reloadStock() {
    stock.page = 1
    loadStock()
}


async function loadStaffOptions() {
    const res: any = await lookup.live('制单员', getErpStaffOptions, v => Array.isArray(v?.users))
    if (!res) return
    currentUid.value = Number(res?.data?.current_uid || 0)
    staffOptions.value = res?.data?.users || []
    if (!create.form.salesman_uid) {
        create.form.salesman_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    }
}

async function loadWarehouses() {
    const res: any = await lookup.read('warehouse-options', getErpWarehouseOptions, Array.isArray)
    if (!res) return
    warehouses.value = Array.isArray(res?.data) ? res.data : []
}

function buildSearchParams() {
    const [start_at, end_at] = Array.isArray(search.dateRange) ? search.dateRange : []
    return {
        ...search,
        start_at: start_at || '',
        end_at: end_at || '',
        dateRange: undefined
    }
}

function openCreate(assetIds: number[] = []) {
    if (!lookup.sessionOK()) return
    partyResolving.value = false
    create.form = defaultForm()
    entryStep.value = 0; entryError.value = ''
    stockError.value = ''; stockRead = false; ++stockVersion; stock.loading = false; stock.data = []; stock.total = 0
    creditProfile.value = null
    create.form.salesman_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    const preferredChannel = saleChannelOptions.value.find((row: any) => Number(row.is_default || 0) === 1) || saleChannelOptions.value[0]
    if (preferredChannel) onSaleChannelChange(preferredChannel.key)
    resetStockDraft()
    pendingAssetIds.value = assetIds
    stock.keyword = ''
    stock.item_type = 'device'
    stock.warehouse_id = ''
    stock.location_id = ''
    stock.catalog_product_id = ''
    stock.page = 1
    stock.limit = assetIds.length ? Math.max(8, Math.min(200, assetIds.length)) : 8
    create.visible = true
    loadStaffOptions()
}

function onStockSelection(rows: any[]) {
    selectedAssets.value = rows
}

function resetStockDraft() {
    stockTableRef.value?.clearSelection()
    selectedAssets.value = []
    Object.keys(salePrices).forEach(id => delete salePrices[Number(id)])
    Object.keys(saleQuantities).forEach(id => delete saleQuantities[Number(id)])
}
function onSaleItemTypeChange() {
    resetStockDraft()
    pendingAssetIds.value = []
    stock.catalog_product_id = ''
    stock.warehouse_id = ''
    stock.location_id = ''
    reloadStock()
}

function onStockRowClick(row: any, _column: any, event: MouseEvent) {
    const target = event?.target as HTMLElement | null
    if (target?.closest('input,button,a,.el-input-number,.el-checkbox')) return
    stockTableRef.value?.toggleRowSelection(row)
}

const reviewLines = computed(() => selectedAssets.value.map(row => ({
    name: row.model || '未命名货品', identity: stock.item_type === 'standard' ? '数量 ' + quantityText(saleQuantities[row.id]) : (row.imei ? 'IMEI ' + row.imei : 'SN ' + (row.sn || '未登记')),
    amount: money(salePrices[row.id])
})))
function entryIssue(n: number): string {
    if (!n) {
        if (partyResolving.value) return '正在匹配往来主体，请稍候'
        if (!create.form.party_id && !create.form.party_name) return '请选择销售客户'
        if (creditProfile.value?.can_sale === false) return creditProfile.value.message || '该客户已暂停交易'
        if (!create.form.sale_channel_key) return '请选择销售渠道'
        if (!create.form.salesman_uid) return '请选择制单员'
    } else if (n === 1) {
        if (stock.loading) return '正在读取库存，请稍候'
        if (stockError.value) return '请先重新读取库存，再核对结算'
        if (!selectedAssets.value.length) return '请选择要销售的库存货品'
        if (selectedAssets.value.some(row => Number(salePrices[row.id] || 0) <= 0)) return stock.item_type === 'standard' ? '请填写每项标品销售总价' : '请填写每台机器销售价'
        if (stock.item_type === 'standard' && selectedAssets.value.some(row => Number(saleQuantities[row.id] || 0) <= 0 || Number(saleQuantities[row.id] || 0) > Number(row.available_quantity || 0))) {
            return '标品销售数量必须大于0且不能超过可售库存'
        }
    }
    return ''
}
function chooseStep(target: number) {
    if (!lookup.sessionOK() || create.saving || target < 0 || target > 2) return
    entryError.value = ''
    let next = target
    for (let n = 0; n < target; n++) {
        const err = entryIssue(n)
        if (err) { entryError.value = err; next = n; break }
    }
    entryStep.value = next
    if (next === 1 && !stockRead) { stockRead = true; void loadStock() }
}
let confirmingEntry = false
async function submitCreate() {
    if (!lookup.sessionOK() || create.saving || confirmingEntry) return
    for (const n of [0,1]) { const err = entryIssue(n); if (err) { entryStep.value = n; entryError.value = err; return } }
    if (create.form.settle_mode === 'credit' && !creditAllowedForOrder.value) return hsxFeedback.warning(creditNotice.value || '该客户不允许挂账，请改为现结')
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.received_amount || 0) <= 0) return hsxFeedback.warning('请填写本次收款')
        if (accountLoading.value || accountError.value || !accounts.value.some(a => Number(a.id) === Number(create.form.capital_account_id))) return hsxFeedback.warning('请选择收款账户')
        if (Number(create.form.received_amount) > selectedAmount.value) return hsxFeedback.warning('收款不能大于销售金额')
    }
    const consignedRows = selectedAssets.value.filter(isConsigned)
    const consignmentNotice = consignedRows.length
        ? `其中客户代卖 ${consignedRows.length} 台，售出后将生成货主应付 ${money(consignedRows.reduce((sum, row) => sum + saleCostBasis(row), 0))}。`
        : ''
    confirmingEntry = true
    const confirmed = await ElMessageBox.confirm(
        `确认向「${create.form.party_name || '所选客户'}」销售出库 ${selectedQuantityText.value}，销售总额 ${money(selectedAmount.value)}。${consignmentNotice}提交后货品立即扣减库存并生成应收；${create.form.settle_mode === 'cash' ? `同时确认现结收款 ${money(create.form.received_amount)}。` : '本次按挂账处理。'}普通操作不能直接撤销。`,
        '确认销售出库',
        { type: 'warning', confirmButtonText: '确认出库', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    confirmingEntry = false
    if (!confirmed || !lookup.sessionOK()) return
    create.saving = true
    try {
        await createErpSale({
            ...create.form,
            settle_mode: create.form.settle_mode,
            settle_method: create.form.settle_mode === 'cash' ? '现结' : '挂账',
            received_amount: create.form.received_amount,
            capital_account_id: create.form.capital_account_id,
            voucher_urls: create.form.voucher_urls,
            items: selectedAssets.value.map(row => stock.item_type === 'standard' ? ({
                item_type: 'standard',
                stock_id: Number(row.stock_id || row.id),
                quantity_product_id: Number(row.product_id || 0),
                warehouse_id: Number(row.warehouse_id || 0),
                location_id: Number(row.location_id || 0),
                quantity: Number(saleQuantities[row.id] || 0),
                sale_price: Number(salePrices[row.id] || 0),
            }) : ({ item_type: 'device', asset_id: row.id, sale_price: Number(salePrices[row.id] || 0) }))
        })
        hsxFeedback.success('销售出库已完成')
        create.visible = false
        await loadList()
    } finally {
        create.saving = false
    }
}

function onPartyResolved(row: any) {
    if (row) entryError.value = ''
    create.form.party_name = row?.party_name || row?.name || ''
    creditProfile.value = row?.credit_profile || null
    if (creditProfile.value?.can_sale !== false && !creditAllowedForOrder.value) {
        create.form.settle_mode = 'cash'
        hsxFeedback.warning(creditProfile.value.message || '该客户当前仅允许现结')
    }
}

function openDetail(row: any) {
    return loadDetail(row.sale_order_id || row.id)
}

async function printSaleReceipt(row: any) {
    if (!lookup.sessionOK()) return
    const result: any = await printErpSaleReceipt(Number(row.sale_order_id || row.id || 0))
    if (result?.data?.skipped) {
        hsxFeedback.warning(result.data.message || '请先在打印中心启用销售开单场景')
        return
    }
    hsxFeedback.success(result?.data?.status === 'waiting_client' ? '任务已生成，请在移动打印台连接蓝牙设备' : '销售小票已发送')
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    Object.assign(search, { keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', catalog_product_id: '', salesman_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined, min_profit: undefined, max_profit: undefined })
    activeTab.value = ''
    handleSearch()
}

function onSearchWarehouseChange() {
    search.location_id = ''
}

function onStockWarehouseChange() {
    stock.location_id = ''
    reloadStock()
}

/** 已形成收款事实的在售设备走销售退货；未收款设备直接取消销售。 */
function canReturnSale(row: any) {
    return !isExternalGoods(row)
        && !isStandardGoods(row)
        && Number(row.asset_id || 0) > 0
        && row.status === 'sold'
        && row.order_status !== 'void'
        && row.return_status !== 'pending'
        && ['partial', 'settled'].includes(String(row.finance_status || ''))
}

function goSaleReturn(row: any) {
    router.push({
        path: '/site/hsx_erp/sale_return',
        query: { sale_order_id: row.sale_order_id, asset_id: row.asset_id },
    })
}

function canCancelSaleItemFromList(row: any) {
    return !isExternalGoods(row)
        && (isStandardGoods(row) || Number(row.asset_id || 0) > 0)
        && row.status === 'sold'
        && row.order_status === 'completed'
        && row.finance_status === 'pending'
        && row.return_status !== 'pending'
}

function canCancelSaleItem(row: any) {
    return !isExternalGoods(row)
        && (isStandardGoods(row) || Number(row.asset_id || 0) > 0)
        && detail.data?.status === 'completed'
        && detail.data?.finance_status === 'pending'
        && row.status === 'sold'
        && row.return_status !== 'pending'
}

const canCancelSaleOrder = computed(() => {
    const order = detail.data
    if (!order || order.status !== 'completed' || order.finance_status !== 'pending' || Number(order.received_amount || 0) > 0) return false
    return !(order.items || []).some((item: any) => isExternalGoods(item))
})

async function cancelSaleOrder() {
    if (!lookup.sessionOK()) return
    if (!canCancelSaleOrder.value || !detail.data?.id) return
    try {
        const result: any = await ElMessageBox.prompt(
            '撤销后本单全部设备或标品会按原位置返库，应收同步作废。',
            '确认整单撤销',
            {
                confirmButtonText: '确认撤销',
                cancelButtonText: '返回检查',
                inputPlaceholder: '填写撤销原因，便于后续追溯',
            }
        )
        if (!lookup.sessionOK()) return
        await cancelErpSale(Number(detail.data.id), { remark: result?.value || '' })
        hsxFeedback.success('销售单已撤销，库存已恢复')
        detail.visible = false
        await loadList()
    } catch (e: any) {
        if (e !== 'cancel' && e !== 'close') throw e
    }
}

async function cancelSaleItem(row: any) {
    if (!lookup.sessionOK()) return
    try {
        const result: any = await ElMessageBox.prompt(
            '取消后该设备会回到原仓库，并冲销对应未收应收；如果这是销售单最后一台有效设备，系统会同步结束整张销售单。已有收款或折账的设备必须走销售退货。',
            '确认取消销售',
            {
                confirmButtonText: '确认取消销售',
                cancelButtonText: '返回检查',
                inputPlaceholder: '填写取消原因，便于后续追溯'
            }
        )
        if (!lookup.sessionOK()) return
        await cancelErpSaleItem(Number(row.id), { remark: result?.value || '' })
        hsxFeedback.success('设备销售已取消')
        if (detail.data?.id) await openDetail(detail.data)
        await loadList()
    } catch (e: any) {
        if (e !== 'cancel' && e !== 'close') throw e
    }
}

function statusMeta(status: string) {
    const map: any = {
        pending: { label: '待收款', type: 'warning' },
        partial: { label: '部分收款', type: 'primary' },
        settled: { label: '已结清', type: 'success' },
        void: { label: '已取消', type: 'info' }
    }
    return map[status] || { label: '状态待确认', type: 'info' }
}

function orderStatusMeta(status: string) {
    const map: any = {
        completed: { label: '已完成', type: 'success' },
        returned: { label: '已退货', type: 'warning' },
        void: { label: '已取消', type: 'info' }
    }
    return map[status] || { label: '状态待确认', type: 'info' }
}

function saleStateMeta(row: any) {
    if (row.return_status === 'pending') return { label: '退货处理中', type: 'warning' }
    if (row.status === 'returned') return { label: '已销售退货', type: 'warning' }
    if (row.status === 'void' || row.order_status === 'void') return { label: '已取消销售', type: 'info' }
    return { label: '已出库', type: 'success' }
}

function showFinanceStatus(row: any) {
    return row.status === 'sold' && row.order_status !== 'void'
}

function saleStateHint(row: any) {
    if (row.return_no) return row.return_no
    if (row.status === 'void') return row.remark || '未形成有效销售'
    return ''
}

function saleItemStatusLabel(status: string) {
    const map: any = { sold: '已出库', returned: '已销售退货', void: '已取消销售' }
    return erpEnumLabel(status, map)
}

function compactDeviceInfo(row: any) {
    return [
        row.spec || '',
        row.imei ? `IMEI ${row.imei}` : '',
        row.sn ? `SN ${row.sn}` : ''
    ].filter(Boolean).join(' · ') || '-'
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}
function recordedMoney(value:unknown) {
    return (typeof value==='number'||(typeof value==='string' && value.trim()!=='')) && Number.isFinite(Number(value)) && Number(value)>=0 ? money(value) : '待核对'
}
function localNextStep(order:any) {
    if(order.status==='void')return '这张销售单已撤销，不需要登记收款。'
    if(recordedMoney(order.receivable_amount)==='待核对')return '本站应收余额待核对；请财务先查看原应收。'
    if(Number(order.receivable_amount)>0)return '下一步：财务按实际到账金额，在本站应收款中登记；已收部分无需重复登记。'
    if(order.finance_status!=='settled')return '请财务核对本站应收和原凭证，不要重复登记。'
    return '这张销售单在本站已结清，无需再收款。'
}

function isConsigned(row: any) {
    return String(row?.ownership_type || '') === 'consigned'
}

function saleCostBasis(row: any) {
    return Number(row?.sale_cost_basis ?? (isConsigned(row) ? row?.consignment_settlement_amount : row?.total_cost) ?? 0)
}

function rowSelectedCost(row: any) {
    return stock.item_type === 'standard'
        ? Number(row?.average_cost || 0) * Number(saleQuantities[row.id] || 0)
        : saleCostBasis(row)
}

function quantityText(value: any) {
    return Number(value || 0).toFixed(3).replace(/0+$/, '').replace(/\.$/, '') || '0'
}

function formatTime(value: any) {
    const time = Number(value || 0)
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
}

function staffName(user: any) {
    return user?.name || user?.real_name || user?.username || '姓名未登记'
}

function batchKey(row: any) {
    return Number(row?.sale_order_id || row?.id || 0)
}

function batchTone(row: any) {
    return Math.abs(batchKey(row)) % 4
}

function isBatchFirst(index: number) {
    if (index <= 0) return true
    return batchKey(table.data[index]) !== batchKey(table.data[index - 1])
}

function batchPageSize(row: any) {
    const key = batchKey(row)
    return table.data
        .filter((item: any) => batchKey(item) === key)
        .reduce((total: number, item: any) => total + itemQuantity(item), 0)
}

function isExternalGoods(row: any) {
    if (isStandardGoods(row)) return false
    return Number(row?.external_goods_id || 0) > 0
        || (Number(row?.asset_id || 0) <= 0 && String(row?.inventory_source || '') !== 'erp_asset')
}

function isStandardGoods(row: any) {
    return String(row?.item_type || '') === 'standard' || String(row?.inventory_source || '') === 'erp_quantity'
}

function itemQuantity(row: any) {
    return Math.max(1, Number(row?.quantity || 1))
}

function saleRowClassName({ row, rowIndex }: { row: any; rowIndex: number }) {
    return [`erp-batch-tone-${batchTone(row)}`, isBatchFirst(rowIndex) ? 'erp-batch-start' : '', row.status !== 'sold' ? 'erp-sale-inactive' : ''].filter(Boolean).join(' ')
}
</script>

<style scoped>
.sale-overview,.sale-original{margin:0 0 16px;padding:16px;border:1px solid var(--el-border-color-lighter);border-radius:8px;background:var(--el-bg-color)}
.sale-overview__title,.sale-overview dl{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}.sale-overview h3{margin:2px 0;font-size:19px}.sale-overview small,.sale-overview span,.sale-overview dt,.sale-original small{color:var(--el-text-color-regular);font-size:12px}.sale-overview dl{justify-content:flex-start;gap:18px 56px;margin:14px 0}.sale-overview dd{margin:3px 0;font-size:18px;font-weight:600;font-variant-numeric:tabular-nums}.sale-overview p{border-top:1px solid var(--el-border-color-lighter);margin:0;padding-top:10px;font-size:13px}.sale-original summary{cursor:pointer;font-weight:600}.sale-original summary small{font-weight:400;margin-left:8px}.sale-original[open] summary{margin-bottom:14px}
.sale-original summary:focus-visible{outline:2px solid var(--el-color-primary);outline-offset:4px}
.trade-settlement-mode{grid-column:1 / -1}.trade-settlement-mode :deep(.el-select){max-width:240px}.trade-checkout{display:flex;flex-wrap:wrap;gap:16px 40px;padding:14px 18px;margin-bottom:14px;background:var(--el-fill-color-light)}.trade-checkout small,.trade-checkout b{display:block}.trade-checkout b{font-size:16px;margin-top:4px}.trade-checkout small,.trade-entry-hint{color:var(--el-text-color-regular)}.trade-entry-hint{margin:0 0 14px;font-size:13px}.trade-entry-error{color:var(--el-text-color-primary);background:var(--el-color-warning-light-9);border-left:3px solid var(--el-color-warning);padding:8px 12px;margin-bottom:12px}

.summary-tile {
    border-radius: 8px;
    background: var(--el-fill-color-light);
    padding: 14px 16px;
}
.summary-label {
    color: var(--el-text-color-secondary);
    font-size: 13px;
}
.summary-value {
    margin-top: 6px;
    font-size: 22px;
    font-weight: 650;
}
.batch-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 auto;
    border-radius: 50%;
}
.batch-dot--0 { background: var(--el-color-primary); }
.batch-dot--1 { background: var(--el-color-success); }
.batch-dot--2 { background: #a78bfa; }
.batch-dot--3 { background: var(--el-color-warning); }
.sale-status-stack { display: flex; align-items: flex-start; flex-direction: column; gap: 5px; }
.sale-status-stack__finance { color: var(--el-text-color-secondary); font-size: 12px; }
.sale-status-stack__hint { overflow: hidden; max-width: 170px; color: var(--el-text-color-secondary); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
:deep(.el-table__body tr.erp-batch-tone-0 > td.el-table__cell) { background: var(--el-bg-color); }
:deep(.el-table__body tr.erp-batch-tone-1 > td.el-table__cell) { background: var(--el-fill-color-lighter); }
:deep(.el-table__body tr.erp-batch-tone-2 > td.el-table__cell) { background: var(--el-bg-color); }
:deep(.el-table__body tr.erp-batch-tone-3 > td.el-table__cell) { background: var(--el-fill-color-lighter); }
:deep(.el-table__body tr.erp-batch-start > td.el-table__cell) { border-top: 2px solid var(--el-border-color); }
:deep(.el-table__body tr.erp-sale-inactive > td.el-table__cell) { color: var(--el-text-color-secondary); background: var(--el-fill-color-light); }
:deep(.el-table__body tr:hover > td.el-table__cell) { background: var(--el-color-primary-light-9) !important; }
.section-title {
    margin: 18px 0 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 650;
}
.sale-pick-metric {
    border-radius: 8px;
    background: var(--el-fill-color-light);
    padding: 10px 12px;
}
.metric-label {
    color: var(--el-text-color-secondary);
    font-size: 12px;
}
.metric-value {
    margin-top: 4px;
    color: var(--el-text-color-primary);
    font-size: 18px;
    font-weight: 650;
}
</style>
