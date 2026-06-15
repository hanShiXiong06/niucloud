<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">财务中心</div>
                    <div class="mt-1 text-sm text-gray-500">往来对账、应收应付明细、结算记录与经营支出，一处看清账目往来。</div>
                </div>
                <div class="flex items-center gap-2">
                    <el-button type="warning" plain @click="openExpense">记一笔支出</el-button>
                    <el-button @click="refreshAll" :loading="loading">刷新</el-button>
                </div>
            </div>

            <!-- 汇总卡片 -->
            <div class="mt-5 grid grid-cols-4 gap-4">
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">应收未结(欠我)</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600">{{ money(summary.receivable_total) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">应付未结(我欠)</div>
                    <div class="mt-1 text-2xl font-semibold text-orange-600">{{ money(summary.payable_total) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">净额(应付-应收)</div>
                    <div class="mt-1 text-2xl font-semibold" :class="Number(summary.net) >= 0 ? 'text-orange-600' : 'text-green-600'">
                        {{ money(Math.abs(Number(summary.net || 0))) }}
                        <span class="text-xs text-gray-400">{{ Number(summary.net) > 0 ? '净付' : (Number(summary.net) < 0 ? '净收' : '已平') }}</span>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">资金账户总余额</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">{{ money(summary.balance_total) }}</div>
                    <div v-if="summary.accounts && summary.accounts.length" class="mt-2 flex flex-wrap gap-1">
                        <el-tag v-for="a in summary.accounts" :key="a.id" size="small" effect="plain">
                            {{ a.account_name }}：{{ money(a.balance) }}
                        </el-tag>
                    </div>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="mt-4" @tab-change="onTabChange">
                <!-- 往来汇总 -->
                <el-tab-pane label="往来汇总" name="board">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <el-radio-group v-model="boardFilter">
                            <el-radio-button label="">全部</el-radio-button>
                            <el-radio-button label="offsetable">可折账</el-radio-button>
                            <el-radio-button label="pay">我应付</el-radio-button>
                            <el-radio-button label="collect">我应收</el-radio-button>
                        </el-radio-group>
                        <el-input v-model="boardKeyword" placeholder="主体/对接人/电话" clearable class="!w-[200px]" />
                        <el-select v-model="boardSort" placeholder="排序" style="width: 170px">
                            <el-option v-for="s in boardSortOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                    </div>
                    <el-table :data="filteredBoard" v-loading="loading" size="large" empty-text="暂无未结往来" @sort-change="onBoardSortChange">
                        <el-table-column label="主体 / 对接人" min-width="200">
                            <template #default="{ row }">
                                <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                                <div v-else class="text-xs text-gray-400">未归属主体</div>
                                <div class="text-xs text-gray-500">{{ row.counterparty_name || ('#' + row.counterparty_id) }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="应付(我欠)" width="140" align="right" prop="payable" sortable="custom">
                            <template #default="{ row }"><span class="text-orange-600">{{ money(row.payable) }}</span></template>
                        </el-table-column>
                        <el-table-column label="应收(欠我)" width="140" align="right" prop="receivable" sortable="custom">
                            <template #default="{ row }"><span class="text-green-600">{{ money(row.receivable) }}</span></template>
                        </el-table-column>
                        <el-table-column label="可折账" width="120" align="right" prop="offsetable" sortable="custom">
                            <template #default="{ row }">
                                <el-tag v-if="row.offsetable > 0" type="primary" effect="light">{{ money(row.offsetable) }}</el-tag>
                                <span v-else class="text-gray-400">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="净额" width="150" align="right" prop="net" sortable="custom">
                            <template #default="{ row }">
                                <span :class="row.net > 0 ? 'text-orange-600' : (row.net < 0 ? 'text-green-600' : 'text-gray-400')">{{ money(Math.abs(row.net)) }}</span>
                                <span class="ml-1 text-xs text-gray-400">{{ netLabel(row.net_direction) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="110" align="center" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.offsetable > 0" type="primary" link @click="openSettle(row)">折账</el-button>
                                <span v-else class="text-xs text-gray-400">无可折</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 应收明细 / 应付明细 -->
                <el-tab-pane v-for="t in detailTabs" :key="t.name" :label="t.label" :name="t.name">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <el-radio-group v-model="detail.settle_state" @change="onDetailFilter">
                            <el-radio-button label="">全部</el-radio-button>
                            <el-radio-button label="open">未结清</el-radio-button>
                            <el-radio-button label="settled">已结清</el-radio-button>
                        </el-radio-group>
                        <el-input v-model="detail.keyword" placeholder="往来单位/来源单号" clearable class="!w-[180px]" @keyup.enter="onDetailFilter" />
                        <el-date-picker v-model="detail.dateRange" class="fin-range" type="daterange" value-format="X" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" />
                        <el-input v-model="detail.amount_min" placeholder="金额≥" class="!w-[100px]" />
                        <el-input v-model="detail.amount_max" placeholder="金额≤" class="!w-[100px]" />
                        <el-select v-model="detail.quickSort" placeholder="排序" class="!w-[150px]" @change="onQuickSort">
                            <el-option v-for="s in sortOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                        <el-button type="primary" @click="onDetailFilter">查询</el-button>
                        <el-button @click="resetDetailFilter">重置</el-button>
                    </div>
                    <el-table :data="detail.list" v-loading="detail.loading" size="large" empty-text="暂无数据" @sort-change="onSortChange"
                        :default-sort="{ prop: detail.sort_field, order: detail.sort_order === 'asc' ? 'ascending' : 'descending' }">
                        <el-table-column label="主体 / 对接人" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                                <div v-else class="text-xs text-gray-400">未归属主体</div>
                                <div class="text-xs text-gray-500">{{ row.counterparty_name }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="业务类型" width="100" align="center">
                            <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.source_type_text }}</el-tag></template>
                        </el-table-column>
                        <el-table-column prop="source_no" label="来源单号" min-width="140" show-overflow-tooltip />
                        <el-table-column label="金额" width="120" align="right" prop="amount" sortable="custom">
                            <template #default="{ row }">{{ money(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column label="已结" width="120" align="right" prop="settled_amount" sortable="custom">
                            <template #default="{ row }">{{ money(row.settled_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="未结" width="120" align="right" prop="outstanding" sortable="custom">
                            <template #default="{ row }"><span class="font-medium">{{ money(row.outstanding) }}</span></template>
                        </el-table-column>
                        <el-table-column label="状态" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag :type="statusTagType(row.status)" effect="light" size="small">{{ row.status_text }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="时间" width="160" prop="occurred_at" sortable="custom">
                            <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                        </el-table-column>
                        <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip />
                    </el-table>
                    <div class="mt-3 flex justify-end">
                        <el-pagination layout="total, prev, pager, next" :total="detail.total" :page-size="detail.limit" :current-page="detail.page" @current-change="onDetailPage" />
                    </div>
                </el-tab-pane>

                <!-- 结算记录 -->
                <el-tab-pane label="结算记录" name="settlement">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <el-input v-model="settle.keyword" placeholder="结算单号/往来单位" clearable class="!w-[200px]" @keyup.enter="loadSettlement" />
                        <el-date-picker v-model="settle.dateRange" class="fin-range" type="daterange" value-format="X" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" />
                        <el-button type="primary" @click="loadSettlement">查询</el-button>
                        <el-button @click="resetSettleFilter">重置</el-button>
                    </div>
                    <el-table :data="settle.list" v-loading="settle.loading" size="large" empty-text="暂无结算记录">
                        <el-table-column prop="settlement_no" label="结算单号" min-width="170" show-overflow-tooltip />
                        <el-table-column label="主体 / 对接人" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                                <div v-else class="text-xs text-gray-400">未归属主体</div>
                                <div class="text-xs text-gray-500">{{ row.counterparty_name }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="应付合计" width="110" align="right"><template #default="{ row }">{{ money(row.payable_total) }}</template></el-table-column>
                        <el-table-column label="应收合计" width="110" align="right"><template #default="{ row }">{{ money(row.receivable_total) }}</template></el-table-column>
                        <el-table-column label="折账" width="100" align="right"><template #default="{ row }">{{ money(row.offset_amount) }}</template></el-table-column>
                        <el-table-column label="现金" width="130" align="right">
                            <template #default="{ row }">
                                {{ money(row.cash_amount) }}
                                <span class="text-xs text-gray-400">{{ cashDirLabel(row.cash_direction) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="方式 / 户头" width="150" align="center">
                            <template #default="{ row }">
                                <el-tag size="small" :type="methodTagType(row.method)" effect="light">{{ methodText(row.method) }}</el-tag>
                                <div v-if="row.method !== 'offset' && row.account_name" class="mt-0.5 text-xs text-gray-500">{{ row.account_name }}</div>
                                <div v-else-if="row.method !== 'offset'" class="mt-0.5 text-xs text-gray-300">未记户头</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="已结清" width="90" align="center">
                            <template #default><el-tag type="success" size="small" effect="light">已结清</el-tag></template>
                        </el-table-column>
                        <el-table-column label="时间" width="160"><template #default="{ row }">{{ formatTime(row.occurred_at) }}</template></el-table-column>
                        <el-table-column prop="operator_name" label="操作人" width="100" show-overflow-tooltip />
                    </el-table>
                    <div class="mt-3 flex justify-end">
                        <el-pagination layout="total, prev, pager, next" :total="settle.total" :page-size="settle.limit" :current-page="settle.page" @current-change="onSettlePage" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 折账结算弹框 -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="880px" @closed="resetDialog">
            <div v-loading="dialogLoading">
                <el-alert type="info" :closable="false" class="mb-4"
                    title="勾选要一起结算的应付与应收。系统自动按 折账=min(应付,应收) 冲抵，余下一侧走现金。被勾选项均视为本次全额结清。" />
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <div class="mb-2 font-medium text-orange-600">应付(我欠对方)</div>
                        <el-table :data="payables" size="small" @selection-change="onPayableSelect" max-height="280" empty-text="无待结应付">
                            <el-table-column type="selection" width="40" />
                            <el-table-column prop="source_no" label="来源单" min-width="120" show-overflow-tooltip />
                            <el-table-column label="待结" width="110" align="right"><template #default="{ row }">{{ money(row.outstanding) }}</template></el-table-column>
                        </el-table>
                    </div>
                    <div>
                        <div class="mb-2 font-medium text-green-600">应收(对方欠我)</div>
                        <el-table :data="receivables" size="small" @selection-change="onReceivableSelect" max-height="280" empty-text="无待结应收">
                            <el-table-column type="selection" width="40" />
                            <el-table-column prop="source_no" label="来源单" min-width="120" show-overflow-tooltip />
                            <el-table-column label="待结" width="110" align="right"><template #default="{ row }">{{ money(row.outstanding) }}</template></el-table-column>
                        </el-table>
                    </div>
                </div>
                <div class="mt-5 rounded-lg bg-gray-50 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">结算预演</div>
                        <el-button size="small" @click="doPreview" :loading="previewing" :disabled="!canPreview">重新计算</el-button>
                    </div>
                    <div v-if="preview" class="mt-3 grid grid-cols-4 gap-4 text-center">
                        <div><div class="text-xs text-gray-500">应付合计</div><div class="mt-1 font-semibold text-orange-600">{{ money(preview.payable_total) }}</div></div>
                        <div><div class="text-xs text-gray-500">应收合计</div><div class="mt-1 font-semibold text-green-600">{{ money(preview.receivable_total) }}</div></div>
                        <div><div class="text-xs text-gray-500">折账冲抵</div><div class="mt-1 font-semibold text-blue-600">{{ money(preview.offset_amount) }}</div></div>
                        <div><div class="text-xs text-gray-500">现金{{ cashDirLabel(preview.cash_direction) }}</div><div class="mt-1 font-semibold">{{ money(preview.cash_amount) }}</div></div>
                    </div>
                    <div v-else class="mt-3 text-sm text-gray-400">勾选应付/应收后将自动计算折账与现金净额。</div>
                    <div v-if="preview" class="mt-3 text-center"><el-tag :type="methodTagType(preview.method)" effect="light">结算方式：{{ preview.method_text }}</el-tag></div>
                </div>
                <div v-if="preview && Number(preview.cash_amount) > 0" class="mt-4">
                    <div class="mb-1 text-sm text-gray-500">现金{{ cashDirLabel(preview.cash_direction) }}账户(必选)：现金将从该资金账户{{ preview.cash_direction === 'pay' ? '出账' : '入账' }}</div>
                    <el-select v-model="settleAccountId" filterable class="w-full" placeholder="选择资金账户">
                        <el-option v-for="a in summary.accounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </div>
                <el-input v-model="remark" class="mt-4" type="textarea" :rows="2" placeholder="结算备注(可选)" maxlength="200" show-word-limit />
            </div>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" :disabled="!canSettle" @click="doSettle">确认结算</el-button>
            </template>
        </el-dialog>

        <!-- 主体抽屉(信息/对接人/财务对账) -->
        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" @changed="onEntityChanged" />

        <!-- 经营支出弹框 -->
        <el-dialog v-model="expense.visible" title="记一笔经营支出" width="460px">
            <el-form label-width="90px">
                <el-form-item label="出账账户" required>
                    <el-select v-model="expense.account_id" filterable class="w-full" placeholder="从哪个资金账户出">
                        <el-option v-for="a in summary.accounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="费用类型">
                    <el-select v-model="expense.category" filterable allow-create default-first-option class="w-full" placeholder="水电/房租/快递…">
                        <el-option v-for="c in expenseCategories" :key="c" :label="c" :value="c" />
                    </el-select>
                </el-form-item>
                <el-form-item label="金额" required>
                    <el-input-number v-model="expense.amount" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="对手方">
                    <el-input v-model.trim="expense.counterparty_name" placeholder="付给谁(可选)：如 国家电网 / 房东 / 顺丰" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="expense.remark" type="textarea" :rows="2" placeholder="如：6月房租 / 顺丰快递费" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="expense.visible = false">取消</el-button>
                <el-button type="primary" :loading="expense.submitting" @click="submitExpense">确认出账</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import EntityDrawer from './entity-drawer.vue'
import {
    getFinanceBalanceBoard,
    getFinancePayableOutstanding,
    getFinanceReceivableOutstanding,
    previewFinanceSettlement,
    settleFinance,
    getFinanceSummary,
    getFinancePayableList,
    getFinanceReceivableList,
    getFinanceSettlementList,
    recordFinanceExpense,
} from '@/addon/hsx_erp/api/finance'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const netLabel = (d: string) => (d === 'pay' ? '我付' : d === 'collect' ? '我收' : '已平')
const cashDirLabel = (d: string) => (d === 'pay' ? '付出' : d === 'collect' ? '收取' : '')
const methodTagType = (m: string) => (m === 'offset' ? 'primary' : m === 'mixed' ? 'warning' : 'success')
const methodText = (m: string) => (m === 'offset' ? '折账' : m === 'mixed' ? '混合' : '现金')
const statusTagType = (s: string) => (s === 'settled' ? 'success' : s === 'partial' ? 'warning' : s === 'void' ? 'info' : 'danger')
const formatTime = (t: any) => {
    const n = Number(t || 0)
    if (!n) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
const statusOptions = [
    { value: 'pending', label: '待结算' },
    { value: 'partial', label: '部分结算' },
    { value: 'settled', label: '已结清' },
    { value: 'void', label: '已作废' },
]
const sortOptions = [
    { value: 'occurred_at:desc', label: '时间 最新优先' },
    { value: 'occurred_at:asc', label: '时间 最早优先' },
    { value: 'amount:desc', label: '金额 从高到低' },
    { value: 'amount:asc', label: '金额 从低到高' },
    { value: 'outstanding:desc', label: '未结额 从高到低' },
    { value: 'outstanding:asc', label: '未结额 从低到高' },
]
const expenseCategories = ['水电', '房租', '快递/物流', '办公', '工资', '其它']
const detailTabs = [
    { name: 'receivable', label: '应收明细' },
    { name: 'payable', label: '应付明细' },
]

const loading = ref(false)
const activeTab = ref('board')

// 汇总
const summary = reactive<any>({ payable_total: 0, receivable_total: 0, net: 0, balance_total: 0, accounts: [] })
async function loadSummary() {
    try {
        const res: any = await getFinanceSummary()
        Object.assign(summary, res.data || {})
    } catch (e) { /* ignore */ }
}

// 往来汇总(数据全量在前端，筛选+排序均在本地)
const board = ref<any[]>([])
const boardKeyword = ref('')
const boardFilter = ref('')        // '' | offsetable | pay | collect
const boardSort = ref('offsetable:desc')
const boardSortOptions = [
    { value: 'offsetable:desc', label: '可折账 高→低' },
    { value: 'net:desc', label: '净额 高→低' },
    { value: 'net:asc', label: '净额 低→高' },
    { value: 'payable:desc', label: '应付 高→低' },
    { value: 'receivable:desc', label: '应收 高→低' },
]
const filteredBoard = computed(() => {
    const kw = boardKeyword.value.trim()
    let rows = board.value.slice()
    if (kw) {
        rows = rows.filter((r: any) =>
            String(r.entity_name || '').includes(kw) ||
            String(r.counterparty_name || '').includes(kw) ||
            String(r.counterparty_mobile || '').includes(kw) ||
            String(r.counterparty_id || '') === kw)
    }
    if (boardFilter.value === 'offsetable') rows = rows.filter((r: any) => Number(r.offsetable) > 0)
    else if (boardFilter.value === 'pay') rows = rows.filter((r: any) => Number(r.net) > 0)
    else if (boardFilter.value === 'collect') rows = rows.filter((r: any) => Number(r.net) < 0)
    const [f, o] = String(boardSort.value || 'offsetable:desc').split(':')
    const sign = o === 'asc' ? 1 : -1
    rows.sort((a: any, b: any) => (Number(a[f] || 0) - Number(b[f] || 0)) * sign)
    return rows
})
function onBoardSortChange({ prop, order }: any) {
    if (!order) { boardSort.value = 'offsetable:desc'; return }
    boardSort.value = `${prop}:${order === 'ascending' ? 'asc' : 'desc'}`
}
async function loadBoard() {
    loading.value = true
    try {
        const res: any = await getFinanceBalanceBoard()
        board.value = res.data || []
    } finally {
        loading.value = false
    }
}

// 应收/应付明细
const detail = reactive<any>({ list: [], loading: false, page: 1, limit: 15, total: 0, keyword: '', settle_state: '', dateRange: [], amount_min: '', amount_max: '', sort_field: 'occurred_at', sort_order: 'desc', quickSort: 'occurred_at:desc' })
function detailParams() {
    const [start, end] = Array.isArray(detail.dateRange) ? detail.dateRange : []
    return {
        keyword: detail.keyword, settle_state: detail.settle_state,
        start_time: start || 0, end_time: end || 0,
        amount_min: detail.amount_min, amount_max: detail.amount_max,
        sort_field: detail.sort_field, sort_order: detail.sort_order,
        page: detail.page, limit: detail.limit,
    }
}
// 改筛选/排序回到第1页
function onDetailFilter() { detail.page = 1; loadDetail() }
function onQuickSort(v: string) {
    const [f, o] = String(v || 'occurred_at:desc').split(':')
    detail.sort_field = f; detail.sort_order = o === 'asc' ? 'asc' : 'desc'
    detail.page = 1; loadDetail()
}
// 点列头排序: el-table 给 {prop, order: 'ascending'|'descending'|null}
function onSortChange({ prop, order }: any) {
    if (!order) { detail.sort_field = 'occurred_at'; detail.sort_order = 'desc' }
    else { detail.sort_field = prop; detail.sort_order = order === 'ascending' ? 'asc' : 'desc' }
    detail.quickSort = `${detail.sort_field}:${detail.sort_order}`
    detail.page = 1; loadDetail()
}
async function loadDetail() {
    detail.loading = true
    try {
        const fn = activeTab.value === 'payable' ? getFinancePayableList : getFinanceReceivableList
        const res: any = await fn(detailParams())
        detail.list = res.data?.data || []
        detail.total = res.data?.total || 0
    } finally {
        detail.loading = false
    }
}
function onDetailPage(p: number) { detail.page = p; loadDetail() }
function resetDetailFilter() {
    Object.assign(detail, { keyword: '', settle_state: '', dateRange: [], amount_min: '', amount_max: '', sort_field: 'occurred_at', sort_order: 'desc', quickSort: 'occurred_at:desc', page: 1 })
    loadDetail()
}

// 结算记录
const settle = reactive<any>({ list: [], loading: false, page: 1, limit: 15, total: 0, keyword: '', dateRange: [] })
async function loadSettlement() {
    settle.loading = true
    try {
        const [start, end] = Array.isArray(settle.dateRange) ? settle.dateRange : []
        const res: any = await getFinanceSettlementList({ keyword: settle.keyword, start_time: start || 0, end_time: end || 0, page: settle.page, limit: settle.limit })
        settle.list = res.data?.data || []
        settle.total = res.data?.total || 0
    } finally {
        settle.loading = false
    }
}
function onSettlePage(p: number) { settle.page = p; loadSettlement() }
function resetSettleFilter() {
    Object.assign(settle, { keyword: '', dateRange: [], page: 1 })
    loadSettlement()
}

function onTabChange(name: string) {
    if (name === 'receivable' || name === 'payable') {
        Object.assign(detail, { page: 1 })
        loadDetail()
    } else if (name === 'settlement') {
        settle.page = 1
        loadSettlement()
    } else if (name === 'board') {
        loadBoard()
    }
}

function refreshAll() {
    loadSummary()
    if (activeTab.value === 'board') loadBoard()
    else if (activeTab.value === 'settlement') loadSettlement()
    else loadDetail()
}

// 折账结算弹框
const dialogVisible = ref(false)
const dialogLoading = ref(false)
const current = ref<any>(null)
const payables = ref<any[]>([])
const receivables = ref<any[]>([])
const selectedPayables = ref<any[]>([])
const selectedReceivables = ref<any[]>([])
const preview = ref<any>(null)
const previewing = ref(false)
const submitting = ref(false)
const remark = ref('')
const settleAccountId = ref<number | undefined>(undefined)
const dialogTitle = computed(() => '结算 · ' + (current.value?.counterparty_name || ''))
const canPreview = computed(() => selectedPayables.value.length > 0 || selectedReceivables.value.length > 0)
const canSettle = computed(() => {
    const p = preview.value
    if (!p) return false
    if (!(p.payable_total > 0 || p.receivable_total > 0)) return false
    if (Number(p.cash_amount) > 0 && !settleAccountId.value) return false // 有现金必须选户头
    return true
})

async function openSettle(row: any) {
    current.value = row
    dialogVisible.value = true
    dialogLoading.value = true
    try {
        const [p, r]: any = await Promise.all([
            getFinancePayableOutstanding(row.counterparty_id),
            getFinanceReceivableOutstanding(row.counterparty_id),
        ])
        payables.value = p.data || []
        receivables.value = r.data || []
    } finally {
        dialogLoading.value = false
    }
}
function onPayableSelect(rows: any[]) { selectedPayables.value = rows; autoPreview() }
function onReceivableSelect(rows: any[]) { selectedReceivables.value = rows; autoPreview() }
let previewTimer: any = null
function autoPreview() {
    preview.value = null
    if (previewTimer) clearTimeout(previewTimer)
    if (!canPreview.value) return
    previewTimer = setTimeout(doPreview, 250)
}
async function doPreview() {
    if (!canPreview.value || !current.value) return
    previewing.value = true
    try {
        const res: any = await previewFinanceSettlement({
            counterparty_id: current.value.counterparty_id,
            payable_ids: selectedPayables.value.map((x) => x.id),
            receivable_ids: selectedReceivables.value.map((x) => x.id),
        })
        preview.value = res.data
    } finally {
        previewing.value = false
    }
}
async function doSettle() {
    if (!canSettle.value || !current.value) return
    const p = preview.value
    const tip = p.method === 'offset'
        ? `折账冲抵 ${money(p.offset_amount)}，无现金往来。`
        : `折账 ${money(p.offset_amount)} + 现金${cashDirLabel(p.cash_direction)} ${money(p.cash_amount)}。`
    try { await ElMessageBox.confirm(tip + ' 确认结算？', '确认结算', { type: 'warning' }) } catch { return }
    submitting.value = true
    try {
        await settleFinance({
            counterparty_id: current.value.counterparty_id,
            payable_ids: selectedPayables.value.map((x) => x.id),
            receivable_ids: selectedReceivables.value.map((x) => x.id),
            remark: remark.value,
            capital_account_id: settleAccountId.value || 0,
        })
        ElMessage.success('结算完成')
        dialogVisible.value = false
        refreshAll()
    } finally {
        submitting.value = false
    }
}
function resetDialog() {
    current.value = null; payables.value = []; receivables.value = []
    selectedPayables.value = []; selectedReceivables.value = []; preview.value = null; remark.value = ''; settleAccountId.value = undefined
}

// 经营支出
const expense = reactive<any>({ visible: false, submitting: false, account_id: undefined, category: '', amount: 0, counterparty_name: '', remark: '' })
function openExpense() {
    Object.assign(expense, { account_id: undefined, category: '', amount: 0, counterparty_name: '', remark: '' })
    if (!summary.accounts || !summary.accounts.length) loadSummary()
    expense.visible = true
}
async function submitExpense() {
    if (!expense.account_id) return ElMessage.warning('请选择出账账户')
    if (!Number(expense.amount) || Number(expense.amount) <= 0) return ElMessage.warning('请填写金额')
    expense.submitting = true
    try {
        await recordFinanceExpense({
            account_id: expense.account_id, amount: Number(expense.amount),
            category: expense.category, counterparty_name: expense.counterparty_name, remark: expense.remark,
        })
        ElMessage.success('已记一笔支出')
        expense.visible = false
        refreshAll()
    } catch (e: any) {
        ElMessage.error(e?.message || '记账失败')
    } finally {
        expense.submitting = false
    }
}

// 主体抽屉
const entityDrawer = reactive<any>({ visible: false, id: 0 })
function openEntity(id: number) {
    if (!id) return ElMessage.info('该往来未归属主体，请在出库/录入时归属或在此添加')
    entityDrawer.id = id
    entityDrawer.visible = true
}
function onEntityChanged() {
    refreshAll()
}

loadSummary()
loadBoard()
</script>

<style scoped>
/* 钉死日期区间组件宽度: Element Plus 自带样式会盖掉行内 width, 用 :deep + !important 强制 */
:deep(.el-date-editor.fin-range) {
    width: 300px !important;
}
</style>
