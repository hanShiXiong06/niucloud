<template>
    <ErpDesktopPage class="main-container">
        <el-alert v-if="lookup.sessionChanged.value" type="warning" :closable="false" show-icon role="alert">
            <template #title>当前登录账号或店铺已切换</template>
            <p>本页已停止开单。请刷新并核对店铺，原草稿不会带入其他店铺。 <el-button type="primary" link @click="lookup.reopen">刷新并核对店铺</el-button></p>
        </el-alert>
        <section v-else class="erp-list-surface">
            <ErpTradeHeader page="purchase" title="采购入库" subtitle="回收设备入库后会生成采购单，按串号查原单即可，不用重新开单。" :advanced-count="advancedFilterCount" @search="handleSearch" @reset="handleReset">
                <template #actions>
                    <el-button :icon="Refresh" :loading="table.loading" @click="refreshWorkspace">刷新</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate">新建采购单</el-button>
                    <ErpRoleFocus :items="purchaseRoleFocus" />
                </template>
                <template #mode><el-radio-group v-model="listMode" @change="onListModeChange">
                    <el-radio-button label="device">设备采购</el-radio-button>
                    <el-radio-button label="standard">标品采购</el-radio-button>
                </el-radio-group></template>
                <template #quick>
                    <el-form-item v-if="listMode === 'device'" label="串号">
                        <el-input v-model.trim="search.serial" clearable class="!w-[220px]" placeholder="输入 IMEI、SN 或尾号" />
                    </el-form-item>
                    <el-form-item label="供货商">
                        <ErpPartySelect
                            v-model="search.party_id"
                            v-model:party-name="searchPartyName"
                            party-type="supplier"
                            :allow-create="false"
                            class="!w-[220px]"
                            placeholder="查询供货商"
                        />
                    </el-form-item>
                    <el-form-item label="采购单">
                        <el-input v-model.trim="search.purchase_no" clearable class="!w-[230px]" placeholder="输入采购单号" />
                    </el-form-item>
                </template>
                <template #advanced>
                    <el-form-item v-if="listMode === 'device'" label="整单状态">
                        <el-select v-model="search.finance_status" clearable class="!w-[160px]" placeholder="不限整单状态">
                            <el-option label="整单待付款" value="pending" /><el-option label="整单部分付款" value="partial" />
                            <el-option label="整单已结清" value="settled" /><el-option label="采购单已撤销" value="void" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="仓库">
                        <el-select v-model="search.warehouse_id" clearable class="!w-[160px]" placeholder="全部仓库" @change="onSearchWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="库位">
                            <el-select v-model="search.location_id" clearable class="!w-[160px]" placeholder="全部库位" :disabled="!search.warehouse_id">
                                <el-option v-for="item in searchLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    <el-form-item v-if="listMode === 'device'" label="商品型号">
                            <ErpCatalogProductSelect v-model="search.catalog_product_id" class="!w-[280px]" placeholder="搜索品牌、系列或型号" />
                        </el-form-item>
                    <el-form-item label="采购员">
                            <el-select v-model="search.purchaser_uid" clearable filterable class="!w-[150px]" placeholder="全部">
                                <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                            </el-select>
                        </el-form-item>
                    <el-form-item label="采购时间">
                            <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" start-placeholder="开始" end-placeholder="结束" class="!w-[260px]" />
                        </el-form-item>
                    <el-form-item label="成本">
                            <el-input-number v-model="search.min_amount" :min="0" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                            <span class="mx-1 text-[color:var(--el-text-color-secondary)]">-</span>
                            <el-input-number v-model="search.max_amount" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                        </el-form-item>
                </template>
                <template #summary>
                    <p v-if="table.loading || listError" role="status">{{ table.loading ? '正在读取本页数据…' : '读取成功后显示汇总' }}</p>
                    <template v-else><div class="mt-5 flex flex-wrap items-center justify-between gap-2 erp-summary-heading">
                <div class="text-sm font-medium text-[color:var(--el-text-color-regular)]">本页有效采购汇总</div>
                <div class="text-xs text-[color:var(--el-text-color-secondary)]">{{ summary.review ? '有 ' + summary.review + ' 台应付待核对，金额未计入汇总。' : '已退货、已作废货品不计入' }}</div>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-4 erp-metrics">
                <div class="summary-tile">
                    <div class="summary-label">{{ listMode === 'device' ? '采购台数' : '采购数量' }}</div>
                    <div class="summary-value">{{ quantityText(summary.count) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">有效采购本金</div>
                    <div class="summary-value">{{ summary.review && !summary.known ? '—' : money(summary.totalCost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">已结采购款</div>
                    <div class="summary-value text-[color:var(--el-color-success)]">{{ summary.review && !summary.known ? '—' : money(summary.paid) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">待结采购款</div>
                    <div class="summary-value text-[color:var(--el-color-warning)]">{{ summary.review && !summary.known ? '—' : money(summary.payable) }}</div>
                </div>
            </div></template>
                </template>
            </ErpTradeHeader>
            <el-alert v-if="listError" type="warning" :closable="false" show-icon>
                <template #title>未能读取采购单。筛选条件已保留。 <el-button link type="primary" @click="loadList">重新读取</el-button></template>
            </el-alert>
            <el-tabs v-model="activeTab" class="mt-4 erp-status-tabs" @tab-change="onTabChange">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane v-if="listMode === 'device'" label="本站待付" name="outstanding" />
                <el-tab-pane v-if="listMode === 'standard'" label="待付款" name="pending" />
                <el-tab-pane v-if="listMode === 'standard'" label="部分付款" name="partial" />
                <el-tab-pane :label="listMode === 'device' ? '本站已结清' : '已结清'" name="settled" />
                <el-tab-pane v-if="listMode === 'device'" label="原应付待核对" name="review" />
                <el-tab-pane v-else label="已撤销" name="void" />
            </el-tabs>

            <ErpDataTable v-if="listMode === 'device'" v-show="!listError" class="device-purchase-table" row-key="id"
                :data="table.data" v-loading="table.loading" :expand-row-keys="expandedDeviceKeys" @expand-change="onDeviceExpand">
                <template #empty><span>{{ table.loading ? '正在读取…' : '没有符合当前条件的设备采购记录' }}</span>
                    <el-button v-if="!table.loading && (search.serial || search.party_id || search.purchase_no || advancedFilterCount || activeTab)" link type="primary" @click="handleReset">清除筛选</el-button></template>
                <el-table-column type="expand" width="58" align="center" class-name="purchase-expander" label="资料">
                    <template #default="{ row }">
                        <dl class="purchase-device-facts" aria-label="设备与批次资料">
                            <div><dt>采购单号</dt><dd><el-button v-if="row.purchase_order_id && row.purchase_no" type="primary" link @click="openDetail(row)">{{ row.purchase_no }}</el-button><template v-else>未关联采购单</template></dd></div>
                            <div v-for="fact in deviceRowFacts(row)" :key="fact[0]"><dt>{{ fact[0] }}</dt><dd>{{ fact[1] }}</dd></div>
                        </dl>
                    </template>
                </el-table-column>
                <el-table-column label="设备 / 串号" min-width="245">
                    <template #default="{ row }"><ErpDeviceIdentity compact :model="row.model" :spec="row.spec" :imei="row.imei" :sn="row.sn" /></template>
                </el-table-column>
                <el-table-column label="供货商" min-width="120" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.party_name || '未登记' }}</template>
                </el-table-column>
                <el-table-column label="库存状态" min-width="105">
                    <template #default="{ row }">
                        <span class="purchase-stock" :class="{ 'purchase-stock--ready':row.status === 'in_stock', 'purchase-stock--attention':['void','returned'].includes(row.status) }">{{ row.status ? assetStatusMeta(row.status).label : '待核对' }}</span>
                        <small v-if="row.order_status === 'void'" class="purchase-exception">采购单已撤销</small>
                    </template>
                </el-table-column>
                <el-table-column label="本机应付" min-width="155">
                    <template #default="{ row }">
                        <ErpDevicePayableCell :state="rowPayState(row)" :label="devicePayLabel(row)" :can-view="canViewPayable(row)" @view="openDevicePayable(row)" />
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="165">
                    <template #default="{ row }">
                        <div class="purchase-row-actions">
                            <el-button type="primary" link :disabled="!row.purchase_order_id" @click="openDetail(row)">查看采购单</el-button>
                            <el-dropdown trigger="click" placement="bottom-end" @command="handleRowCommand($event, row)">
                                <el-button link aria-haspopup="menu">更多 <el-icon><ArrowDown /></el-icon></el-button>
                                <template #dropdown><el-dropdown-menu>
                                    <el-dropdown-item command="facts">{{ expandedDeviceKeys.includes(String(row.id)) ? '收起设备资料' : '展开设备资料' }}</el-dropdown-item>
                                    <el-dropdown-item divided command="adjust" :disabled="!canAdjustSupplierPrice(row)">
                                        <div>供应商调价<small v-if="!canAdjustSupplierPrice(row)" class="purchase-menu-reason">{{ supplierAdjustBlockedReason(row) }}</small></div>
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="canReturnPurchase(row) || (row.status === 'in_stock' && row.return_flow?.returnable === false)" command="return" :disabled="!canReturnPurchase(row)">
                                        <div>{{ purchaseReturnActionLabel(row) }}<small v-if="!canReturnPurchase(row)" class="purchase-menu-reason">{{ row.return_flow?.block_reason }}</small></div>
                                    </el-dropdown-item>
                                </el-dropdown-menu></template>
                            </el-dropdown>
                        </div>
                    </template>
                </el-table-column>
            </ErpDataTable>
            <ErpDataTable v-else v-show="!listError" :data="table.data" v-loading="table.loading" :row-class-name="purchaseRowClassName">
                <template #empty><span>{{ table.loading ? '正在读取…' : '没有符合当前条件的采购记录' }}</span></template>
                <el-table-column label="标品" min-width="250">
                    <template #default="{ row }">
                        <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ row.model || '-' }}</div>
                        <div v-if="row.spec" class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ row.spec }}</div>
                        <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">编码：{{ row.product_code || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购数量" min-width="130" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ quantityText(row.quantity) }} {{ row.unit || '件' }}</div>
                        <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">单价 {{ money(row.unit_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购金额" min-width="140" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ money(row.purchase_cost) }}</div>
                        <div class="mt-1 text-xs" :class="Number(row.asset_unpaid_amount || 0) > 0 ? 'text-[color:var(--el-color-warning)]' : 'text-[color:var(--el-color-success)]'">
                            {{ Number(row.asset_unpaid_amount || 0) > 0 ? `待付 ${money(row.asset_unpaid_amount)}` : '已结清' }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="库存位置" min-width="190">
                    <template #default="{ row }">
                        <div>{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</div>
                        <div class="mt-1 text-xs text-[color:var(--erp-text-accent)]">当前库存 {{ quantityText(row.current_stock) }} {{ row.unit || '件' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="供货商 / 批次" min-width="230">
                    <template #default="{ row }">
                        <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ row.purchase_no || '-' }}</div>
                        <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ formatTime(row.purchase_at) }} · {{ row.purchaser_name || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" min-width="150">
                    <template #default="{ row }">
                        <el-tag :type="row.order_status === 'void' ? 'info' : 'success'" effect="plain">
                            {{ row.order_status === 'void' ? '已撤销' : '已入库' }}
                        </el-tag>
                        <div class="mt-2 text-xs text-[color:var(--el-text-color-secondary)]">采购款 · {{ financeStatusMeta(row.finance_status).label }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="100" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">采购单</el-button>
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

        <HsxDialog :confirm-loading="create.saving"
            v-model="create.visible"
            title="采购开单"
            :width="entryStep === 1 ? 'min(94vw, 1440px)' : 'min(90vw, 800px)'"
            destroy-on-close
            class="erp-desktop-overlay create-purchase-dialog"
        >
            <el-form label-width="96px" class="create-purchase-form">
                <ErpTradeEntryGuide :step="entryStep" :labels="['选供货方', '录入货品', '核对结算']" :busy="create.saving" @choose="chooseStep" />
                <p v-if="entryError" role="alert" class="trade-entry-error">{{ entryError }}</p>
                <p v-if="lookup.errors.value.length" role="alert" class="trade-entry-error">{{ lookup.errors.value.join('、') }}未读取。<el-button type="primary" link @click="refreshWorkspace">重新读取资料</el-button></p>
                <section v-show="entryStep === 0" aria-label="选择供货方">
                    <el-form-item label="供货方" required>
                        <counterparty-select v-model="create.form.party_id" role-type="supplier" placeholder="搜索会员、姓名或手机号" @resolving-change="partyResolving = $event" @resolved="onPartyResolved" />
                    </el-form-item>
                    <HsxFold title="业务编号（选填）" :summary="create.form.m_no ? '已带入供货方编号' : ''">
                        <el-form-item label="业务编号"><el-input v-model.trim="create.form.m_no" placeholder="客户 M 号或业务编号" /></el-form-item>
                    </HsxFold>
                </section>
                <section v-show="entryStep === 1" aria-label="录入采购货品">
                <el-form-item label="入库方式" required>
                    <el-radio-group v-model="create.form.item_type_mode" @change="onPurchaseItemModeChange">
                        <el-radio-button value="device">二手机 / 一机一码</el-radio-button>
                        <el-radio-button value="standard">标品 / 数量入库</el-radio-button>
                    </el-radio-group>
                    <span class="ml-3 text-xs text-[color:var(--el-text-color-secondary)]">
                        {{ create.form.item_type_mode === 'device' ? '每台设备建立独立资产与串号追踪' : '适合壳、膜、配件和批量新机' }}
                    </span>
                </el-form-item>
                <!-- <div class="batch-location-panel">
                    <div class="batch-location-panel__head">
                        <div>
                            <div class="font-medium text-[color:var(--el-text-color-primary)]">批量默认位置</div>
                            <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">只用于快速填充；每台设备最终保存自己的仓库和库位，可在“编辑设备”中单独修改。</div>
                        </div>
                        <el-button :disabled="!create.form.warehouse_id || !create.form.location_id" @click="applyDefaultLocationToAll(true)">应用到全部设备</el-button>
                    </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="默认仓库">
                        <el-select v-model="create.form.warehouse_id" class="w-full" placeholder="选择仓库" @change="onWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="默认库位">
                        <el-select v-model="create.form.location_id" class="w-full" placeholder="选择库位" :disabled="!create.form.warehouse_id" @change="onDefaultLocationChange">
                            <el-option v-for="item in currentLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>
                </div> -->

                <div class="purchase-device-section">
                    <div class="mt-1 flex items-center justify-between">
                        <div class="font-medium">{{ create.form.item_type_mode === 'device' ? '机器明细' : '标品明细' }}</div>
                        <div class="flex items-center gap-2">
                            <el-button v-if="create.form.item_type_mode === 'device'" @click="openGoodsMeta">管理商品目录</el-button>
                            <el-button :icon="Plus" @click="addItem">{{ create.form.item_type_mode === 'device' ? '加一台' : '添加商品' }}</el-button>
                        </div>
                    </div>
                    <HsxNotice default-expanded
                        v-if="create.form.item_type_mode === 'device'"
                        class="mt-3"
                        type="info"
                        :closable="false"
                        show-icon
                        title="扫码后按回车可连续新增设备；采购事实在表格快速录入，分类、规格、图片及质检资料进入右侧抽屉完善。"
                    />
                    <div v-if="create.form.item_type_mode === 'device'" class="device-entry">
                        <div class="device-entry__toolbar">
                            <div class="device-entry__default">
                                <span class="device-entry__default-label">本批默认位置</span>
                                <ErpWarehouseLocationCascader
                                    :warehouses="warehouses"
                                    :warehouse-id="create.form.warehouse_id"
                                    :location-id="create.form.location_id"
                                    placeholder="选择后自动带入新增设备"
                                    @change="onDefaultWarehouseLocationChange"
                                />
                                <el-button
                                    :disabled="!create.form.warehouse_id || !create.form.location_id"
                                    @click="applyDefaultLocationToAll(true)"
                                >
                                    覆盖全部
                                </el-button>
                            </div>
                            <div class="device-entry__count">已录入 <strong>{{ create.form.items.length }}</strong> 台</div>
                        </div>
                        <el-table
                            :data="create.form.items"
                            border
                            max-height="390"
                            class="device-entry-table"
                            empty-text="请添加采购设备"
                        >
                            <el-table-column type="index" label="#" width="52" align="center" fixed="left" />
                            <el-table-column min-width="210" fixed="left">
                                <template #header><span class="standard-required">IMEI / SN</span></template>
                                <template #default="{ row, $index }">
                                    <el-input
                                        :ref="element => setDeviceImeiRef(element, $index)"
                                        v-model.trim="row.imei"
                                        clearable
                                        placeholder="扫码或输入，回车继续"
                                        @keyup.enter="handleDeviceImeiEnter($index)"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="商品型号" min-width="230">
                                <template #default="{ row, $index }">
                                    <button type="button" class="device-entry__model" @click="openItemExtra(row, $index)">
                                        <span :class="{ 'is-placeholder': !row.model }">{{ row.model || '选择商品型号' }}</span>
                                        <small>{{ row.spec || row.category_path || '分类、规格及销售资料' }}</small>
                                    </button>
                                </template>
                            </el-table-column>
                            <el-table-column min-width="140" align="right">
                                <template #header><span class="standard-required">采购成本</span></template>
                                <template #default="{ row }">
                                    <el-input-number v-model="row.purchase_cost" :min="0" :precision="2" :controls="false" placeholder="0.00" />
                                </template>
                            </el-table-column>
                            <el-table-column min-width="230">
                                <template #header><span class="standard-required">入库位置</span></template>
                                <template #default="{ row }">
                                    <ErpWarehouseLocationCascader
                                        :warehouses="warehouses"
                                        :warehouse-id="row.warehouse_id"
                                        :location-id="row.location_id"
                                        @change="payload => onItemWarehouseLocationChange(row, payload)"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="资料进度" min-width="190">
                                <template #default="{ row }">
                                    <div v-if="purchaseOneStop" class="device-entry__progress">
                                        <span :class="{ 'is-done': row.catalog_product_id || row.model }">型号</span>
                                        <span :class="{ 'is-done': row.spec }">规格</span>
                                        <span :class="{ 'is-done': hasDeviceImage(row) }">影像</span>
                                        <span :class="{ 'is-done': Number(row.retail_price || 0) > 0 }">售价</span>
                                    </div>
                                    <div v-else class="device-entry__progress">
                                        <span :class="{ 'is-done': deviceCoreReady(row) }">采购事实</span>
                                        <span>后续分岗</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="状态" width="105" align="center">
                                <template #default="{ row }">
                                    <el-tag :type="deviceCoreReady(row) ? 'success' : 'warning'" size="small" effect="light">
                                        {{ deviceCoreReady(row) ? '可入库' : `待补 ${deviceCoreMissingCount(row)} 项` }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="操作" width="142" align="center" fixed="right">
                                <template #default="{ row, $index }">
                                    <el-button type="primary" link @click="openItemExtra(row, $index)">{{ purchaseOneStop ? '一次完善' : '采购事实' }}</el-button>
                                    <el-button type="danger" link @click="removeItem($index)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="device-entry__footer">
                            <el-button :icon="Plus" @click="addItem">继续添加一台</el-button>
                            <span>扫码枪录入后按回车，自动定位到下一台设备</span>
                        </div>
                    </div>
                    <div v-else class="standard-entry">
                        <div class="standard-entry__tip">
                            <span>每行录入一个商品，支持横向滚动；商品编码留空时由系统自动生成。</span>
                            <span>共 {{ create.form.items.length }} 项</span>
                        </div>
                        <el-table
                            :data="create.form.items"
                            border
                            height="360"
                            class="standard-entry-table"
                            empty-text="请添加采购商品"
                        >
                            <el-table-column type="index" label="序号" width="58" align="center" fixed="left" />
                            <el-table-column min-width="300" fixed="left">
                                <template #header><span class="standard-required">已有商品 / 新品</span></template>
                                <template #default="{ row }">
                                    <ErpQuantityProductSelect
                                        v-model="row.quantity_product_id"
                                        :selected="row.quantity_product_snapshot"
                                        @change="product => onStandardProductChange(row, product)"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="商品信息" min-width="220">
                                <template #default="{ row }">
                                    <div class="font-medium text-[color:var(--el-text-color-primary)]">{{ row.model || '待选择' }}</div>
                                    <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ [row.spec, row.product_code].filter(Boolean).join(' · ') || '选择已有商品，找不到可创建新品' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="unit" label="单位" min-width="82" align="center" />
                            <el-table-column min-width="125" align="right">
                                <template #header><span class="standard-required">采购数量</span></template>
                                <template #default="{ row }">
                                    <el-input-number v-model="row.quantity" :min="0" :precision="3" :controls="false" />
                                </template>
                            </el-table-column>
                            <el-table-column min-width="145" align="right">
                                <template #header><span class="standard-required">采购总价</span></template>
                                <template #default="{ row }">
                                    <el-input-number v-model="row.line_total" :min="0" :precision="2" :controls="false" />
                                </template>
                            </el-table-column>
                            <el-table-column label="折算单价" min-width="130" align="right">
                                <template #default="{ row }">
                                    <span class="standard-entry__unit-price">¥{{ standardUnitCostText(row) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column min-width="230">
                                <template #header><span class="standard-required">入库位置</span></template>
                                <template #default="{ row }">
                                    <ErpWarehouseLocationCascader
                                        :warehouses="warehouses"
                                        :warehouse-id="row.warehouse_id"
                                        :location-id="row.location_id"
                                        :filter-types="['accessory', 'new_device']"
                                        placeholder="配件仓 / 新机仓及库位"
                                        @change="payload => onItemWarehouseLocationChange(row, payload)"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="备注" min-width="180">
                                <template #default="{ row }">
                                    <el-input v-model.trim="row.remark" placeholder="选填" />
                                </template>
                            </el-table-column>
                            <el-table-column label="操作" width="72" align="center" fixed="right">
                                <template #default="{ $index }">
                                    <el-button type="danger" link @click="removeItem($index)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="standard-entry__footer">
                            <el-button :icon="Plus" @click="addItem">继续添加一行</el-button>
                            <div>
                                <span>采购合计</span>
                                <strong>{{ money(createTotal) }}</strong>
                            </div>
                        </div>
                    </div>
                    <div v-if="create.form.item_type_mode === 'device'" class="mt-3 text-right text-sm text-[color:var(--el-text-color-secondary)]">本单采购成本合计：<span class="font-semibold text-[color:var(--el-text-color-primary)]">{{ money(createTotal) }}</span></div>
                </div>

                </section>
                <section v-show="entryStep === 2" aria-label="核对结算">
                <div class="trade-checkout"><div><small>供货方</small><b>{{ create.form.party_name || '已选往来方' }}</b></div>
                    <div><small>本单货品</small><b>{{ create.form.items.length + (create.form.item_type_mode === 'standard' ? ' 项' : ' 台') }}</b></div>
                    <div><small>采购总额</small><b>{{ money(createTotal) }}</b></div></div>
                <ErpTradeReviewLines :lines="reviewLines" />
                <p class="trade-entry-hint">{{ create.form.settle_mode === 'credit' ? '挂账入库：生成采购应付，由财务核对实际付款后登记。' : '现结只登记已经付出的金额，这里不会转账。' }}</p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <el-form-item class="trade-settlement-mode" label="结算方式">
                        <el-select v-model="create.form.settle_mode" class="w-full">
                            <el-option label="挂账，稍后付款" value="credit" />
                            <el-option label="现结，本次付款" value="cash" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="本次付款" required>
                        <el-input-number v-model="create.form.paid_amount" :min="0" :precision="2" :controls="false" class="!w-full" />
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="付款账户" required>
                        <el-select v-model="create.form.capital_account_id" class="w-full" :loading="accountLoading" placeholder="选择账户">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="付款凭证">
                        <ErpFinanceVoucherUpload v-model="create.form.voucher_urls" />
                    </el-form-item>
                </div>
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
                <el-button v-if="entryStep < 2" type="primary" :loading="entryStep === 0 && partyResolving" @click="chooseStep(entryStep + 1)">{{ partyResolving ? '正在匹配…' : entryStep === 0 ? '下一步：录入货品' : '下一步：核对结算' }}</el-button>
                <el-button v-else :disabled="create.saving" type="primary" :loading="create.saving" @click="submitCreate">确认开单</el-button>
            </template>
        </HsxDialog>

        <HsxDrawer v-model="itemExtra.visible" size="720px" direction="rtl" append-to-body class="erp-desktop-overlay purchase-item-drawer" :destroy-on-close="false">
            <template #header>
                <div class="item-extra-drawer-title">
                    <div>
                        <div class="item-extra-title">编辑设备 {{ itemExtra.index + 1 }}</div>
                        <div class="item-extra-sub">{{ itemExtra.item ? [itemExtra.item.model, itemExtra.item.imei || itemExtra.item.sn].filter(Boolean).join(' · ') : '' }}</div>
                    </div>
                    <el-tag v-if="itemExtra.item" :type="deviceCoreReady(itemExtra.item) ? 'success' : 'warning'" effect="light">
                        {{ deviceCoreReady(itemExtra.item) ? (purchaseOneStop ? '本次资料已完整' : '采购事实已完整') : `待补 ${deviceCoreMissingCount(itemExtra.item)} 项` }}
                    </el-tag>
                </div>
            </template>
            <div v-if="firstMissingField" class="trade-entry-hint" role="status">
                下一项：{{ firstMissingField.label }}
                <el-button v-if="itemExtra.activeTab !== firstMissingField.tab" link type="primary" @click="itemExtra.activeTab = firstMissingField.tab">去填写</el-button>
            </div>

            <el-tabs v-if="itemExtra.item" v-model="itemExtra.activeTab" class="item-extra-tabs" stretch>
                <el-tab-pane label="① 基础归档" name="base">
                    <div class="item-extra-layout">
                        <section class="item-extra-section">
                            <div class="item-extra-section__head">
                                <div>
                                    <div class="item-extra-section__title">入库资料</div>
                                </div>
                            </div>
                            <el-form label-position="top" class="drawer-mobile-form">
                                <el-form-item label="商品分类 / 目录型号" :required="purchaseOneStop && listingFieldRequired('catalog_product_id')">
                                    <ErpPurchaseDeviceCatalogSelect
                                        :key="itemExtra.index"
                                        :model-value="itemExtra.item.catalog_product_id"
                                        :category-path="itemExtra.item.category_path"
                                        :product-name="itemExtra.item.catalog_product_name"
                                        :selection="itemExtra.item.catalog_reference"
                                        @change="node => onItemCatalogChange(itemExtra.item, node)"
                                    />
                                </el-form-item>
                                <div class="spec-grid">
                                    <el-form-item label="设备名称 / 型号" required>
                                        <el-input
                                            v-model.trim="itemExtra.item.model"
                                            placeholder="如：苹果 iPhone 15 Pro"
                                            @input="markManualModel(itemExtra.item)"
                                        />
                                    </el-form-item>
                                    <el-form-item label="IMEI">
                                        <el-input v-model.trim="itemExtra.item.imei" placeholder="与 SN 至少填一项" />
                                    </el-form-item>
                                    <el-form-item label="SN">
                                        <el-input v-model.trim="itemExtra.item.sn" placeholder="与 IMEI 至少填一项" />
                                    </el-form-item>
                                    <el-form-item label="采购成本" required>
                                        <el-input-number v-model="itemExtra.item.purchase_cost" :min="0" :precision="2" :controls="false" class="!w-full" />
                                    </el-form-item>
                                    <el-form-item label="仓库 / 库位" required>
                                        <ErpWarehouseLocationCascader
                                            :warehouses="warehouses"
                                            :warehouse-id="itemExtra.item.warehouse_id"
                                            :location-id="itemExtra.item.location_id"
                                            @change="payload => onItemWarehouseLocationChange(itemExtra.item, payload)"
                                        />
                                    </el-form-item>
                                </div>
                                <el-form-item label="采购备注">
                                    <el-input v-model.trim="itemExtra.item.remark" type="textarea" :rows="2" placeholder="仅记录来源、异常或采购约定" />
                                </el-form-item>
                            </el-form>
                        </section>
                    </div>
                </el-tab-pane>

                <el-tab-pane v-if="purchaseOneStop" label="② 商品资料" name="goods">
                    <div class="item-extra-layout">
                        <section v-if="listingFieldEnabled('spec')" class="item-extra-section">
                            <div class="item-extra-section__head">
                                <div>
                                    <div class="item-extra-section__title">规格与成色</div>
                                    <div class="item-extra-section__desc">这些字段用于库存检索、商城筛选和设备名称生成。</div>
                                </div>
                                <el-button link type="primary" @click="openGoodsMeta">管理规格</el-button>
                            </div>
                            <div v-if="specGroups.length" class="spec-grid">
                                <div v-for="group in specGroups" :key="group.key" class="spec-field">
                                    <div class="spec-label">{{ group.label }}</div>
                                    <el-select
                                        :model-value="selectedSpecValue(itemExtra.item, group)"
                                        clearable
                                        filterable
                                        class="w-full"
                                        placeholder="请选择"
                                        @change="value => onSpecChange(itemExtra.item, group, value)"
                                    >
                                        <el-option v-for="option in group.items" :key="option.value" :label="option.label" :value="option.value" />
                                    </el-select>
                                </div>
                            </div>
                            <el-empty v-else :image-size="68" description="暂无规格项">
                                <el-button type="primary" link @click="openGoodsMeta">去添加规格</el-button>
                            </el-empty>
                            <div class="spec-grid mt-3">
                                <div class="spec-field">
                                    <div class="spec-label">成色</div>
                                    <el-select
                                        :model-value="itemExtra.item.selected_grade?.value || ''"
                                        clearable
                                        filterable
                                        class="w-full"
                                        placeholder="请选择"
                                        @change="value => onGradeChange(itemExtra.item, value)"
                                    >
                                        <el-option v-for="option in gradeOptions" :key="option.value" :label="option.label" :value="option.value" />
                                    </el-select>
                                </div>
                                <div class="spec-field">
                                    <div class="spec-label">颜色</div>
                                    <el-input v-model.trim="itemExtra.item.color" clearable placeholder="如：黑色 / 橙色" @change="rebuildItemSpec(itemExtra.item)" />
                                </div>
                                <div class="spec-field">
                                    <div class="spec-label">电池效率</div>
                                    <el-input-number v-model="itemExtra.item.battery" :min="0" :max="100" :precision="0" :controls="false" placeholder="选填" class="!w-full" @change="rebuildItemSpec(itemExtra.item)" />
                                </div>
                                <div class="spec-field">
                                    <div class="spec-label">保修截止</div>
                                    <el-date-picker v-model="itemExtra.item.warranty" type="date" value-format="X" clearable class="!w-full" placeholder="选填" @change="rebuildItemSpec(itemExtra.item)" />
                                </div>
                            </div>
                        </section>
                        <section v-if="listingFieldEnabled('remark_public')" class="item-extra-section">
                            <el-form label-position="top" class="drawer-mobile-form">
                                <el-form-item label="对外说明" :required="listingFieldRequired('remark_public')">
                                    <el-input v-model.trim="itemExtra.item.remark_public" type="textarea" :rows="2" placeholder="展示给商城客户的商品说明" />
                                </el-form-item>
                            </el-form>
                        </section>
                    </div>
                </el-tab-pane>

                <el-tab-pane v-if="purchaseOneStop && hasPurchaseSalesFields" label="③ 销售资料" name="sales">
                    <div class="item-extra-layout">
                        <section class="item-extra-section">
                            <div class="item-extra-section__head">
                                <div>
                                    <div class="item-extra-section__title">质检、媒体与销售定价</div>
                                    <div class="item-extra-section__desc">用于后续销售和商城展示，不影响采购事实。</div>
                                </div>
                                <el-tag :type="purchaseSalesRequired ? 'warning' : 'info'" effect="plain">{{ purchaseSalesRequired ? '按规则必填' : '选填' }}</el-tag>
                            </div>
                            <el-form label-position="top" class="drawer-mobile-form">
                                <div class="spec-grid">
                                    <el-form-item label="质检员">
                                        <el-select v-model="itemExtra.item.inspector_uid" clearable filterable class="w-full" placeholder="未质检可不选">
                                            <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item v-if="listingFieldEnabled('retail_price')" label="销售定价" :required="listingFieldRequired('retail_price')">
                                        <el-input-number v-model="itemExtra.item.retail_price" :min="0" :precision="2" :controls="false" placeholder="选填" class="!w-full" />
                                    </el-form-item>
                                </div>
                                <el-form-item v-if="listingFieldEnabled('image_urls')" label="设备图片" :required="listingFieldRequired('image_urls')">
                                    <div class="erp-image-upload">
                                        <upload-image v-model="itemExtra.item.image_urls" :limit="9" width="72px" height="72px" image-text="上传/选择" />
                                        <div class="erp-image-upload__tips">支持素材库与本地上传，多图可拖动排序。</div>
                                    </div>
                                </el-form-item>
                                <el-form-item v-if="listingFieldEnabled('video_url')" label="展示视频" :required="listingFieldRequired('video_url')">
                                    <div class="erp-image-upload">
                                        <upload-video v-model="itemExtra.item.video_url" :limit="1" />
                                        <div class="erp-image-upload__tips">选填，最多 1 个，用于商城商品视频。</div>
                                    </div>
                                </el-form-item>
                                <el-form-item v-if="listingFieldEnabled('quality_remark')" label="质检备注" :required="listingFieldRequired('quality_remark')">
                                    <el-input v-model.trim="itemExtra.item.quality_remark" type="textarea" :rows="3" placeholder="如：屏幕划痕、电池效率等" />
                                </el-form-item>
                                <el-form-item v-if="listingFieldEnabled('remark_internal')" label="对内备注" :required="listingFieldRequired('remark_internal')">
                                    <el-input v-model.trim="itemExtra.item.remark_internal" type="textarea" :rows="2" placeholder="仅员工可见，不同步到商城" />
                                </el-form-item>
                            </el-form>
                        </section>
                    </div>
                </el-tab-pane>
            </el-tabs>
            <template #footer>
                <div class="item-extra-footer">
                    <div>
                        <el-button :disabled="itemExtra.index <= 0" @click="switchItemExtra(-1)">上一台</el-button>
                        <el-button :disabled="itemExtra.index >= create.form.items.length - 1" @click="switchItemExtra(1)">下一台</el-button>
                    </div>
                    <div>
                        <el-button @click="itemExtra.visible = false">关闭</el-button>
                        <el-button type="primary" @click="finishItemExtra">完成当前设备</el-button>
                    </div>
                </div>
            </template>
        </HsxDrawer>

        <HsxDrawer class="erp-desktop-overlay" v-model="detail.visible" title="采购单详情" size="76%" destroy-on-close>
            <div v-loading="detail.loading" class="purchase-record">
                <div v-if="detail.error" class="mb-4 flex items-center gap-3" role="alert">
                    <span>{{ detail.error }}</span><el-button link type="primary" @click="loadDetail(detail.orderId)">重新读取</el-button>
                </div>
                <section v-if="handoff" class="purchase-handoff" :class="'purchase-handoff--'+handoff.tone" aria-label="本站采购下一步">
                    <div class="purchase-handoff__source"><strong>{{ detail.data.purchase_no }}</strong><span>{{ detail.data.party_name || '未登记供货商' }}</span></div>
                    <div class="purchase-handoff__title"><strong>{{ handoff.title }}</strong><span>{{ handoff.role }}</span></div>
                    <p>{{ handoff.note }}</p>
                </section>
                <section v-if="detailDevices.length" class="purchase-devices" aria-label="按设备核对应付">
                    <header><div><h3>设备明细 <small>{{ detailDevices.length }} 台</small></h3></div></header>
                    <el-table :data="detailDevices" row-key="id" size="default" max-height="380" aria-label="本单设备应付明细">
                        <el-table-column label="设备 / 串号" min-width="220">
                            <template #default="{ row }"><ErpDeviceIdentity compact :model="row.model" :spec="row.spec" :imei="row.imei" :sn="row.sn" /></template>
                        </el-table-column>
                        <el-table-column label="采购成本" width="110" align="right">
                            <template #default="{ row }">{{ row.purchase_cost == null ? '—' : money(row.purchase_cost) }}</template>
                        </el-table-column>
                        <el-table-column label="库存状态" width="110"><template #default="{ row }">{{ row.asset_status ? assetStatusMeta(row.asset_status).label : '待核对' }}</template></el-table-column>
                        <el-table-column label="本站已结算" width="120" align="right">
                            <template #default="{ row }">{{ deviceSettledText(row) }}</template>
                        </el-table-column>
                        <el-table-column label="本机剩余应付" min-width="185">
                            <template #default="{ row }">
                                <ErpDevicePayableCell :state="rowPayState(row)" :label="rowPayState(row) === 'settled' ? '¥0.00 · 已结清' : devicePayLabel(row)" :can-view="canViewPayable(row)" @view="openDevicePayable(row)" />
                            </template>
                        </el-table-column>
                    </el-table>
                </section>
                <ErpDocumentSyncResult ref="syncResult" operator-view="purchase" :results="detail.data?.integration_results" @refresh="loadDetail(detail.orderId)" />
                <div v-if="detail.data?.integration_actions?.length" class="mb-4 flex flex-wrap gap-2">
                    <el-button v-for="action in detail.data.integration_actions" :key="action.key" type="primary" plain @click="openPurchaseIntegration(action)">{{ action.label }}</el-button>
                </div>
                <component v-if="detail.data" :is="detailDevices.length ? 'details' : 'div'" :key="detail.orderId" class="purchase-original">
                <summary v-if="detailDevices.length">整单资料、设备备注与调价</summary>
                <el-descriptions :column="4" border>
                    <el-descriptions-item label="采购单号">{{ detail.data.purchase_no }}</el-descriptions-item>
                    <el-descriptions-item label="采购用户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="M号">{{ detail.data.m_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="业务来源">{{ erpSourceLabel(detail.data.origin_name, 'ERP采购') }}</el-descriptions-item>
                    <el-descriptions-item label="原业务单号">{{ detail.data.origin_no || detail.data.purchase_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="业务状态">{{ detail.data.business_status_label || orderStatusMeta(detail.data.status).label }}</el-descriptions-item>
                    <el-descriptions-item label="整单付款状态">
                        <el-tag :type="financeStatusMeta(detail.data.finance_status).type">{{ financeStatusMeta(detail.data.finance_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="原采购金额">{{ money(detail.data.original_total_cost ?? detail.data.total_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="退货冲减">{{ detail.data.finance_status === 'review' ? '待核对' : money(Math.max(0, Number(detail.data.original_total_cost || detail.data.total_cost || 0) - Number(detail.data.effective_purchase_amount || 0))) }}</el-descriptions-item>
                    <el-descriptions-item label="有效采购本金">{{ detail.data.finance_status === 'review' ? '待核对' : money(detail.data.effective_purchase_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="整单已结算">{{ detail.data.finance_status === 'review' ? '待核对' : money(detail.data.paid_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="整单剩余应付">{{ detail.data.finance_status === 'review' ? '待核对' : money(detail.data.payable_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="仓库">{{ detail.data.warehouse_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="库位">{{ detail.data.location_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="付款账户">{{ detail.data.capital_account_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="采购员">{{ detail.data.purchaser_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="4">{{ detail.data.remark || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-5 font-medium">{{ detailIsStandard ? '标品明细' : '机器明细' }}</div>
                <el-table class="mt-3" :data="detail.data?.items || []" size="large">
                    <el-table-column prop="model" :label="detailIsStandard ? '商品名称' : '型号'" min-width="180" />
                    <el-table-column v-if="detailIsStandard" prop="product_code" label="商品编码" min-width="150" />
                    <el-table-column v-else prop="imei" label="IMEI" min-width="170" />
                    <el-table-column prop="spec" label="规格" min-width="150" />
                    <el-table-column v-if="detailIsStandard" label="数量 / 单价" min-width="150" align="right">
                        <template #default="{ row }">
                            <div>{{ quantityText(row.quantity) }} {{ row.unit || '件' }}</div>
                            <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ money(row.unit_cost) }} / {{ row.unit || '件' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="入库位置" min-width="170">
                        <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                    </el-table-column>
                    <el-table-column v-if="!detailIsStandard" prop="inspector_name" label="质检员" min-width="120" />
                    <el-table-column v-if="!detailIsStandard" label="销售价格" width="130" align="right">
                        <template #default="{ row }">{{ Number(row.retail_price || row.estimate_sale_price || 0) ? money(row.retail_price || row.estimate_sale_price) : '-' }}</template>
                    </el-table-column>
                    <el-table-column label="成本" width="160" align="right">
                        <template #default="{ row }">
                            <div>{{ money(row.purchase_cost) }}</div>
                            <div v-if="Number(row.adjust_cost)" class="text-xs text-[color:var(--el-text-color-secondary)]">调整 {{ money(row.adjust_cost) }}</div>
                            <div v-if="Number(row.refurbish_cost)" class="text-xs text-[color:var(--el-color-warning)]">整备 {{ money(row.refurbish_cost) }}（另付）</div>
                        </template>
                    </el-table-column>
                    <el-table-column v-if="!detailIsStandard" label="质检备注" min-width="210">
                        <template #default="{ row }"><ErpOverflowText :text="row.quality_remark" :lines="2" max-width="240px" /></template>
                    </el-table-column>
                    <el-table-column label="备注" min-width="180">
                        <template #default="{ row }"><ErpOverflowText :text="row.remark" :lines="2" max-width="210px" /></template>
                    </el-table-column>
                    <el-table-column label="操作" width="110" align="center">
                        <template #default="{ row }">
                            <el-button v-if="canAdjustSupplierPrice(row)" type="primary" link @click="openAdjust(row)">供应商调价</el-button>
                            <el-tooltip v-else :content="supplierAdjustBlockedReason(row)" placement="top">
                                <span><el-button type="primary" link disabled>供应商调价</el-button></span>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>
                </component>
            </div>
        </HsxDrawer>

        <HsxDialog class="erp-desktop-overlay" :confirm-loading="adjust.saving" v-model="adjust.visible" title="供应商采购价调整" width="620px" :destroy-on-close="false">
            <div v-if="adjust.item" class="mb-4 rounded bg-[var(--el-fill-color-light)] px-4 py-3 text-sm text-[color:var(--el-text-color-regular)]">
                <div>设备：<span class="font-medium text-[color:var(--el-text-color-primary)]">{{ adjust.item.model || '-' }}</span></div>
                <div class="mt-1">{{ adjust.item.spec || '-' }} · IMEI {{ adjust.item.imei || '-' }}</div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded bg-[var(--el-fill-color-light)] px-3 py-2">
                    <div class="text-xs text-[color:var(--el-text-color-secondary)]">当前成本</div>
                    <div class="mt-1 font-medium text-[color:var(--el-text-color-primary)]">{{ money(adjustCurrentCost) }}</div>
                </div>
                <div :class="adjust.form.type === 'deduct' ? 'bg-[var(--el-color-danger-light-9)]' : 'bg-[var(--el-color-primary-light-9)]'" class="rounded px-3 py-2">
                    <div class="text-xs text-[color:var(--el-text-color-secondary)]">本次调整</div>
                    <div class="mt-1 font-medium" :class="adjust.form.type === 'deduct' ? 'text-[color:var(--el-color-danger)]' : 'text-[color:var(--erp-text-accent)]'">{{ money(adjustSignedAmount) }}</div>
                </div>
                <div class="rounded bg-[var(--el-fill-color-light)] px-3 py-2">
                    <div class="text-xs text-[color:var(--el-text-color-secondary)]">调整后成本</div>
                    <div class="mt-1 font-medium text-[color:var(--el-text-color-primary)]">{{ money(adjustAfterCost) }}</div>
                </div>
            </div>
            <el-form label-width="90px">
                <el-form-item label="调整类型" required>
                    <el-radio-group v-model="adjust.form.type">
                        <el-radio-button label="deduct">扣款</el-radio-button>
                        <el-radio-button label="supplement">补款</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="调整金额" required>
                    <el-input-number v-model="adjust.form.amount" :min="0" :precision="2" :controls="false" class="!w-[220px]" />
                    <div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">只填正数；扣款会减少成本，补款会增加成本。</div>
                </el-form-item>
                <el-form-item label="影响说明">
                    <HsxNotice v-if="adjustHint" default-expanded :closable="false" show-icon type="warning" :title="adjustHint" />
                    <HsxNotice default-expanded
                        :closable="false"
                        show-icon
                        :type="adjust.form.type === 'deduct' ? 'warning' : 'info'"
                        :title="adjust.form.type === 'deduct' ? '扣款会减少采购成本和应付款，后续毛利会增加。' : '补款会增加采购成本和应付款，后续毛利会减少。'"
                    />
                </el-form-item>
                <el-form-item label="原因">
                    <el-input v-model.trim="adjust.form.remark" type="textarea" :rows="2" placeholder="例如：验机发现屏幕瑕疵，扣供应商 100" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="adjust.saving" @click="adjust.visible = false">取消</el-button>
                <el-button :disabled="adjust.saving" type="primary" :loading="adjust.saving" @click="submitAdjust">确认调整并记账</el-button>
            </template>
        </HsxDialog>
    </ErpDesktopPage>
</template>

<script setup lang="ts">
import ErpDesktopPage from '@/addon/hsx_erp/components/ErpDesktopPage.vue'
import ErpDocumentSyncResult from '@/addon/hsx_erp/components/ErpDocumentSyncResult.vue'
import { sourceId, useSourceDocument } from '@/addon/hsx_erp/hooks/useSourceDocument'
import useUserStore from '@/stores/modules/user'
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
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import { ArrowDown, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { adjustErpPurchaseCost, createErpPurchase, getErpDicts, getErpGoodsSpecMeta, getErpPurchaseInfo, getErpPurchaseList, getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import { getErpConfig } from '@/addon/hsx_erp/api/config'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import ErpDeviceIdentity from '@/addon/hsx_erp/components/ErpDeviceIdentity.vue'
import ErpDevicePayableCell from '@/addon/hsx_erp/components/ErpDevicePayableCell.vue'
import { deviceBillState, purchaseHandoff } from '@/addon/hsx_erp/utils/purchase-handoff'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
import ErpPurchaseDeviceCatalogSelect from '@/addon/hsx_erp/components/ErpPurchaseDeviceCatalogSelect.vue'
import ErpQuantityProductSelect from '@/addon/hsx_erp/components/ErpQuantityProductSelect.vue'
import ErpOverflowText from '@/addon/hsx_erp/components/ErpOverflowText.vue'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import ErpWarehouseLocationCascader from '@/addon/hsx_erp/components/ErpWarehouseLocationCascader.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
const hsxFeedback = useFeedback()
const lookup = useTradeDirectory()
const partyResolving = ref(false)
const entryStep = ref(0), entryError = ref(''), listError = ref(false)
let listVersion = 0
let confirmingEntry = false
function refreshWorkspace(withList: boolean | Event = true) {
    if (!lookup.sessionOK()) return
    lookup.clear(); void loadWarehouses(); void loadStaffOptions(); void loadDicts(); void loadSpecMeta(); void loadRules()
    if (withList !== false) void loadList()
}

const search = reactive<any>({ serial: '', party_id: null, purchase_no: '', finance_status: '', status: '', warehouse_id: '', location_id: '', catalog_product_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
const searchPartyName = ref('')
const advancedFilterCount = computed(() => [search.finance_status, search.warehouse_id, search.location_id, search.catalog_product_id, search.purchaser_uid, search.dateRange?.length || undefined, search.min_amount, search.max_amount].filter(v => v != null && v !== '').length)
const activeTab = ref('')
const listMode = ref<'device' | 'standard'>('device')
const router = useRouter()
const route = useRoute()
const user = useUserStore()
const expandedDeviceKeys = ref<string[]>([])
const syncResult = ref<InstanceType<typeof ErpDocumentSyncResult>>()
const openPurchaseIntegration = (action: any) => {
    if (action?.query?.detail_extension) { syncResult.value?.openAction(action, Number(detail.data?.id)); return }
    if (!/^\/[A-Za-z0-9_/-]+$/.test(String(action.pc_route || ''))) return
    router.push({ path: action.pc_route, query: action.query?.purchase_no === detail.data?.purchase_no && action.query?.purchase_no ? { purchase_no: action.query.purchase_no } : { purchase_id: Number(detail.data?.id) } })
}

function onTabChange() {
    table.page = 1
    loadList()
}
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const warehouses = ref<any[]>([])
const specMeta = ref<any>({ groups: [], grades: [] })
const staffOptions = ref<any[]>([])
const currentUid = ref(0)
const deviceImeiInputs = ref<any[]>([])
const titleRules = reactive({ category_mode: 'auto', spec_in_title: 1, grade_in_title: 0, separator: ' ' })
const listingWorkspace = ref<any>({
    mode: 'one_stop',
    field_rules: {
        catalog_product_id: { enabled: 1, required: 1 },
        spec: { enabled: 1, required: 1 },
        image_urls: { enabled: 1, required: 1 },
        video_url: { enabled: 1, required: 0 },
        retail_price: { enabled: 1, required: 1 },
        quality_remark: { enabled: 1, required: 0 },
        remark_public: { enabled: 1, required: 0 },
        remark_internal: { enabled: 1, required: 0 },
    }
})
const create = reactive({ visible: false, saving: false, form: defaultForm() })
const itemExtra = reactive({ visible: false, index: -1, item: null as any, activeTab: 'base' })
const { detail, loadDetail } = useSourceDocument(getErpPurchaseInfo)
const detailDevices = computed(() => (detail.data?.items || []).filter((item: any) => item.item_type !== 'standard'))
const handoff = computed(() => purchaseHandoff(detailDevices.value, !!(detail.data?.integration_results?.length || detail.data?.integration_actions?.length)))
const detailIsStandard = computed(() => Array.isArray(detail.data?.items) && detail.data.items.some((row: any) => row.item_type === 'standard'))
const listCostHint = ref('')
const adjustHint = ref('')
const adjust = reactive({ visible: false, saving: false, itemId: 0, item: null as any, form: { type: 'deduct', amount: 0, remark: '' } })
const erpDicts = ref<Record<string, any[]>>({})
const purchaseRoleFocus = [
    { role: '采购', focus: '供应商、设备身份、成本与采购批次' },
    { role: '仓管', focus: '逐台入库仓库、库位与库存状态' },
    { role: '财务', focus: '付款状态、已付事实与剩余应付' },
]

const summary = computed(() => {
    return table.data.reduce((acc, row: any) => {
        const assetStatus = String(row.status || '')
        if (listMode.value === 'device' && rowPayState(row) === 'review') acc.review++
        if (row.order_status === 'void' || Number(row.is_returned || 0) === 1 || assetStatus === 'returned' || assetStatus === 'void') return acc
        acc.count += listMode.value === 'standard' ? Number(row.quantity || 0) : 1
        if (listMode.value === 'device' && ['review','void'].includes(rowPayState(row))) return acc
        acc.known++
        const paid = Number(row.asset_paid_amount || 0)
        const purchaseAmount = Number(row.asset_payable_amount ?? row.purchase_cost ?? 0)
        acc.totalCost += purchaseAmount
        acc.paid += paid
        acc.payable += Math.max(0, Number(row.asset_unpaid_amount ?? (purchaseAmount - paid)))
        return acc
    }, { count: 0, known: 0, review: 0, totalCost: 0, paid: 0, payable: 0 })
})
const createTotal = computed(() => create.form.items.reduce((sum: number, row: any) =>
    sum + (row.item_type === 'standard' ? standardLineTotal(row) : Number(row.purchase_cost || 0)), 0))
const specGroups = computed(() => normalizeSpecGroups(specMeta.value?.groups || []))
const gradeOptions = computed(() => normalizeOptions(specMeta.value?.grades || []))
const currentWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(create.form.warehouse_id)) || null)
const currentLocations = computed(() => currentWarehouse.value?.locations || [])
const purchaseOneStop = computed(() => String(listingWorkspace.value?.mode || 'one_stop') === 'one_stop')
const hasPurchaseSalesFields = computed(() => ['image_urls', 'video_url', 'retail_price', 'quality_remark', 'remark_internal'].some(listingFieldEnabled))
const purchaseSalesRequired = computed(() => ['image_urls', 'video_url', 'retail_price', 'quality_remark', 'remark_internal'].some(listingFieldRequired))
const standardWarehouses = computed(() => warehouses.value.filter((row: any) => ['accessory', 'new_device'].includes(String(row.warehouse_type || ''))))
const searchWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(search.warehouse_id)) || null)
const searchLocations = computed(() => searchWarehouse.value?.locations || [])
const adjustCurrentCost = computed(() => Number(adjust.item?.total_cost || 0))
const adjustSignedAmount = computed(() => {
    const amount = Number(adjust.form.amount || 0)
    return adjust.form.type === 'deduct' ? -amount : amount
})
const adjustAfterCost = computed(() => adjustCurrentCost.value + adjustSignedAmount.value)

watch(createTotal, amount => {
    if (create.form.settle_mode === 'cash') create.form.paid_amount = amount
})
watch(() => create.form.settle_mode, mode => {
    create.form.paid_amount = mode === 'cash' ? createTotal.value : 0
    if (mode !== 'cash') create.form.capital_account_id = 0
})

onMounted(() => {
    if (route.query.purchase_id) loadDetail(route.query.purchase_id)
    loadWarehouses()
    loadStaffOptions()
    loadSpecMeta()
    loadRules()
    loadDicts()
})
useErpPageRefresh(loadList)
const { accounts, accountError, accountLoading, reloadAccounts } = useTradeAccounts(
    () => create.visible && entryStep.value === 2 && create.form.settle_mode === 'cash', () => lookup.scope.value, () => lookup.sessionOK() ? getCapitalAccounts() : Promise.reject(new Error('店铺已切换'))
)
watch(lookup.sessionChanged, changed => {
    if (!changed) return
    create.visible = false; detail.visible = false; warehouses.value = []; staffOptions.value = []; currentUid.value = 0
    itemExtra.visible = false; adjust.visible = false; specMeta.value = { groups: [], grades: [] }; erpDicts.value = {}
    ++listVersion; table.data = []; table.total = 0; table.loading = false
    expandedDeviceKeys.value = []
})
watch(lookup.scope, (next, previous) => { if (next && !previous && lookup.sessionOK()) refreshWorkspace() })
function defaultForm() {
    return {
        party_id: 0,
        party_name: '',
        m_no: '',
        purchase_channel: '',
        purchaser_uid: 0,
        settle_method: '',
        settle_mode: 'credit',
        paid_amount: 0,
        capital_account_id: 0,
        voucher_urls: '',
        warehouse_id: 0,
        warehouse_name: '',
        location_id: 0,
        location_name: '',
        remark: '',
        item_type_mode: 'device',
        items: [blankItem('device')]
    }
}

function blankItem(type = 'device') {
    if (type === 'standard') {
        const warehouse = standardWarehouses.value[0]
        const location = warehouse?.locations?.[0]
        return {
            item_type: 'standard',
            quantity_product_id: 0,
            quantity_product_snapshot: null,
            model: '',
            spec: '',
            product_code: '',
            unit: '件',
            quantity: 1,
            unit_cost: 0,
            line_total: 0,
            purchase_cost: 0,
            warehouse_id: Number(warehouse?.id || 0),
            warehouse_name: warehouse?.warehouse_name || '',
            location_id: Number(location?.id || 0),
            location_name: location?.location_name || '',
            remark: ''
        }
    }
    return {
        item_type: 'device',
        model: '',
        imei: '',
        sn: '',
        spec: '',
        spec_json: {},
        selected_specs: {},
        selected_grade: null,
        color: '',
        battery: undefined,
        warranty: undefined,
        catalog_product_id: 0,
        catalog_reference: null,
        catalog_product_name: '',
        category_name: '',
        category_names: [],
        category_path: '',
        purchase_cost: 0,
        inspector_uid: null,
        estimate_sale_price: undefined,
        retail_price: undefined,
        image_urls: '',
        video_url: '',
        quality_remark: '',
        remark_public: '',
        remark_internal: '',
        remark: '',
        warehouse_id: 0,
        warehouse_name: '',
        location_id: 0,
        location_name: '',
        _auto_model: '',
        _model_manual: false
    }
}

function onStandardProductChange(row: any, product: any | null) {
    row.quantity_product_id = Number(product?.id || 0)
    row.quantity_product_snapshot = product || null
    row.model = String(product?.product_name || '')
    row.spec = String(product?.spec || '')
    row.product_code = String(product?.product_code || '')
    row.unit = String(product?.unit || '件')
}

async function loadList() {
    if (!lookup.sessionOK()) return
    const v = ++listVersion
    expandedDeviceKeys.value = []
    table.loading = true; listError.value = false; listCostHint.value = ''
    try {
        const res: any = await getErpPurchaseList({ ...buildSearchParams(), item_type: listMode.value, page: table.page, limit: table.limit })
        if (!lookup.sessionOK() || v !== listVersion) return
        if (!Array.isArray(res?.data?.data)) throw new Error('incomplete list')
        table.data = res.data.data
        listCostHint.value = res.data.cost_maintenance_hint || ''
        table.total = Number(res.data.total || 0)
    } catch {
        if (v === listVersion) { table.data = []; table.total = 0; listError.value = true }
    } finally { if (v === listVersion) table.loading = false }
}

function onListModeChange() {
    table.page = 1
    activeTab.value = ''
    search.finance_status = ''
    search.serial = ''
    search.catalog_product_id = ''
    loadList()
}


async function loadWarehouses() {
    const res: any = await lookup.read('warehouse-options', getErpWarehouseOptions, Array.isArray)
    if (!res) return
    warehouses.value = Array.isArray(res?.data) ? res.data : []
    applyDefaultWarehouse()
}

async function loadStaffOptions() {
    const res: any = await lookup.live('制单员', getErpStaffOptions, v => Array.isArray(v?.users))
    if (!res) return
    currentUid.value = Number(res?.data?.current_uid || 0)
    staffOptions.value = res?.data?.users || []
}

async function loadSpecMeta() {
    const res: any = await lookup.read('spec-options', getErpGoodsSpecMeta, (v: any) => v != null && typeof v === 'object')
    if (!res) return
    specMeta.value = res?.data || { groups: [], grades: [] }
}

async function loadRules() {
    const res: any = await lookup.live('开单规则', getErpConfig, v => v != null && typeof v === 'object' && !Array.isArray(v))
    if (!res) return
    Object.assign(titleRules, res?.data?.product_title || {})
    listingWorkspace.value = {
        ...listingWorkspace.value,
        ...(res?.data?.listing_workspace || {}),
        field_rules: {
            ...listingWorkspace.value.field_rules,
            ...(res?.data?.listing_workspace?.field_rules || {})
        }
    }
}

async function loadDicts() {
    const res: any = await lookup.read('dicts', getErpDicts, (v: any) => v != null && typeof v === 'object')
    if (!res) return
    erpDicts.value = res?.data || {}
}

function buildSearchParams() {
    const [start_at, end_at] = Array.isArray(search.dateRange) ? search.dateRange : []
    const device = listMode.value === 'device'
    const wholeOrder = device ? search.finance_status : activeTab.value
    return {
        ...search,
        device_pay_state: device ? activeTab.value : undefined,
        finance_status: wholeOrder === 'void' ? '' : wholeOrder,
        status: wholeOrder === 'void' ? 'void' : '',
        start_at: start_at || '',
        end_at: end_at || '',
        dateRange: undefined
    }
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    Object.assign(search, { serial: '', party_id: null, purchase_no: '', finance_status: '', status: '', warehouse_id: '', location_id: '', catalog_product_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
    searchPartyName.value = ''
    activeTab.value = ''
    handleSearch()
}

function onSearchWarehouseChange() {
    search.location_id = ''
}

function openCreate() {
    if (!lookup.sessionOK()) return
    partyResolving.value = false
    create.form = defaultForm()
    entryStep.value = 0; entryError.value = ''
    if (listMode.value === 'standard') { create.form.item_type_mode = 'standard'; create.form.items = [blankItem('standard')] }
    create.form.purchaser_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    create.visible = true
    loadWarehouses()
    loadStaffOptions()
    loadRules()
}

function applyDefaultWarehouse() {
    if (!create.visible || create.form.warehouse_id || !warehouses.value.length) return
    create.form.warehouse_id = Number(warehouses.value[0]?.id || 0)
    onWarehouseChange()
}

function onWarehouseChange() {
    const warehouse = currentWarehouse.value
    create.form.warehouse_name = warehouse?.warehouse_name || ''
    const firstLocation = warehouse?.locations?.[0]
    create.form.location_id = firstLocation?.id || 0
    create.form.location_name = firstLocation?.location_name || ''
    applyDefaultLocationToAll(false)
}

function onDefaultLocationChange() {
    const location = currentLocations.value.find((row: any) => Number(row.id) === Number(create.form.location_id))
    create.form.location_name = location?.location_name || ''
    applyDefaultLocationToAll(false)
}

function assignWarehouseLocation(target: any, payload: any) {
    target.warehouse_id = Number(payload?.warehouse_id || 0)
    target.warehouse_name = String(payload?.warehouse_name || '')
    target.location_id = Number(payload?.location_id || 0)
    target.location_name = String(payload?.location_name || '')
}

function onDefaultWarehouseLocationChange(payload: any) {
    assignWarehouseLocation(create.form, payload)
    applyDefaultLocationToAll(false)
}

function onItemWarehouseLocationChange(item: any, payload: any) {
    assignWarehouseLocation(item, payload)
}

function applyDefaultLocationToAll(overwrite = false) {
    const warehouse = currentWarehouse.value
    const location = currentLocations.value.find((row: any) => Number(row.id) === Number(create.form.location_id))
    if (!warehouse || !location) return
    create.form.items.forEach((item: any) => {
        if (!overwrite && item.warehouse_id && item.location_id) return
        item.warehouse_id = Number(warehouse.id)
        item.warehouse_name = warehouse.warehouse_name || ''
        item.location_id = Number(location.id)
        item.location_name = location.location_name || ''
    })
    if (overwrite) hsxFeedback.success('已应用到全部设备，仍可逐台修改')
}

function addItem() {
    const item = blankItem(create.form.item_type_mode)
    if (create.form.item_type_mode === 'standard') {
        create.form.items.push(item)
        return
    }
    const warehouse = currentWarehouse.value
    const location = currentLocations.value.find((row: any) => Number(row.id) === Number(create.form.location_id))
    if (warehouse && location) {
        item.warehouse_id = Number(warehouse.id)
        item.warehouse_name = warehouse.warehouse_name || ''
        item.location_id = Number(location.id)
        item.location_name = location.location_name || ''
    }
    create.form.items.push(item)
}

function setDeviceImeiRef(element: any, index: number) {
    if (element) deviceImeiInputs.value[index] = element
}

async function focusDeviceImei(index: number) {
    await nextTick()
    deviceImeiInputs.value[index]?.focus?.()
}

async function handleDeviceImeiEnter(index: number) {
    const current = create.form.items[index]
    const imei = String(current?.imei || '').trim()
    if (!imei) return
    const duplicateIndex = create.form.items.findIndex((item: any, itemIndex: number) =>
        itemIndex !== index && String(item?.imei || '').trim() === imei
    )
    if (duplicateIndex >= 0) {
        hsxFeedback.warning(`该串号已在第 ${duplicateIndex + 1} 行录入`)
        return focusDeviceImei(index)
    }
    if (index === create.form.items.length - 1) addItem()
    await focusDeviceImei(index + 1)
}

function removeItem(index: number) {
    if (create.form.items.length === 1) {
        hsxFeedback.warning(create.form.item_type_mode === 'device' ? '至少保留一台机器' : '至少保留一项商品')
        return
    }
    if (itemExtra.index === index) itemExtra.visible = false
    create.form.items.splice(index, 1)
    deviceImeiInputs.value.splice(index, 1)
}

function onPurchaseItemModeChange(type: string | number | boolean | undefined) {
    const nextType = String(type || 'device')
    create.form.items = [blankItem(nextType)]
    create.form.warehouse_id = 0
    create.form.location_id = 0
}

function standardLineTotal(row: any) {
    if (row?.line_total !== undefined && row?.line_total !== null && row?.line_total !== '') {
        return Math.round(Number(row.line_total || 0) * 100) / 100
    }
    return Math.round(Number(row?.quantity || 0) * Number(row?.unit_cost || 0) * 100) / 100
}

function standardUnitCost(row: any) {
    const quantity = Number(row?.quantity || 0)
    return quantity > 0 ? Math.round((standardLineTotal(row) / quantity) * 1000000) / 1000000 : 0
}

function standardUnitCostText(row: any) {
    return standardUnitCost(row).toFixed(6).replace(/0+$/, '').replace(/\.$/, '') || '0'
}

function openItemExtra(row: any, index: number) {
    hydrateItemSpecState(row)
    itemExtra.item = row
    itemExtra.index = index
    itemExtra.activeTab = itemExtraIncompleteTab(row) || (purchaseOneStop.value && hasPurchaseSalesFields.value ? 'sales' : 'base')
    itemExtra.visible = true
    if (!specMeta.value?.groups?.length && !specMeta.value?.grades?.length) loadSpecMeta()
}

function switchItemExtra(step: number) {
    const nextIndex = itemExtra.index + step
    const row = create.form.items[nextIndex]
    if (!row) return
    openItemExtra(row, nextIndex)
}

function finishItemExtra() {
    const incompleteTab = itemExtraIncompleteTab(itemExtra.item)
    if (incompleteTab) {
        itemExtra.activeTab = incompleteTab
        hsxFeedback.warning(`请先填写：${firstMissingField.value?.label || '入库资料'}`)
        return
    }
    if (itemExtra.index < create.form.items.length - 1) {
        hsxFeedback.success('当前设备资料已保留，已切换到下一台')
        switchItemExtra(1)
        return
    }
    itemExtra.visible = false
}

function itemWarehouse(item: any) {
    return warehouses.value.find((row: any) => Number(row.id) === Number(item?.warehouse_id)) || null
}

function itemLocations(item: any) {
    return itemWarehouse(item)?.locations || []
}

function onItemWarehouseChange(item: any) {
    const warehouse = itemWarehouse(item)
    const location = warehouse?.locations?.[0]
    item.warehouse_name = warehouse?.warehouse_name || ''
    item.location_id = Number(location?.id || 0)
    item.location_name = location?.location_name || ''
}

function onItemLocationChange(item: any) {
    const location = itemLocations(item).find((row: any) => Number(row.id) === Number(item?.location_id))
    item.location_name = location?.location_name || ''
}

function itemLocationLabel(item: any) {
    if (!item?.warehouse_id || !item?.location_id) return '待选择'
    return [item.warehouse_name, item.location_name].filter(Boolean).join(' / ') || '待选择'
}

function openGoodsMeta() {
    router.push('/hsx_erp/goods/meta')
}

function onItemCatalogChange(item: any, node: any) {
    const isProduct = node?.node_type === 'product'
    if (!isProduct && item.catalog_product_name && !item._model_manual) { item.model = ''; item._auto_model = '' }
    item.catalog_reference = node?.catalog_reference || null
    item.catalog_product_id = isProduct ? Number(node?.site_product_id || 0) : 0
    item.catalog_product_name = isProduct ? String(node?.product_name || node?.label || '') : ''
    item.category_path = String(node?.category_path || '')
    const categoryParts = item.category_path.split('/').map((value: string) => value.trim()).filter(Boolean)
    item.category_name = categoryParts[categoryParts.length - 1] || ''
    item.category_names = categoryParts
    if (isProduct && item.catalog_product_name) item.model = item.catalog_product_name
    item.selected_specs = {}
    item.selected_grade = null
    item.spec = ''
    item.spec_json = {}
    if (isProduct) {
        rebuildItemModel(item, true)
        hsxFeedback.success('已带入商品目录型号与品类快照')
    }
}

function normalizeOptions(list: any): any[] {
    if (!Array.isArray(list)) return []
    return list.map((option: any, index: number) => {
        const label = String(option?.label ?? option?.item_value ?? option?.grade_name ?? option?.name ?? option?.value ?? '').trim()
        return {
            ...option,
            id: option?.id ?? option?.item_id ?? option?.grade_id ?? index + 1,
            label,
            value: String(option?.value ?? option?.item_value ?? option?.grade_name ?? label).trim(),
        }
    }).filter(option => option.label && option.value)
}

function normalizeSpecGroups(groups: any[]): any[] {
    return (groups || []).map((group: any) => ({
        ...group,
        key: group.key || `spec_${group.id || group.source_id || group.label}`,
        items: normalizeOptions(group.items),
    })).filter((group: any) => group.items.length)
}

function hydrateItemSpecState(item: any) {
    if (!item) return
    if (!item.selected_specs) item.selected_specs = item.spec_json?.specs || {}
    if (!item.selected_grade) item.selected_grade = item.spec_json?.grade || null
    if (item.color === undefined) item.color = item.spec_json?.color || ''
    if (item.battery === undefined) item.battery = item.spec_json?.battery || undefined
    if (item.warranty === undefined) item.warranty = item.spec_json?.warranty || undefined
}

function selectedSpecValue(item: any, group: any) {
    return item?.selected_specs?.[group.key]?.value || ''
}

function onSpecChange(item: any, group: any, value: string) {
    if (!item) return
    const option = group.items.find((row: any) => row.value === value)
    item.selected_specs = { ...(item.selected_specs || {}) }
    if (!option) delete item.selected_specs[group.key]
    else item.selected_specs[group.key] = { label: option.label, value: option.value, group_key: group.key, group_label: group.label, title_part: !!group.title_part }
    rebuildItemSpec(item)
}

function onGradeChange(item: any, value: string) {
    if (!item) return
    const option = gradeOptions.value.find((row: any) => row.value === value)
    item.selected_grade = option ? { label: option.label, value: option.value } : null
    rebuildItemSpec(item)
}

function selectedSpecParts(item: any) {
    const selected = item?.selected_specs || {}
    return specGroups.value.map((group: any) => selected[group.key]?.value || '').filter(Boolean)
}

function selectedTitleSpecParts(item: any) {
    if (Number(titleRules.spec_in_title) !== 1) return []
    const selected = item?.selected_specs || {}
    return specGroups.value
        .filter((group: any) => !!group.title_part)
        .map((group: any) => selected[group.key]?.value || '')
        .filter(Boolean)
}

function uniqueParts(parts: any[]) {
    const seen = new Set<string>()
    return parts.map(part => String(part || '').trim()).filter(part => {
        if (!part || seen.has(part)) return false
        seen.add(part)
        return true
    })
}

function rebuildItemSpec(item: any) {
    if (!item) return
    const parts = selectedSpecParts(item)
    if (item.selected_grade?.value) parts.push(item.selected_grade.value)
    if (item.color) parts.push(item.color)
    if (item.battery !== undefined && item.battery !== null && item.battery !== '') parts.push(`电池${item.battery}%`)
    if (Number(item.warranty || 0) > 0) parts.push(`保修至${formatDate(item.warranty)}`)
    item.spec = uniqueParts(parts).join(' ')
    item.spec_json = {
        specs: item.selected_specs || {},
        grade: item.selected_grade || null,
        color: item.color || '',
        battery: item.battery || '',
        warranty: Number(item.warranty || 0),
        title_rule: { ...titleRules }
    }
    rebuildItemModel(item)
}

function categoryNames(item: any): string[] {
    if (Array.isArray(item?.category_names) && item.category_names.length) {
        return item.category_names.map((name: string) => String(name || '').trim()).filter(Boolean)
    }
    return String(item?.category_name || '').split(/[>\-/\\｜|,，]+/).map((name: string) => name.trim()).filter(Boolean)
}

function categoryTitleByRule(item: any) {
    const names = categoryNames(item)
    if (!names.length) return ''
    const mode = titleRules.category_mode || 'auto'
    if (mode === 'full') return names.join(' ')
    if (mode === 'level_1_2') return names.slice(0, 2).join(' ')
    if (mode === 'level_2_3') return names.slice(-2).join(' ')
    if (mode === 'level_3') return names[names.length - 1] || ''
    if (names.length >= 3) return names.slice(1, 3).join(' ')
    if (names.length === 2) return names.join(' ')
    return names[0] || ''
}

function buildItemModel(item: any) {
    // 目录叶子型号是设备名称的主数据，不能再被“品牌 + 系列”的展示规则覆盖。
    // 非目录录入仍沿用原有分类标题规则，兼容历史手工录入。
    const baseTitle = String(item?.catalog_product_name || '').trim() || categoryTitleByRule(item)
    const parts = [baseTitle, ...selectedTitleSpecParts(item)]
    if (Number(titleRules.grade_in_title) === 1 && item?.selected_grade?.value) parts.push(item.selected_grade.value)
    return uniqueParts(parts).join(titleRules.separator || ' ')
}

function rebuildItemModel(item: any, force = false) {
    const model = buildItemModel(item)
    if (!model) return
    if (force || !item.model || item.model === item._auto_model || !item._model_manual) {
        item.model = model
        item._auto_model = model
        item._model_manual = false
    }
}

function markManualModel(item: any) {
    item._model_manual = item.model !== item._auto_model
}

function itemCoreSummary(item: any) {
    return [
        item.imei ? `IMEI ${item.imei}` : '未填 IMEI',
        item.remark || ''
    ].filter(Boolean).join(' · ')
}

function requiredDeviceGaps(item: any): {label: string; tab: string}[] {
    if (!item) return []
    const missing: {label: string; tab: string}[] = []
    if (!String(item.imei || item.sn || '').trim()) missing.push({label:'IMEI 或 SN',tab:'base'})
    if (!String(item.model || '').trim()) missing.push({label:'设备名称 / 型号',tab:'base'})
    if (!(Number(item.purchase_cost) > 0)) missing.push({label:'采购成本',tab:'base'})
    if (!(Number(item.warehouse_id) > 0)) missing.push({label:'入库仓库',tab:'base'})
    if (!(Number(item.location_id) > 0)) missing.push({label:'入库库位',tab:'base'})
    if (purchaseOneStop.value) {
        for (const [key,label,tab] of [
            ['catalog_product_id','目录型号','base'],['spec','设备规格','goods'],['remark_public','对外说明','goods'],
            ['retail_price','销售定价','sales'],['image_urls','设备图片','sales'],['video_url','展示视频','sales'],
            ['quality_remark','质检备注','sales'],['remark_internal','对内备注','sales']
        ]) if (listingFieldRequired(key) && !listingFieldHasValue(item, key)) missing.push({label,tab})
    }
    return missing
}
const firstMissingField = computed(() => requiredDeviceGaps(itemExtra.item)[0])
function deviceCoreMissingCount(item: any) { return requiredDeviceGaps(item).length }
function deviceCoreReady(item: any) { return deviceCoreMissingCount(item) === 0 }
function itemExtraIncompleteTab(item: any) { return requiredDeviceGaps(item)[0]?.tab || '' }

function hasDeviceImage(item: any) {
    return Boolean(String(item?.image_urls || '').trim())
}

function listingFieldEnabled(field: string) {
    return Number(listingWorkspace.value?.field_rules?.[field]?.enabled ?? 1) === 1
}

function listingFieldRequired(field: string) {
    return listingFieldEnabled(field) && Number(listingWorkspace.value?.field_rules?.[field]?.required ?? 0) === 1
}

function hasCatalogProduct(item: any) {
    return Number(item?.catalog_product_id || 0) > 0 || Boolean(item?.catalog_reference?.product_id && item?.catalog_reference?.revision)
}

function listingFieldHasValue(item: any, field: string) {
    if (field === 'catalog_product_id') return hasCatalogProduct(item)
    if (field === 'retail_price') return Number(item?.retail_price || 0) > 0
    if (field === 'image_urls') return hasDeviceImage(item)
    return Boolean(String(item?.[field] || '').trim())
}

function purchaseItemPayload(item: any) {
    const payload = { ...item }
    const listingFields = ['spec', 'image_urls', 'video_url', 'retail_price', 'quality_remark', 'remark_public', 'remark_internal']
    listingFields.forEach(field => {
        if (!purchaseOneStop.value || !listingFieldEnabled(field)) {
            payload[field] = field === 'retail_price' ? 0 : ''
        }
    })
    payload.estimate_sale_price = Number(payload.retail_price || 0)
    if (!purchaseOneStop.value) {
        payload.spec_json = {}
        payload.selected_specs = {}
        payload.selected_grade = null
        payload.color = ''
        payload.battery = undefined
        payload.warranty = undefined
    }
    return payload
}

function formatDate(value: any) {
    const time = Number(value || 0)
    if (!time) return ''
    const date = new Date(time * 1000)
    const month = `${date.getMonth() + 1}`.padStart(2, '0')
    const day = `${date.getDate()}`.padStart(2, '0')
    return `${date.getFullYear()}-${month}-${day}`
}

const reviewLines = computed(() => create.form.items.map((row: any) => ({
    name: row.model || '未命名货品', identity: create.form.item_type_mode === 'standard' ? '数量 ' + quantityText(row.quantity) : (row.imei ? 'IMEI ' + row.imei : 'SN ' + row.sn),
    amount: money(create.form.item_type_mode === 'standard' ? standardLineTotal(row) : row.purchase_cost)
})))
function entryIssue(n: number): string {
    if (n === 0) {
        if (partyResolving.value) return '正在匹配往来主体，请稍候'
        if (!create.form.party_id && !create.form.party_name) return '请选择本单供货方'
    } else if (n === 1) {
        const isStandard = create.form.item_type_mode === 'standard'
        if (lookup.errors.value.includes('开单规则')) return '开单规则未能读取，请先重新读取资料。'
        if (!create.form.items.length) return '请先添加本次采购的货品'
        for (const [i, row] of create.form.items.entries()) {
            const label = isStandard ? `第 ${i+1} 项标品` : `第 ${i+1} 台设备`
            if (!row.model) return label + '还没有商品名称或型号'
            if (isStandard && (Number(row.quantity || 0) <= 0 || standardLineTotal(row) <= 0)) return label + '需要填写采购数量和总价'
            if (!isStandard && !row.imei && !row.sn) return label + '需要填写 IMEI 或 SN'
            if (!isStandard && Number(row.purchase_cost || 0) <= 0) return label + '需要填写采购成本'
            if (!row.warehouse_id || !row.location_id) return label + '需要选择入库仓库和库位'
            if (!isStandard && deviceCoreMissingCount(row) > 0) return label + '仍缺必填资料，请打开该行「一次完善」补齐'
        }
    }
    return ''
}
function chooseStep(target: number) {
    if (!lookup.sessionOK() || create.saving || target < 0 || target > 2) return
    entryError.value = ''
    for (let n = 0; n < target; n++) {
        const message = entryIssue(n)
        if (message) { entryStep.value = n; entryError.value = message; return }
    }
    entryStep.value = target
}

async function submitCreate() {
    if (!lookup.sessionOK() || create.saving || confirmingEntry) return
    for (const n of [0,1]) { const message = entryIssue(n); if (message) { entryStep.value = n; entryError.value = message; return } }
    const isStandard = create.form.item_type_mode === 'standard'
    create.form.purchaser_uid = create.form.purchaser_uid || currentUid.value || staffOptions.value[0]?.uid || 0
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.paid_amount || 0) <= 0) return hsxFeedback.warning('请填写本次付款')
        if (accountLoading.value || accountError.value || !accounts.value.some(a => Number(a.id) === Number(create.form.capital_account_id))) return hsxFeedback.warning('请选择付款账户')
        if (Number(create.form.paid_amount || 0) > createTotal.value) return hsxFeedback.warning('付款不能大于采购成本')
    }
    confirmingEntry = true
    const createConfirmed = await ElMessageBox.confirm(
        `确认向「${create.form.party_name || '所选供货商'}」采购 ${create.form.items.length} ${isStandard ? '项标品' : '台设备'}，采购总额 ${money(createTotal.value)}。提交后将生成${isStandard ? '数量库存与采购应付' : '设备资产和设备应付'}；${create.form.settle_mode === 'cash' ? `在所选账户登记已付 ${money(create.form.paid_amount)}，无需再次到财务确认。` : '本次按挂账处理，后续到应付款结算。'}`,
        '确认采购开单',
        { type: 'warning', confirmButtonText: '确认开单', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    confirmingEntry = false
    if (!createConfirmed || !lookup.sessionOK()) return
    create.saving = true
    try {
        await createErpPurchase({
            ...create.form,
            items: create.form.items.map((row: any) => row.item_type === 'standard'
                ? { ...row, unit_cost: standardUnitCost(row), purchase_cost: standardLineTotal(row) }
                : purchaseItemPayload(row)),
            warehouse_name: currentWarehouse.value?.warehouse_name || create.form.warehouse_name,
            location_name: currentLocations.value.find((row: any) => Number(row.id) === Number(create.form.location_id))?.location_name || create.form.location_name,
            settle_method: create.form.settle_mode === 'cash' ? '现结' : '挂账'
        })
        const remaining = Math.max(0, createTotal.value - Number(create.form.paid_amount || 0))
        hsxFeedback.success(create.form.settle_mode === 'cash'
            ? (remaining > 0.0001 ? `采购已现付，剩余 ${money(remaining)} 进入应付款` : '采购已入库，现付款已登记')
            : '采购单已生成，等待财务付款')
        listMode.value = create.form.item_type_mode
        create.visible = false
        loadList()
    } finally {
        create.saving = false
    }
}

function onPartyResolved(row: any) {
    if (row) entryError.value = ''
    create.form.party_name = row?.party_name || row?.name || ''
    create.form.m_no = row?.m_no || create.form.m_no || ''
}

function openDetail(row: any) {
    return loadDetail(row.purchase_order_id || row.id)
}

function adjustmentItemId(row: any) {
    // 列表的 id 是资产 ID；采购详情的 id 才是采购明细 ID。
    if (row && Object.prototype.hasOwnProperty.call(row, 'purchase_item_id')) return sourceId(row.purchase_item_id)
    return sourceId(row?.asset_id) ? sourceId(row?.id) : 0
}

function openAdjust(row: any) {
    if (!canAdjustSupplierPrice(row)) {
        hsxFeedback.warning(supplierAdjustBlockedReason(row))
        return
    }
    adjust.itemId = adjustmentItemId(row)
    adjustHint.value = (detail.visible && detail.data?.cost_maintenance_hint) || listCostHint.value
    adjust.item = row
    adjust.form = { type: 'deduct', amount: 0, remark: '' }
    adjust.visible = true
}

function canAdjustSupplierPrice(row: any) {
    return !!adjustmentItemId(row) && row?.status === 'in_stock'
        && !['review','void'].includes(row?.device_payment_hint?.state)
        && row?.order_status !== 'void'
        && Number(row?.paid_amount || 0) <= 0.0001
}

function supplierAdjustBlockedReason(row: any) {
    if (row?.status === 'returned') return '设备已完成采购退货，不能再调整供应商采购价'
    if (row?.status === 'sold') return '设备已经销售出库，不能再调整供应商采购价'
    if (row?.status !== 'in_stock') return '只有仍在库存中的设备可以调整供应商采购价'
    if (row?.order_status === 'void') return '采购单已作废，不能调整供应商采购价'
    if (!adjustmentItemId(row)) return '未找到本机的采购明细，请核对原采购单'
    if (['review','void'].includes(row?.device_payment_hint?.state)) return '本机无可确认的有效应付，请财务核对原单'
    if (Number(row?.paid_amount || 0) > 0.0001) return Object.prototype.hasOwnProperty.call(row, 'purchase_item_id')
        ? '本采购单已有付款或折账，请在采购单详情核对本机后处理'
        : '该设备已形成付款或折账，请走退款、补款或财务调账流程'
    return '当前设备不能调整供应商采购价'
}

/** 采购入库完成后，设备退出统一走采购退货；系统在退货页自动判断财务分流。 */
function canReturnPurchase(row: any) {
    return row.status === 'in_stock' && row.order_status === 'completed' && row.return_flow?.returnable !== false
}

function purchaseReturnActionLabel(_row: any) {
    return '采购退货'
}

function goReturn(row: any) {
    router.push({
        path: '/site/hsx_erp/purchase_return',
        query: { purchase_order_id: row.purchase_order_id, asset_id: row.id },
    })
}

async function submitAdjust() {
    if (!adjust.form.amount) return hsxFeedback.warning('请填写调整金额')
    if (adjustAfterCost.value <= 0) return hsxFeedback.warning('调整后成本必须大于0')
    if (!adjust.form.remark.trim()) return hsxFeedback.warning('请填写调整原因')
    const adjustConfirmed = await ElMessageBox.confirm(
        `确认对设备「${adjust.item?.model || adjust.item?.imei || '-'}」执行供应商调价 ${signedMoney(adjustSignedAmount.value)}，成本将由 ${money(adjustCurrentCost.value)} 变为 ${money(adjustAfterCost.value)}。该操作会同步修改采购本金和应付，并保留账务流水。`,
        '确认供应商调价',
        { type: 'warning', confirmButtonText: '确认调价并记账', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!adjustConfirmed || !lookup.sessionOK()) return
    adjust.saving = true
    try {
        await adjustErpPurchaseCost(adjust.itemId, {
            amount: adjustSignedAmount.value,
            remark: adjust.form.remark
        })
        if (adjustHint.value) hsxFeedback.warning('本站成本已调整。' + adjustHint.value)
        else hsxFeedback.success('成本已调整')
        adjust.visible = false
        if (detail.data?.id) await openDetail(detail.data)
        loadList()
    } finally {
        adjust.saving = false
    }
}

function rowPayState(row: any) {
    return deviceBillState(row)
}
function devicePayLabel(row: any) {
    switch (rowPayState(row)) {
        case 'pending': return '待付 ' + money(row.device_payment_hint.remaining)
        case 'partial': return '剩余待付 ' + money(row.device_payment_hint.remaining)
        case 'settled': return '本站已结清'
        case 'void': return '应付已作废'
        default: return '应付待核对'
    }
}
function deviceSettledText(row: any) {
    const state = rowPayState(row), paid = row.device_payment_hint?.paid
    if (state === 'void') return '—'
    if (state === 'review' || paid == null || paid === '' || !Number.isFinite(Number(paid)) || Number(paid) < 0) return '待核对'
    return money(paid)
}
function canViewPayable(row: any) {
    return ['pending', 'partial', 'settled'].includes(rowPayState(row)) && !!sourceId(row.device_payment_hint?.payable_id) && !!sourceId(row.purchase_order_id)
        && router.hasRoute('hsx_erp_payable') && ['hsx_erp_payable_info','hsx_erp_payable_party_items'].every(key => user.rules.includes(key))
}
function openDevicePayable(row: any) {
    if (!lookup.sessionOK() || !canViewPayable(row)) return
    if (detail.visible) detail.visible = false
    router.push({ name:'hsx_erp_payable', query:{ payable_id:String(row.device_payment_hint.payable_id), ...(rowPayState(row) === 'settled' ? { view:'settlement' } : {}) } })
}
function onDeviceExpand(row: any, expanded: any[]) {
    expandedDeviceKeys.value = expanded.some(item => item.id === row.id) ? [String(row.id)] : []
}
function handleRowCommand(command: string, row: any) {
    if (!lookup.sessionOK()) return
    if (command === 'facts') expandedDeviceKeys.value = expandedDeviceKeys.value.includes(String(row.id)) ? [] : [String(row.id)]
    if (command === 'adjust' && canAdjustSupplierPrice(row)) openAdjust(row)
    if (command === 'return' && canReturnPurchase(row)) goReturn(row)
}
function deviceRowFacts(row: any) {
    const fields = [
        ['采购成本', row.purchase_cost == null ? '—' : money(row.purchase_cost)],
        ['本站已结算', deviceSettledText(row)],
        ['业务来源', erpSourceLabel(row.origin_name, 'ERP采购')], ['本批设备', Number(row.order_asset_count) > 0 ? row.order_asset_count + ' 台' : ''],
        ['仓库 / 库位', [row.warehouse_name,row.location_name].filter(Boolean).join(' / ') || '未登记'],
        ['采购时间', row.purchase_at ? formatTime(row.purchase_at) : '未登记'], ['采购员', row.purchaser_name], ['质检员', row.inspector_name],
        ['供货商 M 号', row.m_no],
        ['整单状态', row.order_status === 'void' ? '采购单已撤销' : row.order_business_status_label], ['退货单号', row.return_no],
        ['销售价', Number(row.retail_price || row.estimate_sale_price) ? money(row.retail_price || row.estimate_sale_price) : ''],
        ['供应商调价', Number(row.adjust_cost) ? signedMoney(row.adjust_cost) : ''],
        ['整备支出（独立应付）', Number(row.refurbish_cost) ? money(row.refurbish_cost) : ''],
        ['当前总成本', row.total_cost != null && Number(row.total_cost) !== Number(row.purchase_cost) ? money(row.total_cost) : ''],
    ]
    return fields.filter(field => field[1] != null && field[1] !== '')
}
function financeStatusMeta(status: string) {
    if (status === 'review') return { label: '待核对', type: 'warning' as const }
    return dictMeta('purchase_finance_status', status, {
        pending: { label: '待付款', type: 'warning' },
        partial: { label: '部分付款', type: 'primary' },
        settled: { label: '已结清', type: 'success' },
        void: { label: '已撤销', type: 'info' },
    })
}

function assetStatusMeta(status: string) {
    return dictMeta('asset_status', status, {
        in_stock: { label: '在库', type: 'success' },
        sold: { label: '已售', type: 'primary' },
        returned: { label: '已退货', type: 'warning' },
        void: { label: '已作废', type: 'info' },
    })
}

function orderStatusMeta(status: string) {
    return dictMeta('purchase_order_status', status, {
        completed: { label: '已完成', type: 'success' },
        returned: { label: '已退货', type: 'warning' },
        void: { label: '已撤销', type: 'info' },
    })
}

function dictMeta(group: string, value: string, fallback: Record<string, any>) {
    const option = (erpDicts.value[group] || []).find((item: any) => item.value === value)
    return option ? { label: erpEnumLabel(option.label, {}, fallback[value]?.label || '状态待确认'), type: option.type || 'info' } : (fallback[value] || { label: '状态待确认', type: 'info' })
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function quantityText(value: any) {
    const number = Number(value || 0)
    return Number.isInteger(number) ? String(number) : number.toFixed(3).replace(/0+$/, '').replace(/\.$/, '')
}

function signedMoney(value: any) {
    const amount = Number(value || 0)
    return `${amount > 0 ? '+' : amount < 0 ? '-' : ''}¥${Math.abs(amount).toFixed(2)}`
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
    return Number(row?.purchase_order_id || row?.id || 0)
}

function batchTone(row: any) {
    return Math.abs(batchKey(row)) % 4
}

function isBatchFirst(index: number) {
    if (index <= 0) return true
    return batchKey(table.data[index]) !== batchKey(table.data[index - 1])
}

function purchaseRowClassName({ row, rowIndex }: { row: any; rowIndex: number }) {
    return [`erp-batch-tone-${batchTone(row)}`, isBatchFirst(rowIndex) ? 'erp-batch-start' : ''].filter(Boolean).join(' ')
}
</script>

<style scoped>
.trade-settlement-mode{grid-column:1 / -1}.trade-settlement-mode :deep(.el-select){max-width:240px}.trade-checkout{display:flex;flex-wrap:wrap;gap:16px 40px;background:var(--el-fill-color-light);padding:14px 18px;margin-bottom:14px}.trade-checkout small,.trade-checkout b{display:block}.trade-checkout small,.trade-entry-hint{color:var(--el-text-color-regular)}.trade-checkout b{font-size:16px;margin-top:4px}.trade-entry-error{color:var(--el-text-color-primary);border-left:3px solid var(--el-color-warning);padding:8px 12px;background:var(--el-color-warning-light-9);margin-bottom:12px}.trade-entry-hint{margin:0 0 14px;font-size:13px}

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
.section-title {
    margin: 18px 0 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 650;
}
.batch-location-panel {
    border: 1px solid var(--el-color-primary-light-9);
    border-radius: 8px;
    background: var(--el-color-primary-light-9);
    padding: 12px 14px 0;
}
.batch-location-panel__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
}
.device-purchase-table { --el-table-row-hover-bg-color:var(--el-fill-color-light); }
.device-purchase-table :deep(.purchase-expander .cell) {padding-inline:4px;white-space:nowrap}
.device-purchase-table :deep(.el-table__body td.el-table__cell) {padding-top:7px;padding-bottom:7px}
.device-purchase-table :deep(.el-table__expanded-cell.el-table__cell) {padding:0}
.device-purchase-table :deep(.el-table__body tr:hover > td.el-table__cell) {background:var(--el-fill-color-light)!important}
.purchase-price {font-variant-numeric:tabular-nums}
.purchase-record {min-height:155px}
.purchase-handoff {border:1px solid var(--el-border-color-lighter);border-radius:8px;margin-bottom:18px;padding:15px 18px;background:var(--el-fill-color-extra-light)}
.purchase-handoff__source,.purchase-handoff__title {display:flex;align-items:center;flex-wrap:wrap;gap:5px 12px}
.purchase-handoff__source {margin-bottom:10px;color:var(--el-text-color-regular);font-size:13px}
.purchase-handoff__source strong {font-weight:500;font-variant-numeric:tabular-nums;color:var(--el-text-color-primary);overflow-wrap:anywhere}
.purchase-handoff__source span {border-left:1px solid var(--el-border-color);padding-left:12px}
.purchase-handoff__title strong {font-size:18px;line-height:1.45;color:var(--el-text-color-primary)}
.purchase-handoff__title span {border-radius:4px;background:var(--el-fill-color);color:var(--el-text-color-regular);padding:1px 8px;font-size:12px}
.purchase-handoff--settled .purchase-handoff__title span {background:var(--el-color-success-light-9);color:var(--el-color-success-dark-2)}
.purchase-handoff--due .purchase-handoff__title span,.purchase-handoff--review .purchase-handoff__title span {background:var(--el-color-warning-light-9);color:var(--el-color-warning-dark-2)}
.purchase-handoff p {margin:5px 0 0;color:var(--el-text-color-regular);font-size:13px;line-height:1.55}
.purchase-devices {margin-bottom:20px}
.purchase-devices header {display:flex;justify-content:space-between;align-items:center;gap:16px;margin:0 0 12px}
.purchase-devices h3 {font-size:16px;font-weight:600;margin:0 0 2px;color:var(--el-text-color-primary)}
.purchase-devices h3 small {font-size:12px;font-weight:400;color:var(--el-text-color-regular);margin-left:4px}
.purchase-devices header span {color:var(--el-text-color-regular);font-size:13px}
.purchase-original summary {cursor:pointer;color:var(--el-color-primary);padding:10px 0;font-weight:500}
.purchase-stock {color:var(--el-text-color-regular)}
.purchase-stock::before {content:'';display:inline-block;vertical-align:2px;margin-right:6px;width:6px;height:6px;border-radius:50%;background:var(--el-text-color-secondary)}
.purchase-stock--ready::before {background:var(--el-color-success)}
.purchase-stock--attention,.purchase-exception {color:var(--el-text-color-primary);font-weight:600}
.purchase-exception {display:block;font-size:12px;line-height:18px;color:var(--el-text-color-regular)}
.purchase-row-actions {display:flex;align-items:center;gap:10px;white-space:nowrap}
.purchase-row-actions :deep(.el-button.is-link) {margin:0;padding-left:0;padding-right:0}
.purchase-row-actions :deep(.el-icon){margin-left:3px;font-size:11px}
.purchase-device-facts {display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin:0;gap:12px 22px;padding:15px 26px 15px 48px;background:var(--el-fill-color-lighter)}
.purchase-device-facts>div {min-width:0}.purchase-device-facts dt {font-size:12px;color:var(--el-text-color-regular)}.purchase-device-facts dd {margin:2px 0 0;overflow-wrap:anywhere;color:var(--el-text-color-primary)}
.purchase-device-facts :deep(.el-button.is-link) {padding:0;min-height:20px;white-space:normal;text-align:left}
.purchase-menu-reason {display:block;max-width:250px;white-space:normal;font-size:12px;line-height:17px;color:var(--el-text-color-regular)}
:deep(.el-table__body tr.erp-batch-tone-0 > td.el-table__cell) { background: var(--el-bg-color); }
:deep(.el-table__body tr.erp-batch-tone-1 > td.el-table__cell) { background: var(--el-fill-color-lighter); }
:deep(.el-table__body tr.erp-batch-tone-2 > td.el-table__cell) { background: var(--el-bg-color); }
:deep(.el-table__body tr.erp-batch-tone-3 > td.el-table__cell) { background: var(--el-fill-color-lighter); }
:deep(.el-table__body tr.erp-batch-start > td.el-table__cell) { border-top: 2px solid var(--el-border-color); }
:deep(.el-table__body tr:hover > td.el-table__cell) { background: var(--el-color-primary-light-9) !important; }
.create-purchase-dialog :deep(.el-dialog__body) {
    padding-top: 10px;
}
.create-purchase-dialog :deep(.el-dialog) {
    max-width: 1440px;
}
.create-purchase-form {
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.purchase-device-section {
    display: flex;
    flex-direction: column;
    flex: none;
    min-height: 0;
}
.standard-entry {
    min-width: 0;
    margin-top: 12px;
}
.standard-entry__tip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border: 1px solid var(--el-border-color);
    border-bottom: 0;
    border-radius: 8px 8px 0 0;
    background: var(--el-fill-color-light);
    padding: 9px 12px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
}
.standard-entry-table {
    width: 100%;
}
.standard-entry-table :deep(.el-table__header th.el-table__cell) {
    background: var(--el-fill-color-light);
    color: var(--el-text-color-regular);
    font-size: 12px;
    font-weight: 650;
}
.standard-entry-table :deep(.el-table__cell) {
    padding: 7px 0;
}
.standard-entry-table :deep(.el-input),
.standard-entry-table :deep(.el-input-number),
.standard-entry-table :deep(.el-select) {
    width: 100%;
}
.standard-entry-table :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px var(--el-border-color) inset;
}
.standard-entry-table :deep(.el-input__wrapper:hover),
.standard-entry-table :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px var(--el-color-primary) inset;
}
.standard-required::after {
    margin-left: 3px;
    color: var(--el-color-danger);
    content: '*';
}
.standard-entry__subtotal {
    color: var(--el-text-color-primary);
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}
.standard-entry__unit-price {
    color: var(--el-text-color-secondary);
    font-variant-numeric: tabular-nums;
}
.standard-entry__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid var(--el-border-color);
    border-top: 0;
    border-radius: 0 0 8px 8px;
    background: var(--el-bg-color-overlay);
    padding: 10px 12px;
}
.standard-entry__footer > div {
    display: flex;
    align-items: baseline;
    gap: 12px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}
.standard-entry__footer strong {
    color: var(--el-text-color-primary);
    font-size: 20px;
    font-variant-numeric: tabular-nums;
}
.device-entry {
    min-width: 0;
    margin-top: 12px;
}
.device-entry__toolbar,
.device-entry__default,
.device-entry__footer {
    display: flex;
    align-items: center;
}
.device-entry__toolbar {
    justify-content: space-between;
    gap: 16px;
    border: 1px solid var(--el-border-color);
    border-bottom: 0;
    border-radius: 10px 10px 0 0;
    background: linear-gradient(90deg, var(--el-color-primary-light-9) 0%, var(--el-fill-color-light) 100%);
    padding: 10px 12px;
}
.device-entry__default {
    width: min(560px, 75%);
    gap: 10px;
}
.device-entry__default-label {
    flex: 0 0 auto;
    color: var(--el-text-color-regular);
    font-size: 13px;
    font-weight: 650;
}
.device-entry__count {
    flex: 0 0 auto;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}
.device-entry__count strong {
    color: var(--el-color-primary);
    font-size: 16px;
}
.device-entry-table {
    width: 100%;
}
.device-entry-table :deep(.el-table__header th.el-table__cell) {
    height: 42px;
    background: var(--el-fill-color-light);
    color: var(--el-text-color-regular);
    font-size: 12px;
    font-weight: 650;
}
.device-entry-table :deep(.el-table__cell) {
    padding: 7px 0;
}
.device-entry-table :deep(.el-input),
.device-entry-table :deep(.el-input-number),
.device-entry-table :deep(.el-cascader) {
    width: 100%;
}
.device-entry-table :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px var(--el-border-color) inset;
}
.device-entry-table :deep(.el-input__wrapper:hover),
.device-entry-table :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px var(--el-color-primary) inset;
}
.device-entry__model {
    display: flex;
    width: 100%;
    min-width: 0;
    flex-direction: column;
    border: 0;
    background: transparent;
    padding: 1px 0;
    color: var(--el-text-color-primary);
    text-align: left;
    cursor: pointer;
}
.device-entry__model > span {
    overflow: hidden;
    font-size: 13px;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.device-entry__model > span.is-placeholder {
    color: var(--el-color-primary);
}
.device-entry__model > small {
    overflow: hidden;
    margin-top: 3px;
    color: var(--el-text-color-secondary);
    font-size: 11px;
    font-weight: 400;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.device-entry__model:hover > span {
    color: var(--el-color-primary);
}
.device-entry__progress {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.device-entry__progress span {
    border-radius: 999px;
    background: var(--el-fill-color-light);
    padding: 3px 7px;
    color: var(--el-text-color-secondary);
    font-size: 11px;
    line-height: 1;
}
.device-entry__progress span.is-done {
    background: var(--el-color-success-light-9);
    color: var(--el-color-success);
}
.device-entry__footer {
    justify-content: space-between;
    border: 1px solid var(--el-border-color);
    border-top: 0;
    border-radius: 0 0 10px 10px;
    background: var(--el-bg-color-overlay);
    padding: 9px 12px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
}
.purchase-device-scroll {
    flex: 1;
    min-height: 0;
    margin-top: 12px;
    overflow-y: auto;
    padding-right: 6px;
}
.purchase-device-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.purchase-device-card {
    border: 1px solid var(--el-border-color);
    border-radius: 10px;
    background: var(--el-bg-color-overlay);
    padding: 0;
    overflow: hidden;
}
.purchase-device-card__head,
.purchase-device-card__footer {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.purchase-device-card__head {
    border-bottom: 1px solid var(--el-border-color-lighter);
    background: var(--el-fill-color-light);
    padding: 12px 14px;
}
.purchase-device-card__identity {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 10px;
}
.purchase-device-card__sequence {
    display: inline-flex;
    width: 32px;
    height: 32px;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-size: 12px;
    font-weight: 700;
}
.purchase-device-card__index {
    overflow: hidden;
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.purchase-device-card__hint {
    margin-top: 2px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
}
.purchase-device-card__editor {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 14px;
}
.purchase-device-field {
    min-width: 0;
}
.purchase-device-field__label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
    color: var(--el-text-color-regular);
    font-size: 12px;
    font-weight: 600;
}
.purchase-device-field__label em {
    color: var(--el-color-warning);
    font-size: 11px;
    font-style: normal;
    font-weight: 400;
}
.purchase-device-field :deep(.el-input),
.purchase-device-field :deep(.el-input-number),
.purchase-device-field :deep(.el-select) {
    width: 100%;
}
.purchase-device-card__footer {
    align-items: center;
    border-top: 1px solid var(--el-border-color-lighter);
    padding: 10px 14px;
}
.purchase-device-profile {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.purchase-device-profile span {
    border-radius: 999px;
    background: var(--el-fill-color-light);
    padding: 3px 8px;
    color: var(--el-text-color-secondary);
    font-size: 11px;
}
.purchase-device-profile span.is-done {
    background: var(--el-color-success-light-9);
    color: var(--el-color-success);
}
.purchase-device-actions {
    display: flex;
    flex-shrink: 0;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
}
.purchase-device-actions :deep(.el-button + .el-button) {
    margin-left: 0;
}
.item-extra-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
    border: 1px solid var(--el-color-primary-light-9);
    border-radius: 10px;
    background: linear-gradient(135deg, var(--el-color-primary-light-9) 0%, var(--el-fill-color-light) 72%);
    padding: 16px 18px;
}
.item-extra-drawer-title {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.item-extra-title {
    color: var(--el-text-color-primary);
    font-size: 16px;
    font-weight: 650;
}
.item-extra-sub {
    margin-top: 6px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}
.item-extra-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 6px;
    margin-top: 10px;
}
.item-extra-progress {
    display: flex;
    flex-shrink: 0;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    color: var(--el-text-color-secondary);
    font-size: 11px;
}
.item-extra-layout {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.item-extra-section {
    border: 1px solid var(--el-border-color);
    border-radius: 10px;
    background: var(--el-bg-color-overlay);
    padding: 18px;
}
.item-extra-section--primary {
    border-color: var(--el-color-primary-light-5);
    box-shadow: 0 4px 14px rgb(37 99 235 / 6%);
}
.drawer-mobile-form :deep(.el-form-item) {
    margin-bottom: 14px;
}
.drawer-mobile-form :deep(.el-form-item__label) {
    color: var(--el-text-color-regular);
    font-weight: 600;
    line-height: 22px;
}
.purchase-item-drawer :deep(.el-drawer__body) {
    background: var(--el-fill-color-light);
    padding-top: 14px;
}
.purchase-item-drawer :deep(.el-drawer__footer) {
    border-top: 1px solid var(--el-border-color-lighter);
    background: var(--el-bg-color-overlay);
}
.item-extra-tabs :deep(.el-tabs__header) {
    margin: 0 0 14px;
    border-radius: 9px;
    background: var(--el-border-color-lighter);
    padding: 4px;
}
.item-extra-tabs :deep(.el-tabs__nav-wrap::after),
.item-extra-tabs :deep(.el-tabs__active-bar) {
    display: none;
}
.item-extra-tabs :deep(.el-tabs__item) {
    height: 38px;
    border-radius: 7px;
    color: var(--el-text-color-secondary);
    font-weight: 600;
}
.item-extra-tabs :deep(.el-tabs__item.is-active) {
    background: var(--el-bg-color-overlay);
    color: var(--el-color-primary);
    box-shadow: 0 1px 4px rgb(15 23 42 / 8%);
}
.item-extra-footer {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.item-extra-footer :deep(.el-button + .el-button) {
    margin-left: 8px;
}
.item-extra-section__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
}
.item-extra-section__title {
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 650;
}
.item-extra-section__desc {
    margin-top: 4px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
}
.spec-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.spec-field {
    min-width: 0;
}
.spec-label {
    margin-bottom: 6px;
    color: var(--el-text-color-regular);
    font-size: 13px;
    font-weight: 600;
}
.erp-image-upload {
    width: 100%;
}
.erp-image-upload__tips {
    margin-top: 6px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 18px;
}
@media (max-width: 768px) {
    .batch-location-panel__head {
        flex-direction: column;
    }
    .item-extra-head,
    .item-extra-section__head {
        flex-direction: column;
    }
    .item-extra-tags {
        justify-content: flex-start;
        max-width: none;
    }
    .item-extra-footer {
        align-items: stretch;
        flex-direction: column;
    }
    .item-extra-footer > div {
        display: flex;
    }
    .item-extra-footer :deep(.el-button) {
        flex: 1;
    }
    .spec-grid {
        grid-template-columns: 1fr;
    }
    .purchase-device-grid {
        grid-template-columns: 1fr;
    }
    .purchase-device-card__head,
    .purchase-device-card__footer {
        flex-direction: column;
        align-items: stretch;
    }
    .purchase-device-actions {
        justify-content: flex-end;
    }
}
</style>
