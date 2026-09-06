<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">采购管理</div>
                    <div class="mt-1 text-sm text-gray-500">{{ listMode === 'device' ? '一机一码管理二手机采购、成本、位置和账目状态。' : '按数量管理壳、膜、配件和批量新机的采购入库。' }}</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate">采购开单</el-button>
                </div>
            </div>

            <ErpRoleFocus :items="purchaseRoleFocus" />

            <div class="mt-5 flex items-center justify-between rounded-lg bg-slate-50 p-2">
                <el-radio-group v-model="listMode" @change="onListModeChange">
                    <el-radio-button label="device">设备采购</el-radio-button>
                    <el-radio-button label="standard">标品采购</el-radio-button>
                </el-radio-group>
                <div class="pr-2 text-xs text-slate-400">{{ listMode === 'device' ? '二手机 · 独立串号资产' : '壳膜配件 · 数量库存' }}</div>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm font-medium text-gray-700">本页有效采购汇总</div>
                <div class="text-xs text-gray-400">已退货、已作废货品不计入</div>
            </div>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">{{ listMode === 'device' ? '采购台数' : '采购数量' }}</div>
                    <div class="summary-value">{{ quantityText(summary.count) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">有效采购本金</div>
                    <div class="summary-value">{{ money(summary.totalCost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">已结采购款</div>
                    <div class="summary-value text-green-600">{{ money(summary.paid) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">待结采购款</div>
                    <div class="summary-value text-orange-600">{{ money(summary.payable) }}</div>
                </div>
            </div>

            <!-- 状态快筛 Tab -->
            <el-tabs v-model="activeTab" class="mt-4 erp-status-tabs" @tab-change="onTabChange">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待付款" name="pending" />
                <el-tab-pane label="部分付款" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
                <el-tab-pane label="已撤销" name="void" />
            </el-tabs>

            <el-form :inline="true" class="mt-2" @submit.prevent>
                <el-form-item v-if="listMode === 'device'" label="IMEI">
                    <el-input v-model.trim="search.imei" clearable class="!w-[190px]" placeholder="输入 IMEI 查询" @keyup.enter="handleSearch" />
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
                    <el-input v-model.trim="search.purchase_no" clearable class="!w-[230px]" placeholder="输入采购单号" @keyup.enter="handleSearch" />
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
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.max_amount" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table v-if="listMode === 'device'" :data="table.data" v-loading="table.loading" size="large" :row-class-name="purchaseRowClassName">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <ErpDeviceIdentity :model="row.model" :spec="row.spec" :imei="row.imei" :sn="row.sn" :asset-no="row.asset_no" />
                    </template>
                </el-table-column>
                <el-table-column label="供货商" min-width="160">
                    <template #default="{ row }">
                        <div>{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">M号：{{ row.m_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购成本" min-width="180" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ money(row.purchase_cost) }}</div>
                        <div v-if="Number(row.adjust_cost)" class="mt-1 text-xs" :class="Number(row.adjust_cost) > 0 ? 'text-orange-500' : 'text-green-600'">供应商调价 {{ signedMoney(row.adjust_cost) }}</div>
                        <div v-else class="mt-1 text-xs text-gray-400">采购本金</div>
                        <div v-if="Number(row.refurbish_cost)" class="mt-1 text-xs text-orange-500">整备支出 +{{ money(row.refurbish_cost) }}（独立应付）</div>
                        <div v-if="Math.abs(Number(row.total_cost || 0) - Number(row.purchase_cost || 0)) > 0.0001" class="mt-1 text-xs text-gray-400">当前总成本 {{ money(row.total_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="160">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="采购批次" min-width="230">
                    <template #default="{ row, $index }">
                        <div class="flex items-center gap-2">
                            <span class="batch-dot" :class="`batch-dot--${batchTone(row)}`"></span>
                            <span class="font-medium">{{ row.purchase_no || '-' }}</span>
                        </div>
                        <div v-if="isBatchFirst($index)" class="mt-1 text-xs font-medium text-blue-600">本页同批 {{ batchPageSize(row) }} 台</div>
                        <div class="mt-1 text-xs text-slate-500">来源：{{ erpSourceLabel(row.origin_name, 'ERP采购') }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.purchase_at) }}</div>
                        <div class="batch-staff-line">
                            <span>采购 {{ row.purchaser_name || '-' }}</span>
                            <span v-if="row.inspector_name">质检 {{ row.inspector_name }}</span>
                        </div>
                        <div v-if="Number(row.retail_price || row.estimate_sale_price)" class="mt-1 text-xs text-gray-400">销售价格：{{ money(row.retail_price || row.estimate_sale_price) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="当前状态" min-width="175">
                    <template #default="{ row }">
                        <div class="purchase-status-stack">
                            <el-tag :type="assetStatusMeta(row.status).type" effect="plain">{{ assetStatusMeta(row.status).label }}</el-tag>
                            <span class="purchase-status-stack__business">订单 · {{ row.order_business_status_label || orderStatusMeta(row.order_status).label }}</span>
                            <span class="purchase-status-stack__finance">采购款 · {{ financeStatusMeta(row.finance_status).label }}</span>
                            <span v-if="row.order_status === 'void'" class="purchase-status-stack__exception">采购单已撤销</span>
                            <span v-else-if="row.status === 'returned' && row.return_no" class="purchase-status-stack__source">{{ row.return_no }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="220" align="center">
                    <template #default="{ row, $index }">
                        <el-button type="primary" link @click="openDetail(row)">采购单</el-button>
                        <el-button v-if="canAdjustSupplierPrice(row)" type="primary" link @click="openAdjust(row)">供应商调价</el-button>
                        <el-tooltip v-else :content="supplierAdjustBlockedReason(row)" placement="top">
                            <span class="supplier-adjust-disabled-wrap"><el-button type="primary" link disabled>供应商调价</el-button></span>
                        </el-tooltip>
                        <el-button v-if="canReturnPurchase(row)" class="purchase-return-action" type="warning" link @click="goReturn(row)">{{ purchaseReturnActionLabel(row) }}</el-button>
                        <el-tooltip v-else-if="row.status === 'in_stock' && row.return_flow?.returnable === false" :content="row.return_flow?.block_reason" placement="top">
                            <span class="purchase-return-disabled-wrap"><el-button class="purchase-return-action" type="warning" link disabled>{{ purchaseReturnActionLabel(row) }}</el-button></span>
                        </el-tooltip>
                    </template>
                </el-table-column>
            </el-table>
            <el-table v-else :data="table.data" v-loading="table.loading" size="large" :row-class-name="purchaseRowClassName">
                <el-table-column label="标品" min-width="250">
                    <template #default="{ row }">
                        <div class="font-medium text-slate-900">{{ row.model || '-' }}</div>
                        <div v-if="row.spec" class="mt-1 text-xs text-slate-500">{{ row.spec }}</div>
                        <div class="mt-1 text-xs text-slate-400">编码：{{ row.product_code || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购数量" min-width="130" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-slate-900">{{ quantityText(row.quantity) }} {{ row.unit || '件' }}</div>
                        <div class="mt-1 text-xs text-slate-400">单价 {{ money(row.unit_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购金额" min-width="140" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-slate-900">{{ money(row.purchase_cost) }}</div>
                        <div class="mt-1 text-xs" :class="Number(row.asset_unpaid_amount || 0) > 0 ? 'text-orange-500' : 'text-green-600'">
                            {{ Number(row.asset_unpaid_amount || 0) > 0 ? `待付 ${money(row.asset_unpaid_amount)}` : '已结清' }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="库存位置" min-width="190">
                    <template #default="{ row }">
                        <div>{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</div>
                        <div class="mt-1 text-xs text-blue-600">当前库存 {{ quantityText(row.current_stock) }} {{ row.unit || '件' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="供货商 / 批次" min-width="230">
                    <template #default="{ row }">
                        <div class="font-medium text-slate-900">{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ row.purchase_no || '-' }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ formatTime(row.purchase_at) }} · {{ row.purchaser_name || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" min-width="150">
                    <template #default="{ row }">
                        <el-tag :type="row.order_status === 'void' ? 'info' : 'success'" effect="plain">
                            {{ row.order_status === 'void' ? '已撤销' : '已入库' }}
                        </el-tag>
                        <div class="mt-2 text-xs text-slate-500">采购款 · {{ financeStatusMeta(row.finance_status).label }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="100" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">采购单</el-button>
                    </template>
                </el-table-column>
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

        <el-dialog
            v-model="create.visible"
            title="采购开单"
            width="94vw"
            destroy-on-close
            class="create-purchase-dialog"
        >
            <el-form label-width="96px" class="create-purchase-form">
                <div class="section-title">1. 用户</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="采购用户" required>
                        <counterparty-select v-model="create.form.party_id" role-type="supplier" placeholder="搜索或新建采购用户" @resolved="onPartyResolved" />
                    </el-form-item>
                    <el-form-item label="M号">
                        <el-input v-model.trim="create.form.m_no" placeholder="客户M号/业务编号" />
                    </el-form-item>
                </div>

                <div class="section-title">2. 采购货品</div>
                <el-form-item label="入库方式" required>
                    <el-radio-group v-model="create.form.item_type_mode" @change="onPurchaseItemModeChange">
                        <el-radio-button value="device">二手机 / 一机一码</el-radio-button>
                        <el-radio-button value="standard">标品 / 数量入库</el-radio-button>
                    </el-radio-group>
                    <span class="ml-3 text-xs text-gray-400">
                        {{ create.form.item_type_mode === 'device' ? '每台设备建立独立资产与串号追踪' : '适合壳、膜、配件和批量新机' }}
                    </span>
                </el-form-item>
                <!-- <div class="batch-location-panel">
                    <div class="batch-location-panel__head">
                        <div>
                            <div class="font-medium text-gray-900">批量默认位置</div>
                            <div class="mt-1 text-xs text-gray-500">只用于快速填充；每台设备最终保存自己的仓库和库位，可在“编辑设备”中单独修改。</div>
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
                    <el-alert
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
                            height="390"
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
                                    <div class="font-medium text-slate-800">{{ row.model || '待选择' }}</div>
                                    <div class="mt-1 text-xs text-slate-400">{{ [row.spec, row.product_code].filter(Boolean).join(' · ') || '选择已有商品，找不到可创建新品' }}</div>
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
                    <div v-if="create.form.item_type_mode === 'device'" class="mt-3 text-right text-sm text-gray-500">本单采购成本合计：<span class="font-semibold text-gray-800">{{ money(createTotal) }}</span></div>
                </div>

                <div class="section-title">3. 账目</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <el-form-item label="结算方式">
                        <el-select v-model="create.form.settle_mode" class="w-full">
                            <el-option label="挂账，稍后付款" value="credit" />
                            <el-option label="现结，本次付款" value="cash" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="本次付款" required>
                        <el-input-number v-model="create.form.paid_amount" :min="0" :precision="2" :controls="false" class="!w-full" />
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="付款账户" required>
                        <el-select v-model="create.form.capital_account_id" class="w-full" placeholder="选择账户">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="付款凭证">
                        <ErpFinanceVoucherUpload v-model="create.form.voucher_urls" />
                    </el-form-item>
                </div>
                <el-form-item class="mt-4" label="备注">
                    <el-input v-model.trim="create.form.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="create.visible = false">取消</el-button>
                <el-button type="primary" :loading="create.saving" @click="submitCreate">确认开单</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="itemExtra.visible" size="720px" direction="rtl" append-to-body class="purchase-item-drawer">
            <template #header>
                <div class="item-extra-drawer-title">
                    <div>
                        <div class="item-extra-title">编辑设备 {{ itemExtra.index + 1 }}</div>
                        <div class="item-extra-sub">{{ purchaseOneStop ? '采购事实与销售资料在一个入口完成' : '当前岗位只录采购事实，销售资料将按团队分工自动流转' }}</div>
                    </div>
                    <el-tag v-if="itemExtra.item" :type="deviceCoreReady(itemExtra.item) ? 'success' : 'warning'" effect="light">
                        {{ deviceCoreReady(itemExtra.item) ? (purchaseOneStop ? '本次资料已完整' : '采购事实已完整') : `待补 ${deviceCoreMissingCount(itemExtra.item)} 项` }}
                    </el-tag>
                </div>
            </template>
            <div v-if="itemExtra.item" class="item-extra-head">
                <div class="min-w-0 flex-1">
                    <div class="item-extra-title">{{ itemExtra.item.model || `第 ${itemExtra.index + 1} 台设备` }}</div>
                    <div class="item-extra-sub">{{ compactItemSubTitle(itemExtra.item) }}</div>
                    <div class="item-extra-tags">
                        <el-tag v-if="itemExtra.item.category_name" effect="plain">{{ itemExtra.item.category_name }}</el-tag>
                        <el-tag v-if="itemExtra.item.spec" type="success" effect="plain">{{ itemExtra.item.spec }}</el-tag>
                        <el-tag v-if="itemExtra.item.warehouse_name" type="info" effect="plain">
                            {{ itemExtra.item.warehouse_name }} / {{ itemExtra.item.location_name || '待选库位' }}
                        </el-tag>
                    </div>
                </div>
                <div class="item-extra-progress">
                    <el-progress type="circle" :percentage="deviceMaterialCompletion(itemExtra.item)" :width="62" :stroke-width="6" />
                    <span>资料完成度</span>
                </div>
            </div>

            <el-tabs v-if="itemExtra.item" v-model="itemExtra.activeTab" class="item-extra-tabs" stretch>
                <el-tab-pane label="① 基础归档" name="base">
                    <div class="item-extra-layout">
                        <section class="item-extra-section">
                            <div class="item-extra-section__head">
                                <div>
                                    <div class="item-extra-section__title">设备与采购事实</div>
                                    <div class="item-extra-section__desc">串号、采购成本和备注，属于本次采购的核心事实。</div>
                                </div>
                                <el-tag type="warning" effect="plain">必填</el-tag>
                            </div>
                            <el-form label-position="top" class="drawer-mobile-form">
                                <el-form-item label="商品分类 / 目录型号">
                                    <ErpCatalogProductSelect
                                        v-model="itemExtra.item.catalog_product_id"
                                        :category-path="itemExtra.item.category_path"
                                        placeholder="先选分类，可继续选择品牌、系列和型号"
                                        @change="node => onItemCatalogChange(itemExtra.item, node)"
                                    />
                                    <div class="item-extra-section__desc">
                                        分类用于缩小型号范围；选中标准型号会自动带入设备名称，团队模式也会保留这份归档信息。
                                    </div>
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
        </el-drawer>

        <el-drawer v-model="detail.visible" title="采购单详情" size="76%" destroy-on-close>
            <div v-loading="detail.loading">
                <el-descriptions v-if="detail.data" :column="4" border>
                    <el-descriptions-item label="采购单号">{{ detail.data.purchase_no }}</el-descriptions-item>
                    <el-descriptions-item label="采购用户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="M号">{{ detail.data.m_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="业务来源">{{ erpSourceLabel(detail.data.origin_name, 'ERP采购') }}</el-descriptions-item>
                    <el-descriptions-item label="原业务单号">{{ detail.data.origin_no || detail.data.purchase_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="业务状态">{{ detail.data.business_status_label || orderStatusMeta(detail.data.status).label }}</el-descriptions-item>
                    <el-descriptions-item label="付款状态">
                        <el-tag :type="financeStatusMeta(detail.data.finance_status).type">{{ financeStatusMeta(detail.data.finance_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="原采购金额">{{ money(detail.data.original_total_cost ?? detail.data.total_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="退货冲减">{{ money(Math.max(0, Number(detail.data.original_total_cost || detail.data.total_cost || 0) - Number(detail.data.effective_purchase_amount || 0))) }}</el-descriptions-item>
                    <el-descriptions-item label="有效采购本金">{{ money(detail.data.effective_purchase_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="已付款">{{ money(detail.data.paid_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="剩余应付">{{ money(detail.data.payable_amount) }}</el-descriptions-item>
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
                            <div class="mt-1 text-xs text-slate-400">{{ money(row.unit_cost) }} / {{ row.unit || '件' }}</div>
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
                            <div v-if="Number(row.adjust_cost)" class="text-xs text-gray-500">调整 {{ money(row.adjust_cost) }}</div>
                            <div v-if="Number(row.refurbish_cost)" class="text-xs text-orange-500">整备 {{ money(row.refurbish_cost) }}（另付）</div>
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
            </div>
        </el-drawer>

        <el-dialog v-model="adjust.visible" title="供应商采购价调整" width="620px">
            <div v-if="adjust.item" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>设备：<span class="font-medium text-gray-900">{{ adjust.item.model || '-' }}</span></div>
                <div class="mt-1">{{ adjust.item.spec || '-' }} · IMEI {{ adjust.item.imei || '-' }}</div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">当前成本</div>
                    <div class="mt-1 font-medium text-gray-900">{{ money(adjustCurrentCost) }}</div>
                </div>
                <div :class="adjust.form.type === 'deduct' ? 'bg-red-50' : 'bg-blue-50'" class="rounded px-3 py-2">
                    <div class="text-xs text-gray-500">本次调整</div>
                    <div class="mt-1 font-medium" :class="adjust.form.type === 'deduct' ? 'text-red-600' : 'text-blue-600'">{{ money(adjustSignedAmount) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">调整后成本</div>
                    <div class="mt-1 font-medium text-gray-900">{{ money(adjustAfterCost) }}</div>
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
                    <div class="mt-1 text-xs text-gray-500">只填正数；扣款会减少成本，补款会增加成本。</div>
                </el-form-item>
                <el-form-item label="影响说明">
                    <el-alert
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
                <el-button @click="adjust.visible = false">取消</el-button>
                <el-button type="primary" :loading="adjust.saving" @click="submitAdjust">确认调整并记账</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { erpEnumLabel, erpSourceLabel } from '@/addon/hsx_erp/utils/display'
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { adjustErpPurchaseCost, createErpPurchase, getErpDicts, getErpGoodsSpecMeta, getErpPurchaseInfo, getErpPurchaseList, getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import { getErpConfig } from '@/addon/hsx_erp/api/config'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import ErpDeviceIdentity from '@/addon/hsx_erp/components/ErpDeviceIdentity.vue'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
import ErpQuantityProductSelect from '@/addon/hsx_erp/components/ErpQuantityProductSelect.vue'
import ErpOverflowText from '@/addon/hsx_erp/components/ErpOverflowText.vue'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import ErpWarehouseLocationCascader from '@/addon/hsx_erp/components/ErpWarehouseLocationCascader.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'

const search = reactive<any>({ imei: '', party_id: null, purchase_no: '', finance_status: '', status: '', warehouse_id: '', location_id: '', catalog_product_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
const searchPartyName = ref('')
const activeTab = ref('')
const listMode = ref<'device' | 'standard'>('device')
const router = useRouter()

function onTabChange(tab: string) {
    // Tab 映射：void 用 status 字段，其余用 finance_status
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
const accounts = ref<any[]>([])
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
const detail = reactive({ visible: false, loading: false, data: null as any })
const detailIsStandard = computed(() => Array.isArray(detail.data?.items) && detail.data.items.some((row: any) => row.item_type === 'standard'))
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
        if (row.order_status === 'void' || Number(row.is_returned || 0) === 1 || assetStatus === 'returned' || assetStatus === 'void') return acc
        const paid = Number(row.asset_paid_amount || 0)
        const purchaseAmount = Number(row.asset_payable_amount ?? row.purchase_cost ?? 0)
        acc.count += listMode.value === 'standard' ? Number(row.quantity || 0) : 1
        acc.totalCost += purchaseAmount
        acc.paid += paid
        acc.payable += Math.max(0, Number(row.asset_unpaid_amount ?? (purchaseAmount - paid)))
        return acc
    }, { count: 0, totalCost: 0, paid: 0, payable: 0 })
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
    loadAccounts()
    loadWarehouses()
    loadStaffOptions()
    loadSpecMeta()
    loadRules()
    loadDicts()
})
useErpPageRefresh(loadList)

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
    table.loading = true
    try {
        const res: any = await getErpPurchaseList({ ...buildSearchParams(), item_type: listMode.value, page: table.page, limit: table.limit })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
}

function onListModeChange() {
    table.page = 1
    search.imei = ''
    search.catalog_product_id = ''
    loadList()
}

async function loadAccounts() {
    const res: any = await getCapitalAccounts()
    accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
}

async function loadWarehouses() {
    const res: any = await getErpWarehouseOptions()
    warehouses.value = Array.isArray(res?.data) ? res.data : []
    applyDefaultWarehouse()
}

async function loadStaffOptions() {
    const res: any = await getErpStaffOptions()
    currentUid.value = Number(res?.data?.current_uid || 0)
    staffOptions.value = res?.data?.users || []
}

async function loadSpecMeta() {
    const res: any = await getErpGoodsSpecMeta()
    specMeta.value = res?.data || { groups: [], grades: [] }
}

async function loadRules() {
    const res: any = await getErpConfig()
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
    const res: any = await getErpDicts()
    erpDicts.value = res?.data || {}
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

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    Object.assign(search, { imei: '', party_id: null, purchase_no: '', finance_status: '', status: '', warehouse_id: '', location_id: '', catalog_product_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
    searchPartyName.value = ''
    activeTab.value = ''
    handleSearch()
}

function onSearchWarehouseChange() {
    search.location_id = ''
}

function openCreate() {
    create.form = defaultForm()
    create.form.purchaser_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    create.visible = true
    loadAccounts()
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
    if (overwrite) ElMessage.success('已应用到全部设备，仍可逐台修改')
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
        ElMessage.warning(`该串号已在第 ${duplicateIndex + 1} 行录入`)
        return focusDeviceImei(index)
    }
    if (index === create.form.items.length - 1) addItem()
    await focusDeviceImei(index + 1)
}

function removeItem(index: number) {
    if (create.form.items.length === 1) {
        ElMessage.warning(create.form.item_type_mode === 'device' ? '至少保留一台机器' : '至少保留一项商品')
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
        ElMessage.warning(`当前设备还有 ${deviceCoreMissingCount(itemExtra.item)} 项入库必填资料未完成`)
        return
    }
    if (itemExtra.index < create.form.items.length - 1) {
        ElMessage.success('当前设备资料已保留，已切换到下一台')
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
        ElMessage.success('已带入商品目录型号与品类快照')
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

function deviceCoreMissingCount(item: any) {
    const values = [
        String(item?.imei || item?.sn || '').trim(),
        String(item?.model || '').trim(),
        Number(item?.purchase_cost || 0) > 0,
        Number(item?.warehouse_id || 0) > 0,
        Number(item?.location_id || 0) > 0
    ]
    if (purchaseOneStop.value) {
        const listingValues: Record<string, any> = {
            catalog_product_id: Number(item?.catalog_product_id || 0) > 0,
            spec: String(item?.spec || '').trim(),
            image_urls: hasDeviceImage(item),
            video_url: String(item?.video_url || '').trim(),
            retail_price: Number(item?.retail_price || 0) > 0,
            quality_remark: String(item?.quality_remark || '').trim(),
            remark_public: String(item?.remark_public || '').trim(),
            remark_internal: String(item?.remark_internal || '').trim(),
        }
        Object.entries(listingValues).forEach(([field, value]) => {
            if (listingFieldRequired(field)) values.push(value)
        })
    }
    return values.filter((value: any) => !value).length
}

function deviceCoreReady(item: any) {
    return deviceCoreMissingCount(item) === 0
}

function itemExtraIncompleteTab(item: any): string {
    const baseReady = Boolean(String(item?.imei || item?.sn || '').trim())
        && Boolean(String(item?.model || '').trim())
        && Number(item?.purchase_cost || 0) > 0
        && Number(item?.warehouse_id || 0) > 0
        && Number(item?.location_id || 0) > 0
    if (!baseReady) return 'base'
    if (!purchaseOneStop.value) return ''
    if (!String(item?.model || '').trim()) return 'goods'
    if (listingFieldRequired('catalog_product_id') && Number(item?.catalog_product_id || 0) <= 0) return 'base'
    if (listingFieldRequired('spec') && !String(item?.spec || '').trim()) return 'goods'
    if (['image_urls', 'video_url', 'retail_price', 'quality_remark', 'remark_internal'].some(field => listingFieldRequired(field) && !listingFieldHasValue(item, field))) return 'sales'
    if (listingFieldRequired('remark_public') && !String(item?.remark_public || '').trim()) return 'goods'
    return ''
}

function hasDeviceImage(item: any) {
    return Boolean(String(item?.image_urls || '').trim())
}

function deviceMaterialCompletion(item: any) {
    const checks = [
        Boolean(String(item?.imei || item?.sn || '').trim()),
        Number(item?.purchase_cost || 0) > 0,
        Number(item?.warehouse_id || 0) > 0 && Number(item?.location_id || 0) > 0,
        Boolean(String(item?.category_path || '').trim()),
        Boolean(String(item?.model || '').trim()),
        Boolean(String(item?.spec || '').trim()),
        hasDeviceImage(item),
        Number(item?.retail_price || 0) > 0
    ]
    const relevantChecks = purchaseOneStop.value ? checks : checks.slice(0, 3)
    return Math.round(relevantChecks.filter(Boolean).length / relevantChecks.length * 100)
}

function listingFieldEnabled(field: string) {
    return Number(listingWorkspace.value?.field_rules?.[field]?.enabled ?? 1) === 1
}

function listingFieldRequired(field: string) {
    return listingFieldEnabled(field) && Number(listingWorkspace.value?.field_rules?.[field]?.required ?? 0) === 1
}

function listingFieldHasValue(item: any, field: string) {
    if (field === 'catalog_product_id') return Number(item?.catalog_product_id || 0) > 0
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

function compactItemSubTitle(item: any) {
    return [
        item.spec || '',
        item.imei ? `IMEI ${item.imei}` : '',
        item.sn ? `SN ${item.sn}` : ''
    ].filter(Boolean).join(' · ') || '还没有填写规格和串号'
}

function formatDate(value: any) {
    const time = Number(value || 0)
    if (!time) return ''
    const date = new Date(time * 1000)
    const month = `${date.getMonth() + 1}`.padStart(2, '0')
    const day = `${date.getDate()}`.padStart(2, '0')
    return `${date.getFullYear()}-${month}-${day}`
}

async function submitCreate() {
    if (!create.form.party_id && !create.form.party_name) return ElMessage.warning('请选择采购渠道')
    const isStandard = create.form.item_type_mode === 'standard'
    if (!create.form.items.length || create.form.items.some((row: any) => isStandard
        ? (!row.model || Number(row.quantity || 0) <= 0 || standardLineTotal(row) <= 0)
        : (!row.model || (!row.imei && !row.sn) || Number(row.purchase_cost || 0) <= 0 || deviceCoreMissingCount(row) > 0))) {
        return ElMessage.warning(isStandard ? '请补全商品名称、采购数量和采购总价' : (purchaseOneStop.value ? '请补全当前规则要求的采购及销售资料' : '请补全设备名称、IMEI/SN 和采购成本'))
    }
    const missingLocationIndex = create.form.items.findIndex((row: any) => !row.warehouse_id || !row.location_id)
    if (missingLocationIndex >= 0) return ElMessage.warning(`请为第 ${missingLocationIndex + 1} ${isStandard ? '项商品' : '台设备'}选择入库仓库和库位`)
    create.form.purchaser_uid = create.form.purchaser_uid || currentUid.value || staffOptions.value[0]?.uid || 0
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.paid_amount || 0) <= 0) return ElMessage.warning('请填写本次付款')
        if (!create.form.capital_account_id) return ElMessage.warning('请选择付款账户')
        if (Number(create.form.paid_amount || 0) > createTotal.value) return ElMessage.warning('付款不能大于采购成本')
    }
    const createConfirmed = await ElMessageBox.confirm(
        `确认向「${create.form.party_name || '所选供货商'}」采购 ${create.form.items.length} ${isStandard ? '项标品' : '台设备'}，采购总额 ${money(createTotal.value)}。提交后将生成${isStandard ? '数量库存与采购应付' : '设备资产和设备应付'}；${create.form.settle_mode === 'cash' ? `立即从所选账户付款 ${money(create.form.paid_amount)}，无需再次到财务确认。` : '本次按挂账处理，后续到应付款结算。'}`,
        '确认采购开单',
        { type: 'warning', confirmButtonText: '确认开单', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!createConfirmed) return
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
        ElMessage.success(create.form.settle_mode === 'cash'
            ? (remaining > 0.0001 ? `采购已现付，剩余 ${money(remaining)} 进入应付款` : '采购入库与现结付款已完成')
            : '采购单已生成，等待财务付款')
        listMode.value = create.form.item_type_mode
        create.visible = false
        loadList()
    } finally {
        create.saving = false
    }
}

function onPartyResolved(row: any) {
    create.form.party_name = row?.party_name || row?.name || ''
    create.form.m_no = row?.m_no || create.form.m_no || ''
}

async function openDetail(row: any) {
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpPurchaseInfo(row.purchase_order_id || row.id)
        detail.data = res?.data || null
    } finally {
        detail.loading = false
    }
}

function openAdjust(row: any) {
    if (!canAdjustSupplierPrice(row)) {
        ElMessage.warning(supplierAdjustBlockedReason(row))
        return
    }
    adjust.itemId = row.id
    adjust.item = row
    adjust.form = { type: 'deduct', amount: 0, remark: '' }
    adjust.visible = true
}

function canAdjustSupplierPrice(row: any) {
    return row?.status === 'in_stock'
        && row?.order_status !== 'void'
        && Number(row?.paid_amount || 0) <= 0.0001
}

function supplierAdjustBlockedReason(row: any) {
    if (row?.status === 'returned') return '设备已完成采购退货，不能再调整供应商采购价'
    if (row?.status === 'sold') return '设备已经销售出库，不能再调整供应商采购价'
    if (row?.status !== 'in_stock') return '只有仍在库存中的设备可以调整供应商采购价'
    if (row?.order_status === 'void') return '采购单已作废，不能调整供应商采购价'
    if (Number(row?.paid_amount || 0) > 0.0001) return '该设备已形成付款或折账，请走退款、补款或财务调账流程'
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
    if (!adjust.form.amount) return ElMessage.warning('请填写调整金额')
    if (adjustAfterCost.value <= 0) return ElMessage.warning('调整后成本必须大于0')
    if (!adjust.form.remark.trim()) return ElMessage.warning('请填写调整原因')
    const adjustConfirmed = await ElMessageBox.confirm(
        `确认对设备「${adjust.item?.model || adjust.item?.imei || '-'}」执行供应商调价 ${signedMoney(adjustSignedAmount.value)}，成本将由 ${money(adjustCurrentCost.value)} 变为 ${money(adjustAfterCost.value)}。该操作会同步修改采购本金和应付，并保留账务流水。`,
        '确认供应商调价',
        { type: 'warning', confirmButtonText: '确认调价并记账', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!adjustConfirmed) return
    adjust.saving = true
    try {
        await adjustErpPurchaseCost(adjust.itemId, {
            amount: adjustSignedAmount.value,
            remark: adjust.form.remark
        })
        ElMessage.success('成本已调整')
        adjust.visible = false
        if (detail.data?.id) await openDetail(detail.data)
        loadList()
    } finally {
        adjust.saving = false
    }
}

function financeStatusMeta(status: string) {
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

function batchPageSize(row: any) {
    const key = batchKey(row)
    return table.data.filter((item: any) => batchKey(item) === key).length
}

function purchaseRowClassName({ row, rowIndex }: { row: any; rowIndex: number }) {
    return [`erp-batch-tone-${batchTone(row)}`, isBatchFirst(rowIndex) ? 'erp-batch-start' : ''].filter(Boolean).join(' ')
}
</script>

<style scoped>
.summary-tile {
    border-radius: 8px;
    background: #f8fafc;
    padding: 14px 16px;
}
.summary-label {
    color: #64748b;
    font-size: 13px;
}
.summary-value {
    margin-top: 6px;
    color: #111827;
    font-size: 22px;
    font-weight: 650;
}
.section-title {
    margin: 18px 0 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
.batch-location-panel {
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #f8fbff;
    padding: 12px 14px 0;
}
.batch-location-panel__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
}
.batch-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 auto;
    border-radius: 50%;
}
.batch-dot--0 { background: #60a5fa; }
.batch-dot--1 { background: #34d399; }
.batch-dot--2 { background: #a78bfa; }
.batch-dot--3 { background: #f59e0b; }
.batch-staff-line { display:flex; flex-wrap:wrap; gap:4px 12px; margin-top:6px; color:#94a3b8; font-size:12px; }
.purchase-status-stack { display:flex; align-items:flex-start; flex-direction:column; gap:5px; }
.purchase-status-stack__business { color:#334155; font-size:12px; font-weight:600; }
.purchase-status-stack__finance { color:#64748b; font-size:12px; }
.purchase-status-stack__exception { color:#dc2626; font-size:12px; }
.purchase-status-stack__source { overflow:hidden; max-width:160px; color:#94a3b8; font-size:11px; text-overflow:ellipsis; white-space:nowrap; }
.purchase-return-action { white-space: nowrap; }
.supplier-adjust-disabled-wrap { display:inline-flex; margin-left:12px; vertical-align:middle; }
.purchase-return-disabled-wrap { display: inline-flex; margin-left: 12px; vertical-align: middle; }
:deep(.el-table__body tr.erp-batch-tone-0 > td.el-table__cell) { background: #f7fbff; }
:deep(.el-table__body tr.erp-batch-tone-1 > td.el-table__cell) { background: #f7fcfa; }
:deep(.el-table__body tr.erp-batch-tone-2 > td.el-table__cell) { background: #fbf9ff; }
:deep(.el-table__body tr.erp-batch-tone-3 > td.el-table__cell) { background: #fffaf3; }
:deep(.el-table__body tr.erp-batch-start > td.el-table__cell) { border-top: 2px solid #dbe4ef; }
:deep(.el-table__body tr:hover > td.el-table__cell) { background: #eef5ff !important; }
.create-purchase-dialog :deep(.el-dialog__body) {
    padding-top: 10px;
}
.create-purchase-dialog :deep(.el-dialog) {
    max-width: 1440px;
}
.create-purchase-form {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 190px);
    min-height: 0;
}
.purchase-device-section {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 220px;
    overflow: hidden;
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
    border: 1px solid #e2e8f0;
    border-bottom: 0;
    border-radius: 8px 8px 0 0;
    background: #f8fafc;
    padding: 9px 12px;
    color: #64748b;
    font-size: 12px;
}
.standard-entry-table {
    width: 100%;
}
.standard-entry-table :deep(.el-table__header th.el-table__cell) {
    background: #f1f5f9;
    color: #475569;
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
    box-shadow: 0 0 0 1px #dbe3ee inset;
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
    color: #0f172a;
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}
.standard-entry__unit-price {
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.standard-entry__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid #e2e8f0;
    border-top: 0;
    border-radius: 0 0 8px 8px;
    background: #fff;
    padding: 10px 12px;
}
.standard-entry__footer > div {
    display: flex;
    align-items: baseline;
    gap: 12px;
    color: #64748b;
    font-size: 13px;
}
.standard-entry__footer strong {
    color: #0f172a;
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
    border: 1px solid #dbe5f1;
    border-bottom: 0;
    border-radius: 10px 10px 0 0;
    background: linear-gradient(90deg, #f8fbff 0%, #f8fafc 100%);
    padding: 10px 12px;
}
.device-entry__default {
    width: min(560px, 75%);
    gap: 10px;
}
.device-entry__default-label {
    flex: 0 0 auto;
    color: #475569;
    font-size: 13px;
    font-weight: 650;
}
.device-entry__count {
    flex: 0 0 auto;
    color: #64748b;
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
    background: #f1f5f9;
    color: #475569;
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
    box-shadow: 0 0 0 1px #dbe3ee inset;
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
    color: #1e293b;
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
    color: #94a3b8;
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
    background: #f1f5f9;
    padding: 3px 7px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1;
}
.device-entry__progress span.is-done {
    background: #ecfdf3;
    color: #16a34a;
}
.device-entry__footer {
    justify-content: space-between;
    border: 1px solid #dbe5f1;
    border-top: 0;
    border-radius: 0 0 10px 10px;
    background: #fff;
    padding: 9px 12px;
    color: #94a3b8;
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
    border: 1px solid #dfe7f1;
    border-radius: 10px;
    background: #fff;
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
    border-bottom: 1px solid #edf2f7;
    background: #f8fafc;
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
    background: #eaf2ff;
    color: var(--el-color-primary);
    font-size: 12px;
    font-weight: 700;
}
.purchase-device-card__index {
    overflow: hidden;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.purchase-device-card__hint {
    margin-top: 2px;
    color: #94a3b8;
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
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}
.purchase-device-field__label em {
    color: #f97316;
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
    border-top: 1px solid #edf2f7;
    padding: 10px 14px;
}
.purchase-device-profile {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.purchase-device-profile span {
    border-radius: 999px;
    background: #f1f5f9;
    padding: 3px 8px;
    color: #94a3b8;
    font-size: 11px;
}
.purchase-device-profile span.is-done {
    background: #ecfdf3;
    color: #16a34a;
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
    border: 1px solid #dbeafe;
    border-radius: 10px;
    background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 72%);
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
    color: #111827;
    font-size: 16px;
    font-weight: 650;
}
.item-extra-sub {
    margin-top: 6px;
    color: #64748b;
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
    color: #64748b;
    font-size: 11px;
}
.item-extra-layout {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.item-extra-section {
    border: 1px solid #e5eaf2;
    border-radius: 10px;
    background: #fff;
    padding: 18px;
}
.item-extra-section--primary {
    border-color: #bfdbfe;
    box-shadow: 0 4px 14px rgb(37 99 235 / 6%);
}
.drawer-mobile-form :deep(.el-form-item) {
    margin-bottom: 14px;
}
.drawer-mobile-form :deep(.el-form-item__label) {
    color: #475569;
    font-weight: 600;
    line-height: 22px;
}
.purchase-item-drawer :deep(.el-drawer__body) {
    background: #f8fafc;
    padding-top: 14px;
}
.purchase-item-drawer :deep(.el-drawer__footer) {
    border-top: 1px solid #eef2f7;
    background: #fff;
}
.item-extra-tabs :deep(.el-tabs__header) {
    margin: 0 0 14px;
    border-radius: 9px;
    background: #eef2f7;
    padding: 4px;
}
.item-extra-tabs :deep(.el-tabs__nav-wrap::after),
.item-extra-tabs :deep(.el-tabs__active-bar) {
    display: none;
}
.item-extra-tabs :deep(.el-tabs__item) {
    height: 38px;
    border-radius: 7px;
    color: #64748b;
    font-weight: 600;
}
.item-extra-tabs :deep(.el-tabs__item.is-active) {
    background: #fff;
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
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
.item-extra-section__desc {
    margin-top: 4px;
    color: #94a3b8;
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
    color: #475569;
    font-size: 13px;
    font-weight: 600;
}
.erp-image-upload {
    width: 100%;
}
.erp-image-upload__tips {
    margin-top: 6px;
    color: #94a3b8;
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
