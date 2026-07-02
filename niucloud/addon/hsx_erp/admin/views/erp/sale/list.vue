<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">销售出库</div>
                    <div class="mt-1 text-sm text-gray-500">从库存选择机器销售，系统自动计算成本、毛利，并生成应收款。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate">销售出库</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile"><div class="summary-label">销售单数</div><div class="summary-value">{{ summary.count }}</div></div>
                <div class="summary-tile"><div class="summary-label">销售金额</div><div class="summary-value">{{ money(summary.amount) }}</div></div>
                <div class="summary-tile"><div class="summary-label">总成本</div><div class="summary-value">{{ money(summary.cost) }}</div></div>
                <div class="summary-tile"><div class="summary-label">毛利</div><div class="summary-value text-green-600">{{ money(summary.profit) }}</div></div>
            </div>

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[260px]" placeholder="销售单号 / 客户 / 渠道" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="财务状态">
                    <el-select v-model="search.finance_status" clearable class="!w-[150px]" placeholder="全部">
                        <el-option label="待收款" value="pending" />
                        <el-option label="部分收款" value="partial" />
                        <el-option label="已结清" value="settled" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column prop="sale_no" label="销售单号" min-width="170" />
                <el-table-column prop="party_name" label="销售客户" min-width="150" />
                <el-table-column prop="sale_channel" label="销售渠道" min-width="120" />
                <el-table-column label="金额" min-width="230">
                    <template #default="{ row }">
                        <div>销售 {{ money(row.total_amount) }}</div>
                        <div class="mt-1 text-xs text-gray-500">成本 {{ money(row.total_cost) }} · 毛利 {{ money(row.profit) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="收款" min-width="220">
                    <template #default="{ row }">
                        <div>已收 {{ money(row.received_amount) }}</div>
                        <div class="mt-1 text-xs text-gray-500">剩余 {{ money(row.receivable_amount) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="财务状态" width="120">
                    <template #default="{ row }"><el-tag :type="statusMeta(row.finance_status).type">{{ statusMeta(row.finance_status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="销售时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.sale_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="120" align="center">
                    <template #default="{ row }"><el-button type="primary" link @click="openDetail(row)">详情</el-button></template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit" layout="total, sizes, prev, pager, next, jumper" :total="table.total" @size-change="loadList" @current-change="loadList" />
            </div>
        </el-card>

        <el-dialog v-model="create.visible" title="销售出库" width="980px" top="5vh">
            <el-form label-width="96px">
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="销售客户" required><counterparty-select v-model="create.form.party_id" role-type="customer" placeholder="搜索或新建销售客户" @resolved="onPartyResolved" /></el-form-item>
                    <el-form-item label="销售渠道"><el-input v-model.trim="create.form.sale_channel" placeholder="如：门店 / 同行 / 小程序" /></el-form-item>
                    <el-form-item label="结算方式">
                        <el-select v-model="create.form.settle_mode" class="w-full">
                            <el-option label="挂账，稍后收款" value="credit" />
                            <el-option label="现结，本次收款" value="cash" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="本次收款" required><el-input-number v-model="create.form.received_amount" :min="0" :precision="2" :controls="false" class="!w-[180px]" /></el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="收款账户" required>
                        <el-select v-model="create.form.capital_account_id" class="w-full" placeholder="选择账户">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="备注" class="md:col-span-2"><el-input v-model.trim="create.form.remark" type="textarea" :rows="2" /></el-form-item>
                </div>
            </el-form>

            <div class="mb-3 flex items-center justify-between">
                <div class="font-medium">待售库存</div>
                <div class="flex gap-2">
                    <el-input v-model.trim="stock.keyword" clearable class="!w-[240px]" placeholder="型号 / IMEI / 采购渠道" @keyup.enter="loadStock" />
                    <el-button :icon="Search" @click="loadStock">查询</el-button>
                </div>
            </div>
            <el-table :data="stock.data" v-loading="stock.loading" size="small" max-height="300" @selection-change="onStockSelection">
                <el-table-column type="selection" width="48" />
                <el-table-column prop="asset_no" label="资产号" min-width="150" />
                <el-table-column label="机器" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="party_name" label="采购来源" min-width="130" />
                <el-table-column label="位置" min-width="150">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="总成本" width="120" align="right"><template #default="{ row }">{{ money(row.total_cost) }}</template></el-table-column>
                <el-table-column label="销售价" width="150" align="right">
                    <template #default="{ row }"><el-input-number v-model="salePrices[row.id]" :min="0" :precision="2" :controls="false" class="!w-[120px]" /></template>
                </el-table-column>
            </el-table>
            <div class="mt-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">已选 {{ selectedAssets.length }} 台 · 成本 {{ money(selectedCost) }} · 销售 {{ money(selectedAmount) }} · 预计毛利 {{ money(selectedProfit) }}</div>
                <el-pagination v-model:current-page="stock.page" v-model:page-size="stock.limit" layout="total, prev, pager, next" :total="stock.total" @current-change="loadStock" />
            </div>

            <template #footer>
                <el-button @click="create.visible = false">取消</el-button>
                <el-button type="primary" :loading="create.saving" @click="submitCreate">确认出库</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="销售单详情" size="62%">
            <div v-loading="detail.loading">
                <el-descriptions v-if="detail.data" :column="2" border>
                    <el-descriptions-item label="销售单号">{{ detail.data.sale_no }}</el-descriptions-item>
                    <el-descriptions-item label="客户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="销售金额">{{ money(detail.data.total_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="毛利">{{ money(detail.data.profit) }}</el-descriptions-item>
                </el-descriptions>
                <el-table class="mt-4" :data="detail.data?.items || []" size="large">
                    <el-table-column prop="model" label="型号" min-width="160" />
                    <el-table-column prop="imei" label="IMEI" min-width="160" />
                    <el-table-column label="成本" width="120" align="right"><template #default="{ row }">{{ money(row.cost) }}</template></el-table-column>
                    <el-table-column label="售价" width="120" align="right"><template #default="{ row }">{{ money(row.sale_price) }}</template></el-table-column>
                    <el-table-column label="毛利" width="120" align="right"><template #default="{ row }">{{ money(row.profit) }}</template></el-table-column>
                </el-table>
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { confirmErpReceipt, createErpSale, getErpSaleInfo, getErpSaleList, getErpSaleStock } from '@/addon/hsx_erp/api/erp'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'

const search = reactive({ keyword: '', finance_status: '' })
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const stock = reactive({ loading: false, data: [] as any[], keyword: '', page: 1, limit: 8, total: 0 })
const accounts = ref<any[]>([])
const selectedAssets = ref<any[]>([])
const salePrices = reactive<Record<number, number>>({})
const create = reactive({ visible: false, saving: false, form: defaultForm() })
const detail = reactive({ visible: false, loading: false, data: null as any })

const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.total_amount || 0)
    acc.cost += Number(row.total_cost || 0)
    acc.profit += Number(row.profit || 0)
    return acc
}, { count: 0, amount: 0, cost: 0, profit: 0 }))
const selectedCost = computed(() => selectedAssets.value.reduce((sum, row) => sum + Number(row.total_cost || 0), 0))
const selectedAmount = computed(() => selectedAssets.value.reduce((sum, row) => sum + Number(salePrices[row.id] || 0), 0))
const selectedProfit = computed(() => selectedAmount.value - selectedCost.value)

watch(() => create.form.settle_mode, () => {
    if (create.form.settle_mode === 'cash') create.form.received_amount = selectedAmount.value
})
watch(selectedAmount, amount => {
    if (create.form.settle_mode === 'cash') create.form.received_amount = amount
})

onMounted(() => {
    loadList()
    loadAccounts()
})

function defaultForm() {
    return { party_id: 0, party_name: '', sale_channel: '', settle_method: '', settle_mode: 'credit', received_amount: 0, capital_account_id: 0, remark: '' }
}

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpSaleList({ ...search, page: table.page, limit: table.limit })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
}

async function loadStock() {
    stock.loading = true
    try {
        const res: any = await getErpSaleStock({ keyword: stock.keyword, page: stock.page, limit: stock.limit })
        stock.data = res?.data?.data || []
        stock.total = res?.data?.total || 0
        stock.data.forEach((row: any) => {
            if (!salePrices[row.id]) salePrices[row.id] = Number(row.total_cost || 0)
        })
    } finally {
        stock.loading = false
    }
}

async function loadAccounts() {
    const res: any = await getCapitalAccounts()
    accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
}

function openCreate() {
    create.form = defaultForm()
    selectedAssets.value = []
    stock.keyword = ''
    create.visible = true
    loadStock()
    loadAccounts()
}

function onStockSelection(rows: any[]) {
    selectedAssets.value = rows
}

async function submitCreate() {
    if (!create.form.party_id && !create.form.party_name) return ElMessage.warning('请选择销售客户')
    if (!selectedAssets.value.length) return ElMessage.warning('请选择要销售的库存机器')
    if (selectedAssets.value.some(row => Number(salePrices[row.id] || 0) <= 0)) return ElMessage.warning('请填写每台机器销售价')
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.received_amount || 0) <= 0) return ElMessage.warning('请填写本次收款')
        if (!create.form.capital_account_id) return ElMessage.warning('请选择收款账户')
        if (Number(create.form.received_amount) > selectedAmount.value) return ElMessage.warning('收款不能大于销售金额')
    }
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
                    remark: '销售现结收款'
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
        const res: any = await getErpSaleInfo(row.id)
        detail.data = res?.data || null
    } finally {
        detail.loading = false
    }
}

function handleSearch() { table.page = 1; loadList() }
function handleReset() { search.keyword = ''; search.finance_status = ''; handleSearch() }
function statusMeta(status: string) {
    const map: any = { pending: { label: '待收款', type: 'warning' }, partial: { label: '部分收款', type: 'primary' }, settled: { label: '已结清', type: 'success' } }
    return map[status] || { label: status || '-', type: 'info' }
}
function money(value: any) { return `¥${Number(value || 0).toFixed(2)}` }
function formatTime(value: any) { return Number(value || 0) ? new Date(Number(value) * 1000).toLocaleString() : '-' }
</script>

<style scoped>
.summary-tile { border-radius: 8px; background: #f8fafc; padding: 14px 16px; }
.summary-label { color: #64748b; font-size: 13px; }
.summary-value { margin-top: 6px; color: #0f172a; font-size: 22px; font-weight: 700; }
</style>
