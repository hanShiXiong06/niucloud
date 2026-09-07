<template>
    <HsxPage padding="none" class="main-container">
        <!-- 退货单列表：只在列表模式展示，不再固定占用左栏 -->
        <div class="erp-list-panel">
            <HsxTitle size="page" collapsible-subtitle class="mb-4">
                <template #default>销售退货</template>
                <template #subtitle>客户退回已售设备；系统按单台已收款情况自动冲销应收或生成退款应付。</template>
                <template #extra><div class="flex gap-2 flex-wrap">
                        <el-button :icon="Refresh" :loading="listLoading" @click="loadList">刷新</el-button>
                        <el-button type="primary" :icon="Plus" @click="openCreate">新建退货</el-button>
                        <el-button type="warning" plain @click="openCompensation">售后补差</el-button>
                    </div></template>
            </HsxTitle>

            <HsxSearchPanel>
                <template #extra>
                    <el-button type="primary" :icon="Search" @click="searchList">查询</el-button>
                    <el-button @click="resetListWhere">重置</el-button>
                </template>
                <el-form :inline="true" class="mt-2" @submit.prevent>
                    <el-form-item label="关键词">
                        <el-input v-model.trim="listWhere.keyword" clearable class="!w-[260px]" placeholder="退货单号 / 销售单号" @keyup.enter="searchList" />
                    </el-form-item>
                    <el-form-item label="客户">
                        <ErpPartySelect v-model="listWhere.party_id" v-model:party-name="listPartyName" party-type="customer" :allow-create="false" class="!w-[220px]" placeholder="全部客户" />
                    </el-form-item>
                    <el-form-item label="退货时间">
                        <el-date-picker v-model="listWhere.dateRange" type="daterange" value-format="X" start-placeholder="开始" end-placeholder="结束" class="!w-[260px]" />
                    </el-form-item>

                </el-form>
            </HsxSearchPanel>

            <el-tabs v-model="listWhere.status" class="mt-4 erp-status-tabs" @tab-change="switchStatus">
                <el-tab-pane v-for="tab in statusTabs" :key="tab.value" :label="tab.label" :name="tab.value" />
            </el-tabs>

            <el-table :data="listData" v-loading="listLoading" size="large" @row-click="selectItem">
                <el-table-column label="业务单据" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.return_no || '-' }}</div>
                        <div class="mt-1 text-xs" :class="row.business_type === 'after_sale_compensation' ? 'text-orange-500' : 'text-gray-500'">{{ businessTypeLabel(row.business_type) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="客户 / 原销售单" min-width="220">
                    <template #default="{ row }"><div class="font-medium">{{ row.party_name || '-' }}</div><div class="mt-1 text-xs text-gray-500">{{ row.sale_no || '-' }}</div></template>
                </el-table-column>
                <el-table-column label="账务处理" min-width="150"><template #default="{ row }">{{ refundModeLabel(row.refund_mode) }}</template></el-table-column>
                <el-table-column label="业务金额" width="140" align="right"><template #default="{ row }"><span class="font-medium text-orange-600">¥{{ row.total_amount }}</span></template></el-table-column>
                <el-table-column label="状态" width="140" align="center"><template #default="{ row }"><el-tag :type="statusTagType(row.status)" effect="plain">{{ statusLabel(row.status, row.business_type) }}</el-tag></template></el-table-column>
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
            :title="createType === 'compensation' ? '新建售后补差单' : '新建销售退货单'"
            :subtitle="createType === 'compensation' ? '客户继续持有设备；补差按设备减少毛利并生成客户应付。' : '选择客户和实际退回的设备，系统自动关联原销售记录并处理回库与退款。'"
            :confirm-text="createType === 'compensation' ? '确认售后补差' : '确认退货'"
            :tip="createType === 'compensation' ? '提交前请核对设备、补差金额和付款方式；确认后将直接影响设备毛利和客户应付。' : '确认前请核对设备、实际退款金额和交接事实；提交后立即回库并处理账务。'"
            :loading="submitting"
            @close="resetCreate"
            @confirm="submitCreate"
        >
                <div class="return-flow-guide">
                    <div><span>1</span><b>选择客户</b><small>系统加载该客户相关设备</small></div>
                    <i></i>
                    <div><span>2</span><b>{{ createType === 'compensation' ? '选择补差设备' : '选择退回设备' }}</b><small>逐台填写金额和原因</small></div>
                    <i></i>
                    <div><span>3</span><b>{{ createType === 'compensation' ? '确认补差' : '确认回库与退款' }}</b><small>{{ createType === 'compensation' ? '设备保持已售并减少毛利' : '系统自动判断冲应收或退款' }}</small></div>
                </div>

                <el-form ref="formRef" :model="form" label-position="top" class="form-body sale-return-shell">
                    <section class="sale-source-card">
                        <div class="sale-source-heading">
                            <div><div class="section-kicker">{{ createType === 'compensation' ? '补差对象' : '退货对象' }}</div><div class="section-hint">{{ createType === 'compensation' ? '选择客户后加载其已售设备，补差精确记录到单台机器' : '选择客户后自动加载可退设备，系统按原仓位回库' }}</div></div>
                        </div>
                        <div class="sale-source-fields">
                            <el-form-item :label="createType === 'compensation' ? '补差客户' : '退货客户'" prop="party_id" :rules="[{ required: true, message: '请选择客户' }]">
                                <ErpPartySelect v-model="form.party_id" v-model:party-name="sourcePartyName" party-type="customer" :allow-create="false" placeholder="选择客户" @change="onSourcePartyChange" />
                            </el-form-item>
                            <el-form-item label="退款方式">
                                <el-select v-model="form.refund_mode" class="w-full"><el-option :label="createType === 'compensation' ? '现场补差' : '现场退款'" value="cash" /><el-option label="转财务退款" value="payable" /></el-select>
                            </el-form-item>
                            <el-form-item v-if="form.refund_mode === 'cash'" label="出款账户" required>
                                <el-select v-model="form.capital_account_id" class="w-full" placeholder="选择实际退款账户"><el-option v-for="account in accounts" :key="account.id" :label="`${account.account_name}（余额 ¥${Number(account.balance || 0).toFixed(2)}）`" :value="account.id" /></el-select>
                            </el-form-item>
                            <el-form-item v-else :label="createType === 'compensation' ? '设备处理' : '回库规则'"><div class="original-location-rule">{{ createType === 'compensation' ? '客户继续持有设备，不改变库存；补差直接减少该设备毛利' : '自动退回每台设备的原仓库 / 原库位' }}</div></el-form-item>
                            <el-form-item v-if="createType === 'return' && requiresReturnDestination" label="商城补录设备回库位置" required>
                                <ErpWarehouseLocationCascader
                                    :warehouses="warehouses"
                                    :warehouse-id="form.return_to_warehouse_id"
                                    :location-id="form.return_to_location_id"
                                    :filter-types="['owned', 'accessory', 'new_device', 'exception']"
                                    placeholder="选择实际收到退货的仓库 / 库位"
                                    @change="onReturnLocationChange"
                                />
                                <div class="mt-2 text-xs text-orange-600">该设备由商城销售后补录到 ERP，没有原入库仓位；请明确本次实际收货位置。</div>
                            </el-form-item>
                        </div>
                        <HsxNotice default-expanded :title="saleRefundModeTip(form.refund_mode)" type="info" :closable="false" show-icon />
                        <div v-if="form.refund_mode === 'cash'" class="mt-3"><div class="mb-2 text-sm text-gray-600">退款凭证（选填）</div><ErpFinanceVoucherUpload v-model="form.voucher_urls" /></div>
                    </section>

                    <div class="sale-return-grid">
                        <main class="sale-device-panel">
                            <div class="section-heading">
                                <div><div class="section-kicker">{{ createType === 'compensation' ? '选择补差设备' : '选择退回设备' }}</div><div class="section-hint">先看设备名称、规格、IMEI 和价格；销售单号仅用于追溯</div></div>
                                <span class="selected-count">已选 {{ form.items.length }} 台</span>
                            </div>
                            <div v-loading="assetsLoading" class="sale-device-grid">
                                <article v-for="row in availableAssets" :key="row.asset_id || row.id" class="sale-device-card" :class="{ selected: isSaleAssetSelected(row) }" @click="toggleSaleAssetCard(row)">
                                    <div class="device-card-head">
                                        <el-checkbox :model-value="isSaleAssetSelected(row)" @click.stop @change="setSaleAssetSelected(row, Boolean($event))" />
                                        <div class="device-card-title-wrap"><div class="device-card-title">{{ row.model || '未填写设备名称' }}</div><div class="device-card-spec">{{ row.spec || '未填写规格' }}</div></div>
                                        <el-tag :type="createType === 'compensation' ? 'warning' : 'success'" size="small" effect="light">{{ createType === 'compensation' ? '可补差' : '可退货' }}</el-tag>
                                    </div>
                                    <div class="device-core-info"><span class="device-imei">IMEI {{ row.imei || row.sn || '-' }}</span><span>原仓位 {{ row.warehouse_name || '-' }} / {{ row.location_name || '-' }}</span><span>销售来源 {{ row.sale_no || '-' }}</span></div>
                                    <div class="device-money-grid"><div><span>原销售价</span><b>¥{{ Number(row.sale_price || 0).toFixed(2) }}</b></div><div><span>{{ createType === 'compensation' ? '本次补差' : '本次退货价' }}</span><b class="text-orange-600">¥{{ Number(row._return_price || 0).toFixed(2) }}</b></div></div>
                                    <div v-if="isSaleAssetSelected(row)" class="device-card-form" @click.stop>
                                        <div class="device-field"><label>{{ createType === 'compensation' ? '补差金额' : '退货价' }}</label><el-input-number v-model="row._return_price" :min="0" :max="Number(row.sale_price || 0)" :precision="2" :step="1" @change="syncReturnItems" /></div>
                                        <div class="device-field"><label>{{ createType === 'compensation' ? '补差原因' : '退货原因' }} <span>选填</span></label><el-input v-model="row._reason" :placeholder="createType === 'compensation' ? '例如售后协商补偿' : '例如客户反悔、设备问题'" @change="syncReturnItems" /></div>
                                    </div>
                                </article>
                                <el-empty v-if="!assetsLoading && form.party_id && !availableAssets.length" description="该客户暂无可退设备" :image-size="70" />
                                <div v-if="!assetsLoading && !form.party_id" class="device-empty-guide">选择客户后，在这里核对{{ createType === 'compensation' ? '要补差' : '要退回' }}的设备</div>
                            </div>
                        </main>
                        <aside class="sale-decision-panel">
                            <section class="sale-decision-card">
                                <div class="decision-label">{{ createType === 'compensation' ? '本次补差结果' : '本次退货结果' }}</div><div class="decision-title">{{ form.items.length ? `已选 ${form.items.length} 台` : '请先选择设备' }}</div>
                                <div class="decision-copy">{{ createType === 'compensation' ? '设备继续保持已售；补差逐台减少销售毛利，并生成客户退款应付。' : '提交即表示设备已经实际交回；系统立即按原仓位回库，并按单台已收款情况冲应收或生成退款应付。' }}</div>
                                <div class="decision-metrics"><div><span>{{ createType === 'compensation' ? '涉及设备' : '退回库存' }}</span><b>{{ form.items.length }} 台</b></div><div><span>{{ createType === 'compensation' ? '补差合计' : '退款合计' }}</span><b class="text-orange-600">¥{{ totalReturnAmount }}</b></div></div>
                            </section>
                            <section class="side-form-card"><label class="side-form-label">整单备注</label><el-input v-model="form.remark" type="textarea" :rows="3" :placeholder="createType === 'compensation' ? '选填，记录售后协商背景' : '选填，记录退货背景或特殊说明'" /></section>
                        </aside>
                    </div>
                </el-form>
        </ErpReturnDialog>

        <HsxDrawer
            v-model="detail.visible"
            :title="detail.businessType === 'after_sale_compensation' ? '售后补差详情' : '销售退货详情'"
            size="76%"
            destroy-on-close
            @closed="onDetailClosed"
        >
            <SaleReturnDetail
                v-if="detail.id"
                :id="detail.id"
                embedded
                @updated="onDetailUpdated"
                @close="detail.visible = false"
            />
        </HsxDrawer>

    </HsxPage>
</template>

<script setup lang="ts">
import { HsxTitle, HsxPage, HsxSearchPanel, HsxDrawer, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'
import { ref, computed, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessageBox } from 'element-plus'
import {
    getErpSaleReturnList,
    createAndConfirmErpSaleReturn,
    createErpSaleCompensation,
} from '@/addon/hsx_erp/api/erp'
import { getErpSaleList, getErpSaleInfo } from '@/addon/hsx_erp/api/erp'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import ErpReturnDialog from '@/addon/hsx_erp/components/ErpReturnDialog.vue'
import SaleReturnDetail from './detail.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import ErpWarehouseLocationCascader from '@/addon/hsx_erp/components/ErpWarehouseLocationCascader.vue'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
const hsxFeedback = useFeedback()

const statusTabs = [
    { label: '全部', value: '' },
    { label: '待确认收货', value: 'pending' },
    { label: '已完成退货', value: 'confirmed' },
    { label: '已取消', value: 'cancelled' },
]
const mode = ref<'idle' | 'create'>('idle')
const listLoading = ref(false)
const submitting = ref(false)
const createType = ref<'return'|'compensation'>('return')
const listData = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 15, total: 0 })
const listWhere = reactive({ keyword: '', party_id: null as number | null, status: '', dateRange: [] as any[] })
const listPartyName = ref('')

const formRef = ref()
const assetsTableRef = ref()
const form = reactive({
    party_id: null as number | null,
    sale_order_id: null as number | null,
    refund_mode: 'cash',
    capital_account_id: 0,
    voucher_urls: '',
    return_to_warehouse_id: 0,
    return_to_location_id: 0,
    remark: '',
    items: [] as any[],
})
const sourcePartyName = ref('')
const saleOptions = ref<any[]>([])
const saleSearchLoading = ref(false)
const availableAssets = ref<any[]>([])
const assetsLoading = ref(false)
const selectedAssets = ref<any[]>([])
const detail = reactive({ visible: false, id: 0, businessType: '' })
const accounts = ref<any[]>([])
const warehouses = ref<any[]>([])

const totalReturnAmount = computed(() =>
    form.items.reduce((s: number, i: any) => s + (Number(i.return_price) || 0), 0).toFixed(2)
)
const requiresReturnDestination = computed(() => selectedAssets.value.some((item: any) =>
    Number(item.warehouse_id || 0) <= 0 || Number(item.location_id || 0) <= 0
))

async function loadList() {
    listLoading.value = true
    try {
        const [startAt, endAt] = listWhere.dateRange || []
        const res = await getErpSaleReturnList({
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
    detail.id = Number(item?.id || 0)
    detail.businessType = String(item?.business_type || '')
    detail.visible = detail.id > 0
}

function onDetailClosed() {
    detail.id = 0
    detail.businessType = ''
}

async function onDetailUpdated() {
    await loadList()
}

function openCreate() {
    createType.value = 'return'
    mode.value = 'create'
    form.party_id = null
    sourcePartyName.value = ''
    form.sale_order_id = null
    form.refund_mode = 'cash'
    form.capital_account_id = Number(accounts.value[0]?.id || 0)
    form.voucher_urls = ''
    form.return_to_warehouse_id = 0
    form.return_to_location_id = 0
    form.remark = ''
    form.items = []
    availableAssets.value = []
    selectedAssets.value = []
}
function openCompensation() { openCreate(); createType.value='compensation'; form.refund_mode='payable' }

function resetCreate() {
    mode.value = 'idle'
}

async function searchSaleOrders(query: string) {
    if (!query) return
    saleSearchLoading.value = true
    try {
        const res = await getErpSaleList({ keyword: query, limit: 20 })
        // 从sale_item列表中提取唯一的销售单
        const orders: Record<number, any> = {}
        ;(res.data?.data || []).forEach((item: any) => {
            const sid = item.sale_order_id || item.id
            if (sid && !orders[sid]) {
                orders[sid] = {
                    id: sid,
                    sale_no: item.sale_no || item.source_no,
                    party_name: item.party_name || item.customer_name,
                }
            }
        })
        saleOptions.value = Object.values(orders)
    } finally {
        saleSearchLoading.value = false
    }
}

async function onSourcePartyChange(party: any) {
    form.party_id = party?.id ? Number(party.id) : null
    sourcePartyName.value = party?.party_name || ''
    formRef.value?.clearValidate('party_id')
    form.sale_order_id = null
    availableAssets.value = []
    selectedAssets.value = []
    form.items = []
    if (!form.party_id) return
    assetsLoading.value = true
    try {
        const res = await getErpSaleList({ party_id: form.party_id, party_name: sourcePartyName.value, origin_plugin: '', limit: 100 })
        availableAssets.value = (res.data?.data || [])
            .filter((item: any) => item.status === 'sold' && item.order_status !== 'void' && !item.return_id)
            .map((item: any) => ({ ...item, _return_price: createType.value === 'compensation' ? 0 : Number(item.sale_price || 0), _reason: '' }))
    } finally {
        assetsLoading.value = false
    }
}

function onReturnLocationChange(location: any) {
    form.return_to_warehouse_id = Number(location?.warehouse_id || 0)
    form.return_to_location_id = Number(location?.location_id || 0)
}

async function onSaleOrderChange(orderId: number) {
    if (!orderId) {
        availableAssets.value = []
        return
    }
    assetsLoading.value = true
    try {
        const res = await getErpSaleInfo(orderId)
        form.party_id = Number(res.data?.party_id || 0) || null
        sourcePartyName.value = res.data?.party_name || ''
        const items = (res.data?.items || [])
            .filter((i: any) => i.status === 'sold')
            .map((i: any) => ({
                ...i,
                _return_price: Number(i.sale_price || 0),
                _reason: '',
            }))
        availableAssets.value = items
    } finally {
        assetsLoading.value = false
    }
}

function onAssetSelectionChange(selection: any[]) {
    selectedAssets.value = selection
    syncReturnItems()
}

function isSaleAssetSelected(row: any) {
    const assetId = Number(row.asset_id || row.id)
    return selectedAssets.value.some((item: any) => Number(item.asset_id || item.id) === assetId)
}

function setSaleAssetSelected(row: any, checked: boolean) {
    const assetId = Number(row.asset_id || row.id)
    const next = selectedAssets.value.filter((item: any) => Number(item.asset_id || item.id) !== assetId)
    if (checked) next.push(row)
    selectedAssets.value = next
    syncReturnItems()
}

function toggleSaleAssetCard(row: any) {
    setSaleAssetSelected(row, !isSaleAssetSelected(row))
}

function syncReturnItems() {
    form.items = selectedAssets.value.map((a: any) => ({
        asset_id: a.asset_id || a.id,
        sale_order_id: a.sale_order_id || form.sale_order_id,
        return_price: Number(a._return_price) || 0,
        reason: a._reason || '',
    }))
}

async function submitCreate() {
    syncReturnItems()
    await formRef.value?.validate()
    if (!form.items.length) {
        hsxFeedback.warning(createType.value === 'compensation' ? '请选择至少一台补差设备' : '请选择至少一台退货设备')
        return
    }
    if (form.items.some((item: any) => Number(item.return_price || 0) <= 0)) {
        hsxFeedback.warning(createType.value === 'compensation' ? '请填写每台设备的补差金额' : '退货金额必须大于 0')
        return
    }
    if (form.refund_mode === 'cash' && !form.capital_account_id) {
        hsxFeedback.warning('现场退款必须选择实际出款账户')
        return
    }
    if (createType.value === 'return' && requiresReturnDestination.value
        && (!form.return_to_warehouse_id || !form.return_to_location_id)) {
        hsxFeedback.warning('商城补录设备没有 ERP 原仓位，请选择本次实际退回的仓库和库位')
        return
    }
    const actionName = createType.value === 'compensation' ? '售后补差' : '销售退货'
    const confirmed = await ElMessageBox.confirm(
        createType.value === 'compensation'
            ? `确认对 ${form.items.length} 台设备补差 ¥${totalReturnAmount.value}？设备仍由客户持有，补差将逐台减少销售毛利并生成客户退款应付。`
            : `确认发起销售退货：已收到客户退回的 ${form.items.length} 台设备，退货金额 ¥${totalReturnAmount.value}？提交后设备立即按原仓位回库，并逐台冲销应收或生成退款应付。`,
        `确认${actionName}`,
        { type: 'warning', confirmButtonText: `确认${actionName}`, cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    submitting.value = true
    try {
        const payload = {
            party_id: form.party_id,
            sale_order_id: 0,
            refund_mode: form.refund_mode,
            capital_account_id: form.capital_account_id,
            voucher_urls: form.voucher_urls,
            return_to_warehouse_id: form.return_to_warehouse_id,
            return_to_location_id: form.return_to_location_id,
            remark: form.remark,
            items: form.items,
        }
        if (createType.value === 'compensation') await createErpSaleCompensation(payload)
        else await createAndConfirmErpSaleReturn(payload)
        hsxFeedback.success(createType.value === 'compensation' ? '售后补差已生成设备级客户应付' : '销售退货已完成，库存和账务已同步处理')
        mode.value = 'idle'
        loadList()
    } finally {
        submitting.value = false
    }
}

function statusLabel(status: string, businessType = '') {
    if (businessType === 'after_sale_compensation') return status === 'cancelled' ? '补差已取消' : '补差已确认'
    const map: Record<string, string> = {
        pending: '待确认收货', confirmed: '已完成退货', cancelled: '已取消',
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
    const map: Record<string, string> = { cash: '现场退款', payable: '转财务退款', offset: '往来折抵' }
    return erpEnumLabel(mode, map, '退款方式待确认')
}
function businessTypeLabel(type: string) {
    return type === 'after_sale_compensation' ? '售后补差' : '退货退款'
}
function saleRefundModeTip(mode: string) {
    if (createType.value === 'compensation') {
        if (mode === 'cash') return '现场补差：从所选账户立即向客户付款并记账；付款凭证可在确认时选填。'
        return '转财务退款：补差确认后逐台生成客户应付，由财务选择账户付款；是否折账由财务决定。'
    }
    if (mode === 'payable') return '转财务退款：确认收货后逐台生成待退款应付，由财务选择账户付款。'
    return '现场退款：必须先指定出款账户；确认实际收到退货设备后，系统立即向客户付款、核销并记录资金流水。'
}

const route = useRoute()
async function loadPage() {
    await Promise.all([
        loadList(),
        getCapitalAccounts().then((res: any) => { accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || []) }),
        getErpWarehouseOptions().then((res: any) => { warehouses.value = Array.isArray(res?.data) ? res.data : [] }),
    ])
}
useErpPageRefresh(loadPage)
// 如果从销售页带着 sale_order_id 过来，自动打开新建并预选销售单
if (route.query.sale_order_id) {
    openCreate()
    form.sale_order_id = Number(route.query.sale_order_id)
    onSaleOrderChange(Number(route.query.sale_order_id))
}
</script>

<style scoped>
.erp-list-panel, .erp-form-panel { width: 100%; padding: 20px; border: 0; border-radius: 0; background: #fff; box-shadow: none; }
.erp-form-panel { padding: 20px; }
.panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 0; }
.panel-title { color: #111827; font-weight: 650; font-size: 20px; }
.panel-subtitle, .form-subtitle { margin-top: 5px; color: #64748b; font-size: 13px; line-height: 20px; }
.return-status-tabs { padding: 0 22px; border-top: 1px solid #f0f3f7; }
.return-status-tabs :deep(.el-tabs__header) { margin: 0; }
.return-status-tabs :deep(.el-tabs__nav-wrap::after) { height: 1px; background: #f0f3f7; }
.return-status-tabs :deep(.el-tabs__item) { height: 48px; padding: 0 18px; }
.panel-search { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; padding: 14px 22px; background: #fbfcfe; }
.panel-search > * { max-width: 100%; min-width: 0; }
.filter-keyword { width: min(360px, 100%); }
.filter-party { width: 220px; max-width: 100%; min-width: 0; }
.filter-date { width: 280px; max-width: 100%; min-width: 0 !important; }
.filter-date:deep(.el-date-editor) { width: 100%; max-width: 100%; min-width: 0; }
.filter-actions { margin-left: auto; }
.panel-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 12px; min-height: 240px; padding: 18px 22px; }
.list-item { min-width: 0; padding: 14px 15px; cursor: pointer; border: 1px solid #e7ecf3; border-radius: 9px; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; }
.list-item:hover { border-color: #a8c7ff; box-shadow: 0 5px 16px rgba(37, 99, 235, .08); transform: translateY(-1px); }
.list-item.selected { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
.panel-list :deep(.el-empty) { grid-column: 1 / -1; }
.panel-footer { padding-top: 16px; display: flex; justify-content: flex-end; }
.form-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
.form-title { color: #111827; font-size: 18px; font-weight: 650; }
.form-body, .detail-body { max-width: 1440px; margin: 0 auto; padding: 0 4px; }
.return-flow-guide { display:grid; max-width:1100px; margin:0 auto 18px; grid-template-columns:minmax(0,1fr) 44px minmax(0,1fr) 44px minmax(0,1fr); align-items:center; border:1px solid #dbeafe; border-radius:10px; background:#f8fbff; padding:12px 16px; }
.return-flow-guide div { display:grid; grid-template-columns:28px 1fr; column-gap:9px; align-items:center; }
.return-flow-guide span { display:flex; width:28px; height:28px; grid-row:1/3; align-items:center; justify-content:center; border-radius:50%; color:#fff; background:var(--el-color-primary); font-size:12px; font-weight:700; }
.return-flow-guide b { color:#1e293b; font-size:13px; }
.return-flow-guide small { margin-top:2px; color:#94a3b8; font-size:11px; }
.return-flow-guide i { height:1px; background:#bfdbfe; }
.return-metric { border-radius: 8px; background: #f8fafc; padding: 12px 14px; }
.metric-label { color: #64748b; font-size: 12px; }
.metric-value { margin-top: 4px; color: #111827; font-size: 18px; font-weight: 600; }
.sale-return-shell { max-width:1440px; margin:0 auto; }
.sale-source-card { margin-bottom:18px; padding:15px 16px; border-radius:6px; background:#f8fafc; }
.sale-source-heading { margin-bottom:12px; }
.sale-source-fields { display:grid; grid-template-columns:minmax(220px,1fr) minmax(180px,.7fr) minmax(300px,1.2fr); gap:12px; }
.sale-source-fields :deep(.el-form-item) { margin-bottom:12px; }
.original-location-rule { display:flex; width:100%; min-height:32px; align-items:center; padding:0 11px; border:1px solid #dbeafe; border-radius:4px; color:#1d4ed8; background:#eff6ff; font-size:13px; }
.section-kicker { color:#111827; font-size:15px; font-weight:650; }
.section-hint { margin-top:3px; color:#94a3b8; font-size:12px; }
.sale-return-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:20px; align-items:start; }
.sale-device-panel { min-width:0; }
.section-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
.selected-count { padding:4px 10px; border-radius:999px; color:var(--el-color-primary); background:var(--el-color-primary-light-9); font-size:12px; font-weight:600; }
.sale-device-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:12px; min-height:100px; }
.sale-device-grid :deep(.el-empty) { grid-column:1/-1; }
.sale-device-card { min-width:0; padding:14px; border:1px solid #dfe5ec; border-radius:6px; background:#fff; cursor:pointer; transition:border-color .15s,box-shadow .15s,background .15s; }
.sale-device-card:hover { border-color:#a5b4fc; }
.sale-device-card.selected { border-color:var(--el-color-primary); background:#f8fbff; box-shadow:0 0 0 1px var(--el-color-primary-light-7); }
.device-card-head { display:flex; align-items:flex-start; gap:10px; }
.device-card-title-wrap { flex:1; min-width:0; }
.device-card-title { overflow:hidden; color:#111827; font-size:15px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.device-card-spec { margin-top:4px; overflow:hidden; color:#64748b; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.device-core-info { display:flex; flex-wrap:wrap; gap:6px 14px; margin:12px 0; color:#64748b; font-size:12px; }
.device-core-info span { padding:3px 7px; border-radius:5px; background:#f1f5f9; }
.device-core-info .device-imei { color:#334155; font-weight:650; }
.device-money-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; padding-top:11px; border-top:1px solid #eef2f7; }
.device-money-grid div { display:flex; flex-direction:column; gap:4px; }
.device-money-grid span { color:#94a3b8; font-size:11px; }
.device-money-grid b { color:#334155; font-size:13px; font-weight:650; }
.device-card-form { display:grid; grid-template-columns:minmax(160px,.65fr) minmax(220px,1.35fr); gap:10px; margin-top:12px; padding-top:12px; border-top:1px dashed #cbd5e1; }
.device-field { display:flex; min-width:0; flex-direction:column; gap:6px; }
.device-field label { color:#64748b; font-size:12px; }
.device-field label span { margin-left:4px; color:#a8b2c1; font-weight:400; }
.device-field :deep(.el-input-number) { width:100%; }
.device-empty-guide { display:flex; grid-column:1/-1; min-height:100px; align-items:center; justify-content:center; border:1px dashed #cbd5e1; border-radius:6px; color:#94a3b8; background:#f8fafc; font-size:13px; }
.sale-decision-panel { position:sticky; top:0; display:flex; flex-direction:column; gap:12px; }
.sale-decision-card { padding:16px; border:1px solid #fed7aa; border-radius:8px; background:#fffaf5; }
.decision-label { color:#64748b; font-size:12px; }
.decision-title { margin-top:5px; color:#c2410c; font-size:18px; font-weight:700; }
.decision-copy { margin-top:8px; color:#64748b; font-size:12px; line-height:1.6; }
.decision-metrics { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #ffedd5; }
.decision-metrics div { display:flex; flex-direction:column; gap:4px; }
.decision-metrics span { color:#94a3b8; font-size:11px; }
.decision-metrics b { color:#334155; font-size:13px; font-weight:650; }
.side-form-card { padding:14px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
.side-form-label { display:block; margin-bottom:8px; color:#334155; font-size:13px; font-weight:650; }
@media (max-width: 768px) {
    .erp-list-panel, .erp-form-panel { padding: 12px; }
    .panel-header, .form-header { align-items: stretch; flex-direction: column; }
    .panel-search { align-items: stretch; flex-direction: column; }
    .filter-keyword, .filter-party, .filter-date { width: 100%; }
    .filter-actions { margin-left: 0; text-align: right; }
    .panel-list { grid-template-columns: 1fr; padding: 12px; }
    .return-flow-guide { grid-template-columns:1fr; gap:9px; }
    .return-flow-guide i { display:none; }
    .sale-source-fields, .sale-return-grid, .device-card-form { grid-template-columns:1fr; }
    .sale-decision-panel { position:static; }
    .sale-device-grid { grid-template-columns:1fr; }
}
</style>
