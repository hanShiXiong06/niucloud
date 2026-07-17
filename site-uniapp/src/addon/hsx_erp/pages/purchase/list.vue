<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            placeholder="型号 / IMEI / 供应商"
            :show-filter="true"
            :filter-count="filterCount"
            :compact-mp="true"
            @search="handleSearch"
            @filter="filterVisible = true"
        >
            <template #below>
                <ErpQuickFilterBar :items="quickFilters" @change="onQuickFilter" />
            </template>
        </ErpListHeader>
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无采购记录" /></template>
            <view class="list-wrap">
                <view v-if="returnMode" class="return-mode-tip">
                    <u-icon name="info-circle" color="#ea580c" size="15" />
                    <text>请选择实际交还供货方的在库设备；系统会自动关联原采购单并判断是否需要追回退款。</text>
                </view>
                <view v-for="batch in groupedBatches" :key="batch.key" class="purchase-batch" :class="`purchase-batch--tone-${batch.tone}`">
                    <view class="purchase-batch__head" @click="toggleBatch(batch.key)">
                        <view class="batch-overview">
                            <view class="batch-main">
                                <view class="batch-title-line">
                                    <text class="batch-dot" :class="`batch-dot--${batch.tone}`"></text>
                                    <text class="batch-title">{{ erpPartyDisplayName(batch, '未设置供应商') }}</text>
                                    <text class="batch-count">{{ batch.rows.length }} 台</text>
                                </view>
                                <text class="batch-machine">{{ batchMachineSummary(batch) }}</text>
                            </view>
                            <view class="batch-toggle">
                                <text>{{ isBatchExpanded(batch.key) ? '收起' : '展开' }}</text>
                                <u-icon :name="isBatchExpanded(batch.key) ? 'arrow-up' : 'arrow-down'" color="#2563eb" size="12" />
                            </view>
                        </view>
                        <view class="batch-statuses">
                            <view class="batch-status-item">
                                <u-tag :text="batch.business_status_label || orderStatusLabel(batch.order_status)" :type="orderStatusType(batch.order_status)" plain plainFill size="mini" />
                            </view>
                            <view class="batch-status-item">
                                <u-tag :text="financeLabel(batch.finance_status)" :type="financeType(batch.finance_status)" plain plainFill size="mini" />
                            </view>
                            <text class="batch-payment-text">采购款：已付 ¥{{ money(batch.paid_amount) }} · 未付 ¥{{ money(batch.unpaid_amount) }}</text>
                        </view>
                        <view class="batch-meta">
                            <text class="batch-time">{{ formatErpTime(batch.rows[0]?.purchase_at) }} · {{ batch.origin_name || 'ERP采购' }}{{ batch.origin_plugin_name ? ' · ' + batch.origin_plugin_name : '' }}</text>
                        </view>
                    </view>
                    <view
                        v-for="row in batch.rows"
                        v-if="isBatchExpanded(batch.key)"
                        :key="row.id"
                        class="purchase-device-row"
                        :class="{ 'purchase-device-row--returned': row.is_returned || row.status === 'returned', 'purchase-device-row--void': row.status === 'void' || row.order_status === 'void' }"
                        @click="goDetail(row)"
                    >
                        <view class="device-head">
                            <view class="device-identity">
                                <text class="card-title">{{ row.model || '-' }}</text>
                                <text class="device-spec">{{ erpSpecLine(row.spec) }}</text>
                            </view>
                            <u-tag v-if="!(row.status === 'void' && row.order_status === 'void')" :text="assetLabel(row.status)" :type="assetType(row.status)" plain plainFill size="mini" />
                        </view>
                        <text class="device-serial">{{ serialText(row) }}</text>
                        <view class="device-summary">
                            <view class="device-summary-item">
                                <text class="device-summary-label">入库位置</text>
                                <text class="device-summary-value">{{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}</text>
                            </view>
                            <view class="device-summary-item device-summary-item--money">
                                <text class="device-summary-label">采购成本</text>
                                <text class="device-cost">¥{{ money(row.purchase_cost) }}</text>
                                <text v-if="Number(row.refurbish_cost || 0)" class="device-extra-cost">整备 +¥{{ money(row.refurbish_cost) }}（独立应付）</text>
                            </view>
                            <view class="device-summary-item">
                                <text class="device-summary-label">{{ row.is_returned || row.status === 'returned' ? '退货时间' : '入库时间' }}</text>
                                <text class="device-summary-value">{{ erpTimeLine(row, ['return_at', 'stock_in_at', 'purchase_at']) }}</text>
                            </view>
                            <view class="device-summary-item device-summary-item--money">
                                <text class="device-summary-label">{{ row.is_returned || row.status === 'returned' ? '退货金额' : '付款情况' }}</text>
                                <text v-if="!(row.is_returned || row.status === 'returned')" class="device-pay">已付 {{ money(row.asset_paid_amount) }} · 未付 {{ money(row.asset_unpaid_amount) }}</text>
                                <text v-else class="device-pay">¥{{ money(row.return_cost || row.purchase_cost) }}</text>
                            </view>
                        </view>
                        <view class="device-foot">
                            <text class="device-asset-no">查看设备详情</text>
                            <view v-if="row.status === 'in_stock' && row.return_flow?.returnable !== false" class="return-action-link" @click.stop="goReturn(row)">
                                <text>采购退货</text>
                                <u-icon name="arrow-right" color="#ea580c" size="11" />
                            </view>
                            <text v-else-if="row.status === 'in_stock'" class="return-blocked-hint">不可采购退货</text>
                        </view>
                        <view v-if="row.is_returned || row.status === 'returned'" class="return-banner">
                            <u-icon name="info-circle" color="#ea580c" size="14" />
                            <text>{{ returnSummary(row) }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <view v-if="!returnMode" class="fab fab--label" @click="goCreate">
            <u-icon name="plus" color="#fff" size="20" />
            <text class="fab__text">新建采购</text>
        </view>

        <ErpFilterPopup
            v-model:show="filterVisible"
            v-model="filters"
            title="筛选采购记录"
            :fields="filterFields"
            @confirm="applyFilter"
            @reset="resetFilter"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'

import { getMobilePurchaseList } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
import ErpQuickFilterBar from '@/addon/hsx_erp/components/ErpQuickFilterBar.vue'
// useListHeader hook
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine, formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import { erpSpecLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import { erpPartyDisplayName } from '@/addon/hsx_erp/hooks/useErpPartyText'
import { ERP_DICT_FALLBACK, dictLabel, dictTabs, dictType, loadErpDicts, type ErpDictMap } from '@/addon/hsx_erp/api/dict'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const expandedBatchKeys = ref<Set<string>>(new Set())
const returnMode = ref(false)

const dicts = ref<ErpDictMap>(ERP_DICT_FALLBACK)
const tabs = computed(() => dictTabs(dicts.value, 'purchase_finance_status'))
const activeTab = ref('')
const quickFilters = computed(() => [
    { key: 'finance_status', label: '付款状态', title: '付款状态', value: activeTab.value, options: tabs.value },
    { key: 'status', label: '采购状态', title: '采购状态', value: filters.value.status || '', options: dictTabs(dicts.value, 'purchase_order_status', true) },
])
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const filterFields = [
    { key: 'asset_no', label: '资产号', type: 'text', placeholder: '输入资产编号' },
    { key: 'imei', label: 'IMEI / 串号', type: 'text', placeholder: '输入 IMEI 或串号' },
    { key: 'model', label: '型号', type: 'text', placeholder: '输入机型型号' },
    { key: 'party_id', label: '供应商', type: 'party', roleType: 'supplier', labelKey: 'party_name', placeholder: '请选择供应商' },
    { key: 'catalog_product_id', label: '商品型号', type: 'category', labelKey: 'catalog_product_name', placeholder: '请选择型号' },
    { key: 'purchase_no', label: '采购单号', type: 'text', placeholder: '输入采购单号' },
    { key: 'warehouse_id', label: '仓库', type: 'warehouse', labelKey: 'warehouse_name', locationKey: 'location_id', locationLabelKey: 'location_name', placeholder: '请选择仓库/库位' },
    { key: 'purchaser_uid', label: '采购员', type: 'staff', labelKey: 'purchaser_name', placeholder: '请选择采购员' },
    { key: 'cost', label: '采购成本', type: 'range', minKey: 'min_amount', maxKey: 'max_amount' },
    { key: 'date', label: '采购日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[]
const filterCount = computed(() => Object.entries(filters.value).filter(([key, v]) => !key.endsWith('_name') && v !== '' && v !== undefined && v !== null).length)
const reload = () => pagingRef.value?.reload()
const handleSearch = () => reload()
const onTab = (val: string) => { activeTab.value = val; reload() }
const onQuickFilter = ({ key, value }: { key: string; value: string | number }) => {
    if (key === 'finance_status') return onTab(String(value))
    filters.value = { ...filters.value, [key]: value }
    reload()
}
const { pagingStyle } = useListHeader({ tabs: true, compactMp: true })
onShow(async () => {
    dicts.value = await loadErpDicts()
    reload()
})

onLoad((query: any) => {
    returnMode.value = String(query?.mode || '') === 'return'
})



const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobilePurchaseList({
            keyword: keyword.value, finance_status: activeTab.value,
            ...filterParams(),
            page: pageNo, limit: pageSize
        })
        const rows = res?.data?.data || []
        if (returnMode.value) {
            expandedBatchKeys.value = new Set(rows.map((row: any) => String(row.purchase_order_id || row.purchase_no || row.id)))
        }
        pagingRef.value?.complete(rows)
    } catch { pagingRef.value?.complete(false) }
}

function applyFilter() { reload() }
function resetFilter() { filters.value = {}; reload() }
function filterParams() {
    const params: Record<string, any> = { ...filters.value }
    Object.keys(params).forEach(key => { if (key.endsWith('_name')) delete params[key] })
    dateToRange(params)
    return params
}
function dateToRange(params: Record<string, any>) {
    if (typeof params.start_at === 'string' && params.start_at) params.start_at = Math.floor(new Date(params.start_at + ' 00:00:00').getTime() / 1000)
    if (typeof params.end_at === 'string' && params.end_at) params.end_at = Math.floor(new Date(params.end_at + ' 23:59:59').getTime() / 1000)
}

const money = (v: any) => Number(v || 0).toFixed(2)

const groupedBatches = computed(() => {
    const groups: any[] = []
    const map = new Map<string, any>()
    list.value.forEach((row: any) => {
        const key = String(row.purchase_order_id || row.purchase_no || row.id)
        let group = map.get(key)
        if (!group) {
            group = {
                key,
                tone: groups.length % 4,
                purchase_no: row.purchase_no || '-',
                party_name: row.party_name || '',
                member_name: row.member_name || '',
                m_no: row.m_no || '',
                finance_status: row.finance_status || '',
                order_status: row.order_status || '',
                business_status_label: row.order_business_status_label || '',
                origin_name: row.origin_name || '',
                origin_plugin_name: row.origin_plugin_name || '',
                paid_amount: 0,
                unpaid_amount: 0,
                rows: [] as any[],
            }
            map.set(key, group)
            groups.push(group)
        }
        group.rows.push(row)
        const inactive = row.order_status === 'void' || row.status === 'void' || row.status === 'returned' || Number(row.is_returned || 0) === 1
        if (!inactive) {
            group.paid_amount += Number(row.asset_paid_amount || 0)
            group.unpaid_amount += Number(row.asset_unpaid_amount || 0)
        }
    })
    return groups
})

const isBatchExpanded = (key: any) => expandedBatchKeys.value.has(String(key))
const toggleBatch = (key: any) => {
    const normalizedKey = String(key)
    const next = new Set(expandedBatchKeys.value)
    if (next.has(normalizedKey)) next.delete(normalizedKey)
    else next.add(normalizedKey)
    expandedBatchKeys.value = next
}
const batchMachineSummary = (batch: any) => {
    const models = new Map<string, number>()
    const rows = batch?.rows || []
    rows.forEach((row: any) => {
        const model = String(row.model || '未命名设备')
        models.set(model, (models.get(model) || 0) + 1)
    })
    const entries = Array.from(models.entries())
    const summary = entries.slice(0, 2).map(([model, count]) => `${model}${rows.length > 1 ? ` ×${count}` : ''}`).join('、')
    return entries.length > 2 ? `${summary} 等 ${entries.length} 款` : summary
}

const goDetail = (row: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/purchase/detail?purchase_order_id=${row.purchase_order_id}&purchase_no=${encodeURIComponent(row.purchase_no || '')}`
})
const goReturn = (row: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/purchase_return/create?purchase_order_id=${row.purchase_order_id}&purchase_no=${encodeURIComponent(row.purchase_no || '')}&party_name=${encodeURIComponent(row.party_name || '')}&asset_id=${row.id || row.asset_id || ''}`
})
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/purchase/create' })
const financeLabel = (s: string) => dictLabel(dicts.value, 'purchase_finance_status', s)
const financeType = (s: string) => dictType(dicts.value, 'purchase_finance_status', s)
const assetLabel = (s: string) => dictLabel(dicts.value, 'asset_status', s)
const assetType = (s: string) => dictType(dicts.value, 'asset_status', s)
const orderStatusLabel = (s: string) => dictLabel(dicts.value, 'purchase_order_status', s)
const orderStatusType = (s: string) => dictType(dicts.value, 'purchase_order_status', s)
const refundModeLabel = (s: string) => dictLabel(dicts.value, 'purchase_refund_mode', s)
const serialText = (row: any) => [row.imei ? `IMEI ${row.imei}` : '', row.sn ? `SN ${row.sn}` : ''].filter(Boolean).join(' · ') || '未录入 IMEI / SN'
function returnSummary(row: any) {
    const paid = Number(row.return_paid_amount ?? row.paid_amount ?? 0)
    if (paid <= 0) return `直接退货 · 应付已作废 · 无退款流程${row.return_no ? ' · ' + row.return_no : ''}`
    const settled = Number(row.return_settled_amount || 0)
    const mode = refundModeLabel(row.refund_mode)
    const status = settled > 0 ? '已退款' : '待退款'
    return `${mode} · ${status} ¥${money(paid)}${row.return_no ? ' · ' + row.return_no : ''}`
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.return-mode-tip { display:flex; align-items:flex-start; gap:10rpx; margin:20rpx 24rpx 0; padding:18rpx 20rpx; border-radius:16rpx; background:#fff7ed; color:#7c2d12; font-size:23rpx; line-height:1.5; }
.return-mode-tip text { flex:1; }
.purchase-batch { overflow: hidden; margin: 20rpx 24rpx; border: 1rpx solid #e2e8f0; border-radius: 24rpx; background: #fff; }
.purchase-batch--tone-0 { background: #f7fbff; }
.purchase-batch--tone-1 { background: #f7fcfa; }
.purchase-batch--tone-2 { background: #fbf9ff; }
.purchase-batch--tone-3 { background: #fffaf3; }
.purchase-batch__head { display:grid; grid-template-columns:minmax(0,1fr); gap:16rpx; padding:24rpx; background:rgba(255,255,255,.72); }
.batch-overview { display:flex; min-width:0; align-items:flex-start; justify-content:space-between; gap:16rpx; }
.batch-main { min-width:0; }
.batch-title-line { display:flex; align-items:center; gap:10rpx; }
.batch-dot { width:14rpx; height:14rpx; flex:0 0 auto; border-radius:50%; }
.batch-dot--0 { background:#60a5fa; }.batch-dot--1 { background:#34d399; }.batch-dot--2 { background:#a78bfa; }.batch-dot--3 { background:#f59e0b; }
.batch-title { min-width:0; overflow:hidden; color:#0f172a; font-size:30rpx; font-weight:700; text-overflow:ellipsis; white-space:nowrap; }
.batch-count { flex:0 0 auto; border-radius:999rpx; background:#eaf2ff; padding:3rpx 10rpx; color:#2563eb; font-size:20rpx; }
.batch-machine { display:block; max-width:100%; margin-top:8rpx; overflow:hidden; color:#475569; font-size:23rpx; font-weight:550; text-overflow:ellipsis; white-space:nowrap; }
.batch-toggle { display:flex; flex:0 0 auto; align-items:center; gap:7rpx; padding:8rpx 12rpx; color:#2563eb; font-size:20rpx; }
.batch-time { flex:0 0 auto; color:#94a3b8; font-size:20rpx; }
.batch-statuses { display:flex; flex-wrap:wrap; gap:12rpx 24rpx; padding:14rpx 16rpx; border-radius:14rpx; background:rgba(241,245,249,.82); }
.batch-status-item { display:flex; align-items:center; gap:8rpx; }
.batch-status-label { color:#64748b; font-size:20rpx; }
.batch-payment-text { color:#64748b; font-size:21rpx; display: flex; align-items: center; }
.batch-meta { display:flex; min-width:0; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8rpx 20rpx; }
.batch-number { display:flex; min-width:0; align-items:center; gap:10rpx; color:#94a3b8; font-size:20rpx; }
.batch-number__label { flex:0 0 auto; }
.batch-number__value { min-width:0; overflow:hidden; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; text-overflow:ellipsis; white-space:nowrap; }
.purchase-device-row { position:relative; display:grid; grid-template-columns:minmax(0,1fr); gap:14rpx; padding:22rpx 24rpx; border-top:1rpx solid rgba(148,163,184,.18); }
.purchase-device-row--returned { background:#fff7ed; }
.purchase-device-row--void { background:#f8fafc; }
.purchase-device-row--void .card-title { color:#64748b; }
.device-head { display:flex; min-width:0; align-items:flex-start; justify-content:space-between; gap:16rpx; }
.device-identity { display:flex; min-width:0; flex:1; flex-direction:column; gap:5rpx; }
.device-spec,.device-serial { color:#475569; font-size:22rpx; }
.device-serial { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.device-summary { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:14rpx 20rpx; padding:18rpx; border-radius:16rpx; background:rgba(255,255,255,.56); }
.device-summary-item { display:flex; min-width:0; flex-direction:column; gap:5rpx; }
.device-summary-item--money { align-items:flex-end; text-align:right; }
.device-summary-label { color:#94a3b8; font-size:19rpx; }
.device-summary-value,.device-pay { overflow:hidden; color:#475569; font-size:21rpx; text-overflow:ellipsis; white-space:nowrap; }
.device-cost { color:#0f172a; font-size:26rpx; font-weight:700; }
.device-extra-cost { margin-top:4rpx; color:#ea580c; font-size:18rpx; }
.device-foot { display:flex; min-width:0; align-items:center; justify-content:space-between; gap:16rpx; }
.device-asset-no { min-width:0; overflow:hidden; color:#94a3b8; font-size:19rpx; text-overflow:ellipsis; white-space:nowrap; }
.return-action-link { display:flex; flex:0 0 auto; align-items:center; gap:6rpx; padding:8rpx 12rpx; border-radius:999rpx; background:#fff7ed; color:#ea580c; font-size:21rpx; font-weight:600; }
.return-blocked-hint { flex:0 0 auto; color:#94a3b8; font-size:20rpx; }
.return-banner { display:flex; align-items:center; gap:8rpx; padding:12rpx 16rpx; border-radius:12rpx; background:#ffedd5; color:#c2410c; font-size:21rpx; line-height:1.45; }
@media (min-width: 900px) {
    .purchase-batch__head { grid-template-columns:minmax(320px,1fr) auto minmax(300px,.85fr); align-items:center; gap:24px; }
    .batch-statuses { flex-wrap:nowrap; }
    .batch-meta { justify-content:flex-end; }
    .purchase-device-row { grid-template-columns:minmax(260px,1.1fr) minmax(360px,1.5fr) minmax(180px,.8fr); align-items:center; gap:24px; }
    .device-head { align-items:center; }
    .device-serial { grid-column:1; }
    .device-summary { grid-column:2; grid-row:1 / span 2; }
    .device-foot { grid-column:3; grid-row:1 / span 2; flex-direction:column; align-items:flex-end; }
    .return-banner { grid-column:1 / -1; }
    .purchase-batch { margin:16px 20px; border-radius:12px; }
    .purchase-batch__head,.purchase-device-row { padding:16px 20px; }
}
</style>
