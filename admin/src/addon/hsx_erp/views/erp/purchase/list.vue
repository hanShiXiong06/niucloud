<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">采购管理</div>
                    <div class="mt-1 text-sm text-gray-500">以每台设备为核心查看采购入库、成本、位置和账目状态；采购单作为批次凭证保留。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate">采购开单</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">采购台数</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">设备成本</div>
                    <div class="summary-value">{{ money(summary.totalCost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">分摊已付</div>
                    <div class="summary-value text-green-600">{{ money(summary.paid) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">分摊未付</div>
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
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="型号 / IMEI / 资产号 / 采购单 / 用户" @keyup.enter="handleSearch" />
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
                <el-form-item label="分类">
                        <el-tree-select
                            v-model="search.category_id"
                            :data="categoryTree"
                            :props="{ label: 'category_name', value: 'category_id', children: 'child_list' }"
                            check-strictly
                            clearable
                            default-expand-all
                            filterable
                            class="!w-[220px]"
                            node-key="category_id"
                            placeholder="全部分类"
                    />
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

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">资产号：{{ row.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购用户" min-width="170">
                    <template #default="{ row }">
                        <div>{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">M号：{{ row.m_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="成本" min-width="160" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.total_cost) }}</div>
                        <div v-if="Number(row.adjust_cost)" class="mt-1 text-xs text-gray-500">调整 {{ money(row.adjust_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="160">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="批次" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.purchase_no || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.purchase_at) }}</div>
                        <div class="mt-1 text-xs text-gray-400">采购员：{{ row.purchaser_name || '-' }}</div>
                        <div v-if="row.inspector_name" class="mt-1 text-xs text-gray-400">质检员：{{ row.inspector_name }}</div>
                        <div v-if="Number(row.estimate_sale_price)" class="mt-1 text-xs text-gray-400">预计卖价：{{ money(row.estimate_sale_price) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="付款状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="financeStatusMeta(row.finance_status).type">{{ financeStatusMeta(row.finance_status).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="单据" width="110">
                    <template #default="{ row }"><el-tag :type="orderStatusMeta(row.order_status).type" effect="plain">{{ orderStatusMeta(row.order_status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }"><el-tag effect="plain">{{ assetStatusLabel(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="230" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">批次</el-button>
                        <el-button v-if="row.order_status !== 'void'" type="primary" link @click="openAdjust(row)">调成本</el-button>
                        <el-button v-if="canReturnPurchase(row)" type="warning" link @click="goReturn(row)">退货</el-button>
                        <el-button v-if="canCancelPurchase(row)" type="danger" link @click="cancelPurchase(row)">撤销</el-button>
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

        <el-dialog v-model="create.visible" title="采购开单" width="980px" destroy-on-close>
            <el-form label-width="96px">
                <div class="section-title">1. 用户</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="采购用户" required>
                        <counterparty-select v-model="create.form.party_id" role-type="supplier" placeholder="搜索或新建采购用户" @resolved="onPartyResolved" />
                    </el-form-item>
                    <el-form-item label="M号">
                        <el-input v-model.trim="create.form.m_no" placeholder="客户M号/业务编号" />
                    </el-form-item>
                </div>

                <div class="section-title">2. 货品</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="入库仓库" required>
                        <el-select v-model="create.form.warehouse_id" class="w-full" placeholder="选择仓库" @change="onWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="入库库位" required>
                        <el-select v-model="create.form.location_id" class="w-full" placeholder="选择库位" :disabled="!create.form.warehouse_id">
                            <el-option v-for="item in currentLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>

                <div class="mt-1 flex items-center justify-between">
                    <div class="font-medium">机器明细</div>
                    <div class="flex items-center gap-2">
                        <el-button @click="openGoodsMeta('category')">管理商品资料</el-button>
                        <el-button :icon="Plus" @click="addItem">加一台</el-button>
                    </div>
                </div>
                <el-alert
                    class="mt-3"
                    type="info"
                    :closable="false"
                    show-icon
                    title="分类和规格会影响设备名称，规则可在「业务规则 - 设备命名规则」中调整。"
                />
                <div class="purchase-device-grid mt-3">
                    <div v-for="(row, index) in create.form.items" :key="index" class="purchase-device-card">
                        <div class="purchase-device-card__head">
                            <div>
                                <div class="purchase-device-card__index">{{ row.model || `设备 ${index + 1}` }}</div>
                                <div class="purchase-device-card__hint">{{ itemCoreSummary(row) }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <el-tag v-if="row.category_id" size="small" effect="plain">已选分类</el-tag>
                                <el-tag v-if="row.spec" size="small" type="success" effect="plain">已完善规格</el-tag>
                            </div>
                        </div>
                        <div class="purchase-device-card__body">
                            <div class="purchase-device-meta">
                                <span>IMEI</span>
                                <strong>{{ row.imei || '-' }}</strong>
                            </div>
                            <div class="purchase-device-meta">
                                <span>采购成本</span>
                                <strong>{{ money(row.purchase_cost) }}</strong>
                            </div>
                            <div class="purchase-device-meta">
                                <span>备注</span>
                                <strong>{{ row.remark || '-' }}</strong>
                            </div>
                        </div>
                        <div class="purchase-device-card__footer">
                            <span class="text-xs text-gray-400">基础信息、分类、规格、图片、质检都在右侧抽屉里处理。</span>
                            <div>
                                <el-button type="primary" @click="openItemExtra(row, index)">编辑设备</el-button>
                                <el-button type="danger" plain @click="removeItem(index)">删除</el-button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-right text-sm text-gray-500">本单采购成本合计：<span class="font-semibold text-gray-800">{{ money(createTotal) }}</span></div>

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

        <el-drawer v-model="itemExtra.visible" size="560px" direction="rtl" append-to-body class="purchase-item-drawer">
            <template #header>
                <div>
                    <div class="item-extra-title">编辑设备 {{ itemExtra.index + 1 }}</div>
                    <div class="item-extra-sub">{{ itemExtra.item?.model || '从基础信息开始完善' }}</div>
                </div>
            </template>
            <div v-if="itemExtra.item" class="item-extra-head">
                <div>
                    <div class="item-extra-title">{{ itemExtra.item.model || `第 ${itemExtra.index + 1} 台设备` }}</div>
                    <div class="item-extra-sub">{{ compactItemSubTitle(itemExtra.item) }}</div>
                </div>
                <div class="item-extra-tags">
                    <el-tag v-if="itemExtra.item.category_name" effect="plain">{{ itemExtra.item.category_name }}</el-tag>
                    <el-tag v-if="itemExtra.item.spec" type="success" effect="plain">{{ itemExtra.item.spec }}</el-tag>
                </div>
            </div>

            <div v-if="itemExtra.item" class="item-extra-layout">
                <section class="item-extra-section">
                    <div class="item-extra-section__title mb-3">基础信息</div>
                    <el-alert
                        class="mb-3"
                        type="info"
                        :closable="false"
                        show-icon
                        title="选择分类和规格后会自动生成设备名称；如果手动修改型号，系统会保留你的手动名称。"
                    />
                    <el-form label-position="top" class="drawer-mobile-form">
                        <el-form-item label="型号" required>
                            <el-input v-model.trim="itemExtra.item.model" placeholder="选择分类后可自动生成，也可手动修改" @input="markManualModel(itemExtra.item)" />
                        </el-form-item>
                        <el-form-item label="IMEI">
                            <el-input v-model.trim="itemExtra.item.imei" placeholder="扫描或手输 IMEI" />
                        </el-form-item>
                        <el-form-item label="采购成本" required>
                            <el-input-number v-model="itemExtra.item.purchase_cost" :min="0" :precision="2" :controls="false" class="!w-full" />
                        </el-form-item>
                        <el-form-item label="备注">
                            <el-input v-model.trim="itemExtra.item.remark" type="textarea" :rows="2" placeholder="核心备注，如来源、异常说明" />
                        </el-form-item>
                    </el-form>
                </section>

                <section class="item-extra-section">
                    <div class="item-extra-section__head">
                        <div>
                            <div class="item-extra-section__title">分类</div>
                            <div class="item-extra-section__desc">最多三级分类，选择后会保存到设备快照。</div>
                        </div>
                        <el-button link type="primary" @click="openGoodsMeta('category')">管理分类</el-button>
                    </div>
                    <el-tree-select
                        v-model="itemExtra.item.category_id"
                        :data="categoryTree"
                        :props="{ label: 'category_name', value: 'category_id', children: 'child_list' }"
                        check-strictly
                        clearable
                        default-expand-all
                        filterable
                        class="w-full"
                        node-key="category_id"
                        placeholder="选择设备分类"
                        @change="value => onItemCategoryChange(itemExtra.item, value)"
                    />
                </section>

                <section class="item-extra-section">
                    <div class="item-extra-section__head">
                        <div>
                            <div class="item-extra-section__title">规格与成色</div>
                            <div class="item-extra-section__desc">选择规格会自动生成规格文本，并随采购明细保存。</div>
                        </div>
                        <el-button link type="primary" @click="openGoodsMeta('spec')">管理规格</el-button>
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
                        <el-button type="primary" link @click="openGoodsMeta('spec')">去添加规格</el-button>
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

                <section class="item-extra-section">
                    <div class="item-extra-section__title mb-3">质检与售价</div>
                    <el-form label-width="86px">
                        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                            <el-form-item label="质检员">
                                <el-select v-model="itemExtra.item.inspector_uid" clearable filterable class="w-full" placeholder="未质检可不选">
                                    <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="预计卖价">
                                <el-input-number v-model="itemExtra.item.estimate_sale_price" :min="0" :precision="2" :controls="false" placeholder="选填" class="!w-full" />
                            </el-form-item>
                        </div>
                        <el-form-item label="设备图片">
                            <div class="erp-image-upload">
                                <upload-image v-model="itemExtra.item.image_urls" :limit="9" width="72px" height="72px" image-text="上传/选择" />
                                <div class="erp-image-upload__tips">支持本地上传或从素材库选择，多图可拖动排序。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="质检备注">
                            <el-input v-model.trim="itemExtra.item.quality_remark" type="textarea" :rows="3" placeholder="如：屏幕划痕、电池效率等" />
                        </el-form-item>
                    </el-form>
                </section>
            </div>
            <template #footer>
                <el-button @click="itemExtra.visible = false">关闭</el-button>
                <el-button type="primary" @click="itemExtra.visible = false">完成</el-button>
            </template>
        </el-drawer>

        <el-dialog v-model="goodsMeta.visible" title="管理商品资料" width="920px" destroy-on-close append-to-body>
            <ErpGoodsMetaManager :active="goodsMeta.active" @saved="onGoodsMetaSaved" />
            <template #footer>
                <el-button type="primary" @click="goodsMeta.visible = false">完成</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="采购单详情" size="76%" destroy-on-close>
            <div v-loading="detail.loading">
                <el-descriptions v-if="detail.data" :column="4" border>
                    <el-descriptions-item label="采购单号">{{ detail.data.purchase_no }}</el-descriptions-item>
                    <el-descriptions-item label="采购用户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="M号">{{ detail.data.m_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="付款状态">
                        <el-tag :type="financeStatusMeta(detail.data.finance_status).type">{{ financeStatusMeta(detail.data.finance_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="采购金额">{{ money(detail.data.total_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="已付款">{{ money(detail.data.paid_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="剩余应付">{{ money(detail.data.payable_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="仓库">{{ detail.data.warehouse_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="库位">{{ detail.data.location_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="付款账户">{{ detail.data.capital_account_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="采购员">{{ detail.data.purchaser_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="4">{{ detail.data.remark || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-5 font-medium">机器明细</div>
                <el-table class="mt-3" :data="detail.data?.items || []" size="large">
                    <el-table-column prop="model" label="型号" min-width="180" />
                    <el-table-column prop="imei" label="IMEI" min-width="170" />
                    <el-table-column prop="spec" label="规格" min-width="150" />
                    <el-table-column prop="inspector_name" label="质检员" min-width="120" />
                    <el-table-column label="预计卖价" width="130" align="right">
                        <template #default="{ row }">{{ Number(row.estimate_sale_price || 0) ? money(row.estimate_sale_price) : '-' }}</template>
                    </el-table-column>
                    <el-table-column label="成本" width="160" align="right">
                        <template #default="{ row }">
                            <div>{{ money(row.total_cost) }}</div>
                            <div v-if="Number(row.adjust_cost)" class="text-xs text-gray-500">调整 {{ money(row.adjust_cost) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="quality_remark" label="质检备注" min-width="180" />
                    <el-table-column prop="remark" label="备注" min-width="180" />
                    <el-table-column label="操作" width="110" align="center">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="openAdjust(row)">调成本</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-drawer>

        <el-dialog v-model="adjust.visible" title="成本调整" width="620px">
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { adjustErpPurchaseCost, cancelErpPurchase, createErpPurchase, getErpGoodsCategoryTree, getErpGoodsSpecMeta, getErpPurchaseInfo, getErpPurchaseList, getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import { getErpConfig } from '@/addon/hsx_erp/api/config'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpGoodsMetaManager from '@/addon/hsx_erp/components/ErpGoodsMetaManager.vue'

const search = reactive<any>({ keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', category_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
const activeTab = ref('')
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
const categoryTree = ref<any[]>([])
const specMeta = ref<any>({ groups: [], grades: [] })
const staffOptions = ref<any[]>([])
const currentUid = ref(0)
const titleRules = reactive({ category_mode: 'auto', spec_in_title: 1, grade_in_title: 0, separator: ' ' })
const create = reactive({ visible: false, saving: false, form: defaultForm() })
const itemExtra = reactive({ visible: false, index: -1, item: null as any })
const goodsMeta = reactive({ visible: false, active: 'category' })
const detail = reactive({ visible: false, loading: false, data: null as any })
const adjust = reactive({ visible: false, saving: false, itemId: 0, item: null as any, form: { type: 'deduct', amount: 0, remark: '' } })

const summary = computed(() => {
    return table.data.reduce((acc, row: any) => {
        if (row.order_status === 'void') return acc
        const paid = allocatedPaid(row)
        acc.count += 1
        acc.totalCost += Number(row.total_cost || 0)
        acc.paid += paid
        acc.payable += Math.max(0, Number(row.total_cost || 0) - paid)
        return acc
    }, { count: 0, totalCost: 0, paid: 0, payable: 0 })
})
const createTotal = computed(() => create.form.items.reduce((sum: number, row: any) => sum + Number(row.purchase_cost || 0), 0))
const specGroups = computed(() => normalizeSpecGroups(specMeta.value?.groups || []))
const gradeOptions = computed(() => normalizeOptions(specMeta.value?.grades || []))
const currentWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(create.form.warehouse_id)) || null)
const currentLocations = computed(() => currentWarehouse.value?.locations || [])
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
    loadList()
    loadAccounts()
    loadWarehouses()
    loadStaffOptions()
    loadCategories()
    loadSpecMeta()
    loadRules()
})

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
        warehouse_id: 0,
        warehouse_name: '',
        location_id: 0,
        location_name: '',
        remark: '',
        items: [blankItem()]
    }
}

function blankItem() {
    return {
        model: '',
        imei: '',
        spec: '',
        spec_json: {},
        selected_specs: {},
        selected_grade: null,
        color: '',
        battery: undefined,
        warranty: undefined,
        category_id: 0,
        category_name: '',
        category_path: '',
        purchase_cost: 0,
        inspector_uid: null,
        estimate_sale_price: undefined,
        image_urls: '',
        quality_remark: '',
        remark: '',
        _auto_model: '',
        _model_manual: false
    }
}

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpPurchaseList({ ...buildSearchParams(), page: table.page, limit: table.limit })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
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

async function loadCategories() {
    const res: any = await getErpGoodsCategoryTree()
    categoryTree.value = Array.isArray(res?.data) ? res.data : []
}

async function loadSpecMeta() {
    const res: any = await getErpGoodsSpecMeta()
    specMeta.value = res?.data || { groups: [], grades: [] }
}

async function loadRules() {
    const res: any = await getErpConfig()
    Object.assign(titleRules, res?.data?.product_title || {})
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
    Object.assign(search, { keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', category_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
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
}

function addItem() {
    create.form.items.push(blankItem())
}

function removeItem(index: number) {
    if (create.form.items.length === 1) {
        ElMessage.warning('至少保留一台机器')
        return
    }
    if (itemExtra.index === index) itemExtra.visible = false
    create.form.items.splice(index, 1)
}

function openItemExtra(row: any, index: number) {
    hydrateItemSpecState(row)
    itemExtra.item = row
    itemExtra.index = index
    itemExtra.visible = true
    if (!specMeta.value?.groups?.length && !specMeta.value?.grades?.length) loadSpecMeta()
}

function openGoodsMeta(active = 'category') {
    goodsMeta.active = active
    goodsMeta.visible = true
}

async function onGoodsMetaSaved() {
    await loadCategories()
    await loadSpecMeta()
}

function onItemCategoryChange(item: any, value: any) {
    const node = findCategoryNode(categoryTree.value, Number(value || 0))
    item.category_name = node?.category_full_name || node?.category_name || ''
    item.category_path = node ? categoryPathIds(node).join(',') : ''
    item.selected_specs = {}
    item.selected_grade = null
    item.spec = ''
    item.spec_json = {}
    rebuildItemModel(item, true)
    ElMessage.info('当前分类会参与生成设备名称，可在「业务规则 - 设备命名规则」中修改规则。')
}

function findCategoryNode(rows: any[], id: number, parents: any[] = []): any {
    for (const row of rows || []) {
        const current = { ...row, _parents: parents }
        if (Number(row.category_id) === id) return current
        const child = findCategoryNode(row.child_list || [], id, [...parents, row])
        if (child) return child
    }
    return null
}

function categoryPathIds(node: any) {
    return [...(node?._parents || []), node].map((row: any) => Number(row.category_id || 0)).filter(Boolean)
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
    return String(item?.category_name || '').split(/[>\-/\\｜|,，\s]+/).map((name: string) => name.trim()).filter(Boolean)
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
    const parts = [categoryTitleByRule(item), ...selectedTitleSpecParts(item)]
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
    if (!create.form.items.length || create.form.items.some((row: any) => !row.model || Number(row.purchase_cost || 0) <= 0)) {
        return ElMessage.warning('请补全机器型号和采购成本')
    }
    if (!create.form.warehouse_id) return ElMessage.warning('请选择入库仓库')
    if (!create.form.location_id) return ElMessage.warning('请选择入库库位')
    create.form.purchaser_uid = create.form.purchaser_uid || currentUid.value || staffOptions.value[0]?.uid || 0
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.paid_amount || 0) <= 0) return ElMessage.warning('请填写本次付款')
        if (!create.form.capital_account_id) return ElMessage.warning('请选择付款账户')
        if (Number(create.form.paid_amount || 0) > createTotal.value) return ElMessage.warning('付款不能大于采购成本')
    }
    create.saving = true
    try {
        await createErpPurchase({
            ...create.form,
            warehouse_name: currentWarehouse.value?.warehouse_name || create.form.warehouse_name,
            location_name: currentLocations.value.find((row: any) => Number(row.id) === Number(create.form.location_id))?.location_name || create.form.location_name,
            settle_method: create.form.settle_mode === 'cash' ? '现结' : '挂账'
        })
        ElMessage.success('采购单已生成')
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
    adjust.itemId = row.id
    adjust.item = row
    adjust.form = { type: 'deduct', amount: 0, remark: '' }
    adjust.visible = true
}

function canCancelPurchase(row: any) {
    return row.order_status === 'completed' && row.finance_status === 'pending' && row.status === 'in_stock'
}

/** 设备仍在库（未被销售/退货/作废）且采购单未撤销，才能发起退货 */
function canReturnPurchase(row: any) {
    return row.status === 'in_stock' && row.order_status !== 'void'
}

function goReturn(row: any) {
    router.push({
        path: '/site/hsx_erp/purchase_return',
        query: { purchase_order_id: row.purchase_order_id },
    })
}

async function cancelPurchase(row: any) {
    try {
        const result: any = await ElMessageBox.prompt(
            '撤销后设备会从库存移出，应付款作废。已有付款或折账的单据不能撤销，需要走退货/调整。',
            '撤销采购单',
            {
                confirmButtonText: '确认撤销',
                cancelButtonText: '取消',
                inputPlaceholder: '填写撤销原因，便于后续追溯'
            }
        )
        await cancelErpPurchase(Number(row.purchase_order_id || row.id), { remark: result?.value || '' })
        ElMessage.success('采购单已撤销')
        await loadList()
    } catch (e: any) {
        if (e !== 'cancel' && e !== 'close') throw e
    }
}

async function submitAdjust() {
    if (!adjust.form.amount) return ElMessage.warning('请填写调整金额')
    if (adjustAfterCost.value < 0) return ElMessage.warning('调整后成本不能小于0')
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
    const map: any = {
        pending: { label: '待付款', type: 'warning' },
        partial: { label: '部分付款', type: 'primary' },
        settled: { label: '已结清', type: 'success' },
        void: { label: '已撤销', type: 'info' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function assetStatusLabel(status: string) {
    const map: any = { in_stock: '在库', sold: '已售', returned: '已退', void: '作废' }
    return map[status] || status || '-'
}

function orderStatusMeta(status: string) {
    const map: any = {
        completed: { label: '已完成', type: 'success' },
        returned: { label: '已退货', type: 'warning' },
        void: { label: '已撤销', type: 'info' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function allocatedPaid(row: any) {
    const itemCost = Number(row.total_cost || 0)
    const orderCost = Number(row.order_total_cost || 0)
    const orderPaid = Number(row.paid_amount || 0)
    if (itemCost <= 0 || orderCost <= 0 || orderPaid <= 0) return 0
    return Math.min(itemCost, itemCost * Math.min(orderPaid / orderCost, 1))
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function formatTime(value: any) {
    const time = Number(value || 0)
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
}

function staffName(user: any) {
    return user?.name || user?.real_name || user?.username || `员工#${user?.uid || '-'}`
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
.purchase-device-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.purchase-device-card {
    border: 1px solid #eef2f7;
    border-radius: 8px;
    background: #fff;
    padding: 14px;
}
.purchase-device-card__head,
.purchase-device-card__footer {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.purchase-device-card__index {
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
.purchase-device-card__hint {
    margin-top: 4px;
    color: #94a3b8;
    font-size: 12px;
}
.purchase-device-card__body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}
.purchase-device-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-radius: 6px;
    background: #f8fafc;
    padding: 9px 10px;
    color: #64748b;
    font-size: 13px;
}
.purchase-device-meta strong {
    min-width: 0;
    color: #111827;
    font-weight: 600;
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.purchase-device-card__footer {
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 12px;
}
.item-extra-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
    border: 1px solid #eef2f7;
    border-radius: 8px;
    background: #f8fafc;
    padding: 14px 16px;
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
    justify-content: flex-end;
    gap: 6px;
    max-width: 360px;
}
.item-extra-layout {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.item-extra-section {
    border: 1px solid #eef2f7;
    border-radius: 8px;
    padding: 14px 16px;
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
}
.purchase-item-drawer :deep(.el-drawer__footer) {
    border-top: 1px solid #eef2f7;
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
    .item-extra-head,
    .item-extra-section__head {
        flex-direction: column;
    }
    .item-extra-tags {
        justify-content: flex-start;
        max-width: none;
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
    }
}
</style>
