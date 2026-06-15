<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">资金账户</div>
                    <div class="mt-1 text-sm text-gray-500">现金 / 微信 / 支付宝 / 银行卡，各记余额。可手工记收/付，余额自动更新；账目往来留痕。</div>
                </div>
                <div class="flex gap-2">
                    <el-button @click="loadAll" :loading="loading">刷新</el-button>
                    <el-button type="primary" @click="openEdit()">新建账户</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-4">
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">账户数</div>
                    <div class="mt-1 text-2xl font-semibold">{{ accounts.length }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">余额合计</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">{{ money(totalBalance) }}</div>
                </div>
            </div>

            <el-table class="mt-5" :data="accounts" v-loading="loading" size="large" empty-text="还没有账户，点右上角「新建账户」">
                <el-table-column label="账户" min-width="180">
                    <template #default="{ row }">
                        <span class="font-medium">{{ row.account_name }}</span>
                        <el-tag v-if="row.is_default" size="small" type="success" effect="plain" class="ml-2">默认</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">{{ row.account_type_text }}</template>
                </el-table-column>
                <el-table-column prop="bank_name" label="开户行" min-width="120" show-overflow-tooltip />
                <el-table-column prop="account_no" label="卡号/账号" min-width="140" show-overflow-tooltip />
                <el-table-column label="余额" width="140" align="right">
                    <template #default="{ row }"><span class="font-semibold text-blue-600">{{ money(row.balance) }}</span></template>
                </el-table-column>
                <el-table-column label="状态" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status ? 'success' : 'info'" size="small" effect="light">{{ row.status ? '启用' : '停用' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="220" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openEntry(row)">记收/付</el-button>
                        <el-button type="primary" link @click="openLedger(row)">流水</el-button>
                        <el-button type="primary" link @click="openEdit(row)">编辑</el-button>
                        <el-button type="danger" link @click="onDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建/编辑账户 -->
        <el-dialog v-model="editVisible" :title="form.id ? '编辑账户' : '新建账户'" width="520px">
            <el-form :model="form" label-width="90px">
                <el-form-item label="账户名称" required>
                    <el-input v-model.trim="form.account_name" placeholder="如：招商银行尾号1234 / 老板微信" />
                </el-form-item>
                <el-form-item label="账户类型">
                    <el-select v-model="form.account_type" class="w-full">
                        <el-option v-for="(label, val) in typeMap" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.account_type === 'bank'" label="开户行">
                    <el-input v-model.trim="form.bank_name" placeholder="如：招商银行xx支行" />
                </el-form-item>
                <el-form-item label="卡号/账号">
                    <el-input v-model.trim="form.account_no" placeholder="可脱敏，仅备注" />
                </el-form-item>
                <el-form-item label="户名">
                    <el-input v-model.trim="form.holder" />
                </el-form-item>
                <el-form-item v-if="!form.id" label="初始余额">
                    <el-input-number v-model="form.balance" :min="0" :precision="2" :controls="false" class="!w-[180px]" />
                </el-form-item>
                <el-form-item label="设为默认">
                    <el-switch v-model="form.is_default" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="form.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="editVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
            </template>
        </el-dialog>

        <!-- 记一笔收/付 -->
        <el-dialog v-model="entryVisible" title="记一笔收/付" width="480px">
            <div class="mb-3 text-sm text-gray-500">账户：{{ entryForm.account_name }}（当前余额 {{ money(entryForm.balance) }}）</div>
            <el-form :model="entryForm" label-width="80px">
                <el-form-item label="方向">
                    <el-radio-group v-model="entryForm.direction">
                        <el-radio value="in">收入（+）</el-radio>
                        <el-radio value="out">支出（-）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="金额" required>
                    <el-input-number v-model="entryForm.amount" :min="0" :precision="2" :controls="false" class="!w-[200px]" />
                </el-form-item>
                <el-form-item label="对手方">
                    <el-input v-model.trim="entryForm.counterparty_name" placeholder="可选，如某同行/客户名" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="entryForm.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="entryVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="onEntry">确认记账</el-button>
            </template>
        </el-dialog>

        <!-- 流水(抽屉：分页 + 检索) -->
        <el-drawer v-model="ledgerVisible" :title="`账目往来流水 · ${ledger.accountName}`" size="62%">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <el-select v-model="ledger.direction" placeholder="方向" clearable class="!w-[100px]">
                    <el-option label="收" value="in" />
                    <el-option label="付" value="out" />
                </el-select>
                <el-select v-model="ledger.biz_type" placeholder="业务类型" clearable class="!w-[140px]">
                    <el-option v-for="(label, val) in bizTypeMap" :key="val" :label="label" :value="val" />
                </el-select>
                <el-input v-model="ledger.keyword" placeholder="流水号/对手方/单号/备注" clearable class="!w-[200px]" @keyup.enter="loadLedger" />
                <el-date-picker v-model="ledger.dateRange" type="daterange" value-format="X" range-separator="~" start-placeholder="开始日期" end-placeholder="结束日期" style="width:248px" />
                <el-button type="primary" @click="loadLedger">查询</el-button>
                <el-button @click="resetLedgerFilter">重置</el-button>
            </div>
            <el-table :data="ledger.list" size="small" v-loading="ledger.loading" empty-text="暂无流水">
                <el-table-column prop="ledger_no" label="流水号" min-width="160" show-overflow-tooltip />
                <el-table-column label="方向" width="64" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.direction === 'in' ? 'success' : 'warning'" size="small" effect="light">{{ row.direction === 'in' ? '收' : '付' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="业务" width="96" align="center">
                    <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.biz_type_text || bizTypeMap[row.biz_type] || '其它' }}</el-tag></template>
                </el-table-column>
                <el-table-column label="金额" width="120" align="right">
                    <template #default="{ row }"><span :class="row.direction === 'in' ? 'text-green-600' : 'text-orange-600'">{{ (row.direction === 'in' ? '+' : '-') + money(row.amount) }}</span></template>
                </el-table-column>
                <el-table-column label="记账后余额" width="120" align="right">
                    <template #default="{ row }">{{ money(row.balance_after) }}</template>
                </el-table-column>
                <el-table-column label="对手方" min-width="130" show-overflow-tooltip>
                    <template #default="{ row }">
                        <template v-if="row.counterparty_name">{{ row.counterparty_name }}<span v-if="row.counterparty_mobile" class="text-xs text-gray-400"> · {{ row.counterparty_mobile }}</span></template>
                        <span v-else class="text-gray-300">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="source_no" label="来源单" min-width="120" show-overflow-tooltip />
                <el-table-column prop="operator_name" label="操作人" width="90" show-overflow-tooltip />
                <el-table-column label="时间" width="150">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
                <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip />
            </el-table>
            <div class="mt-3 flex justify-end">
                <el-pagination layout="total, prev, pager, next" :total="ledger.total" :page-size="ledger.limit" :current-page="ledger.page" @current-change="onLedgerPage" />
            </div>
        </el-drawer>
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCapitalAccounts, saveCapitalAccount, deleteCapitalAccount, recordCapitalEntry, getCapitalLedger } from '@/addon/hsx_erp/api/capital_account'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const formatTime = (t: number) => (t ? new Date(t * 1000).toLocaleString() : '-')

const loading = ref(false)
const saving = ref(false)
const accounts = ref<any[]>([])
const typeMap = ref<Record<string, string>>({})
const totalBalance = computed(() => accounts.value.reduce((s, r) => s + Number(r.balance || 0), 0))

async function loadAll() {
    loading.value = true
    try {
        const res: any = await getCapitalAccounts()
        accounts.value = res.data?.list || []
        typeMap.value = res.data?.type_map || {}
    } finally {
        loading.value = false
    }
}

// 新建/编辑
const editVisible = ref(false)
const form = reactive<any>({ id: 0, account_name: '', account_type: 'bank', bank_name: '', account_no: '', holder: '', balance: 0, is_default: 0, status: 1, remark: '' })
function openEdit(row?: any) {
    if (row) {
        Object.assign(form, { id: row.id, account_name: row.account_name, account_type: row.account_type, bank_name: row.bank_name, account_no: row.account_no, holder: row.holder, balance: 0, is_default: row.is_default, status: row.status, remark: row.remark })
    } else {
        Object.assign(form, { id: 0, account_name: '', account_type: 'bank', bank_name: '', account_no: '', holder: '', balance: 0, is_default: 0, status: 1, remark: '' })
    }
    editVisible.value = true
}
async function onSave() {
    if (!form.account_name) { ElMessage.warning('请填写账户名称'); return }
    saving.value = true
    try {
        await saveCapitalAccount(form.id, { ...form })
        ElMessage.success('已保存')
        editVisible.value = false
        loadAll()
    } finally {
        saving.value = false
    }
}
async function onDelete(row: any) {
    try {
        await ElMessageBox.confirm(`确认删除账户「${row.account_name}」？`, '提示', { type: 'warning' })
    } catch { return }
    await deleteCapitalAccount(row.id)
    ElMessage.success('已删除')
    loadAll()
}

// 记收/付
const entryVisible = ref(false)
const entryForm = reactive<any>({ account_id: 0, account_name: '', balance: 0, direction: 'in', amount: 0, counterparty_name: '', remark: '' })
function openEntry(row: any) {
    Object.assign(entryForm, { account_id: row.id, account_name: row.account_name, balance: row.balance, direction: 'in', amount: 0, counterparty_name: '', remark: '' })
    entryVisible.value = true
}
async function onEntry() {
    if (Number(entryForm.amount) <= 0) { ElMessage.warning('金额必须大于0'); return }
    saving.value = true
    try {
        await recordCapitalEntry({ account_id: entryForm.account_id, direction: entryForm.direction, amount: entryForm.amount, counterparty_name: entryForm.counterparty_name, remark: entryForm.remark })
        ElMessage.success('已记账')
        entryVisible.value = false
        loadAll()
    } finally {
        saving.value = false
    }
}

// 流水(抽屉：分页 + 检索)
const ledgerVisible = ref(false)
const bizTypeMap: Record<string, string> = {
    manual: '手工', recycle_payment: '回收打款', expense: '经营支出',
    settlement: '结算', sale: '销售收款', buyout: '代卖买断', transfer: '转账', fee: '费用',
}
const ledger = reactive<any>({ accountId: 0, accountName: '', list: [], loading: false, page: 1, limit: 15, total: 0, direction: '', biz_type: '', keyword: '', dateRange: [] })
async function loadLedger() {
    ledger.loading = true
    try {
        const [start, end] = Array.isArray(ledger.dateRange) ? ledger.dateRange : []
        const res: any = await getCapitalLedger({
            account_id: ledger.accountId, direction: ledger.direction, biz_type: ledger.biz_type,
            keyword: ledger.keyword, start_time: start || 0, end_time: end || 0,
            page: ledger.page, limit: ledger.limit,
        })
        ledger.list = res.data?.data || []
        ledger.total = res.data?.total || 0
    } finally {
        ledger.loading = false
    }
}
function onLedgerPage(p: number) { ledger.page = p; loadLedger() }
function resetLedgerFilter() {
    Object.assign(ledger, { direction: '', biz_type: '', keyword: '', dateRange: [], page: 1 })
    loadLedger()
}
function openLedger(row: any) {
    Object.assign(ledger, { accountId: row.id, accountName: row.account_name, direction: '', biz_type: '', keyword: '', dateRange: [], page: 1 })
    ledgerVisible.value = true
    loadLedger()
}

onMounted(loadAll)
</script>
