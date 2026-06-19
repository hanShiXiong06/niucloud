<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">资金账户</div>
                    <div class="mt-1 text-sm text-gray-500">现金 / 微信 / 支付宝 / 银行卡，各记余额。可手工记收/付，余额自动更新；账目往来留痕。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" @click="loadAll" :loading="loading">刷新</el-button>
                    <el-button v-permission="'hsx_erp_capital_account_save'" type="primary" :icon="Plus" @click="openEdit()">新建账户</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">账户数</div>
                    <div class="mt-1 text-2xl font-semibold">{{ accounts.length }} <span class="text-sm font-normal text-gray-400">个</span></div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">余额合计</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">{{ money(totalBalance) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">默认收款账户</div>
                    <div v-if="defaultAccount" class="mt-1 text-lg font-semibold text-gray-800 truncate">
                        {{ defaultAccount.account_name }}
                        <span class="ml-1 text-sm font-normal text-blue-600">{{ money(defaultAccount.balance) }}</span>
                    </div>
                    <div v-else class="mt-1 text-sm text-gray-400">未设置（编辑账户可设为默认）</div>
                </div>
            </div>

            <!-- 钱包卡片 -->
            <div v-loading="loading" class="acct-grid mt-5">
                <div
                    v-for="row in accounts"
                    :key="row.id"
                    class="acct-card"
                    :class="{ 'is-off': !row.status }"
                    :style="{ '--accent': accentColor(row.account_type) }"
                >
                    <div class="acct-brand" v-html="brandSvg(row.account_type)"></div>
                    <div class="acct-head">
                        <div class="acct-name" :title="row.account_name">
                            {{ row.account_name }}
                            <el-tag v-if="row.is_default" size="small" type="warning" effect="dark" class="ml-1">默认</el-tag>
                        </div>
                        <el-tag size="small" effect="plain" round>{{ row.account_type_text }}</el-tag>
                    </div>

                    <div class="acct-balance">{{ money(row.balance) }}</div>

                    <div class="acct-meta">
                        <span v-if="row.bank_name">{{ row.bank_name }}</span>
                        <span v-if="row.account_no"> · {{ maskNo(row.account_no) }}</span>
                        <span v-if="!row.bank_name && !row.account_no" class="text-gray-300">—</span>
                        <span v-if="!row.status" class="acct-off">已停用</span>
                    </div>

                    <div class="acct-actions">
                        <el-tooltip v-permission="'hsx_erp_capital_account_entry'" content="记一笔收/付" placement="top">
                            <el-button text :icon="Money" @click="openEntry(row)" />
                        </el-tooltip>
                        <el-tooltip content="收支流水" placement="top">
                            <el-button text :icon="Tickets" @click="openLedger(row)" />
                        </el-tooltip>
                        <el-tooltip v-permission="'hsx_erp_capital_account_save'" content="编辑账户" placement="top">
                            <el-button text :icon="Edit" @click="openEdit(row)" />
                        </el-tooltip>
                        <el-tooltip v-permission="'hsx_erp_capital_account_delete'" content="删除账户" placement="top">
                            <el-button text :icon="Delete" class="!text-red-400" @click="onDelete(row)" />
                        </el-tooltip>
                    </div>
                </div>

                <!-- 新建账户卡 -->
                <div class="acct-card acct-add" @click="openEdit()">
                    <el-icon class="text-3xl text-gray-300"><Plus /></el-icon>
                    <div class="mt-2 text-sm text-gray-400">新建账户</div>
                </div>
            </div>

            <el-empty v-if="!loading && !accounts.length" description="还没有账户，点「新建账户」" :image-size="80" />
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
                <el-table-column label="主体 / 对手方" min-width="170" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                        <template v-if="row.counterparty_name"><div class="text-xs text-gray-500">{{ row.counterparty_name }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></div></template>
                        <span v-else-if="!row.entity_name" class="text-gray-300">-</span>
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

        <!-- 主体抽屉 -->
        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" @changed="loadLedger" />
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Plus, Money, Tickets, Edit, Delete } from '@element-plus/icons-vue'
import EntityDrawer from '@/addon/hsx_erp/views/finance/entity-drawer.vue'
import { getCapitalAccounts, saveCapitalAccount, deleteCapitalAccount, recordCapitalEntry, getCapitalLedger } from '@/addon/hsx_erp/api/capital_account'

const entityDrawer = reactive<any>({ visible: false, id: 0 })
function openEntity(id: number) {
    if (!id) return
    entityDrawer.id = id
    entityDrawer.visible = true
}

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const defaultAccount = computed(() => accounts.value.find((a: any) => a.is_default) || null)

// 按账户类型给卡片配色
const accentColor = (type: string) => {
    const map: Record<string, string> = {
        cash: '#52c41a',     // 现金 绿
        wechat: '#07c160',   // 微信 绿
        alipay: '#1677ff',   // 支付宝 蓝
        bank: '#6d5ffd',     // 银行卡 紫
    }
    return map[String(type)] || '#5b8ff9'
}
// 卡号脱敏：只显示后 4 位
const maskNo = (no: string) => {
    const s = String(no || '')
    return s.length > 4 ? '**** ' + s.slice(-4) : s
}
// 账户类型品牌图标（内联 SVG，作卡片背景水印；未知类型用钱包图标兜底）
const brandSvg = (type: string) => {
    const t = String(type)
    if (t === 'wechat') {
        return '<svg viewBox="0 0 1024 1024"><path d="M664 357c-138 0-250 92-250 206 0 64 36 121 92 159 4 3 7 8 7 13 0 2-1 4-1 6l-12 45c-1 2-2 5-2 8 0 6 5 11 11 11 2 0 4-1 6-2l59-34c5-3 10-4 15-4 3 0 6 0 9 1 25 7 52 11 79 11 138 0 250-92 250-206S802 357 664 357z m-83 167c-18 0-32-14-32-32s14-32 32-32 32 14 32 32-14 32-32 32z m166 0c-18 0-32-14-32-32s14-32 32-32 32 14 32 32-14 32-32 32z"/><path d="M383 128C218 128 84 240 84 378c0 77 43 146 110 191 5 4 9 10 9 16 0 3-1 5-2 8l-15 55c-1 3-2 6-2 9 0 7 6 13 13 13 3 0 5-1 7-2l72-42c5-3 11-5 17-5 3 0 7 1 10 2 25 7 51 11 78 12-6-21-9-43-9-66 0-141 137-252 296-252 9 0 18 0 27 1C683 233 545 128 383 128z m-100 200c-21 0-38-17-38-38s17-38 38-38 38 17 38 38-17 38-38 38z m200 0c-21 0-38-17-38-38s17-38 38-38 38 17 38 38-17 38-38 38z"/></svg>'
    }
    if (t === 'alipay') {
        return '<svg viewBox="0 0 1024 1024"><path d="M230 128h564a102 102 0 0 1 102 102v392c-79-33-176-69-256-90 18-33 33-69 44-107H481v-49h182v-30H481v-72h-78c-12 0-12 12-12 12v60H214v30h177v49H241v30h288c-9 28-21 55-35 80-92-25-189-40-251-13-25 11-58 36-63 80-7 60 35 119 148 119 104 0 184-50 245-122 96 47 213 105 271 133A102 102 0 0 1 794 896H230A102 102 0 0 1 128 794V230A102 102 0 0 1 230 128z m41 480c-25 2-69 14-72 53-2 28 27 49 76 47 49-2 92-30 126-72-50-30-104-32-130-28z"/></svg>'
    }
    if (t === 'bank') {
        return '<svg viewBox="0 0 1024 1024"><path d="M512 96 64 320v64h896v-64L512 96z m-256 352v256h96V448h-96z m192 0v256h96V448h-96z m192 0v256h96V448h-96z M64 768v96h896v-96H64z"/></svg>'
    }
    // 现金 / 默认：钱袋/¥
    return '<svg viewBox="0 0 1024 1024"><path d="M512 96a416 416 0 1 0 0 832 416 416 0 0 0 0-832z m104 384h-72v40h72v48h-72v72h-64v-72h-72v-48h72v-40h-72v-48h56l-92-128h70l78 116 78-116h70l-92 128h56v48z"/></svg>'
}
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

<style lang="scss" scoped>
.acct-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}

.acct-card {
    position: relative;
    border-radius: 12px;
    padding: 18px 18px 12px;
    background: #fff;
    border: 1px solid #eef0f4;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: box-shadow 0.18s, transform 0.18s;
    overflow: hidden;
}
.acct-card::before {
    content: '';
    position: absolute;
    left: 0; right: 0; top: 0;
    height: 4px;
    background: var(--accent);
}
.acct-card:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}
.acct-card.is-off {
    background: #fafafa;
    opacity: 0.7;
}

/* 品牌图标水印（背景形式） */
.acct-brand {
    position: absolute;
    right: -10px;
    bottom: -14px;
    width: 110px;
    height: 110px;
    color: var(--accent);
    opacity: 0.1;
    pointer-events: none;
    z-index: 0;
}
.acct-brand :deep(svg) {
    width: 100%;
    height: 100%;
    fill: currentColor;
}
.acct-head,
.acct-balance,
.acct-meta,
.acct-actions {
    position: relative;
    z-index: 1;
}

.acct-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}
.acct-name {
    font-size: 15px;
    font-weight: 600;
    color: #1f2733;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.acct-balance {
    margin-top: 14px;
    font-size: 28px;
    font-weight: 700;
    color: var(--accent);
    line-height: 1.1;
    letter-spacing: 0.5px;
}
.acct-meta {
    margin-top: 8px;
    font-size: 12px;
    color: #b0b3bb;
    min-height: 18px;
}
.acct-off {
    margin-left: 6px;
    color: #f56c6c;
}
.acct-actions {
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px dashed #eef0f4;
    display: flex;
    justify-content: space-around;
}

.acct-add {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 150px;
    cursor: pointer;
    border-style: dashed;
    border-color: #dcdfe6;
}
.acct-add::before { display: none; }
.acct-add:hover {
    border-color: var(--el-color-primary);
    transform: none;
}
</style>
