<template>
    <HsxPage padding="none" class="main-container">
        <!-- 退货单列表：只在列表模式展示，不再固定占用左栏 -->
        <div v-show="mode !== 'detail'" class="erp-list-panel">
            <HsxTitle size="page" collapsible-subtitle class="mb-4">
                <template #default>采购退货</template>
                <template #subtitle>将仍在库且符合条件的设备退还供货方，系统自动判断冲销应付或退款应收。</template>
                <template #extra><div class="flex gap-2 flex-wrap">
                        <el-button :icon="Refresh" :loading="listLoading" @click="loadList">刷新</el-button>
                        <el-button type="primary" :icon="Plus" @click="openCreate">新建退货</el-button>
                    </div></template>
            </HsxTitle>

            <!-- 状态 Tab -->
            <el-tabs v-model="listWhere.status" class="mt-4 erp-status-tabs" @tab-change="switchStatus">
                <el-tab-pane v-for="tab in statusTabs" :key="tab.value" :label="tab.label" :name="tab.value" />
            </el-tabs>

            <HsxSearchPanel>
                <el-form :inline="true" class="mt-2" @submit.prevent>
                    <el-form-item label="关键词">
                        <el-input v-model.trim="listWhere.keyword" clearable class="!w-[260px]" placeholder="退货单号 / 采购单号" @keyup.enter="searchList" />
                    </el-form-item>
                    <el-form-item label="供货商">
                        <ErpPartySelect v-model="listWhere.party_id" v-model:party-name="listPartyName" party-type="supplier" :allow-create="false" class="!w-[220px]" placeholder="全部供货商" />
                    </el-form-item>
                    <el-form-item label="退货时间">
                        <el-date-picker v-model="listWhere.dateRange" type="daterange" value-format="X" start-placeholder="开始" end-placeholder="结束" class="!w-[260px]" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :icon="Search" @click="searchList">查询</el-button>
                        <el-button @click="resetListWhere">重置</el-button>
                    </el-form-item>
                </el-form>
            </HsxSearchPanel>

            <el-table :data="listData" v-loading="listLoading" size="large" @row-click="selectItem">
                <el-table-column prop="return_no" label="退货单" min-width="220" />
                <el-table-column label="供货商 / 原采购单" min-width="220">
                    <template #default="{ row }"><div class="font-medium">{{ row.party_name || '-' }}</div><div class="mt-1 text-xs text-gray-500">{{ row.purchase_no || '-' }}</div></template>
                </el-table-column>
                <el-table-column label="账务处理" min-width="150"><template #default="{ row }">{{ refundModeLabel(row.refund_mode) }}</template></el-table-column>
                <el-table-column label="退货金额" width="140" align="right"><template #default="{ row }"><span class="font-medium text-orange-600">¥{{ row.total_amount }}</span></template></el-table-column>
                <el-table-column label="状态" width="140" align="center"><template #default="{ row }"><el-tag :type="row.process_status_type || statusTagType(row.status)" effect="plain">{{ row.process_status_label || statusLabel(row.status) }}</el-tag></template></el-table-column>
                <el-table-column label="操作" width="100" align="center"><template #default="{ row }"><el-button type="primary" link @click.stop="selectItem(row)">查看</el-button></template></el-table-column>
            </el-table>

            <div class="panel-footer">
                <el-pagination
                    v-model:current-page="pagination.page"
                    :page-size="pagination.limit"
                    :total="pagination.total"
                    layout="total, prev, pager, next"
                    @current-change="loadList"
                />
            </div>
        </div>

        <ErpReturnDialog
            :model-value="mode === 'create'"
            title="新建采购退货单"
            subtitle="核对设备已经实际交还供货方，再确认库存与账务处理。"
            :confirm-text="createConfirmText"
            tip="确认后设备立即退出库存；请先核对交接事实和单台结算。"
            :loading="submitting"
            :disabled="!canSubmit"
            @close="resetCreate"
            @confirm="submitCreate"
        >
                <div class="return-flow-guide">
                    <div><span>1</span><b>选择供货方</b><small>系统加载该供货方可退设备</small></div>
                    <i></i>
                    <div><span>2</span><b>选择退回设备</b><small>系统逐台判断是否可退</small></div>
                    <i></i>
                    <div><span>3</span><b>确认交接与账务</b><small>自动冲应付或生成退款应收</small></div>
                </div>

                <el-form ref="formRef" :model="form" label-position="top" class="form-body create-return-shell">
                    <section class="create-source-card">
                        <div class="source-picker-row">
                            <div>
                                <div class="section-kicker">退货来源</div>
                                <div class="section-hint">选择供货方后，系统自动汇总其当前可退设备</div>
                            </div>
                            <div class="source-query-fields">
                                <el-form-item label="退货供货方" class="source-query-field" prop="party_id" :rules="[{ required: true, message: '请选择供货方' }]">
                                    <ErpPartySelect
                                        v-model="form.party_id"
                                        v-model:party-name="sourcePartyName"
                                        party-type="supplier"
                                        :allow-create="false"
                                        placeholder="选择供货方后自动加载可退设备"
                                        @change="onSourcePartyChange"
                                    />
                                </el-form-item>
                            </div>
                        </div>
                    </section>

                    <div class="create-return-grid">
                        <main class="return-device-panel">
                            <div class="section-heading">
                                <div>
                                    <div class="section-kicker">核对退货设备</div>
                                    <div class="section-hint">只显示该供货方仍在库且符合退货规则的设备；原采购单由系统自动关联</div>
                                </div>
                                <span class="selected-count">已选 {{ form.items.length }} 台</span>
                            </div>

                            <div v-loading="assetsLoading" class="return-device-grid">
                                <article
                                    v-for="row in availableAssets"
                                    :key="row.asset_id || row.id"
                                    class="return-device-card"
                                    :class="{ selected: isAssetSelected(row), blocked: !canSelectReturnAsset(row) }"
                                    @click="toggleAssetCard(row)"
                                >
                                    <div class="device-card-head">
                                        <el-checkbox
                                            :model-value="isAssetSelected(row)"
                                            :disabled="!canSelectReturnAsset(row)"
                                            @click.stop
                                            @change="setAssetSelected(row, Boolean($event))"
                                        />
                                        <div class="device-card-title-wrap">
                                            <div class="device-card-title">{{ row.model || '未填写型号' }}</div>
                                            <div class="device-card-spec">{{ row.spec || '未填写规格' }}</div>
                                        </div>
                                        <el-tag :type="canSelectReturnAsset(row) ? 'success' : 'danger'" size="small" effect="light">
                                            {{ canSelectReturnAsset(row) ? '可退货' : '不可退货' }}
                                        </el-tag>
                                    </div>

                                    <div class="device-card-identities">
                                        <span>IMEI {{ row.imei || '-' }}</span>
                                        <span>{{ row.warehouse_name || '-' }}<template v-if="row.location_name"> / {{ row.location_name }}</template></span>
                                        <span>来源 {{ row.purchase_no || '-' }}</span>
                                    </div>

                                    <div class="device-money-grid">
                                        <div><span>采购本金</span><b>¥{{ Number(row.payable_amount || row.purchase_cost || 0).toFixed(2) }}</b></div>
                                        <div><span>当前总成本</span><b>¥{{ Number(row.total_cost || 0).toFixed(2) }}</b></div>
                                        <div><span>结算情况</span><b :class="assetSettlementClass(row)">{{ assetSettlementText(row) }}</b></div>
                                    </div>

                                    <div v-if="!canSelectReturnAsset(row)" class="device-block-reason">{{ row.return_flow?.block_reason || '当前设备不符合采购退货条件' }}</div>

                                    <div v-if="isAssetSelected(row)" class="device-card-form" @click.stop>
                                        <div v-if="Number(row.paid_amount || 0) > 0" class="device-field">
                                            <label>供货方应退金额</label>
                                            <el-input-number
                                                v-model="row._return_cost"
                                                :min="0"
                                                :max="Number(row.payable_amount || row.purchase_cost || 0)"
                                                :precision="2"
                                                :step="1"
                                                size="small"
                                                @change="syncReturnItems"
                                            />
                                        </div>
                                        <div v-else class="unpaid-result">
                                            <div class="unpaid-result__main">
                                                <span class="unpaid-result__badge">未结算</span>
                                                <b>自动冲销应付 ¥{{ Number(row.payable_amount || row.purchase_cost || 0).toFixed(2) }}</b>
                                            </div>
                                            <span class="unpaid-result__tip">无需供货方退款，确认退货后自动完成</span>
                                        </div>
                                        <div class="device-field device-field--reason">
                                            <label>退货原因 <span>选填</span></label>
                                            <el-input v-model="row._reason" placeholder="选填，例如供货方反悔" size="small" @change="syncReturnItems" />
                                        </div>
                                    </div>
                                </article>

                                <el-empty v-if="!assetsLoading && form.party_id && !availableAssets.length" description="该供货方暂无可退的在库设备" :image-size="70" />
                                <div v-if="!assetsLoading && !form.party_id" class="device-empty-guide">选择供货方后，在这里核对要退回的设备</div>
                            </div>
                        </main>

                        <aside class="return-decision-panel">
                            <section class="decision-card" :class="{ refund: selectedRequiresRefund }">
                                <div class="decision-label">本次处理结果</div>
                                <div class="decision-title">{{ decisionTitle }}</div>
                                <div class="decision-copy">{{ returnPolicyText }}</div>
                                <div class="decision-metrics">
                                    <div><span>退出库存</span><b>{{ form.items.length }} 台</b></div>
                                    <div><span>冲销应付</span><b class="text-green-600">¥{{ selectedOffsetTotal.toFixed(2) }}</b></div>
                                    <div><span>退款应收</span><b :class="selectedRequiresRefund ? 'text-orange-600' : ''">¥{{ selectedRefundTotal.toFixed(2) }}</b></div>
                                </div>
                            </section>

                            <section v-if="selectedRequiresRefund" class="side-form-card">
                                <label class="side-form-label">退款怎么处理</label>
                                <el-radio-group v-model="form.refund_mode" class="w-full">
                                    <el-radio-button value="cash">当场收款</el-radio-button>
                                    <el-radio-button value="receivable">记账待收</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs leading-5 text-gray-500">
                                    {{ form.refund_mode === 'cash' ? '供货方已经退款：选择实际到账账户，本次直接完成，不进入财务待办。' : '供货方暂未退款：生成设备级应收，由财务后续收款或折账。' }}
                                </div>
                                <el-select v-if="form.refund_mode === 'cash'" v-model="form.capital_account_id" class="mt-3 w-full" placeholder="选择实际到账账户">
                                    <el-option v-for="account in capitalAccounts" :key="account.id" :label="account.account_name" :value="account.id" />
                                </el-select>
                                <div v-if="form.refund_mode === 'cash'" class="mt-3">
                                    <ErpFinanceVoucherUpload v-model="form.voucher_urls" />
                                </div>
                            </section>

                            <section class="side-form-card">
                                <label class="side-form-label">整单备注</label>
                                <el-input v-model="form.remark" type="textarea" :rows="3" placeholder="选填，记录退货背景或特殊说明" />
                            </section>

                            <section v-if="form.items.length" class="handover-confirm">
                                <el-checkbox v-model="handoverConfirmed">机器已实际交还供货方</el-checkbox>
                                <div class="handover-confirm__tip">勾选即代表业务交接已完成；确认后设备立即退出库存，不能普通撤销。</div>
                            </section>
                        </aside>
                    </div>
                </el-form>
        </ErpReturnDialog>

            <!-- 详情视图 -->
        <div v-if="mode === 'detail' && selected" class="erp-form-panel">
                <div class="form-header">
                    <div>
                        <el-button link type="primary" class="!ml-0 mb-2" @click="backToList">← 返回退货列表</el-button>
                        <br />
                        <span class="form-title">{{ selected.return_no }}</span>
                        <el-tag :type="selectedDetail?.process_status_type || statusTagType(selected.status)" class="ml-2">{{ selectedDetail?.process_status_label || selected.process_status_label || statusLabel(selected.status) }}</el-tag>
                    </div>
                    <div class="flex gap-2">
                        <el-button
                            v-if="refundReceivable && refundReceivable.status !== 'settled'"
                            type="warning" size="small"
                            @click="goRefundReceivable(selected)"
                        >去应收款确认退款</el-button>
                        <el-button
                            v-if="selected.status === 'pending'"
                            type="danger" size="small"
                            @click="doCancel(selected.id)"
                        >取消退货单</el-button>
                        <el-button
                            v-if="selected.status === 'pending'"
                            type="primary" size="small"
                            :loading="confirming"
                            @click="doConfirm(selected.id)"
                        >确认旧退货单</el-button>
                    </div>
                </div>

                <div class="detail-body">
                    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div class="return-metric">
                            <div class="metric-label">退货金额</div>
                            <div class="metric-value text-orange-600">¥{{ selected.total_amount }}</div>
                        </div>
                        <div class="return-metric">
                            <div class="metric-label">退货台数</div>
                            <div class="metric-value">{{ selectedDetail?.items?.length || 0 }}</div>
                        </div>
                        <div class="return-metric">
                            <div class="metric-label">退款方式</div>
                            <div class="metric-value">{{ refundModeLabel(selected.refund_mode) }}</div>
                        </div>
                        <div class="return-metric">
                            <div class="metric-label">退款到账</div>
                            <div class="metric-value" :class="refundReceivable?.status === 'settled' ? 'text-green-600' : 'text-orange-600'">{{ refundProgressText }}</div>
                        </div>
                    </div>
                    <el-descriptions :column="3" border size="small" class="mb-4">
                        <el-descriptions-item label="供货方">{{ selected.party_name }}</el-descriptions-item>
                        <el-descriptions-item label="原采购单">{{ selected.purchase_no }}</el-descriptions-item>
                        <el-descriptions-item label="退货金额">¥{{ selected.total_amount }}</el-descriptions-item>
                        <el-descriptions-item label="退款方式">{{ refundModeLabel(selected.refund_mode) }}</el-descriptions-item>
                        <el-descriptions-item label="操作员">{{ selected.operator_name }}</el-descriptions-item>
                        <el-descriptions-item label="备注">{{ selected.remark || '-' }}</el-descriptions-item>
                    </el-descriptions>
                    <HsxNotice default-expanded class="mb-4" :title="purchaseRefundModeTip(selected.refund_mode)" type="info" :closable="false" show-icon />

                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium">退货明细</span>
                        <span class="text-xs text-gray-500">已付款才需要真实退款，未付款部分会冲销原应付。</span>
                    </div>
                    <el-table :data="selectedDetail?.items || []" size="small" border>
                        <el-table-column prop="imei" label="IMEI" min-width="130" />
                        <el-table-column prop="model" label="型号" min-width="110" />
                        <el-table-column prop="return_cost" label="退货价" width="90">
                            <template #default="{ row }">¥{{ row.return_cost }}</template>
                        </el-table-column>
                        <el-table-column prop="paid_amount" label="已付金额" width="90">
                            <template #default="{ row }">¥{{ row.paid_amount }}</template>
                        </el-table-column>
                        <el-table-column label="处理方式" width="120">
                            <template #default="{ row }">
                                <span v-if="Number(row.refund_receivable_amount || 0) <= 0" class="text-gray-500">冲销应付 ¥{{ Number(row.unpaid_offset_amount || 0).toFixed(2) }}</span>
                                <span v-else-if="Number(row.unpaid_offset_amount || 0) <= 0" class="text-blue-600">退款应收 ¥{{ Number(row.refund_receivable_amount || 0).toFixed(2) }}</span>
                                <span v-else class="text-orange-600">冲应付 {{ Number(row.unpaid_offset_amount || 0).toFixed(2) }} / 应收 {{ Number(row.refund_receivable_amount || 0).toFixed(2) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="reason" label="原因" min-width="100" />
                    </el-table>
                </div>
        </div>
    </HsxPage>
</template>

<script setup lang="ts">
import { HsxTitle, HsxPage, HsxSearchPanel, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'
import { ref, computed, reactive } from 'vue'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessageBox } from 'element-plus'
import {
    getErpPurchaseReturnList,
    getErpPurchaseReturnInfo,
    createErpPurchaseReturn,
    confirmErpPurchaseReturn,
    cancelErpPurchaseReturn,
} from '@/addon/hsx_erp/api/erp'
import { getErpPurchaseList, getErpPurchaseInfo } from '@/addon/hsx_erp/api/erp'
import { useRoute, useRouter } from 'vue-router'
import useUserStore from '@/stores/modules/user'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import ErpReturnDialog from '@/addon/hsx_erp/components/ErpReturnDialog.vue'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
const hsxFeedback = useFeedback()


// ── 状态 ──────────────────────────────────────────────────────────────────────
const statusTabs = [
    { label: '全部', value: '' },
    { label: '待确认', value: 'pending' },
    { label: '已完成退货', value: 'confirmed' },
    { label: '已取消', value: 'cancelled' },
]

const mode = ref<'idle' | 'create' | 'detail'>('idle')
const listLoading = ref(false)
const submitting = ref(false)
const confirming = ref(false)
const listData = ref<any[]>([])
const selected = ref<any>(null)
const selectedDetail = ref<any>(null)
const pagination = reactive({ page: 1, limit: 15, total: 0 })
const listWhere = reactive({ keyword: '', party_id: null as number | null, status: '', dateRange: [] as any[] })
const listPartyName = ref('')

// 新建表单
const formRef = ref()
const form = reactive({
    party_id: null as number | null,
    purchase_order_id: null as number | null,
    refund_mode: 'cash',
    capital_account_id: null as number | null,
    voucher_urls: '',
    remark: '',
    items: [] as any[],
})
const purchaseOptions = ref<any[]>([])
const sourcePartyName = ref('')
const purchaseSearchLoading = ref(false)
const availableAssets = ref<any[]>([])
const assetsLoading = ref(false)
const selectedAssets = ref<any[]>([])
const selectedPurchaseInfo = ref<any>(null)
const handoverConfirmed = ref(false)
const capitalAccounts = ref<any[]>([])
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const targetAssetId = ref(Number(route.query.asset_id || 0))
const operatorName = computed(() => {
    const user: any = userStore.userInfo || {}
    return user.real_name || user.name || user.username || '当前登录管理员'
})

// ── 计算 ──────────────────────────────────────────────────────────────────────
const selectedFlows = computed(() => selectedAssets.value.map(item => calculateReturnFlow(item)))
const selectedOffsetTotal = computed(() => selectedFlows.value.reduce((sum, flow) => sum + flow.offset_amount, 0))
const selectedRefundTotal = computed(() => selectedFlows.value.reduce((sum, flow) => sum + flow.refund_amount, 0))
const selectedRequiresRefund = computed(() => selectedRefundTotal.value > 0.0001)
const createConfirmText = computed(() => !selectedRequiresRefund.value
    ? '确认退货'
    : (form.refund_mode === 'cash' ? '确认退货并收款' : '确认退货并记账'))
const decisionTitle = computed(() => {
    if (!selectedAssets.value.length) return '请先选择设备'
    return selectedRequiresRefund.value ? '已退机 · 等待财务收款' : '已退机 · 无需财务处理'
})
const returnPolicyText = computed(() => {
    if (!selectedAssets.value.length) return '选择设备后，系统会根据单台实际结算自动决定是否生成退款应收。'
    if (!selectedRequiresRefund.value) return '所选设备未形成需要追回的款项；确认后直接作废设备应付，不生成退款应收。'
    return `冲销未付款 ¥${selectedOffsetTotal.value.toFixed(2)}，并生成供货方退款应收 ¥${selectedRefundTotal.value.toFixed(2)}。`
})
const canSubmit = computed(() => form.items.length > 0 && !!form.party_id && handoverConfirmed.value
    && (!selectedRequiresRefund.value || form.refund_mode !== 'cash' || Number(form.capital_account_id || 0) > 0)
    && selectedAssets.value.every((item: any) => {
        if (Number(item.paid_amount || 0) <= 0.0001) return true
        const amount = Number(item._return_cost || 0)
        const supplierAmount = Number(item.payable_amount || item.purchase_cost || 0)
        return amount > 0 && amount <= supplierAmount + 0.0001
    }))
const refundReceivable = computed(() => selectedDetail.value?.refund_receivable || null)
const refundProgressText = computed(() => {
    if (selected.value?.refund_mode === 'none') return '未付款，已冲销应付'
    if (!refundReceivable.value) return '待生成应收'
    return refundReceivable.value.status === 'settled'
        ? `已到账 ¥${Number(refundReceivable.value.settled_amount || 0).toFixed(2)}`
        : `待确认 ¥${Number(refundReceivable.value.amount || 0).toFixed(2)}`
})

// ── 列表 ──────────────────────────────────────────────────────────────────────
async function loadList() {
    listLoading.value = true
    try {
        const [startAt, endAt] = listWhere.dateRange || []
        const res = await getErpPurchaseReturnList({
            ...listWhere,
            start_at: Number(startAt || 0),
            end_at: Number(endAt || 0) ? Number(endAt) + 86399 : 0,
            dateRange: undefined,
            page: pagination.page,
            limit: pagination.limit,
        })
        listData.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } finally {
        listLoading.value = false
    }
}

function switchStatus(status: any) {
    listWhere.status = String(status || '')
    pagination.page = 1
    loadList()
}

function searchList() {
    pagination.page = 1
    loadList()
}

function resetListWhere() {
    listWhere.keyword = ''
    listWhere.party_id = null
    listPartyName.value = ''
    listWhere.status = ''
    listWhere.dateRange = []
    pagination.page = 1
    loadList()
}

async function selectItem(item: any) {
    selected.value = item
    mode.value = 'detail'
    const res = await getErpPurchaseReturnInfo(item.id)
    selectedDetail.value = res.data
}

// ── 新建 ──────────────────────────────────────────────────────────────────────
function openCreate() {
    mode.value = 'create'
    selected.value = null
    form.party_id = null
    form.purchase_order_id = null
    form.refund_mode = 'receivable'
    form.capital_account_id = null
    form.voucher_urls = ''
    form.remark = ''
    form.items = []
    availableAssets.value = []
    purchaseOptions.value = []
    sourcePartyName.value = ''
    selectedAssets.value = []
    selectedPurchaseInfo.value = null
    handoverConfirmed.value = false
    loadCapitalAccounts()
}

async function loadCapitalAccounts() {
    if (capitalAccounts.value.length) return
    const res: any = await getCapitalAccounts()
    capitalAccounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
    const preferred = capitalAccounts.value.find((item: any) => Number(item.is_default) === 1) || capitalAccounts.value[0]
    if (!form.capital_account_id && preferred) form.capital_account_id = Number(preferred.id)
}

function resetCreate() {
    mode.value = selected.value ? 'detail' : 'idle'
}

function backToList() {
    mode.value = 'idle'
    selected.value = null
    selectedDetail.value = null
    loadList()
}

async function searchPurchaseOrders(query: string) {
    if (!form.party_id) {
        purchaseOptions.value = []
        return
    }
    purchaseSearchLoading.value = true
    try {
        const res = await getErpPurchaseList({ keyword: query || '', party_id: form.party_id, status: 'completed', limit: 100 })
        // 采购列表返回设备列表，需要去重采购单
        const orders: Record<number, any> = {}
        ;(res.data?.data || []).filter((a: any) => a.status === 'in_stock').forEach((a: any) => {
            if (a.purchase_order_id && !orders[a.purchase_order_id]) {
                orders[a.purchase_order_id] = {
                    id: a.purchase_order_id,
                    purchase_no: a.purchase_no,
                    party_name: a.party_name,
                    asset_count: 0,
                }
            }
            if (a.purchase_order_id) orders[a.purchase_order_id].asset_count += 1
        })
        purchaseOptions.value = Object.values(orders)
    } finally {
        purchaseSearchLoading.value = false
    }
}

async function onSourcePartyChange(party: any) {
    form.party_id = party?.id ? Number(party.id) : null
    sourcePartyName.value = party?.party_name || ''
    formRef.value?.clearValidate('party_id')
    form.purchase_order_id = null
    purchaseOptions.value = []
    availableAssets.value = []
    selectedAssets.value = []
    selectedPurchaseInfo.value = null
    form.items = []
    handoverConfirmed.value = false
    if (!form.party_id) return
    assetsLoading.value = true
    try {
        const res = await getErpPurchaseList({ party_id: form.party_id, limit: 100 })
        availableAssets.value = (res.data?.data || [])
            .filter((item: any) => item.status === 'in_stock' && item.order_status !== 'void')
            .map(normalizeReturnAsset)
    } finally {
        assetsLoading.value = false
    }
}

async function onPurchaseOrderChange(orderId: number) {
    selectedAssets.value = []
    form.items = []
    handoverConfirmed.value = false
    if (!orderId) {
        availableAssets.value = []
        selectedPurchaseInfo.value = null
        return
    }
    assetsLoading.value = true
    try {
        const res = await getErpPurchaseInfo(orderId)
        selectedPurchaseInfo.value = res.data || null
        if (selectedPurchaseInfo.value) {
            form.party_id = Number(selectedPurchaseInfo.value.party_id || 0) || null
            sourcePartyName.value = selectedPurchaseInfo.value.party_name || ''
        }
        if (selectedPurchaseInfo.value && !purchaseOptions.value.some((item: any) => Number(item.id) === Number(orderId))) {
            purchaseOptions.value.unshift({
                id: Number(orderId),
                purchase_no: selectedPurchaseInfo.value.purchase_no,
                party_name: selectedPurchaseInfo.value.party_name,
            })
        }
        // 过滤出在库设备
        const assets = (res.data?.items || [])
            .filter((item: any) => item.status === 'in_stock' || item.asset_status === 'in_stock')
            .map(normalizeReturnAsset)
        availableAssets.value = assets
        const target = assets.find((item: any) => Number(item.asset_id || item.id) === targetAssetId.value)
        if (target && canSelectReturnAsset(target)) {
            selectedAssets.value = [target]
            syncReturnItems()
        }
    } finally {
        assetsLoading.value = false
    }
}

function isAssetSelected(row: any) {
    const assetId = Number(row.asset_id || row.id)
    return selectedAssets.value.some((item: any) => Number(item.asset_id || item.id) === assetId)
}

function setAssetSelected(row: any, checked: boolean) {
    if (!canSelectReturnAsset(row)) return
    const assetId = Number(row.asset_id || row.id)
    const next = selectedAssets.value.filter((item: any) => Number(item.asset_id || item.id) !== assetId)
    if (checked) next.push(row)
    selectedAssets.value = next
    handoverConfirmed.value = false
    syncReturnItems()
}

function toggleAssetCard(row: any) {
    if (!canSelectReturnAsset(row)) return
    setAssetSelected(row, !isAssetSelected(row))
}

function canSelectReturnAsset(row: any) {
    return row?.return_flow?.returnable !== false
}

function normalizeReturnAsset(item: any) {
    const payableAmount = Number(item?.return_flow?.payable_amount ?? item?.asset_payable_amount ?? item?.payable_amount ?? item?.purchase_cost ?? 0)
    const paidAmount = Number(item?.return_flow?.paid_amount ?? item?.asset_paid_amount ?? item?.paid_amount ?? 0)
    return {
        ...item,
        payable_amount: payableAmount,
        paid_amount: paidAmount,
        unpaid_amount: Math.max(0, Number(item?.return_flow?.unpaid_amount ?? (payableAmount - paidAmount))),
        _return_cost: Number(item?.return_flow?.default_return_amount ?? payableAmount),
        _reason: '',
    }
}

function assetSettlementText(row: any) {
    const payable = Number(row?.payable_amount || row?.purchase_cost || 0)
    const paid = Number(row?.paid_amount || 0)
    if (paid <= 0.0001) return '未结算'
    if (paid + 0.0001 >= payable) return `已结清 ¥${paid.toFixed(2)}`
    return `已付 ¥${paid.toFixed(2)} · 待付 ¥${Math.max(0, payable - paid).toFixed(2)}`
}

function assetSettlementClass(row: any) {
    const paid = Number(row?.paid_amount || 0)
    return paid > 0.0001 ? 'text-green-600' : 'text-orange-500'
}

function calculateReturnFlow(item: any) {
    const supplierAmount = Math.max(0, Number(item.payable_amount || item.purchase_cost || 0))
    const paidAmount = Math.max(0, Number(item.paid_amount || 0))
    const unpaidAmount = Math.max(0, supplierAmount - paidAmount)
    const returnAmount = paidAmount <= 0.0001
        ? supplierAmount
        : Math.max(0, Number(item._return_cost || 0))
    const offsetAmount = Math.min(unpaidAmount, returnAmount)
    return { offset_amount: offsetAmount, refund_amount: Math.max(0, returnAmount - offsetAmount) }
}

function syncReturnItems() {
    form.items = selectedAssets.value.map((a: any) => ({
        asset_id: a.asset_id || a.id,
        purchase_order_id: a.purchase_order_id || form.purchase_order_id,
        return_cost: Number(a._return_cost) || 0,
        reason: a._reason || '',
    }))
}

async function submitCreate() {
    await formRef.value?.validate()
    if (!form.items.length) {
        hsxFeedback.warning('请选择至少一台退货设备')
        return
    }
    if (!handoverConfirmed.value) {
        hsxFeedback.warning('请先确认设备已经交还供货方')
        return
    }
    const imeis = selectedAssets.value.map((item: any) => item.imei || item.sn || item.model || '未命名设备').join('、')
    const settlementText = !selectedRequiresRefund.value
        ? '本次仅冲销未付款应付，不产生退款应收。'
        : (form.refund_mode === 'cash'
            ? `供货方退款 ¥${selectedRefundTotal.value.toFixed(2)} 已当场到账，将记入所选资金账户。`
            : `供货方暂欠 ¥${selectedRefundTotal.value.toFixed(2)}，将生成退款应收交财务跟进。`)
    const confirmed = await ElMessageBox.confirm(
        `确认退货 ${selectedAssets.value.length} 台（${imeis}）。确认后设备立即退出库存；冲销应付 ¥${selectedOffsetTotal.value.toFixed(2)}。${settlementText}`,
        '确认设备已经交还供货方',
        { confirmButtonText: '确认退货', cancelButtonText: '再检查一下', type: 'warning' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    submitting.value = true
    try {
        const res: any = await createErpPurchaseReturn({
            purchase_order_id: 0,
            refund_mode: selectedRequiresRefund.value ? form.refund_mode : 'none',
            capital_account_id: form.refund_mode === 'cash' ? Number(form.capital_account_id || 0) : 0,
            voucher_urls: form.refund_mode === 'cash' ? form.voucher_urls : '',
            remark: form.remark,
            items: form.items,
        })
        const result = res?.data || {}
        mode.value = 'idle'
        loadList()
        if (selectedRequiresRefund.value && form.refund_mode === 'receivable') {
            const refundAmount = Number(result?.refund_receivable?.amount || selectedRefundTotal.value).toFixed(2)
            const goFinance = await ElMessageBox.confirm(
                `设备已退出库存，退款应收 ¥${refundAmount} 已生成。下一步由财务到「应收款」确认供货方实际退款。`,
                '已退机，等待供货方退款',
                { confirmButtonText: '去应收款', cancelButtonText: '稍后处理', type: 'success' }
            ).then(() => true).catch(() => false)
            if (goFinance) {
                const returnNos = Array.isArray(result.return_nos) ? result.return_nos : []
                router.push({ path: '/site/hsx_erp/receivable', query: returnNos.length === 1 ? { source_no: returnNos[0] } : {} })
            }
        } else {
            const message = selectedRequiresRefund.value
                ? `设备已退出库存，退款 ¥${selectedRefundTotal.value.toFixed(2)} 已记入所选账户，本次无需财务再次处理。`
                : '设备已退出库存，对应设备应付已作废，本次无需财务退款处理。'
            await ElMessageBox.alert(message, '退货已完成', {
                confirmButtonText: '知道了', type: 'success'
            })
        }
    } finally {
        submitting.value = false
    }
}

// ── 确认 / 撤销 ───────────────────────────────────────────────────────────────
async function doConfirm(id: number) {
    await ElMessageBox.confirm('这是旧流程遗留的待确认退货单。确认后设备退出库存，应付账款同步处理，是否继续？', '确认旧退货单', { type: 'warning' })
    confirming.value = true
    try {
        await confirmErpPurchaseReturn(id)
        hsxFeedback.success('退货已确认')
        loadList()
        const res = await getErpPurchaseReturnInfo(id)
        selected.value = listData.value.find((i) => i.id === id) || selected.value
        selectedDetail.value = res.data
    } finally {
        confirming.value = false
    }
}

async function doCancel(id: number) {
    await ElMessageBox.confirm('确认取消该退货单？取消后不会执行设备出库和账务处理。', '取消退货单', { type: 'warning' })
    await cancelErpPurchaseReturn(id)
    hsxFeedback.success('退货单已取消')
    loadList()
    mode.value = 'idle'
    selected.value = null
}

function goRefundReceivable(row: any) {
    router.push({ path: '/site/hsx_erp/receivable', query: { source_no: row?.return_no || '' } })
}

// ── 辅助 ──────────────────────────────────────────────────────────────────────
function statusLabel(status: string) {
    const map: Record<string, string> = {
        pending: '待确认', confirmed: '已完成退货', cancelled: '已取消',
    }
    return erpEnumLabel(status, map)
}
function statusTagType(status: string) {
    const map: Record<string, string> = {
        pending: 'warning', confirmed: 'success', cancelled: 'info',
    }
    return map[status] || ''
}
function refundModeLabel(mode: string) {
    const map: Record<string, string> = { none: '未付款，已冲销应付', cash: '当场收款', receivable: '记账待收', offset: '记账待收（历史）' }
    return erpEnumLabel(mode, map, '退款方式待确认')
}
function purchaseRefundModeTip(mode: string) {
    const map: Record<string, string> = {
        none: '本次退货未形成需要追回的付款，系统仅冲销原应付，无需财务跟进。',
        cash: '供货方退款已当场进入所选资金账户，系统已形成实际收款流水，无需财务再次确认。',
        receivable: '设备已经退给供货方，退款暂未到账；系统已生成应收，由财务后续收款或折账。',
        offset: '历史单按记账待收处理，由财务核对后续结算。',
    }
    return map[mode] || '请根据本单账务处理结果完成后续核对。'
}
function formatSourceTime(value: any) {
    const ts = Number(value || 0)
    if (!ts) return '-'
    return new Date(ts * 1000).toLocaleString('zh-CN', { hour12: false })
}

// 初始化
useErpPageRefresh(loadList)
// 如果从采购页带着 purchase_order_id 过来，自动打开新建并预选采购单
if (route.query.purchase_order_id) {
    openCreate()
    form.purchase_order_id = Number(route.query.purchase_order_id)
    onPurchaseOrderChange(Number(route.query.purchase_order_id))
}
</script>

<style scoped>
.erp-list-panel, .erp-form-panel {
    width: 100%;
    padding: 20px;
    border: 0;
    border-radius: 0;
    background: #fff;
    box-shadow: none;
}
.erp-form-panel {
    padding: 20px;
}
.panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 0;
}
.panel-title { color:#111827; font-weight:650; font-size:20px; }
.panel-subtitle { margin-top:5px; color:#64748b; font-size:13px; line-height:20px; }
.return-status-tabs { padding:0 22px; border-top:1px solid #f0f3f7; }
.return-status-tabs :deep(.el-tabs__header) { margin:0; }
.return-status-tabs :deep(.el-tabs__nav-wrap::after) { height:1px; background:#f0f3f7; }
.return-status-tabs :deep(.el-tabs__item) { height:48px; padding:0 18px; }
.panel-search { display:flex; align-items:center; flex-wrap:wrap; gap:10px; padding:14px 22px; background:#fbfcfe; }
.panel-search > * { max-width:100%; min-width:0; }
.filter-keyword { width:min(360px,100%); }
.filter-party { width:220px; max-width:100%; min-width:0; }
.filter-date { width:280px; max-width:100%; min-width:0 !important; }
.filter-date:deep(.el-date-editor) { width:100%; max-width:100%; min-width:0; }
.filter-actions { margin-left:auto; }
.panel-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(310px,1fr)); gap:12px; min-height:240px; padding:18px 22px; }
.list-item {
    min-width:0;
    padding:14px 15px;
    cursor: pointer;
    border:1px solid #e7ecf3;
    border-radius:9px;
    background:#fff;
    transition:border-color .15s,box-shadow .15s,transform .15s;
}
.list-item:hover { border-color:#a8c7ff; box-shadow:0 5px 16px rgba(37,99,235,.08); transform:translateY(-1px); }
.list-item.selected { border-color:var(--el-color-primary); background:var(--el-color-primary-light-9); }
.panel-list :deep(.el-empty) { grid-column:1/-1; }
.panel-footer { padding-top:16px; display:flex; justify-content:flex-end; }
.form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.form-title { color:#111827; font-size:17px; font-weight:650; }
.form-subtitle { margin-top:3px; color:#94a3b8; font-size:12px; }
.form-body { padding: 0 4px; }
.create-return-shell { max-width:1440px; margin:0 auto; }
.detail-body { max-width:1440px; margin:0 auto; padding:0 4px; }
.return-flow-guide { display:grid; max-width:1100px; margin:0 auto 18px; grid-template-columns:minmax(0,1fr) 44px minmax(0,1fr) 44px minmax(0,1fr); align-items:center; border:1px solid #dbeafe; border-radius:10px; background:#f8fbff; padding:12px 16px; }
.return-flow-guide div { display:grid; grid-template-columns:28px 1fr; column-gap:9px; align-items:center; }
.return-flow-guide span { display:flex; width:28px; height:28px; grid-row:1/3; align-items:center; justify-content:center; border-radius:50%; color:#fff; background:var(--el-color-primary); font-size:12px; font-weight:700; }
.return-flow-guide b { color:#1e293b; font-size:13px; }
.return-flow-guide small { margin-top:2px; color:#94a3b8; font-size:11px; }
.return-flow-guide i { height:1px; background:#bfdbfe; }
.return-metric {
    border-radius: 8px;
    background: #f8fafc;
    padding: 12px 14px;
}
.metric-label {
    color: #64748b;
    font-size: 12px;
}
.metric-value {
    margin-top: 4px;
    color: #111827;
    font-size: 18px;
    font-weight: 600;
}
.create-source-card { margin-bottom:16px; padding:14px 16px; border:0; border-radius:6px; background:#f8fafc; }
.source-picker-row { display:flex; align-items:center; justify-content:space-between; gap:24px; }
.source-query-fields { width:min(420px,100%); }
.source-query-field { margin:0; }
.source-query-field :deep(.el-form-item__label) { height:auto; margin-bottom:5px; color:#64748b; font-size:12px; line-height:1.3; }
.section-kicker { color:#111827; font-size:15px; font-weight:650; }
.section-hint { margin-top:3px; color:#94a3b8; font-size:12px; }
.source-summary { display:grid; grid-template-columns:minmax(220px,1.5fr) repeat(3,minmax(120px,1fr)); gap:16px; margin-top:14px; padding-top:14px; border-top:1px solid #eef2f7; }
.source-summary__main { min-width:0; }
.source-summary__label { color:#94a3b8; font-size:12px; }
.source-summary__party { margin-top:5px; color:#111827; font-size:16px; font-weight:650; }
.source-summary__no { margin-top:5px; color:#64748b; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.source-summary__item { display:flex; flex-direction:column; gap:6px; }
.source-summary__item span { color:#94a3b8; font-size:12px; }
.source-summary__item b { color:#334155; font-size:13px; font-weight:600; }
.create-return-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:20px; align-items:start; }
.return-device-panel { min-width:0; padding:0; border:0; border-radius:0; background:#fff; }
.section-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
.selected-count { padding:4px 10px; border-radius:999px; color:var(--el-color-primary); background:var(--el-color-primary-light-9); font-size:12px; font-weight:600; }
.return-device-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:12px; min-height:100px; }
.return-device-card { min-width:0; padding:14px; border:1px solid #dfe5ec; border-radius:6px; background:#fff; cursor:pointer; transition:border-color .15s,box-shadow .15s,background .15s; }
.return-device-card:hover { border-color:#a5b4fc; }
.return-device-card.selected { border-color:var(--el-color-primary); background:#f8fbff; box-shadow:0 0 0 1px var(--el-color-primary-light-7); }
.return-device-card.blocked { color:#94a3b8; background:#f8fafc; cursor:not-allowed; }
.return-device-card.blocked:hover { border-color:#e2e8f0; }
.device-card-head { display:flex; align-items:flex-start; gap:10px; }
.device-card-title-wrap { flex:1; min-width:0; }
.device-card-title { overflow:hidden; color:#111827; font-size:15px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.device-card-spec { margin-top:4px; overflow:hidden; color:#64748b; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.device-card-identities { display:flex; flex-wrap:wrap; gap:6px 14px; margin:12px 0; color:#64748b; font-size:12px; }
.device-card-identities span { padding:3px 7px; border-radius:5px; background:#f1f5f9; }
.device-money-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; padding-top:11px; border-top:1px solid #eef2f7; }
.device-money-grid div { display:flex; min-width:0; flex-direction:column; gap:4px; }
.device-money-grid span { color:#94a3b8; font-size:11px; }
.device-money-grid b { overflow:hidden; color:#334155; font-size:12px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.device-block-reason { margin-top:11px; padding:8px 10px; border-radius:6px; color:#dc2626; background:#fef2f2; font-size:12px; line-height:1.5; }
.device-card-form { display:grid; grid-template-columns:minmax(0,1fr); gap:11px; margin-top:12px; padding-top:12px; border-top:1px dashed #cbd5e1; }
.device-field { display:flex; min-width:0; flex-direction:column; gap:6px; }
.device-field label { color:#64748b; font-size:12px; }
.device-field label span { margin-left:4px; color:#a8b2c1; font-weight:400; }
.device-field :deep(.el-input-number) { width:100%; }
.unpaid-result { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:9px 11px; border:1px solid #dcfce7; border-radius:7px; background:#f0fdf4; }
.unpaid-result__main { display:flex; min-width:0; align-items:center; gap:8px; }
.unpaid-result__main b { color:#166534; font-size:12px; font-weight:650; white-space:nowrap; }
.unpaid-result__badge { flex:none; padding:2px 6px; border-radius:4px; color:#15803d; background:#dcfce7; font-size:11px; font-weight:650; }
.unpaid-result__tip { color:#65a30d; font-size:11px; text-align:right; }
.device-empty-guide { display:flex; grid-column:1/-1; min-height:100px; align-items:center; justify-content:center; border:1px dashed #cbd5e1; border-radius:6px; color:#94a3b8; background:#f8fafc; font-size:13px; }
.return-device-grid :deep(.el-empty) { grid-column:1/-1; }
.return-decision-panel { position:sticky; top:0; display:flex; flex-direction:column; gap:12px; }
.decision-card { padding:17px; border:1px solid #bbf7d0; border-radius:12px; background:linear-gradient(145deg,#f0fdf4,#fff); }
.decision-card.refund { border-color:#fed7aa; background:linear-gradient(145deg,#fff7ed,#fff); }
.decision-label { color:#64748b; font-size:12px; }
.decision-title { margin-top:5px; color:#166534; font-size:18px; font-weight:700; }
.decision-card.refund .decision-title { color:#c2410c; }
.decision-copy { margin-top:8px; color:#64748b; font-size:12px; line-height:1.6; }
.decision-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:6px; margin-top:14px; padding-top:13px; border-top:1px solid rgba(148,163,184,.22); }
.decision-metrics div { display:flex; min-width:0; flex-direction:column; gap:5px; }
.decision-metrics span { color:#94a3b8; font-size:11px; }
.decision-metrics b { overflow:hidden; color:#334155; font-size:12px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.side-form-card { padding:14px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; }
.side-form-label { display:block; margin-bottom:8px; color:#334155; font-size:13px; font-weight:600; }
.handover-confirm { margin:0; padding:14px; border:1px solid #fed7aa; border-radius:10px; background:#fffaf5; }
.handover-confirm :deep(.el-checkbox__label) { color:#1e293b; font-weight:600; white-space:normal; }
.handover-confirm__tip { margin-top:7px; padding-left:24px; color:#9a3412; font-size:12px; line-height:1.55; }
@media (max-width:1280px) {
    .create-return-grid { grid-template-columns:minmax(0,1fr) 300px; }
}
@media (max-width:980px) {
    .create-return-grid { grid-template-columns:1fr; }
    .return-decision-panel { position:static; }
    .source-picker-row { align-items:stretch; flex-direction:column; gap:12px; }
    .source-query-fields { width:100%; }
    .source-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width:620px) {
    .erp-list-panel, .erp-form-panel { padding:12px; }
    .panel-header, .form-header { align-items:stretch; flex-direction:column; }
    .panel-search { align-items:stretch; flex-direction:column; }
    .filter-keyword, .filter-party, .filter-date { width:100%; }
    .filter-actions { margin-left:0; text-align:right; }
    .panel-list { grid-template-columns:1fr; padding:12px; }
    .return-flow-guide { grid-template-columns:1fr; gap:9px; }
    .return-flow-guide i { display:none; }
    .source-query-fields { grid-template-columns:1fr; }
    .return-device-grid { grid-template-columns:1fr; }
    .unpaid-result { align-items:flex-start; flex-direction:column; }
    .unpaid-result__tip { text-align:left; }
}
</style>
