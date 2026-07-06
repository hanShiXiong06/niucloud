<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">应收款</div>
                    <div class="mt-1 text-sm text-gray-500">以客户和销售批次为账单单位，财务确认到账后才正式结算。</div>
                </div>
                <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile"><div class="summary-label">应收批次</div><div class="summary-value">{{ summary.count }}</div></div>
                <div class="summary-tile"><div class="summary-label">应收总额</div><div class="summary-value">{{ money(summary.amount) }}</div></div>
                <div class="summary-tile"><div class="summary-label">已结算</div><div class="summary-value text-green-600">{{ money(summary.settled) }}</div></div>
                <div class="summary-tile"><div class="summary-label">剩余应收</div><div class="summary-value text-orange-600">{{ money(summary.remain) }}</div></div>
            </div>

            <el-tabs v-model="activeStatus" class="mt-5" @tab-change="handleSearch">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待结算" name="pending" />
                <el-tab-pane label="部分结算" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
            </el-tabs>

            <el-form :inline="true" class="mt-1" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="客户 / 手机号 / IMEI / 销售单 / 渠道" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="时间">
                    <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" start-placeholder="开始时间" end-placeholder="结束时间" class="!w-[300px]" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="客户/往来主体" min-width="190">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ contactText(row) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="销售批次" min-width="230">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.batch_no || row.receivable_no }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.sale_channel || '未填写渠道' }} · {{ row.item_count || 0 }} 台</div>
                        <div class="mt-1 text-xs text-gray-500">销售时间 {{ formatTime(row.sale_at || row.occurred_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="账目" min-width="240">
                    <template #default="{ row }">
                        <div>应收 <span class="font-medium">{{ money(row.amount) }}</span></div>
                        <div class="mt-1 text-xs text-gray-500">已结算 {{ money(row.settled_amount) }} · 剩余 {{ money(row.remain_amount ?? remain(row)) }}</div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <el-tag size="small" effect="plain" type="info">开单：{{ row.opening_settle_method || row.settle_method || '-' }}</el-tag>
                            <el-tag v-if="!(row.settle_summary_items || []).length" size="small" effect="plain" type="warning">待清算</el-tag>
                            <el-tag v-for="item in row.settle_summary_items" :key="item.label" size="small" effect="plain" :type="settleSummaryTagType(item.label)">
                                {{ item.label }} {{ money(item.amount) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="制单/销售" min-width="150">
                    <template #default="{ row }">
                        <div>{{ row.salesman_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.receivable_no }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }"><el-tag :type="statusMeta(row.status).type">{{ statusMeta(row.status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="220" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">查看明细</el-button>
                        <el-button v-if="canConfirmReceipt(row)" type="primary" link @click="openReceipt(row)">收款</el-button>
                        <el-button v-if="canOffset(row)" type="warning" link @click="openOffset(row)">折账</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit" layout="total, sizes, prev, pager, next, jumper" :total="table.total" @size-change="loadList" @current-change="loadList" />
            </div>
        </el-card>

        <el-drawer v-model="detail.visible" title="销售批次明细" size="860px">
            <div v-if="detail.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>客户：<span class="font-medium text-gray-900">{{ detail.row.party_name || '-' }}</span></div>
                <div class="mt-1">批次：{{ detail.row.batch_no || detail.row.receivable_no }} · 应收 {{ money(detail.row.amount) }} · 剩余 {{ money(remain(detail.row)) }}</div>
            </div>
            <div class="mb-2 font-medium text-gray-900">设备明细</div>
            <el-table :data="detail.items" v-loading="detail.loading" size="small" empty-text="暂无设备明细">
                <el-table-column label="设备" min-width="230">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.imei ? `IMEI ${row.imei}` : row.asset_no || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="仓库" min-width="140">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="成本" width="120">
                    <template #default="{ row }">{{ money(row.cost) }}</template>
                </el-table-column>
                <el-table-column label="售价" width="120">
                    <template #default="{ row }">{{ money(row.sale_price) }}</template>
                </el-table-column>
                <el-table-column label="毛利" width="120">
                    <template #default="{ row }"><span :class="Number(row.profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(row.profit) }}</span></template>
                </el-table-column>
            </el-table>

            <div class="mb-2 mt-6 font-medium text-gray-900">结算明细</div>
            <el-table :data="detail.settlements" v-loading="detail.loading" size="small" empty-text="暂无结算记录">
                <el-table-column label="结款方式" min-width="180">
                    <template #default="{ row }">
                        <div class="font-medium" :class="row.settlement_type === 'offset' ? 'text-amber-600' : 'text-gray-900'">{{ row.settlement_type_text || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.pay_method_text || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ row.settlement_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="本次结算" width="130" align="right">
                    <template #default="{ row }">{{ money(row.applied_amount) }}</template>
                </el-table-column>
                <el-table-column label="确认信息" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.operator_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.confirmed_at) }}</div>
                        <div v-if="row.remark" class="mt-1 text-xs text-gray-500">{{ row.remark }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="关联设备 / 账目" min-width="330">
                    <template #default="{ row }">
                        <div v-for="target in row.targets" :key="`${target.target_type}_${target.target_id}`" class="mb-2 rounded bg-gray-50 px-2 py-2 text-xs text-gray-600">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ target.target_type_text }} {{ target.target_no || target.source_no || '-' }}</span>
                                <span class="font-medium text-gray-900">结算 {{ money(target.applied_amount) }}</span>
                            </div>
                            <div v-for="device in target.devices" :key="`${target.target_type}_${target.target_id}_${device.asset_id}_${device.imei}`" class="mt-2 border-l-2 border-gray-200 pl-2">
                                <div class="font-medium text-gray-900">{{ device.model || '-' }}</div>
                                <div class="mt-1 text-gray-500">{{ device.imei ? `IMEI ${device.imei}` : device.asset_no || '-' }}</div>
                                <div class="mt-1 text-gray-500">{{ deviceSummary(device) }}</div>
                            </div>
                            <div v-if="!target.devices?.length" class="mt-1 text-gray-400">{{ target.source_no || '-' }}</div>
                        </div>
                        <div v-if="!row.targets?.length" class="text-xs text-gray-400">-</div>
                    </template>
                </el-table-column>
                <el-table-column label="账户收付明细" min-width="260">
                    <template #default="{ row }">
                        <div v-for="ledger in row.money_ledgers" :key="ledger.ledger_no" class="mb-2 rounded bg-gray-50 px-2 py-2 text-xs text-gray-600">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ ledger.capital_account_name || row.capital_account_name || '-' }}</span>
                                <span :class="ledger.direction === 'in' ? 'text-green-600' : 'text-red-600'">{{ ledger.direction === 'in' ? '收款' : '付款' }} {{ money(ledger.amount) }}</span>
                            </div>
                            <div class="mt-1 text-gray-400">{{ ledger.ledger_no }} · 账户余额 {{ money(ledger.balance_after) }}</div>
                        </div>
                        <div v-if="!row.money_ledgers?.length" class="text-xs text-gray-400">折账不产生资金流水</div>
                    </template>
                </el-table-column>
            </el-table>
        </el-drawer>

        <el-dialog v-model="offset.visible" title="应收应付折账" width="920px">
            <div v-if="offset.row" class="mb-4 rounded bg-amber-50 px-4 py-3 text-sm text-gray-600">
                <div>往来主体：<span class="font-medium text-gray-900">{{ offset.row.party_name }}</span></div>
                <div class="mt-1 text-xs text-gray-500">折账就是把“我要付给他的钱”和“他要付给我的钱”互相抵扣，不产生真实收付款。</div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">我要付给他</div>
                    <div class="mt-1 font-medium text-red-600">{{ money(offsetPayableChecked) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">他要付给我</div>
                    <div class="mt-1 font-medium text-green-600">{{ money(offsetReceivableChecked) }}</div>
                </div>
                <div class="rounded bg-amber-50 px-3 py-2">
                    <div class="text-xs text-gray-500">本次互相抵扣</div>
                    <div class="mt-1 font-medium text-amber-700">{{ money(offsetAmount) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">抵扣后</div>
                    <div class="mt-1 font-medium text-gray-900">{{ offsetResultText }}</div>
                </div>
            </div>
            <div v-loading="offset.loading" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded border border-gray-200 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-medium text-gray-900">我要付给他（应付）</span>
                        <span class="text-xs text-gray-500">勾选合计 {{ money(offsetPayableChecked) }}</span>
                    </div>
                    <el-table :data="offset.payables" size="small" max-height="300" empty-text="无待折应付">
                        <el-table-column width="46">
                            <template #default="{ row }"><el-checkbox v-model="row.checked" @change="syncOffsetAmount" /></template>
                        </el-table-column>
                        <el-table-column label="设备 / 来源" min-width="180">
                            <template #default="{ row }">
                                <div class="text-sm">{{ row.model || row.source_no || '应付' }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ row.imei ? `IMEI ${row.imei}` : row.payable_no }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="剩余应付" width="110" align="right">
                            <template #default="{ row }">{{ money(row.remain) }}</template>
                        </el-table-column>
                    </el-table>
                </div>
                <div class="rounded border border-gray-200 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-medium text-gray-900">他要付给我（应收）</span>
                        <span class="text-xs text-gray-500">勾选合计 {{ money(offsetReceivableChecked) }}</span>
                    </div>
                    <el-table :data="offset.receivables" size="small" max-height="300" empty-text="无待折应收">
                        <el-table-column width="46">
                            <template #default="{ row }"><el-checkbox v-model="row.checked" @change="syncOffsetAmount" /></template>
                        </el-table-column>
                        <el-table-column label="销售批次" min-width="180">
                            <template #default="{ row }">
                                <div class="text-sm">{{ row.source_no || row.batch_no || '应收' }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ row.receivable_no }}</div>
                                <div v-if="row.device_summary" class="mt-0.5 text-xs text-gray-500">{{ row.device_summary }}<span v-if="row.device_more"> 等 {{ row.device_more + 2 }} 台</span></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="剩余应收" width="110" align="right">
                            <template #default="{ row }">{{ money(row.remain) }}</template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
            <el-form class="mt-4" label-width="100px">
                <el-form-item label="可折金额">
                    <span class="font-medium text-gray-900">{{ money(offsetMax) }}</span>
                    <span class="ml-2 text-xs text-gray-500">= 两侧勾选金额的较小值；可改小，剩余部分后续单独结算</span>
                </el-form-item>
                <el-form-item label="本次抵扣" required>
                    <el-input-number v-model="offset.form.amount" :min="0" :max="offsetMax" :precision="2" :controls="false" class="!w-[220px]" />
                    <span class="ml-2 text-xs text-gray-500">这一步只做账目抵扣，不会产生资金流水</span>
                </el-form-item>
                <el-form-item v-if="offsetDiffAmount > 0" label="差额处理">
                    <div class="w-full">
                        <el-checkbox v-model="offset.form.settle_diff">本次结清差额：{{ offsetDiffText }}</el-checkbox>
                        <el-select v-if="offset.form.settle_diff" v-model="offset.form.capital_account_id" clearable class="mt-2 w-full" :placeholder="offsetDiffDirection === 'payable' ? '选择付款账户' : '选择收款账户'">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </div>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="offset.form.remark" type="textarea" :rows="2" placeholder="如：同一客户往来抵扣" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="offset.visible = false">取消</el-button>
                <el-button type="warning" :loading="offset.saving" :disabled="offsetMax <= 0" @click="submitOffset">确认抵扣折账</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="receipt.visible" title="财务确认收款" width="920px">
            <div v-if="receipt.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>收款客户：<span class="font-medium text-gray-900">{{ receipt.row.party_name }}</span></div>
                <div class="mt-1">销售批次：{{ receipt.row.batch_no || receipt.row.receivable_no }}</div>
                <div class="mt-1">剩余应收：<span class="font-medium text-orange-600">{{ money(remain(receipt.row)) }}</span></div>
            </div>
            <div class="mb-2 flex items-center justify-between">
                <span class="font-medium text-gray-900">按设备确认收款</span>
                <span class="text-sm text-gray-500">本次收款合计 <span class="font-medium text-green-600">{{ money(receiptTotal) }}</span></span>
            </div>
            <el-table :data="receipt.items" v-loading="receipt.loading" size="small" max-height="360" empty-text="暂无设备明细">
                <el-table-column width="46">
                    <template #default="{ row }">
                        <el-checkbox v-model="row.checked" @change="syncReceiptAmount(row)" />
                    </template>
                </el-table-column>
                <el-table-column label="设备" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.imei ? `IMEI ${row.imei}` : row.asset_no || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="实际销售价" width="150" align="right">
                    <template #default="{ row }">
                        <el-input-number v-model="row.sale_price" :min="0" :precision="2" :controls="false" class="!w-[120px]" @change="syncReceiptAmount(row)" />
                    </template>
                </el-table-column>
                <el-table-column label="已结算" width="120" align="right">
                    <template #default="{ row }">{{ money(row.allocated_settled) }}</template>
                </el-table-column>
                <el-table-column label="本次收款" width="150" align="right">
                    <template #default="{ row }">
                        <el-input-number v-model="row.receipt_amount" :min="0" :max="itemRemain(row)" :precision="2" :controls="false" class="!w-[120px]" :disabled="!row.checked" />
                    </template>
                </el-table-column>
                <el-table-column label="收后剩余" width="120" align="right">
                    <template #default="{ row }">{{ money(Math.max(0, itemRemain(row) - Number(row.receipt_amount || 0))) }}</template>
                </el-table-column>
            </el-table>
            <el-form label-width="100px">
                <el-form-item label="收款金额" required>
                    <span class="font-medium text-green-600">{{ money(receiptTotal) }}</span>
                    <span class="ml-2 text-xs text-gray-500">由上方设备本次收款自动汇总</span>
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
                <el-button type="primary" :loading="receipt.saving" :disabled="!canSubmitReceipt" @click="submitReceipt">确认收款</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { confirmErpOffset, confirmErpReceipt, getErpPayablePartyItems, getErpReceivableItems, getErpReceivableList } from '@/addon/hsx_erp/api/erp'

const activeStatus = ref('')
const search = reactive({ keyword: '', dateRange: [] as any[] })
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const detail = reactive({ visible: false, loading: false, row: null as any, items: [] as any[], settlements: [] as any[] })
const offset = reactive({ visible: false, saving: false, loading: false, row: null as any, payables: [] as any[], receivables: [] as any[], form: { amount: 0, settle_diff: false, capital_account_id: 0, remark: '' } })
const receipt = reactive({ visible: false, saving: false, loading: false, row: null as any, items: [] as any[], form: { amount: 0, capital_account_id: 0, remark: '' } })
const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.amount || 0)
    acc.settled += Number(row.settled_amount || 0)
    acc.remain += remain(row)
    return acc
}, { count: 0, amount: 0, settled: 0, remain: 0 }))
const offsetPayableChecked = computed(() => offset.payables.reduce((sum: number, row: any) => row.checked ? sum + Number(row.remain || 0) : sum, 0))
const offsetReceivableChecked = computed(() => offset.receivables.reduce((sum: number, row: any) => row.checked ? sum + Number(row.remain || 0) : sum, 0))
const offsetMax = computed(() => Math.max(0, Math.min(offsetPayableChecked.value, offsetReceivableChecked.value)))
const offsetAmount = computed(() => Math.min(Number(offset.form.amount || 0), offsetMax.value))
const offsetPayableAfter = computed(() => Math.max(0, offsetPayableChecked.value - offsetAmount.value))
const offsetReceivableAfter = computed(() => Math.max(0, offsetReceivableChecked.value - offsetAmount.value))
const offsetDiffAmount = computed(() => {
    if (offsetPayableAfter.value > 0 && offsetReceivableAfter.value > 0) return 0
    return Math.max(offsetPayableAfter.value, offsetReceivableAfter.value)
})
const offsetDiffDirection = computed(() => offsetPayableAfter.value > 0 ? 'payable' : (offsetReceivableAfter.value > 0 ? 'receivable' : ''))
const offsetDiffText = computed(() => {
    if (offsetDiffDirection.value === 'payable') return `我还需付他 ${money(offsetDiffAmount.value)}`
    if (offsetDiffDirection.value === 'receivable') return `他还需付我 ${money(offsetDiffAmount.value)}`
    return '无差额'
})
const offsetResultText = computed(() => {
    const payableAfter = offsetPayableAfter.value
    const receivableAfter = offsetReceivableAfter.value
    if (payableAfter > 0 && receivableAfter > 0) return '双方仍有余额'
    if (payableAfter > 0) return `我还需付他 ${money(payableAfter)}`
    if (receivableAfter > 0) return `他还需付我 ${money(receivableAfter)}`
    return '两边互相结清'
})
const canSubmitReceipt = computed(() => {
    if (!receipt.row) return false
    const amount = Number(receiptTotal.value || 0)
    return amount > 0 && amount <= receiptRemainTotal.value && Number(receipt.form.capital_account_id || 0) > 0
})
const receiptTotal = computed(() => receipt.items.reduce((sum: number, row: any) => {
    if (!row.checked) return sum
    return sum + Number(row.receipt_amount || 0)
}, 0))
const receiptRemainTotal = computed(() => receipt.items.reduce((sum: number, row: any) => sum + itemRemain(row), 0))

onMounted(() => {
    loadList()
    loadAccounts()
})

async function loadList() {
    table.loading = true
    try {
        const [startAt, endAt] = search.dateRange || []
        const res: any = await getErpReceivableList({
            keyword: search.keyword,
            status: activeStatus.value,
            start_at: Number(startAt || 0),
            end_at: Number(endAt || 0) ? Number(endAt) + 86399 : 0,
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

async function openDetail(row: any) {
    detail.row = row
    detail.items = []
    detail.settlements = []
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpReceivableItems(row.id)
        detail.items = Array.isArray(res?.data) ? res.data : (res?.data?.items || [])
        detail.settlements = Array.isArray(res?.data) ? [] : (res?.data?.settlements || [])
    } finally {
        detail.loading = false
    }
}

async function openReceipt(row: any) {
    if (!canConfirmReceipt(row)) return
    receipt.row = row
    receipt.items = []
    receipt.form = { amount: remain(row), capital_account_id: accounts.value[0]?.id || 0, remark: '' }
    receipt.visible = true
    receipt.loading = true
    try {
        const res: any = await getErpReceivableItems(row.id)
        const list = Array.isArray(res?.data) ? res.data : (res?.data?.items || [])
        receipt.items = list.map((item: any) => ({
            ...item,
            sale_item_id: Number(item.id || 0),
            sale_price: Number(item.sale_price || 0),
            allocated_settled: Number(item.allocated_settled || 0),
            receipt_amount: Math.max(0, Number(item.allocated_remain || 0)),
            checked: Number(item.allocated_remain || 0) > 0
        }))
        syncReceiptFormAmount()
    } finally {
        receipt.loading = false
    }
}

async function openOffset(row: any) {
    if (!canOffset(row)) return
    offset.row = row
    offset.payables = []
    offset.receivables = []
    offset.form = { amount: 0, settle_diff: false, capital_account_id: 0, remark: '' }
    offset.visible = true
    offset.loading = true
    try {
        const [payRes, recRes]: any = await Promise.all([
            getErpPayablePartyItems(row.party_id, { status: '', page: 1, limit: 200 }),
            getErpReceivableList({ party_id: row.party_id, page: 1, limit: 200 })
        ])
        offset.payables = (payRes?.data?.data || [])
            .map((it: any) => ({
                payable_id: Number(it.asset_payable_id || it.payable_id || 0),
                payable_no: it.payable_no || '',
                model: it.model || '',
                imei: it.imei || '',
                source_no: it.purchase_no || it.source_no || '',
                remain: Number(it.allocated_remain ?? (Number(it.total_cost || 0) - Number(it.allocated_paid || 0))),
                checked: true
            }))
            .filter((it: any) => it.payable_id > 0 && it.remain > 0)
        offset.receivables = (recRes?.data?.data || [])
            .map((it: any) => ({
                id: Number(it.id || 0),
                receivable_no: it.receivable_no || '',
                source_no: it.source_no || it.batch_no || '',
                remain: remain(it),
                checked: true
            }))
            .filter((it: any) => it.id > 0 && it.remain > 0)
        await fillOffsetReceivableDevices(offset.receivables)
        if (!offset.payables.length || !offset.receivables.length) {
            ElMessage.warning('该往来主体需同时存在剩余应付和剩余应收才能折账')
        }
        syncOffsetAmount()
    } finally {
        offset.loading = false
    }
}

function syncOffsetAmount() {
    const current = Number(offset.form.amount || 0)
    const max = Number(offsetMax.value || 0)
    if (current <= 0 || current > max) {
        offset.form.amount = Number(max.toFixed(2))
    }
}

async function fillOffsetReceivableDevices(rows: any[]) {
    await Promise.all((rows || []).map(async (row: any) => {
        try {
            const res: any = await getErpReceivableItems(row.id)
            const data = res?.data
            const list = Array.isArray(data) ? data : (data?.items || [])
            row.device_summary = list.slice(0, 2).map((item: any) => {
                const imei = item.imei ? `IMEI ${item.imei}` : ''
                return [item.model, imei].filter(Boolean).join(' / ')
            }).filter(Boolean).join('；')
            row.device_more = Math.max(0, list.length - 2)
        } catch (e) {
            row.device_summary = ''
            row.device_more = 0
        }
    }))
}

async function submitOffset() {
    if (!offset.row) return
    const payableIds = offset.payables.filter((r: any) => r.checked).map((r: any) => r.payable_id)
    const receivableIds = offset.receivables.filter((r: any) => r.checked).map((r: any) => r.id)
    if (!payableIds.length) return ElMessage.warning('请勾选要抵扣的应付')
    if (!receivableIds.length) return ElMessage.warning('请勾选要抵扣的应收')
    const amount = Number(offset.form.amount || 0)
    if (amount <= 0) return ElMessage.warning('请填写折账金额')
    if (amount > offsetMax.value + 0.001) return ElMessage.warning('折账金额不能大于可折金额')
    if (offset.form.settle_diff) {
        if (amount < offsetMax.value - 0.001) return ElMessage.warning('部分抵扣后双方仍有余额，不能本次结清差额')
        if (offsetDiffAmount.value <= 0) return ElMessage.warning('当前没有需要结清的差额')
        if (!offset.form.capital_account_id) return ElMessage.warning('请选择差额收付款账户')
    }
    offset.saving = true
    try {
        await confirmErpOffset({
            payable_ids: payableIds,
            receivable_ids: receivableIds,
            amount,
            settle_diff: offset.form.settle_diff,
            capital_account_id: offset.form.capital_account_id,
            remark: offset.form.remark
        })
        ElMessage.success(`已折账 ¥${amount.toFixed(2)}`)
        offset.visible = false
        await loadList()
        if (detail.visible) await openDetail(detail.row)
    } finally {
        offset.saving = false
    }
}

async function submitReceipt() {
    if (!receipt.row) return
    const amount = Number(receiptTotal.value || 0)
    if (amount <= 0) return ElMessage.warning('请选择要收款的设备并填写本次收款')
    if (amount > receiptRemainTotal.value) return ElMessage.warning('收款金额不能大于设备剩余应收')
    if (!receipt.form.capital_account_id) return ElMessage.warning('请选择收款账户')
    receipt.saving = true
    try {
        await confirmErpReceipt(receipt.row.id, {
            ...receipt.form,
            amount,
            items: receipt.items.filter((item: any) => item.checked).map((item: any) => ({
                sale_item_id: item.sale_item_id,
                sale_price: item.sale_price,
                amount: item.receipt_amount
            }))
        })
        ElMessage.success('收款已确认')
        receipt.visible = false
        await loadList()
        await loadAccounts()
    } finally {
        receipt.saving = false
    }
}

function handleSearch() { table.page = 1; loadList() }
function handleReset() { search.keyword = ''; search.dateRange = []; activeStatus.value = ''; handleSearch() }
function remain(row: any) { return Math.max(0, Number(row.remain_amount ?? (Number(row.amount || 0) - Number(row.settled_amount || 0)))) }
function itemRemain(row: any) { return Math.max(0, Number(row.sale_price || 0) - Number(row.allocated_settled || 0)) }
function syncReceiptAmount(row: any) {
    const max = itemRemain(row)
    if (!row.checked) {
        row.receipt_amount = 0
    } else if (Number(row.receipt_amount || 0) <= 0 || Number(row.receipt_amount || 0) > max) {
        row.receipt_amount = Number(max.toFixed(2))
    }
    syncReceiptFormAmount()
}
function syncReceiptFormAmount() {
    receipt.form.amount = Number(receiptTotal.value.toFixed(2))
}
function canConfirmReceipt(row: any) { return remain(row) > 0 && row.status !== 'settled' }
function canOffset(row: any) { return Boolean(row.can_offset) && remain(row) > 0 }
function contactText(row: any) { return [row.contact_name, row.contact_mobile || row.m_no].filter(Boolean).join(' / ') || '-' }
function settleSummaryTagType(label: string) {
    if (label === '折账') return 'warning'
    if (label.includes('微信') || label.includes('支付宝') || label.includes('现金')) return 'success'
    if (label.includes('银行') || label.includes('银行卡')) return 'primary'
    return 'primary'
}
function deviceSummary(device: any) {
    return [
        device.spec,
        [device.warehouse_name, device.location_name].filter(Boolean).join(' / '),
        Number(device.sale_price || 0) > 0 ? `售价 ${money(device.sale_price)}` : '',
        Number(device.cost || 0) > 0 ? `成本 ${money(device.cost)}` : ''
    ].filter(Boolean).join(' · ') || '-'
}
function statusMeta(status: string) {
    const map: any = { pending: { label: '待结算', type: 'warning' }, partial: { label: '部分结算', type: 'primary' }, settled: { label: '已结清', type: 'success' } }
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
