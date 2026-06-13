<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">财务中心 · 往来对账</div>
                    <div class="mt-1 text-sm text-gray-500">
                        按往来单位汇总应付与应收。可折账=同一单位两侧可净额冲抵的部分；净额&gt;0 我方仍需付现，&lt;0 对方仍需付我。
                    </div>
                </div>
                <el-button @click="loadBoard" :loading="loading">刷新</el-button>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-4">
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">应付合计(我欠)</div>
                    <div class="mt-1 text-2xl font-semibold text-orange-600">{{ money(sum.payable) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">应收合计(欠我)</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600">{{ money(sum.receivable) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">可折账合计</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">{{ money(sum.offsetable) }}</div>
                </div>
            </div>

            <el-table class="mt-5" :data="board" v-loading="loading" size="large" empty-text="暂无未结往来">
                <el-table-column prop="counterparty_name" label="往来单位" min-width="160">
                    <template #default="{ row }">
                        <span class="font-medium">{{ row.counterparty_name || ('#' + row.counterparty_id) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="应付(我欠)" width="140" align="right">
                    <template #default="{ row }"><span class="text-orange-600">{{ money(row.payable) }}</span></template>
                </el-table-column>
                <el-table-column label="应收(欠我)" width="140" align="right">
                    <template #default="{ row }"><span class="text-green-600">{{ money(row.receivable) }}</span></template>
                </el-table-column>
                <el-table-column label="可折账" width="130" align="right">
                    <template #default="{ row }">
                        <el-tag v-if="row.offsetable > 0" type="primary" effect="light">{{ money(row.offsetable) }}</el-tag>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="净额" width="160" align="right">
                    <template #default="{ row }">
                        <span :class="row.net > 0 ? 'text-orange-600' : (row.net < 0 ? 'text-green-600' : 'text-gray-400')">
                            {{ money(Math.abs(row.net)) }}
                        </span>
                        <span class="ml-1 text-xs text-gray-400">{{ netLabel(row.net_direction) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" align="center" fixed="right">
                    <template #default="{ row }">
                        <!-- 只有两侧都有账(可折账>0)才显示折账, 否则不显示, 避免点进去折不了 -->
                        <el-button v-if="row.offsetable > 0" type="primary" link @click="openSettle(row)">折账</el-button>
                        <span v-else class="text-xs text-gray-400">无可折</span>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="880px" @closed="resetDialog">
            <div v-loading="dialogLoading">
                <el-alert
                    type="info" :closable="false" class="mb-4"
                    title="勾选要一起结算的应付与应收。系统自动按 折账=min(应付,应收) 冲抵，余下一侧走现金。被勾选项均视为本次全额结清。" />

                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <div class="mb-2 font-medium text-orange-600">应付(我欠对方)</div>
                        <el-table :data="payables" size="small" @selection-change="onPayableSelect" max-height="280" empty-text="无待结应付">
                            <el-table-column type="selection" width="40" />
                            <el-table-column prop="source_no" label="来源单" min-width="120" show-overflow-tooltip />
                            <el-table-column label="待结" width="110" align="right">
                                <template #default="{ row }">{{ money(row.outstanding) }}</template>
                            </el-table-column>
                        </el-table>
                    </div>
                    <div>
                        <div class="mb-2 font-medium text-green-600">应收(对方欠我)</div>
                        <el-table :data="receivables" size="small" @selection-change="onReceivableSelect" max-height="280" empty-text="无待结应收">
                            <el-table-column type="selection" width="40" />
                            <el-table-column prop="source_no" label="来源单" min-width="120" show-overflow-tooltip />
                            <el-table-column label="待结" width="110" align="right">
                                <template #default="{ row }">{{ money(row.outstanding) }}</template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>

                <div class="mt-5 rounded-lg bg-gray-50 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">结算预演</div>
                        <el-button size="small" @click="doPreview" :loading="previewing" :disabled="!canPreview">重新计算</el-button>
                    </div>
                    <div v-if="preview" class="mt-3 grid grid-cols-4 gap-4 text-center">
                        <div>
                            <div class="text-xs text-gray-500">应付合计</div>
                            <div class="mt-1 font-semibold text-orange-600">{{ money(preview.payable_total) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">应收合计</div>
                            <div class="mt-1 font-semibold text-green-600">{{ money(preview.receivable_total) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">折账冲抵</div>
                            <div class="mt-1 font-semibold text-blue-600">{{ money(preview.offset_amount) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">现金{{ cashDirLabel(preview.cash_direction) }}</div>
                            <div class="mt-1 font-semibold">{{ money(preview.cash_amount) }}</div>
                        </div>
                    </div>
                    <div v-else class="mt-3 text-sm text-gray-400">勾选应付/应收后将自动计算折账与现金净额。</div>
                    <div v-if="preview" class="mt-3 text-center">
                        <el-tag :type="methodTagType(preview.method)" effect="light">结算方式：{{ preview.method_text }}</el-tag>
                    </div>
                </div>

                <el-input v-model="remark" class="mt-4" type="textarea" :rows="2" placeholder="结算备注(可选)" maxlength="200" show-word-limit />
            </div>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" :disabled="!canSettle" @click="doSettle">确认结算</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getFinanceBalanceBoard,
    getFinancePayableOutstanding,
    getFinanceReceivableOutstanding,
    previewFinanceSettlement,
    settleFinance,
} from '@/addon/hsx_erp/api/finance'

const loading = ref(false)
const board = ref<any[]>([])
const sum = reactive({ payable: 0, receivable: 0, offsetable: 0 })

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const netLabel = (d: string) => (d === 'pay' ? '我付' : d === 'collect' ? '我收' : '已平')
const cashDirLabel = (d: string) => (d === 'pay' ? '付出' : d === 'collect' ? '收取' : '')
const methodTagType = (m: string) => (m === 'offset' ? 'primary' : m === 'mixed' ? 'warning' : 'success')

async function loadBoard() {
    loading.value = true
    try {
        const res: any = await getFinanceBalanceBoard()
        board.value = res.data || []
        sum.payable = board.value.reduce((s, r) => s + Number(r.payable || 0), 0)
        sum.receivable = board.value.reduce((s, r) => s + Number(r.receivable || 0), 0)
        sum.offsetable = board.value.reduce((s, r) => s + Number(r.offsetable || 0), 0)
    } finally {
        loading.value = false
    }
}

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

const dialogTitle = computed(() => '结算 · ' + (current.value?.counterparty_name || ''))
const canPreview = computed(() => selectedPayables.value.length > 0 || selectedReceivables.value.length > 0)
const canSettle = computed(() => !!preview.value && (preview.value.payable_total > 0 || preview.value.receivable_total > 0))

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

function onPayableSelect(rows: any[]) {
    selectedPayables.value = rows
    autoPreview()
}
function onReceivableSelect(rows: any[]) {
    selectedReceivables.value = rows
    autoPreview()
}
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
    try {
        await ElMessageBox.confirm(tip + ' 确认结算？', '确认结算', { type: 'warning' })
    } catch {
        return
    }
    submitting.value = true
    try {
        await settleFinance({
            counterparty_id: current.value.counterparty_id,
            payable_ids: selectedPayables.value.map((x) => x.id),
            receivable_ids: selectedReceivables.value.map((x) => x.id),
            remark: remark.value,
        })
        ElMessage.success('结算完成')
        dialogVisible.value = false
        loadBoard()
    } finally {
        submitting.value = false
    }
}

function resetDialog() {
    current.value = null
    payables.value = []
    receivables.value = []
    selectedPayables.value = []
    selectedReceivables.value = []
    preview.value = null
    remark.value = ''
}

loadBoard()
</script>
