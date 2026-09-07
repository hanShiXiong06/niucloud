<template>
    <HsxPage padding="none" class="main-container">
        <el-card class="!border-none" shadow="never">
            <HsxTitle size="page" collapsible-subtitle class="mb-4">
                <template #default>付款账户</template>
                <template #subtitle>维护现金、微信、支付宝、银行卡等账户。付款和收款都要先选账户，流水自动留痕。</template>
                <template #extra><div class="flex gap-2 flex-wrap">
                        <el-button type="success" :icon="Money" @click="operatingVisible = true">经营收支</el-button>
                        <el-button :icon="Refresh" :loading="loading" @click="loadAll">刷新</el-button>
                        <el-button type="primary" :icon="Plus" @click="openEdit()">新建账户</el-button>
                    </div></template>
            </HsxTitle>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="summary-box">
                    <div class="text-sm text-gray-500">账户数</div>
                    <div class="mt-1 text-2xl font-semibold">{{ accounts.length }} <span class="text-sm font-normal text-gray-400">个</span></div>
                </div>
                <div class="summary-box">
                    <div class="text-sm text-gray-500">余额合计</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">{{ money(totalBalance) }}</div>
                </div>
                <div class="summary-box">
                    <div class="text-sm text-gray-500">默认账户</div>
                    <div v-if="defaultAccount" class="mt-1 truncate text-lg font-semibold text-gray-800">
                        {{ defaultAccount.account_name }}
                        <span class="ml-1 text-sm font-normal text-blue-600">{{ money(defaultAccount.balance) }}</span>
                    </div>
                    <div v-else class="mt-1 text-sm text-gray-400">未设置</div>
                </div>
            </div>

            <div v-loading="loading" class="account-grid mt-5">
                <div
                    v-for="row in accounts"
                    :key="row.id"
                    class="account-card"
                    :class="{ 'is-off': !row.status }"
                    :style="{ '--accent': accentColor(row.account_type) }"
                >
                    <div class="account-watermark">{{ typeMark(row.account_type) }}</div>
                    <div class="account-head">
                        <div class="account-name" :title="row.account_name">
                            {{ row.account_name }}
                            <el-tag v-if="row.is_default" size="small" type="warning" effect="dark" class="ml-1">默认</el-tag>
                        </div>
                        <el-tag size="small" effect="plain" round>{{ row.account_type_text || typeMap[row.account_type] || '其他' }}</el-tag>
                    </div>
                    <div class="account-balance">{{ money(row.balance) }}</div>
                    <div class="account-meta">
                        <span v-if="row.bank_name">{{ row.bank_name }}</span>
                        <span v-if="row.account_no"> · {{ maskNo(row.account_no) }}</span>
                        <span v-if="!row.bank_name && !row.account_no" class="text-gray-300">未填写账号信息</span>
                        <span v-if="!row.status" class="account-off">已停用</span>
                    </div>
                    <div class="account-actions">
                        <el-tooltip content="记一笔收/付" placement="top">
                            <el-button text :icon="Money" aria-label="记一笔收付" @click="openEntry(row)">记收支</el-button>
                        </el-tooltip>
                        <el-tooltip content="账户流水" placement="top">
                            <el-button text :icon="Tickets" aria-label="账户流水" @click="openLedger(row)">流水</el-button>
                        </el-tooltip>
                        <el-tooltip content="编辑账户" placement="top">
                            <el-button text :icon="Edit" aria-label="编辑账户" @click="openEdit(row)" />
                        </el-tooltip>
                        <el-tooltip content="删除账户" placement="top">
                            <el-button text :icon="Delete" aria-label="删除账户" class="!text-red-500" @click="onDelete(row)" />
                        </el-tooltip>
                    </div>
                </div>

                <div class="account-card account-add" role="button" tabindex="0" @click="openEdit()" @keydown.enter.prevent="openEdit()" @keydown.space.prevent="openEdit()">
                    <el-icon class="text-3xl text-gray-300"><Plus /></el-icon>
                    <div class="mt-2 text-sm text-gray-400">新建账户</div>
                </div>
            </div>

            <el-empty v-if="!loading && !accounts.length" description="还没有付款账户" :image-size="80" />
        </el-card>

        <HsxDrawer v-model="operatingVisible" title="经营收支" size="96%" destroy-on-close append-to-body>
            <OperatingFinanceList />
        </HsxDrawer>

        <HsxDialog :confirm-loading="saving" v-model="editVisible" :title="form.id ? '编辑账户' : '新建账户'" width="520px" :destroy-on-close="false">
            <el-form :model="form" label-width="90px">
                <el-form-item label="账户名称" required>
                    <el-input v-model.trim="form.account_name" placeholder="如：老板微信 / 招商银行尾号1234" />
                </el-form-item>
                <el-form-item label="账户类型">
                    <el-select v-model="form.account_type" class="w-full">
                        <el-option v-for="(label, val) in typeMap" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.account_type === 'bank'" label="开户行">
                    <el-input v-model.trim="form.bank_name" placeholder="如：招商银行xx支行" />
                </el-form-item>
                <el-form-item label="账号">
                    <el-input v-model.trim="form.account_no" placeholder="可只填尾号或备注账号" />
                </el-form-item>
                <el-form-item label="户名">
                    <el-input v-model.trim="form.holder" placeholder="账户归属人" />
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
                <el-button :disabled="saving" @click="editVisible = false">取消</el-button>
                <el-button :disabled="saving" type="primary" :loading="saving" @click="onSave">保存</el-button>
            </template>
        </HsxDialog>

        <HsxDialog :confirm-loading="saving" v-model="entryVisible" title="记一笔收/付" width="480px" :destroy-on-close="false">
            <div class="mb-3 text-sm text-gray-500">账户：{{ entryForm.account_name }}（当前余额 {{ money(entryForm.balance) }}）</div>
            <el-form :model="entryForm" label-width="80px">
                <el-form-item label="方向">
                    <el-radio-group v-model="entryForm.direction" @change="resetEntryCategory">
                        <el-radio value="in">收入（+）</el-radio>
                        <el-radio value="out">支出（-）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="entryForm.direction === 'in' ? '收入类型' : '支出类型'" required>
                    <el-select v-model="entryForm.category_key" class="w-full" placeholder="请选择收支类型">
                        <el-option v-for="item in entryCategoryOptions" :key="item.key" :label="item.name" :value="item.key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="金额" required>
                    <el-input-number v-model="entryForm.amount" :min="0" :precision="2" :controls="false" class="!w-[200px]" />
                </el-form-item>
                <el-form-item label="对手方">
                    <counterparty-select v-model="entryForm.party_id" role-type="all" placeholder="可选，搜索或新建往来主体" @resolved="onEntryPartyResolved" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="entryForm.remark" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="资金凭证"><ErpFinanceVoucherUpload v-model="entryForm.voucher_urls" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="saving" @click="entryVisible = false">取消</el-button>
                <el-button :disabled="saving" type="primary" :loading="saving" @click="onEntry">确认记账</el-button>
            </template>
        </HsxDialog>

        <HsxDrawer v-model="ledgerVisible" :title="`账户流水 · ${ledger.accountName}`" size="62%" :destroy-on-close="false">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <el-select v-model="ledger.direction" placeholder="方向" clearable class="!w-[110px]">
                    <el-option label="收入" value="in" />
                    <el-option label="支出" value="out" />
                </el-select>
                <el-input v-model.trim="ledger.keyword" placeholder="流水号/对手方/备注" clearable class="!w-[220px]" @keyup.enter="loadLedger" />
                <el-date-picker v-model="ledger.dateRange" type="daterange" value-format="X" range-separator="~" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 248px" />
                <el-button type="primary" @click="loadLedger">查询</el-button>
                <el-button @click="resetLedgerFilter">重置</el-button>
            </div>
            <el-table :data="ledger.list" size="large" v-loading="ledger.loading" empty-text="暂无流水">
                <el-table-column prop="ledger_no" label="流水号" min-width="170" show-overflow-tooltip />
                <el-table-column label="方向" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.direction === 'in' ? 'success' : 'warning'" size="small" effect="light">{{ row.direction === 'in' ? '收入' : '支出' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="收支类型" min-width="135">
                    <template #default="{ row }">
                        <div>{{ row.category_name || '历史流水' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="金额" width="130" align="right">
                    <template #default="{ row }">
                        <span :class="row.direction === 'in' ? 'text-green-600' : 'text-orange-600'">{{ row.direction === 'in' ? '+' : '-' }}{{ money(row.amount) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="记账后余额" width="130" align="right">
                    <template #default="{ row }">{{ money(row.balance_after) }}</template>
                </el-table-column>
                <el-table-column prop="party_name" label="对手方" min-width="150" show-overflow-tooltip />
                <el-table-column prop="operator_name" label="操作人" width="110" show-overflow-tooltip />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
                <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
                <el-table-column label="凭证" width="190"><template #default="{ row }"><ErpImageGallery :value="row.voucher_urls" :size="42" :limit="3" empty-text="未上传" /></template></el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination layout="total, prev, pager, next" :total="ledger.total" :page-size="ledger.limit" :current-page="ledger.page" @current-change="onLedgerPage" />
            </div>
        </HsxDrawer>
    </HsxPage>
</template>

<script lang="ts" setup>
import { HsxTitle, HsxPage, HsxDialog, HsxDrawer, useFeedback } from '@/addon/hsx_components/core'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { Delete, Edit, Money, Plus, Refresh, Tickets } from '@element-plus/icons-vue'
import { deleteCapitalAccount, getCapitalAccounts, getCapitalLedger, recordCapitalEntry, saveCapitalAccount } from '@/addon/hsx_erp/api/capital_account'
import { getErpFinanceCategories } from '@/addon/hsx_erp/api/config'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import ErpImageGallery from '@/addon/hsx_erp/components/ErpImageGallery.vue'
import OperatingFinanceList from '@/addon/hsx_erp/views/erp/operating_finance/list.vue'
const hsxFeedback = useFeedback()


const money = (value: any) => `¥${Number(value || 0).toFixed(2)}`
const formatTime = (value: number) => value ? new Date(value * 1000).toLocaleString() : '-'
const maskNo = (value: string) => {
    const text = String(value || '')
    return text.length > 4 ? `**** ${text.slice(-4)}` : text
}
const accentColor = (type: string) => ({ cash: '#16a34a', wechat: '#07c160', alipay: '#1677ff', bank: '#6d5ffd', other: '#64748b' } as Record<string, string>)[type] || '#64748b'
const typeMark = (type: string) => ({ cash: '¥', wechat: '微', alipay: '支', bank: '卡', other: '账' } as Record<string, string>)[type] || '账'

const loading = ref(false)
const operatingVisible = ref(false)
const saving = ref(false)
const accounts = ref<any[]>([])
const typeMap = ref<Record<string, string>>({ cash: '现金', wechat: '微信', alipay: '支付宝', bank: '银行卡', other: '其他' })
const financeCategories = ref<any[]>([])
const totalBalance = computed(() => accounts.value.reduce((sum, row) => sum + Number(row.balance || 0), 0))
const defaultAccount = computed(() => accounts.value.find((row: any) => Number(row.is_default) === 1) || null)

async function loadAll() {
    loading.value = true
    try {
        const res: any = await getCapitalAccounts()
        accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
        typeMap.value = res?.data?.type_map || typeMap.value
    } finally {
        loading.value = false
    }
}

async function loadFinanceCategories() {
    const res: any = await getErpFinanceCategories()
    financeCategories.value = (res?.data || []).filter((item: any) => Number(item.enabled) === 1)
}

const editVisible = ref(false)
const form = reactive<any>({})

function resetForm(row?: any) {
    Object.assign(form, row ? {
        id: row.id,
        account_name: row.account_name,
        account_type: row.account_type,
        bank_name: row.bank_name,
        account_no: row.account_no,
        holder: row.holder,
        balance: 0,
        is_default: row.is_default,
        status: row.status,
        remark: row.remark
    } : {
        id: 0,
        account_name: '',
        account_type: 'bank',
        bank_name: '',
        account_no: '',
        holder: '',
        balance: 0,
        is_default: 0,
        status: 1,
        remark: ''
    })
}

function openEdit(row?: any) {
    resetForm(row)
    editVisible.value = true
}

async function onSave() {
    if (!form.account_name) return hsxFeedback.warning('请填写账户名称')
    saving.value = true
    try {
        await saveCapitalAccount(form.id, { ...form })
        hsxFeedback.success('已保存')
        editVisible.value = false
        await loadAll()
    } finally {
        saving.value = false
    }
}

async function onDelete(row: any) {
    try {
        await ElMessageBox.confirm(`确认删除账户「${row.account_name}」？有余额或已有流水的账户不能删除，可改为停用。`, '提示', { type: 'warning' })
    } catch {
        return
    }
    await deleteCapitalAccount(row.id)
    hsxFeedback.success('已删除')
    await loadAll()
}

const entryVisible = ref(false)
const entryForm = reactive<any>({})
const entryCategoryOptions = computed(() => financeCategories.value.filter((item: any) => item.direction === (entryForm.direction === 'in' ? 'income' : 'expense')))

function resetEntryCategory() {
    const preferredKey = entryForm.direction === 'in' ? 'other_income' : 'other_expense'
    entryForm.category_key = entryCategoryOptions.value.find((item: any) => item.key === preferredKey)?.key || entryCategoryOptions.value[0]?.key || ''
}

function openEntry(row: any) {
    Object.assign(entryForm, {
        account_id: row.id,
        account_name: row.account_name,
        balance: row.balance,
        direction: 'in',
        category_key: '',
        amount: 0,
        party_id: 0,
        counterparty_name: '',
        voucher_urls: '',
        remark: ''
    })
    resetEntryCategory()
    entryVisible.value = true
}

function onEntryPartyResolved(row: any) {
    entryForm.counterparty_name = row?.party_name || row?.name || ''
}

async function onEntry() {
    if (Number(entryForm.amount || 0) <= 0) return hsxFeedback.warning('金额必须大于0')
    if (!entryForm.category_key) return hsxFeedback.warning(`请选择${entryForm.direction === 'in' ? '收入' : '支出'}类型`)
    const category = entryCategoryOptions.value.find((item: any) => item.key === entryForm.category_key)
    if (Number(category?.party_required || 0) === 1 && !Number(entryForm.party_id || 0)) return hsxFeedback.warning('该收支类型必须选择往来主体')
    saving.value = true
    try {
        await recordCapitalEntry({ ...entryForm })
        hsxFeedback.success('已记账')
        entryVisible.value = false
        await loadAll()
        if (ledgerVisible.value && ledger.accountId === entryForm.account_id) await loadLedger()
    } finally {
        saving.value = false
    }
}

const ledgerVisible = ref(false)
const ledger = reactive<any>({ accountId: 0, accountName: '', list: [], loading: false, page: 1, limit: 15, total: 0, direction: '', keyword: '', dateRange: [] })

function openLedger(row: any) {
    Object.assign(ledger, { accountId: row.id, accountName: row.account_name, page: 1, direction: '', keyword: '', dateRange: [] })
    ledgerVisible.value = true
    loadLedger()
}

async function loadLedger() {
    ledger.loading = true
    try {
        const [start, end] = Array.isArray(ledger.dateRange) ? ledger.dateRange : []
        const res: any = await getCapitalLedger({
            account_id: ledger.accountId,
            direction: ledger.direction,
            keyword: ledger.keyword,
            start_time: start || 0,
            end_time: end || 0,
            page: ledger.page,
            limit: ledger.limit
        })
        ledger.list = res?.data?.data || []
        ledger.total = res?.data?.total || 0
    } finally {
        ledger.loading = false
    }
}

function resetLedgerFilter() {
    Object.assign(ledger, { direction: '', keyword: '', dateRange: [], page: 1 })
    loadLedger()
}

function onLedgerPage(page: number) {
    ledger.page = page
    loadLedger()
}

onMounted(() => Promise.all([loadAll(), loadFinanceCategories()]))
</script>

<style scoped>
.summary-box {
    border-radius: 8px;
    background: #f8fafc;
    padding: 16px 18px;
}
.account-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}
.account-card {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid #eef0f4;
    background: #fff;
    padding: 18px 18px 12px;
    transition: box-shadow 0.18s, transform 0.18s;
}
.account-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent);
}
.account-card:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}
.account-card.is-off {
    background: #fafafa;
    opacity: 0.7;
}
.account-watermark {
    position: absolute;
    right: 12px;
    bottom: -8px;
    color: var(--accent);
    font-size: 76px;
    font-weight: 800;
    line-height: 1;
    opacity: 0.08;
    pointer-events: none;
}
.account-head {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}
.account-name {
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #1f2937;
    font-size: 15px;
    font-weight: 600;
}
.account-balance {
    position: relative;
    z-index: 1;
    margin-top: 14px;
    color: var(--accent);
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
}
.account-meta {
    position: relative;
    z-index: 1;
    margin-top: 8px;
    min-height: 18px;
    color: #a1a1aa;
    font-size: 12px;
}
.account-off {
    margin-left: 6px;
    color: #f56c6c;
}
.account-actions {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-around;
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px dashed #eef0f4;
}
.account-add {
    display: flex;
    min-height: 150px;
    cursor: pointer;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-style: dashed;
    border-color: #dcdfe6;
}
.account-add::before {
    display: none;
}
.account-add:hover {
    border-color: var(--el-color-primary);
    transform: none;
}
</style>
