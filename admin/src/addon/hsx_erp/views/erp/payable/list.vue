<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">应付款</div>
                    <div class="mt-1 text-sm text-gray-500">先按供应商汇总应付，再查看每台设备追溯明细；付款和折账都以往来主体为入口。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Tickets" @click="openLedger">账目流水</el-button>
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">应付供应商</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">应付总额</div>
                    <div class="summary-value">{{ money(summary.amount) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">已核销</div>
                    <div class="summary-value text-green-600">{{ money(summary.settled) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">剩余应付</div>
                    <div class="summary-value text-orange-600">{{ money(summary.remain) }}</div>
                </div>
            </div>

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="供应商 / 手机号 / IMEI / 型号 / 采购单" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="时间">
                    <el-date-picker v-model="dateRange" type="daterange" value-format="X" start-placeholder="开始日期" end-placeholder="结束日期" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="search.status" clearable class="!w-[150px]" placeholder="全部">
                        <el-option label="待付款" value="pending" />
                        <el-option label="部分付款" value="partial" />
                        <el-option label="已结清" value="settled" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="供应商" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">联系人：{{ row.contact_name || '-' }} · {{ row.contact_mobile || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="应付金额" min-width="220">
                    <template #default="{ row }">
                        <div>应付 {{ money(row.amount) }}</div>
                        <div class="mt-1 text-xs text-gray-500">已核销 {{ money(row.settled_amount) }} · 剩余 {{ money(remain(row)) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="应付批次" width="110">
                    <template #default="{ row }">{{ row.payable_count || 0 }}</template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="partyStatusMeta(row).type">{{ partyStatusMeta(row).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="最近发生" width="170">
                    <template #default="{ row }">{{ formatTime(row.latest_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="210" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openItems(row)">查看列表</el-button>
                        <el-button :disabled="remain(row) <= 0" type="primary" link @click="openPay(row)">确认付款</el-button>
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

        <el-dialog v-model="pay.visible" title="财务确认付款" width="880px">
            <div v-if="pay.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>付款对象：<span class="font-medium text-gray-900">{{ pay.row.party_name }}</span></div>
                <div class="mt-1">本次勾选付款：<span class="font-medium text-orange-600">{{ money(paySelectedTotal) }}</span></div>
            </div>
            <el-form label-width="100px">
                <el-form-item label="付款账户">
                    <el-select v-model="pay.form.capital_account_id" clearable class="w-full" placeholder="选择银行卡/微信/支付宝">
                        <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="设备明细" required>
                    <el-table :data="pay.items" v-loading="pay.loading" size="small" max-height="320" class="w-full">
                        <el-table-column width="54">
                            <template #default="{ row }">
                                <el-checkbox v-model="row.checked" :disabled="Number(row.allocated_remain || 0) <= 0" />
                            </template>
                        </el-table-column>
                        <el-table-column label="设备" min-width="230">
                            <template #default="{ row }">
                                <div class="font-medium">{{ row.model || '-' }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="剩余应付" width="120" align="right">
                            <template #default="{ row }">{{ money(row.allocated_remain) }}</template>
                        </el-table-column>
                        <el-table-column label="本次付款" width="160">
                            <template #default="{ row }">
                                <el-input-number
                                    v-model="row.pay_amount"
                                    :disabled="!row.checked"
                                    :min="0"
                                    :max="Number(row.allocated_remain || 0)"
                                    :precision="2"
                                    :controls="false"
                                    class="!w-[130px]"
                                />
                            </template>
                        </el-table-column>
                    </el-table>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="pay.form.remark" type="textarea" :rows="2" placeholder="如：已核对银行卡流水" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="pay.visible = false">取消</el-button>
                <el-button type="primary" :loading="pay.saving" @click="submitPay">确认付款</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="items.visible" :title="`${items.row?.party_name || '供应商'} · 设备追溯`" size="78%">
            <el-table :data="items.data" v-loading="items.loading" size="large">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">资产号：{{ row.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="150">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column prop="purchase_no" label="采购单" min-width="170" />
                <el-table-column label="应付" min-width="180" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.total_cost) }}</div>
                        <div class="mt-1 text-xs text-gray-500">已核销 {{ money(row.allocated_paid) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="剩余" width="130" align="right">
                    <template #default="{ row }">{{ money(row.allocated_remain) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }"><el-tag :type="statusMeta(row.payable_status).type">{{ statusMeta(row.payable_status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="采购时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.purchase_at) }}</template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="items.page" v-model:page-size="items.limit" layout="total, prev, pager, next" :total="items.total" @current-change="loadItems" />
            </div>
        </el-drawer>

        <el-drawer v-model="ledger.visible" title="账目流水" size="72%">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <el-input v-model.trim="ledger.keyword" clearable class="!w-[260px]" placeholder="流水号 / 往来单位 / 单号 / 备注" @keyup.enter="loadLedger" />
                <el-button type="primary" @click="loadLedger">查询</el-button>
                <el-button @click="ledger.keyword = ''; loadLedger()">重置</el-button>
            </div>
            <el-table :data="ledger.data" v-loading="ledger.loading" size="large">
                <el-table-column prop="ledger_no" label="流水号" min-width="180" />
                <el-table-column label="业务" width="110">
                    <template #default="{ row }"><el-tag effect="plain">{{ bizText(row.biz_type) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="方向" width="100">
                    <template #default="{ row }">{{ row.direction === 'increase' ? '增加' : row.direction === 'decrease' ? '减少' : '-' }}</template>
                </el-table-column>
                <el-table-column label="金额" width="130" align="right">
                    <template #default="{ row }">{{ money(row.amount) }}</template>
                </el-table-column>
                <el-table-column prop="party_name" label="往来单位" min-width="150" />
                <el-table-column prop="source_no" label="来源单号" min-width="160" />
                <el-table-column prop="remark" label="备注" min-width="180" />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="ledger.page"
                    v-model:page-size="ledger.limit"
                    layout="total, prev, pager, next"
                    :total="ledger.total"
                    @current-change="loadLedger"
                />
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Search, Tickets } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { confirmErpPayableItemsPayment, getErpAccountLedger, getErpPayableList, getErpPayablePartyItems } from '@/addon/hsx_erp/api/erp'

const search = reactive({ keyword: '', status: '' })
const dateRange = ref<any[]>([])
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const pay = reactive({ visible: false, saving: false, loading: false, row: null as any, items: [] as any[], form: { capital_account_id: 0, remark: '' } })
const items = reactive({ visible: false, loading: false, row: null as any, data: [] as any[], page: 1, limit: 15, total: 0 })
const ledger = reactive({ visible: false, loading: false, keyword: '', data: [] as any[], page: 1, limit: 15, total: 0 })

const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.amount || 0)
    acc.settled += Number(row.settled_amount || 0)
    acc.remain += remain(row)
    return acc
}, { count: 0, amount: 0, settled: 0, remain: 0 }))

const paySelectedTotal = computed(() => pay.items.reduce((sum: number, row: any) => {
    if (!row.checked) return sum
    return sum + Number(row.pay_amount || 0)
}, 0))

onMounted(() => {
    loadList()
    loadAccounts()
})

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpPayableList({
            ...search,
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
            page: table.page,
            limit: table.limit
        })
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

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    search.keyword = ''
    search.status = ''
    dateRange.value = []
    handleSearch()
}

async function openPay(row: any) {
    pay.row = row
    pay.items = []
    pay.form = { capital_account_id: accounts.value[0]?.id || 0, remark: '' }
    pay.visible = true
    pay.loading = true
    try {
        const res: any = await getErpPayablePartyItems(row.party_id, {
            ...search,
            status: '',
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
            page: 1,
            limit: 200
        })
        pay.items = (res?.data?.data || [])
            .filter((item: any) => Number(item.allocated_remain || 0) > 0 && Number(item.asset_payable_id || 0) > 0)
            .map((item: any) => ({ ...item, checked: true, pay_amount: Number(item.allocated_remain || 0) }))
        if (!pay.items.length && remain(row) > 0) {
            ElMessage.warning('当前应付缺少设备级账目，请用新采购开单数据验证')
        }
    } finally {
        pay.loading = false
    }
}

async function submitPay() {
    if (!pay.row) return
    const selected = pay.items
        .filter((row: any) => row.checked && Number(row.pay_amount || 0) > 0)
        .map((row: any) => ({ payable_id: Number(row.payable_id || 0), amount: Number(row.pay_amount || 0) }))
    if (!selected.length) return ElMessage.warning('请选择要付款的设备')
    if (paySelectedTotal.value <= 0) return ElMessage.warning('请填写付款金额')
    if (!pay.form.capital_account_id) return ElMessage.warning('请选择付款账户')
    pay.saving = true
    try {
        await confirmErpPayableItemsPayment(pay.row.party_id, { ...pay.form, items: selected })
        ElMessage.success('付款已确认')
        pay.visible = false
        await loadList()
        if (items.visible) await loadItems()
        await loadAccounts()
    } finally {
        pay.saving = false
    }
}

function openItems(row: any) {
    items.row = row
    items.page = 1
    items.visible = true
    loadItems()
}

async function loadItems() {
    if (!items.row?.party_id) return
    items.loading = true
    try {
        const res: any = await getErpPayablePartyItems(items.row.party_id, {
            ...search,
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
            page: items.page,
            limit: items.limit
        })
        items.data = res?.data?.data || []
        items.total = res?.data?.total || 0
    } finally {
        items.loading = false
    }
}

function openLedger() {
    ledger.visible = true
    loadLedger()
}

async function loadLedger() {
    ledger.loading = true
    try {
        const res: any = await getErpAccountLedger({ keyword: ledger.keyword, page: ledger.page, limit: ledger.limit })
        ledger.data = res?.data?.data || []
        ledger.total = res?.data?.total || 0
    } finally {
        ledger.loading = false
    }
}

function remain(row: any) {
    return Math.max(0, Number(row.amount || 0) - Number(row.settled_amount || 0))
}

function statusMeta(status: string) {
    const map: any = {
        pending: { label: '待付款', type: 'warning' },
        partial: { label: '部分付款', type: 'primary' },
        settled: { label: '已结清', type: 'success' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function partyStatusMeta(row: any) {
    const amount = Number(row.amount || 0)
    const settled = Number(row.settled_amount || 0)
    if (amount > 0 && settled >= amount) return { label: '已结清', type: 'success' }
    if (settled > 0) return { label: '部分付款', type: 'primary' }
    return { label: '待付款', type: 'warning' }
}

function sourceText(type: string) {
    return type === 'purchase' ? '采购单' : type || '-'
}

function bizText(type: string) {
    const map: any = { purchase: '采购', payment: '付款', adjust: '调整', offset: '折账', sale: '销售', receipt: '收款' }
    return map[type] || type || '-'
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
</style>
