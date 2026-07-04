<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">库存中心</div>
                    <div class="mt-1 text-sm text-gray-500">按每一台设备管理库存、整备、销售去向、图片定价和账目追溯。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-6">
                <div class="summary-tile">
                    <div class="summary-label">当前页台数</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">库存成本</div>
                    <div class="summary-value">{{ money(summary.cost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">均台成本</div>
                    <div class="summary-value">{{ summary.inStockCount > 0 ? money(summary.cost / summary.inStockCount) : '-' }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">待整备</div>
                    <div class="summary-value text-orange-600">{{ summary.needRefurbish }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">可直接卖</div>
                    <div class="summary-value text-green-600">{{ summary.saleable }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">已售</div>
                    <div class="summary-value text-gray-600">{{ summary.sold }}</div>
                </div>
            </div>

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="型号 / IMEI / 资产号 / 来源 / 仓库" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="库存状态">
                    <el-select v-model="search.status" clearable class="!w-[140px]" placeholder="全部">
                        <el-option label="库存中" value="in_stock" />
                        <el-option label="已售" value="sold" />
                        <el-option label="已退" value="returned" />
                    </el-select>
                </el-form-item>
                <el-form-item label="整备">
                    <el-select v-model="search.refurbish_status" clearable class="!w-[140px]" placeholder="全部">
                        <el-option label="无需整备" value="none" />
                        <el-option label="待整备" value="pending" />
                        <el-option label="整备中" value="processing" />
                        <el-option label="已完成" value="done" />
                    </el-select>
                </el-form-item>
                <el-form-item label="去向">
                    <el-select v-model="search.sale_target" clearable class="!w-[140px]" placeholder="全部">
                        <el-option label="未定" value="unset" />
                        <el-option label="卖同行" value="peer" />
                        <el-option label="上商城" value="mall" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="设备" min-width="260">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">资产号：{{ row.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="来源 / 位置" min-width="210">
                    <template #default="{ row }">
                        <div>{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="库龄" width="90" align="center">
                    <template #default="{ row }">
                        <span v-if="row.stock_in_at && row.status === 'in_stock'" :class="stockAgeDaysClass(row.stock_in_at)">
                            {{ stockAgeDays(row.stock_in_at) }}天
                        </span>
                        <span v-else class="text-gray-300">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="成本 / 预计" min-width="170" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.total_cost) }}</div>
                        <div class="mt-1 text-xs text-gray-500">预计卖价 {{ Number(row.estimate_sale_price || 0) ? money(row.estimate_sale_price) : '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="流转状态" min-width="230">
                    <template #default="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <el-tag :type="assetStatusMeta(row.status).type">{{ assetStatusMeta(row.status).label }}</el-tag>
                            <el-tag :type="refurbishMeta(row.refurbish_status).type" effect="plain">{{ refurbishMeta(row.refurbish_status).label }}</el-tag>
                            <el-tag :type="targetMeta(row.sale_target).type" effect="plain">{{ targetMeta(row.sale_target).label }}</el-tag>
                            <el-tag v-if="row.sale_target === 'mall'" :type="listingMeta(row.listing_status).type" effect="plain">{{ listingMeta(row.listing_status).label }}</el-tag>
                        </div>
                        <div v-if="row.quality_remark" class="mt-2 text-xs text-gray-500 line-clamp-1">{{ row.quality_remark }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="账目" min-width="170" align="right">
                    <template #default="{ row }">
                        <div>采购 {{ money(row.purchase_cost) }}</div>
                        <div v-if="Number(row.adjust_cost)" class="mt-1 text-xs text-gray-500">调整 {{ money(row.adjust_cost) }}</div>
                        <div v-if="Number(row.refurbish_cost)" class="mt-1 text-xs text-gray-500">整备 {{ money(row.refurbish_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="150" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">档案</el-button>
                        <el-button v-if="row.status === 'in_stock'" type="primary" link @click="openFlow(row)">流转</el-button>
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

        <el-dialog v-model="flow.visible" title="设备流转设置" width="620px" destroy-on-close>
            <el-form label-width="96px">
                <el-alert title="待整备或整备中的设备不会出现在销售出库的待售库存中。" type="warning" :closable="false" show-icon />
                <div class="mt-4 rounded border border-gray-100 bg-gray-50 px-4 py-3">
                    <div class="font-medium">{{ flow.row?.model || '-' }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ flow.row?.spec || '-' }} · IMEI {{ flow.row?.imei || '-' }}</div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="整备状态">
                        <el-select v-model="flow.form.refurbish_status" class="w-full">
                            <el-option label="无需整备" value="none" />
                            <el-option label="待整备" value="pending" />
                            <el-option label="整备中" value="processing" />
                            <el-option label="已完成" value="done" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="销售去向">
                        <el-select v-model="flow.form.sale_target" class="w-full" @change="onTargetChange">
                            <el-option label="暂未决定" value="unset" />
                            <el-option label="卖同行" value="peer" />
                            <el-option label="上商城" value="mall" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="上架状态">
                        <el-select v-model="flow.form.listing_status" class="w-full" :disabled="flow.form.sale_target !== 'mall'">
                            <el-option label="不需要" value="none" />
                            <el-option label="待拍照" value="need_photo" />
                            <el-option label="待定价" value="need_price" />
                            <el-option label="可上架" value="ready" />
                            <el-option label="已上架" value="listed" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="预计卖价">
                        <el-input-number v-model="flow.form.estimate_sale_price" :min="0" :precision="2" :controls="false" class="!w-full" />
                    </el-form-item>
                    <el-form-item label="零售价">
                        <el-input-number v-model="flow.form.retail_price" :min="0" :precision="2" :controls="false" class="!w-full" placeholder="上架商城定价" />
                    </el-form-item>
                </div>
                <el-form-item label="图片">
                    <el-input v-model.trim="flow.form.image_urls" placeholder="图片地址，多张用逗号分隔" />
                </el-form-item>
                <el-form-item label="质检备注">
                    <el-input v-model.trim="flow.form.quality_remark" type="textarea" :rows="2" placeholder="质检、外观说明（内部使用）" />
                </el-form-item>
                <el-form-item label="对外说明">
                    <el-input v-model.trim="flow.form.remark_public" type="textarea" :rows="2" placeholder="展示给客户/商城的描述" />
                </el-form-item>
                <el-form-item label="对内备注">
                    <el-input v-model.trim="flow.form.remark_internal" placeholder="员工内部备注，不对外展示" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="flow.visible = false">取消</el-button>
                <el-button type="primary" :loading="flow.saving" @click="submitFlow">保存</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="设备档案" size="72%" destroy-on-close>
            <div v-loading="detail.loading">
                <template v-if="detail.data">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold">{{ detail.data.model || '-' }}</div>
                            <div class="mt-1 text-sm text-gray-500">{{ detail.data.spec || '-' }} · IMEI {{ detail.data.imei || '-' }} · 资产号 {{ detail.data.asset_no || '-' }}</div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <el-tag :type="assetStatusMeta(detail.data.status).type">{{ assetStatusMeta(detail.data.status).label }}</el-tag>
                            <el-tag :type="refurbishMeta(detail.data.refurbish_status).type" effect="plain">{{ refurbishMeta(detail.data.refurbish_status).label }}</el-tag>
                            <el-tag :type="targetMeta(detail.data.sale_target).type" effect="plain">{{ targetMeta(detail.data.sale_target).label }}</el-tag>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="summary-tile"><div class="summary-label">采购成本</div><div class="summary-value">{{ money(detail.data.purchase_cost) }}</div></div>
                        <div class="summary-tile"><div class="summary-label">调整成本</div><div class="summary-value">{{ money(detail.data.adjust_cost) }}</div></div>
                        <div class="summary-tile"><div class="summary-label">整备成本</div><div class="summary-value">{{ money(detail.data.refurbish_cost) }}</div></div>
                        <div class="summary-tile"><div class="summary-label">当前总成本</div><div class="summary-value">{{ money(detail.data.total_cost) }}</div></div>
                    </div>

                    <el-descriptions class="mt-5" :column="3" border>
                        <el-descriptions-item label="采购来源">{{ detail.data.party_name || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="仓库库位">{{ [detail.data.warehouse_name, detail.data.location_name].filter(Boolean).join(' / ') || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="质检员">{{ detail.data.inspector_name || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="预计卖价">{{ Number(detail.data.estimate_sale_price || 0) ? money(detail.data.estimate_sale_price) : '-' }}</el-descriptions-item>
                        <el-descriptions-item label="销售价">{{ Number(detail.data.sale_price || 0) ? money(detail.data.sale_price) : '-' }}</el-descriptions-item>
                        <el-descriptions-item label="毛利">{{ Number(detail.data.profit || 0) ? money(detail.data.profit) : '-' }}</el-descriptions-item>
                        <el-descriptions-item label="入库图片" :span="3">{{ detail.data.image_urls || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="备注" :span="3">{{ detail.data.quality_remark || detail.data.remark || '-' }}</el-descriptions-item>
                    </el-descriptions>

                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div class="section-title">采购批次</div>
                            <el-descriptions :column="1" border>
                                <el-descriptions-item label="采购单">{{ detail.data.purchase_order?.purchase_no || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="采购用户">{{ detail.data.purchase_order?.party_name || detail.data.party_name || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="付款状态">{{ financeStatusLabel(detail.data.purchase_order?.finance_status) }}</el-descriptions-item>
                            </el-descriptions>
                        </div>
                        <div>
                            <div class="section-title">销售批次</div>
                            <el-descriptions :column="1" border>
                                <el-descriptions-item label="销售单">{{ detail.data.sale_order?.sale_no || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="销售客户">{{ detail.data.sale_order?.party_name || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="收款状态">{{ financeStatusLabel(detail.data.sale_order?.finance_status) }}</el-descriptions-item>
                            </el-descriptions>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="section-title">设备流水</div>
                        <el-table :data="detail.data.asset_ledgers || []" size="small">
                            <el-table-column label="流水号" min-width="180">
                                <template #default="{ row }">{{ row.ledger_no || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="动作" width="120">
                                <template #default="{ row }">
                                    <el-tag effect="plain">{{ assetActionLabel(row.action) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="变化" min-width="220">
                                <template #default="{ row }">{{ row.before_status || '-' }} → {{ row.after_status || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="仓库库位" min-width="220">
                                <template #default="{ row }">
                                    <div>{{ [row.after_warehouse_name, row.after_location_name].filter(Boolean).join(' / ') || '-' }}</div>
                                    <div v-if="row.before_warehouse_name && row.before_warehouse_name !== row.after_warehouse_name" class="text-xs text-gray-400">
                                        原：{{ [row.before_warehouse_name, row.before_location_name].filter(Boolean).join(' / ') }}
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="成本变化" width="190" align="right">
                                <template #default="{ row }">
                                    <div>{{ money(row.before_total_cost) }} → {{ money(row.after_total_cost) }}</div>
                                    <div v-if="Number(row.cost_delta || 0)" class="text-xs" :class="Number(row.cost_delta || 0) > 0 ? 'text-red-500' : 'text-green-600'">
                                        {{ Number(row.cost_delta || 0) > 0 ? '+' : '' }}{{ money(row.cost_delta) }}
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="来源" min-width="160">
                                <template #default="{ row }">{{ row.source_no || row.source_type || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="说明" prop="remark" min-width="220" />
                            <el-table-column label="时间" width="180"><template #default="{ row }">{{ formatTime(row.occurred_at || row.create_at) }}</template></el-table-column>
                        </el-table>
                    </div>

                    <div class="mt-6">
                        <div class="section-title">账目流水</div>
                        <el-table :data="detail.data.account_ledgers || []" size="small">
                            <el-table-column label="类型" width="120"><template #default="{ row }">{{ bizTypeLabel(row.biz_type) }}</template></el-table-column>
                            <el-table-column label="方向" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="accountDirectionMeta(row).type" effect="plain">{{ accountDirectionMeta(row).label }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="金额" width="130" align="right"><template #default="{ row }">{{ money(row.amount) }}</template></el-table-column>
                            <el-table-column label="来源" min-width="180"><template #default="{ row }">{{ row.source_no || row.source_type || '-' }}</template></el-table-column>
                            <el-table-column label="说明" prop="remark" min-width="220" />
                            <el-table-column label="时间" width="180"><template #default="{ row }">{{ formatTime(row.create_at || row.occurred_at) }}</template></el-table-column>
                        </el-table>
                    </div>
                </template>
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { getErpStockInfo, getErpStockList, updateErpStockFlow } from '@/addon/hsx_erp/api/erp'

const search = reactive({ keyword: '', status: '', refurbish_status: '', sale_target: '' })
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const detail = reactive({ visible: false, loading: false, data: null as any })
const flow = reactive({ visible: false, saving: false, row: null as any, form: defaultFlowForm() })

const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    if (row.status === 'in_stock') acc.cost += Number(row.total_cost || 0)
    if (row.status === 'in_stock') acc.inStockCount += 1
    if (['pending', 'processing'].includes(row.refurbish_status || '')) acc.needRefurbish += 1
    if (row.status === 'in_stock' && !['pending', 'processing'].includes(row.refurbish_status || '')) acc.saleable += 1
    if (row.status === 'sold') acc.sold += 1
    return acc
}, { count: 0, cost: 0, inStockCount: 0, needRefurbish: 0, saleable: 0, sold: 0 }))

onMounted(loadList)

function stockAgeDays(stockInAt: number): number {
    if (!stockInAt) return 0
    return Math.floor((Date.now() / 1000 - stockInAt) / 86400)
}

function stockAgeDaysClass(stockInAt: number): string {
    const days = stockAgeDays(stockInAt)
    if (days >= 90) return 'text-red-600 font-medium'
    if (days >= 30) return 'text-orange-500'
    return 'text-gray-600'
}

function defaultFlowForm() {
    return {
        refurbish_status: 'none',
        sale_target: 'unset',
        listing_status: 'none',
        estimate_sale_price: 0,
        retail_price: 0,
        image_urls: '',
        quality_remark: '',
        remark_public: '',
        remark_internal: '',
    }
}

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpStockList({ ...search, page: table.page, limit: table.limit })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    search.keyword = ''
    search.status = ''
    search.refurbish_status = ''
    search.sale_target = ''
    handleSearch()
}

async function openDetail(row: any) {
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpStockInfo(row.id)
        detail.data = res?.data || null
    } finally {
        detail.loading = false
    }
}

function openFlow(row: any) {
    flow.row = row
    flow.form = {
        refurbish_status: row.refurbish_status || 'none',
        sale_target: row.sale_target || 'unset',
        listing_status: row.listing_status || 'none',
        estimate_sale_price: Number(row.estimate_sale_price || 0),
        retail_price: Number(row.retail_price || 0),
        image_urls: row.image_urls || '',
        quality_remark: row.quality_remark || '',
        remark_public: row.remark_public || '',
        remark_internal: row.remark_internal || '',
    }
    flow.visible = true
}

function onTargetChange(value: string) {
    if (value === 'peer') flow.form.listing_status = 'none'
    if (value === 'mall' && flow.form.listing_status === 'none') {
        flow.form.listing_status = flow.form.image_urls ? (Number(flow.form.estimate_sale_price || 0) > 0 ? 'ready' : 'need_price') : 'need_photo'
    }
}

async function submitFlow() {
    if (!flow.row?.id) return
    flow.saving = true
    try {
        await updateErpStockFlow(flow.row.id, flow.form)
        ElMessage.success('设备流转已更新')
        flow.visible = false
        await loadList()
        if (detail.visible && detail.data?.id === flow.row.id) await openDetail(flow.row)
    } finally {
        flow.saving = false
    }
}

function assetStatusMeta(status: string) {
    const map: any = {
        in_stock: { label: '库存中', type: 'success' },
        sold: { label: '已售', type: 'info' },
        returned: { label: '已退', type: 'warning' },
        void: { label: '作废', type: 'danger' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function refurbishMeta(status: string) {
    const map: any = {
        none: { label: '无需整备', type: 'info' },
        pending: { label: '待整备', type: 'warning' },
        processing: { label: '整备中', type: 'danger' },
        done: { label: '整备完成', type: 'success' }
    }
    return map[status || 'none'] || { label: status || '-', type: 'info' }
}

function targetMeta(status: string) {
    const map: any = {
        unset: { label: '去向未定', type: 'info' },
        peer: { label: '卖同行', type: 'success' },
        mall: { label: '上商城', type: 'primary' }
    }
    return map[status || 'unset'] || { label: status || '-', type: 'info' }
}

function listingMeta(status: string) {
    const map: any = {
        none: { label: '不上架', type: 'info' },
        need_photo: { label: '待拍照', type: 'warning' },
        need_price: { label: '待定价', type: 'warning' },
        ready: { label: '可上架', type: 'success' },
        listed: { label: '已上架', type: 'primary' }
    }
    return map[status || 'none'] || { label: status || '-', type: 'info' }
}

function financeStatusLabel(status: string) {
    const map: any = { pending: '待处理', partial: '部分结清', settled: '已结清' }
    return map[status] || status || '-'
}

function bizTypeLabel(type: string) {
    const map: any = { purchase: '采购', sale: '销售', adjust: '调成本', payment: '付款', receipt: '收款', offset: '折账' }
    return map[type] || type || '-'
}

function assetActionLabel(action: string) {
    const map: any = {
        inbound: '采购入库',
        sold: '销售出库',
        sale_cancel: '销售撤销',
        purchase_cancel: '采购撤销',
        cost_adjust: '成本调整',
        flow: '流转设置',
        return: '退货',
        transfer: '调拨',
        refurbish: '整备',
        void: '作废'
    }
    return map[action] || action || '-'
}

function accountDirectionMeta(row: any) {
    const direction = row?.direction || ''
    const type = row?.biz_type || ''
    if (type === 'purchase') {
        return direction === 'increase' ? { label: '应付增加', type: 'danger' } : { label: '应付减少', type: 'success' }
    }
    if (type === 'sale') {
        return direction === 'increase' ? { label: '应收增加', type: 'success' } : { label: '应收减少', type: 'danger' }
    }
    if (type === 'adjust') {
        return direction === 'increase' ? { label: '成本增加', type: 'danger' } : { label: '成本减少', type: 'success' }
    }
    return direction === 'increase' ? { label: '增加', type: 'primary' } : { label: '减少', type: 'info' }
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function formatTime(value: any) {
    const time = Number(value || 0)
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
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
    margin-bottom: 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
</style>
