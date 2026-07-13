<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">销售出库</div>
                    <div class="mt-1 text-sm text-gray-500">以每台设备为核心查看销售出库、成交金额、毛利、客户和收款状态；销售单作为批次凭证保留。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate()">销售出库</el-button>
                </div>
            </div>

            <ErpRoleFocus :items="saleRoleFocus" />

            <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm font-medium text-gray-700">本页有效销售汇总</div>
                <div class="text-xs text-gray-400">已取消、已退货设备不计入；毛利为成交额减设备成本</div>
            </div>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">有效销售台数</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">实际销售收入</div>
                    <div class="summary-value">{{ money(summary.amount) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">有效设备成本</div>
                    <div class="summary-value">{{ money(summary.cost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">实际毛利</div>
                    <div class="summary-value" :class="summary.profit >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(summary.profit) }}</div>
                </div>
            </div>

            <!-- 状态快筛 Tab -->
            <el-tabs v-model="activeTab" class="mt-4 erp-status-tabs" @tab-change="onTabChange">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待收款" name="pending" />
                <el-tab-pane label="部分收款" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
            </el-tabs>

            <el-form :inline="true" class="mt-2" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="型号 / IMEI / 资产号 / 销售单 / 客户" @keyup.enter="handleSearch" />
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
                        class="!w-[220px]"
                        node-key="category_id"
                        placeholder="全部分类"
                    />
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
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.max_amount" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                </el-form-item>
                <el-form-item label="毛利">
                    <el-input-number v-model="search.min_profit" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.max_profit" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large" :row-class-name="saleRowClassName">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <ErpDeviceIdentity :model="row.model" :spec="row.spec" :imei="row.imei" :sn="row.sn" :asset-no="row.asset_no" />
                    </template>
                </el-table-column>
                <el-table-column label="客户 / 渠道" min-width="170">
                    <template #default="{ row }">
                        <div>{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">渠道：{{ row.sale_channel || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="成交 / 毛利" min-width="180" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.net_sale_amount) }}</div>
                        <div v-if="Number(row.sale_compensation_amount || 0)" class="mt-1 text-xs text-orange-500">原成交 {{ money(row.sale_price) }} · 补差 -{{ money(row.sale_compensation_amount) }}</div>
                        <div class="mt-1 text-xs text-gray-500">成本 {{ money(row.cost) }} · 毛利 {{ money(row.profit) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="160">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="销售批次" min-width="230">
                    <template #default="{ row, $index }">
                        <div class="flex items-center gap-2">
                            <span class="batch-dot" :class="`batch-dot--${batchTone(row)}`"></span>
                            <span class="font-medium">{{ row.sale_no || '-' }}</span>
                        </div>
                        <div v-if="isBatchFirst($index)" class="mt-1 text-xs font-medium text-blue-600">本页同批 {{ batchPageSize(row) }} 台</div>
                        <div class="mt-1 text-xs text-slate-500">来源：{{ row.origin_name || 'ERP销售' }}<span v-if="row.origin_plugin_name">· {{ row.origin_plugin_name }}</span></div>
                        <div class="mt-1 text-xs text-gray-500">渠道：{{ row.sale_channel || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.sale_at || row.create_at) }}</div>
                        <div class="mt-1 text-xs text-gray-400">业务员：{{ row.salesman_name || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="当前状态" min-width="180">
                    <template #default="{ row }">
                        <div class="sale-status-stack">
                            <el-tag :type="saleStateMeta(row).type" effect="plain">{{ saleStateMeta(row).label }}</el-tag>
                            <span v-if="showFinanceStatus(row)" class="sale-status-stack__finance">收款 · {{ statusMeta(row.finance_status).label }}</span>
                            <span v-if="saleStateHint(row)" class="sale-status-stack__hint">{{ saleStateHint(row) }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="210" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">销售单</el-button>
                        <el-button v-if="canReturnSale(row)" type="warning" link @click="goSaleReturn(row)">销售退货</el-button>
                        <el-button v-if="canCancelSaleItemFromList(row)" type="danger" link @click="cancelSaleItem(row)">取消销售</el-button>
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

        <el-dialog v-model="create.visible" title="销售出库" width="980px" top="5vh" destroy-on-close>
            <el-form label-width="96px">
                <div class="section-title">1. 客户</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <el-form-item label="销售客户" required>
                        <counterparty-select v-model="create.form.party_id" role-type="customer" placeholder="搜索或新建销售客户" @resolved="onPartyResolved" />
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

                <div class="section-title">2. 货品</div>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="font-medium">待售库存</div>
                    <div class="flex flex-wrap gap-2">
                        <el-input v-model.trim="stock.keyword" clearable class="!w-[220px]" placeholder="型号 / IMEI / 采购来源" @keyup.enter="reloadStock" />
                        <el-select v-model="stock.warehouse_id" clearable class="!w-[130px]" placeholder="仓库" @change="onStockWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                        <el-select v-model="stock.location_id" clearable class="!w-[130px]" placeholder="库位" :disabled="!stock.warehouse_id" @change="reloadStock">
                            <el-option v-for="item in stockLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                        <el-tree-select
                            v-model="stock.category_id"
                            :data="categoryTree"
                            :props="{ label: 'category_name', value: 'category_id', children: 'child_list' }"
                            check-strictly
                            clearable
                            class="!w-[170px]"
                            node-key="category_id"
                            placeholder="分类"
                            @change="reloadStock"
                        />
                        <el-button :icon="Search" @click="loadStock">查询</el-button>
                    </div>
                </div>
                <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="sale-pick-metric">
                        <div class="metric-label">已选设备</div>
                        <div class="metric-value">{{ selectedAssets.length }} 台</div>
                    </div>
                    <div class="sale-pick-metric">
                        <div class="metric-label">预计销售</div>
                        <div class="metric-value text-blue-600">{{ money(selectedAmount) }}</div>
                    </div>
                    <div class="sale-pick-metric">
                        <div class="metric-label">预计毛利</div>
                        <div class="metric-value" :class="selectedProfit >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(selectedProfit) }}</div>
                    </div>
                </div>
                <el-table ref="stockTableRef" class="sale-select-table" :data="stock.data" v-loading="stock.loading" size="small" max-height="360" @row-click="onStockRowClick" @selection-change="onStockSelection">
                    <el-table-column type="selection" width="48" />
                    <el-table-column label="设备" min-width="280">
                        <template #default="{ row }">
                            <div class="font-medium text-gray-900">{{ row.model || '-' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ compactDeviceInfo(row) }}</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <el-tag v-if="row.category_name" size="small" effect="plain" type="info">{{ row.category_name }}</el-tag>
                                <el-tooltip v-if="row.asset_no" :content="`资产号：${row.asset_no}`" placement="top">
                                    <el-tag size="small" effect="plain">资产</el-tag>
                                </el-tooltip>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="来源 / 位置" min-width="170">
                        <template #default="{ row }">
                            <div>{{ row.party_name || '-' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="总成本" width="120" align="right"><template #default="{ row }">{{ money(row.total_cost) }}</template></el-table-column>
                    <el-table-column label="销售价" width="150" align="right">
                        <template #default="{ row }"><el-input-number v-model="salePrices[row.id]" :min="0" :precision="2" :controls="false" class="!w-[120px]" /></template>
                    </el-table-column>
                </el-table>
                <div class="mt-3 flex items-center justify-between">
                    <div class="text-sm text-gray-500">成本 {{ money(selectedCost) }} · 当前筛选仅展示可直接销售库存</div>
                    <el-pagination v-model:current-page="stock.page" v-model:page-size="stock.limit" layout="total, prev, pager, next" :total="stock.total" @current-change="loadStock" />
                </div>

                <div class="section-title">3. 账目</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <el-form-item label="结算方式">
                        <el-select v-model="create.form.settle_mode" class="w-full">
                            <el-option label="挂账，稍后收款" value="credit" />
                            <el-option label="现结，本次收款" value="cash" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="本次收款" required>
                        <el-input-number v-model="create.form.received_amount" :min="0" :precision="2" :controls="false" class="!w-full" />
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="收款账户" required>
                        <el-select v-model="create.form.capital_account_id" class="w-full" placeholder="选择账户">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-form-item v-if="create.form.settle_mode === 'cash'" label="收款凭证"><ErpFinanceVoucherUpload v-model="create.form.voucher_urls" /></el-form-item>
                <el-form-item class="mt-4" label="备注">
                    <el-input v-model.trim="create.form.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="create.visible = false">取消</el-button>
                <el-button type="primary" :loading="create.saving" @click="submitCreate">确认出库</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="销售单详情" size="72%" destroy-on-close>
            <div v-loading="detail.loading">
                <el-descriptions v-if="detail.data" :column="4" border>
                    <el-descriptions-item label="销售单号">{{ detail.data.sale_no }}</el-descriptions-item>
                    <el-descriptions-item label="客户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="业务来源">{{ detail.data.origin_name || 'ERP销售' }}<span v-if="detail.data.origin_plugin_name">· {{ detail.data.origin_plugin_name }}</span></el-descriptions-item>
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
                <div class="mt-5 font-medium">机器明细</div>
                <el-table class="mt-3" :data="detail.data?.items || []" size="large">
                    <el-table-column prop="model" label="型号" min-width="180" />
                    <el-table-column prop="imei" label="IMEI" min-width="170" />
                    <el-table-column label="成本" width="130" align="right"><template #default="{ row }">{{ money(row.cost) }}</template></el-table-column>
                    <el-table-column label="销售收入" width="180" align="right"><template #default="{ row }"><div>{{ money(row.net_sale_amount) }}</div><div v-if="Number(row.sale_compensation_amount || 0)" class="text-xs text-orange-500">原价 {{ money(row.sale_price) }} · 补差 -{{ money(row.sale_compensation_amount) }}</div></template></el-table-column>
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
                            <span v-else class="text-xs text-gray-400">-</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpSaleChannelOptions } from '@/addon/hsx_erp/api/config'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { cancelErpSaleItem, confirmErpReceipt, createErpSale, getErpGoodsCategoryTree, getErpSaleInfo, getErpSaleList, getErpSaleStock, getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpDeviceIdentity from '@/addon/hsx_erp/components/ErpDeviceIdentity.vue'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'

const search = reactive<any>({ keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', category_id: '', salesman_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined, min_profit: undefined, max_profit: undefined })
const activeTab = ref('')
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
const stock = reactive({ loading: false, data: [] as any[], keyword: '', warehouse_id: '', location_id: '', category_id: '', page: 1, limit: 8, total: 0 })
const accounts = ref<any[]>([])
const staffOptions = ref<any[]>([])
const warehouses = ref<any[]>([])
const categoryTree = ref<any[]>([])
const saleChannelOptions = ref<any[]>([])
const currentUid = ref(0)
const selectedAssets = ref<any[]>([])
const pendingAssetIds = ref<number[]>([])
const stockTableRef = ref<any>()
const salePrices = reactive<Record<number, number>>({})
const create = reactive({ visible: false, saving: false, form: defaultForm() })
const detail = reactive({ visible: false, loading: false, data: null as any })

const summary = computed(() => table.data.reduce((acc, row: any) => {
    if (row.order_status === 'void' || row.status !== 'sold') return acc
    acc.count += 1
    acc.amount += Number(row.net_sale_amount || 0)
    acc.cost += Number(row.cost || 0)
    acc.profit += Number(row.profit || 0)
    return acc
}, { count: 0, amount: 0, cost: 0, profit: 0 }))
const selectedCost = computed(() => selectedAssets.value.reduce((sum, row) => sum + Number(row.total_cost || 0), 0))
const selectedAmount = computed(() => selectedAssets.value.reduce((sum, row) => sum + Number(salePrices[row.id] || 0), 0))
const selectedProfit = computed(() => selectedAmount.value - selectedCost.value)
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

onMounted(() => {
    loadAccounts()
    loadStaffOptions()
    loadWarehouses()
    loadCategories()
    loadSaleChannels()
    const assetIds = String(route.query.asset_ids || '').split(',').map(Number).filter(id => id > 0)
    if (assetIds.length) openCreate(assetIds)
})
useErpPageRefresh(loadList)

function defaultForm() {
    return { party_id: 0, party_name: '', sale_channel: '', sale_channel_key: '', channel_source_plugin: '', channel_source_key: '', salesman_uid: 0, settle_method: '', settle_mode: 'credit', received_amount: 0, capital_account_id: 0, voucher_urls: '', remark: '' }
}

async function loadSaleChannels() {
    const res: any = await getErpSaleChannelOptions()
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
    table.loading = true
    try {
        const res: any = await getErpSaleList({ ...buildSearchParams(), page: table.page, limit: table.limit })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
}

async function loadStock() {
    stock.loading = true
    try {
        const res: any = await getErpSaleStock({
            keyword: stock.keyword,
            warehouse_id: stock.warehouse_id,
            location_id: stock.location_id,
            category_id: stock.category_id,
            asset_ids: pendingAssetIds.value,
            page: stock.page,
            limit: stock.limit
        })
        stock.data = res?.data?.data || []
        stock.total = res?.data?.total || 0
        stock.data.forEach((row: any) => {
            if (!salePrices[row.id]) salePrices[row.id] = Number(row.retail_price || row.estimate_sale_price || row.total_cost || 0)
        })
        if (pendingAssetIds.value.length) {
            selectedAssets.value = stock.data.filter((row: any) => pendingAssetIds.value.includes(Number(row.id)))
            await nextTick()
            selectedAssets.value.forEach(row => stockTableRef.value?.toggleRowSelection(row, true))
        }
    } finally {
        stock.loading = false
    }
}

function reloadStock() {
    stock.page = 1
    loadStock()
}

async function loadAccounts() {
    const res: any = await getCapitalAccounts()
    accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
}

async function loadStaffOptions() {
    const res: any = await getErpStaffOptions()
    currentUid.value = Number(res?.data?.current_uid || 0)
    staffOptions.value = res?.data?.users || []
    if (!create.form.salesman_uid) {
        create.form.salesman_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    }
}

async function loadWarehouses() {
    const res: any = await getErpWarehouseOptions()
    warehouses.value = Array.isArray(res?.data) ? res.data : []
}

async function loadCategories() {
    const res: any = await getErpGoodsCategoryTree()
    categoryTree.value = Array.isArray(res?.data) ? res.data : []
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
    create.form = defaultForm()
    create.form.salesman_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    const preferredChannel = saleChannelOptions.value.find((row: any) => Number(row.is_default || 0) === 1) || saleChannelOptions.value[0]
    if (preferredChannel) onSaleChannelChange(preferredChannel.key)
    selectedAssets.value = []
    pendingAssetIds.value = assetIds
    stock.keyword = ''
    stock.warehouse_id = ''
    stock.location_id = ''
    stock.category_id = ''
    stock.page = 1
    stock.limit = assetIds.length ? Math.max(8, Math.min(200, assetIds.length)) : 8
    create.visible = true
    loadStock()
    loadAccounts()
    loadStaffOptions()
}

function onStockSelection(rows: any[]) {
    selectedAssets.value = rows
}

function onStockRowClick(row: any, _column: any, event: MouseEvent) {
    const target = event?.target as HTMLElement | null
    if (target?.closest('input,button,a,.el-input-number,.el-checkbox')) return
    stockTableRef.value?.toggleRowSelection(row)
}

async function submitCreate() {
    if (!create.form.party_id && !create.form.party_name) return ElMessage.warning('请选择销售客户')
    if (!create.form.sale_channel_key) return ElMessage.warning('请选择销售渠道')
    if (!create.form.salesman_uid) return ElMessage.warning('请选择制单员')
    if (!selectedAssets.value.length) return ElMessage.warning('请选择要销售的库存机器')
    if (selectedAssets.value.some(row => Number(salePrices[row.id] || 0) <= 0)) return ElMessage.warning('请填写每台机器销售价')
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.received_amount || 0) <= 0) return ElMessage.warning('请填写本次收款')
        if (!create.form.capital_account_id) return ElMessage.warning('请选择收款账户')
        if (Number(create.form.received_amount) > selectedAmount.value) return ElMessage.warning('收款不能大于销售金额')
    }
    const confirmed = await ElMessageBox.confirm(
        `确认向「${create.form.party_name || '所选客户'}」销售出库 ${selectedAssets.value.length} 台，销售总额 ${money(selectedAmount.value)}。提交后设备立即退出库存并生成应收；${create.form.settle_mode === 'cash' ? `同时确认现结收款 ${money(create.form.received_amount)}。` : '本次按挂账处理。'}普通操作不能直接撤销。`,
        '确认销售出库',
        { type: 'warning', confirmButtonText: '确认出库', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    create.saving = true
    try {
        const saleRes: any = await createErpSale({
            ...create.form,
            settle_method: create.form.settle_mode === 'cash' ? '现结' : '挂账',
            items: selectedAssets.value.map(row => ({ asset_id: row.id, sale_price: Number(salePrices[row.id] || 0) }))
        })
        if (create.form.settle_mode === 'cash' && Number(create.form.received_amount || 0) > 0) {
            const infoRes: any = await getErpSaleInfo(saleRes?.data?.id)
            const receivable = infoRes?.data?.receivables?.[0]
            if (receivable?.id) {
                await confirmErpReceipt(receivable.id, {
                    amount: Number(create.form.received_amount),
                    capital_account_id: create.form.capital_account_id,
                    remark: '销售现结收款',
                    voucher_urls: create.form.voucher_urls
                })
            }
        }
        ElMessage.success('销售出库已完成')
        create.visible = false
        await loadList()
    } finally {
        create.saving = false
    }
}

function onPartyResolved(row: any) {
    create.form.party_name = row?.party_name || row?.name || ''
}

async function openDetail(row: any) {
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpSaleInfo(row.sale_order_id || row.id)
        detail.data = res?.data || null
    } finally {
        detail.loading = false
    }
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    Object.assign(search, { keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', category_id: '', salesman_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined, min_profit: undefined, max_profit: undefined })
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
    return row.status === 'sold'
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
    return row.status === 'sold'
        && row.order_status === 'completed'
        && row.finance_status === 'pending'
        && row.return_status !== 'pending'
}

function canCancelSaleItem(row: any) {
    return detail.data?.status === 'completed' && detail.data?.finance_status === 'pending' && row.status === 'sold' && row.return_status !== 'pending'
}

async function cancelSaleItem(row: any) {
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
        await cancelErpSaleItem(Number(row.id), { remark: result?.value || '' })
        ElMessage.success('设备销售已取消')
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
    return map[status] || { label: status || '-', type: 'info' }
}

function orderStatusMeta(status: string) {
    const map: any = {
        completed: { label: '已完成', type: 'success' },
        returned: { label: '已退货', type: 'warning' },
        void: { label: '已取消', type: 'info' }
    }
    return map[status] || { label: status || '-', type: 'info' }
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
    return map[status] || status || '-'
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

function formatTime(value: any) {
    const time = Number(value || 0)
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
}

function staffName(user: any) {
    return user?.name || user?.real_name || user?.username || `员工#${user?.uid || '-'}`
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
    return table.data.filter((item: any) => batchKey(item) === key).length
}

function saleRowClassName({ row, rowIndex }: { row: any; rowIndex: number }) {
    return [`erp-batch-tone-${batchTone(row)}`, isBatchFirst(rowIndex) ? 'erp-batch-start' : '', row.status !== 'sold' ? 'erp-sale-inactive' : ''].filter(Boolean).join(' ')
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
.sale-status-stack { display: flex; align-items: flex-start; flex-direction: column; gap: 5px; }
.sale-status-stack__finance { color: #64748b; font-size: 12px; }
.sale-status-stack__hint { overflow: hidden; max-width: 170px; color: #94a3b8; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
:deep(.el-table__body tr.erp-batch-tone-0 > td.el-table__cell) { background: #f7fbff; }
:deep(.el-table__body tr.erp-batch-tone-1 > td.el-table__cell) { background: #f7fcfa; }
:deep(.el-table__body tr.erp-batch-tone-2 > td.el-table__cell) { background: #fbf9ff; }
:deep(.el-table__body tr.erp-batch-tone-3 > td.el-table__cell) { background: #fffaf3; }
:deep(.el-table__body tr.erp-batch-start > td.el-table__cell) { border-top: 2px solid #dbe4ef; }
:deep(.el-table__body tr.erp-sale-inactive > td.el-table__cell) { color: #94a3b8; background: #f8fafc; }
:deep(.el-table__body tr:hover > td.el-table__cell) { background: #eef5ff !important; }
.section-title {
    margin: 18px 0 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
.sale-pick-metric {
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px 12px;
}
.metric-label {
    color: #64748b;
    font-size: 12px;
}
.metric-value {
    margin-top: 4px;
    color: #111827;
    font-size: 18px;
    font-weight: 650;
}
</style>
