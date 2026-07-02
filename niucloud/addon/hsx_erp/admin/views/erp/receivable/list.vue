<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">应收款</div>
                    <div class="mt-1 text-sm text-gray-500">所有收款由财务确认后才正式核销；确认时会写入资金流水和账目流水。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile"><div class="summary-label">应收笔数</div><div class="summary-value">{{ summary.count }}</div></div>
                <div class="summary-tile"><div class="summary-label">应收总额</div><div class="summary-value">{{ money(summary.amount) }}</div></div>
                <div class="summary-tile"><div class="summary-label">已核销</div><div class="summary-value text-green-600">{{ money(summary.settled) }}</div></div>
                <div class="summary-tile"><div class="summary-label">剩余应收</div><div class="summary-value text-orange-600">{{ money(summary.remain) }}</div></div>
            </div>

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[260px]" placeholder="客户 / 来源单号 / 备注" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="search.status" clearable class="!w-[150px]" placeholder="全部">
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
                <el-table-column prop="receivable_no" label="应收单号" min-width="170" />
                <el-table-column prop="party_name" label="收款客户" min-width="170" />
                <el-table-column label="来源" min-width="170">
                    <template #default="{ row }">
                        <div>{{ row.source_type === 'sale' ? '销售单' : row.source_type || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.source_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="金额" min-width="220">
                    <template #default="{ row }">
                        <div>应收 {{ money(row.amount) }}</div>
                        <div class="mt-1 text-xs text-gray-500">已核销 {{ money(row.settled_amount) }} · 剩余 {{ money(remain(row)) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }"><el-tag :type="statusMeta(row.status).type">{{ statusMeta(row.status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="发生时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="140" align="center">
                    <template #default="{ row }"><el-button :disabled="remain(row) <= 0" type="primary" link @click="openReceipt(row)">确认收款</el-button></template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit" layout="total, sizes, prev, pager, next, jumper" :total="table.total" @size-change="loadList" @current-change="loadList" />
            </div>
        </el-card>

        <el-dialog v-model="receipt.visible" title="财务确认收款" width="560px">
            <div v-if="receipt.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>收款客户：<span class="font-medium text-gray-900">{{ receipt.row.party_name }}</span></div>
                <div class="mt-1">剩余应收：<span class="font-medium text-orange-600">{{ money(remain(receipt.row)) }}</span></div>
            </div>
            <el-form label-width="100px">
                <el-form-item label="收款金额" required>
                    <el-input-number v-model="receipt.form.amount" :min="0" :precision="2" :controls="false" class="!w-[220px]" />
                </el-form-item>
                <el-form-item label="收款账户">
                    <el-select v-model="receipt.form.capital_account_id" clearable class="w-full" placeholder="选择银行卡/微信/支付宝">
                        <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="receipt.form.remark" type="textarea" :rows="2" placeholder="如：已核对到账记录" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="receipt.visible = false">取消</el-button>
                <el-button type="primary" :loading="receipt.saving" @click="submitReceipt">确认收款</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { confirmErpReceipt, getErpReceivableList } from '@/addon/hsx_erp/api/erp'

const search = reactive({ keyword: '', status: '' })
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const receipt = reactive({ visible: false, saving: false, row: null as any, form: { amount: 0, capital_account_id: 0, remark: '' } })
const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.amount || 0)
    acc.settled += Number(row.settled_amount || 0)
    acc.remain += remain(row)
    return acc
}, { count: 0, amount: 0, settled: 0, remain: 0 }))

onMounted(() => {
    loadList()
    loadAccounts()
})

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpReceivableList({ ...search, page: table.page, limit: table.limit })
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

function openReceipt(row: any) {
    receipt.row = row
    receipt.form = { amount: remain(row), capital_account_id: accounts.value[0]?.id || 0, remark: '' }
    receipt.visible = true
}

async function submitReceipt() {
    if (!receipt.row) return
    if (Number(receipt.form.amount || 0) <= 0) return ElMessage.warning('请填写收款金额')
    if (Number(receipt.form.amount) > remain(receipt.row)) return ElMessage.warning('收款金额不能大于剩余应收')
    receipt.saving = true
    try {
        await confirmErpReceipt(receipt.row.id, receipt.form)
        ElMessage.success('收款已确认')
        receipt.visible = false
        await loadList()
        await loadAccounts()
    } finally {
        receipt.saving = false
    }
}

function handleSearch() { table.page = 1; loadList() }
function handleReset() { search.keyword = ''; search.status = ''; handleSearch() }
function remain(row: any) { return Math.max(0, Number(row.amount || 0) - Number(row.settled_amount || 0)) }
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
