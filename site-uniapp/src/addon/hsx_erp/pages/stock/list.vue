<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            placeholder="搜索型号 / IMEI / 规格 / 仓位"
            :show-filter="true"
            :filter-count="filterCount"
            @search="handleSearch"
            @filter="filterVisible = true"
        >
            <template #below>
                <view class="quick-filters">
                    <view class="quick-filter" :class="{ active: activeTab }" @click="openQuickFilter('status')">
                        <text>{{ statusFilterLabel }}</text><u-icon name="arrow-down-fill" size="9" :color="activeTab ? '#2563eb' : '#64748b'" />
                    </view>
                    <view class="quick-filter" :class="{ active: filters.warehouse_id }" @click="openWarehouseFilter">
                        <text>{{ warehouseFilterLabel }}</text><u-icon name="arrow-down-fill" size="9" :color="filters.warehouse_id ? '#2563eb' : '#64748b'" />
                    </view>
                    <view class="quick-filter" :class="{ active: filters.turnover_level }" @click="openQuickFilter('turnover')">
                        <text>{{ turnoverFilterLabel }}</text><u-icon name="arrow-down-fill" size="9" :color="filters.turnover_level ? '#2563eb' : '#64748b'" />
                    </view>
                    <view class="quick-filter" :class="{ active: filters.listing_status }" @click="openQuickFilter('listing')">
                        <text>{{ listingFilterLabel }}</text><u-icon name="arrow-down-fill" size="9" :color="filters.listing_status ? '#2563eb' : '#64748b'" />
                    </view>
                </view>
            </template>
        </ErpListHeader>

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无库存设备" /></template>
            <view class="stock-overview">
                <view class="stock-overview__item"><text class="stock-overview__value">{{ inventoryTotal }}</text><text class="stock-overview__label">当前在库</text></view>
                <view class="stock-overview__item"><text class="stock-overview__value">{{ turnoverSummary.average_age_days || 0 }}天</text><text class="stock-overview__label">平均库龄</text></view>
                <view class="stock-overview__item warning" @click="filterTurnover('risk')"><text class="stock-overview__value">{{ turnoverSummary.warning_total_count || 0 }}</text><text class="stock-overview__label">周转预警</text></view>
                <view class="stock-overview__item trace" @click="goSerialTrace"><u-icon name="scan" color="#2563eb" size="18" /><text class="stock-overview__label">串号追踪</text></view>
            </view>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card stock-card" :class="{ 'stock-card--sold': isSold(row), 'stock-card--void': isVoid(row) }" @click="goDetail(row)">
                    <view class="stock-card__head">
                        <view class="stock-title">
                            <text class="card-title stock-title__model">{{ row.model || '-' }}</text>
                            <text class="stock-title__sub">{{ deviceIdentityLine(row) }}</text>
                        </view>
                        <view class="stock-statuses">
                            <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                            <text v-if="operationStatusText(row)" class="stock-operation-status">{{ operationStatusText(row) }}</text>
                        </view>
                    </view>

                    <view v-if="isSold(row)" class="stock-banner sold">
                        <u-icon name="checkmark-circle" color="#2563eb" size="14" />
                        <text>{{ row.sale_party_name ? `已售给 ${row.sale_party_name}` : '设备已售出' }} · {{ formatDate(row.sale_at || row.update_at) }}</text>
                    </view>
                    <view v-else-if="isVoid(row)" class="stock-banner void">
                        <u-icon name="info-circle" color="#64748b" size="14" />
                        <text>该设备已作废</text>
                    </view>
                    <view v-else-if="row.is_turnover_warning" class="stock-banner risk">
                        <u-icon name="warning" :color="row.turnover_level === 'critical' ? '#dc2626' : '#d97706'" size="14" />
                        <text>{{ row.turnover_label }} · {{ row.turnover_action }}</text>
                    </view>

                    <template v-if="!isSold(row)">
                        <view class="stock-meta">
                            <view><u-icon name="map" color="#94a3b8" size="13" /><text>{{ stockPosition(row) }}</text></view>
                            <view><u-icon name="clock" color="#94a3b8" size="13" /><text>{{ formatDate(primaryTime(row)) }} 入库</text></view>
                        </view>
                    </template>

                    <view v-else-if="row.sale_channel || Number(row.outbound_compensation_amount || 0)" class="stock-chips">
                        <view v-if="row.sale_channel" class="stock-chip primary">{{ row.sale_channel }}</view>
                        <view v-if="Number(row.outbound_compensation_amount || 0)" class="stock-chip warning">售后补差 -¥{{ money(row.outbound_compensation_amount) }}</view>
                    </view>

                    <view class="erp-card__foot stock-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">{{ isSold(row) ? '成本' : '总成本' }}</text>
                            <text class="amt-value">¥{{ money(row.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ priceLabel(row) }}</text>
                            <text class="amt-value blue">{{ displayPrice(row) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ isSold(row) ? '毛利' : '库龄' }}</text>
                            <text class="amt-value" :class="isSold(row) ? profitClass(row.profit) : ageClass(row)">
                                {{ isSold(row) ? profitText(row.profit) : `${row.stock_age_days || 0}天` }}
                            </text>
                        </view>
                    </view>
                    <view v-if="row.status === 'in_stock'" class="stock-actions" @click.stop>
                        <text class="stock-actions__reason">{{ row.turnover_action || '查看设备当前处理建议' }}</text>
                        <view class="stock-actions__buttons">
                            <view v-if="showTransferShortcut(row)" class="stock-transfer-btn" @click="openTransfer(row)">
                                <u-icon name="reload" color="#2563eb" size="13" /><text>调拨</text>
                            </view>
                            <view class="stock-actions__btn">
                                <u-button size="small" :type="turnoverActionType(row)" :plain="row.turnover_action_key !== 'direct_sale'" :loading="publishingId === Number(row.id) || transferringId === Number(row.id)" :text="row.turnover_action_label || '查看处理'" @click="handleTurnoverAction(row)" />
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <ErpFilterPopup
            v-model:show="filterVisible"
            v-model="filters"
            title="筛选库存设备"
            :fields="filterFields"
            @confirm="applyFilter"
            @reset="resetFilter"
        />
        <u-popup :show="quickFilterVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="28rpx" @close="quickFilterVisible = false">
            <view class="quick-popup">
                <view class="quick-popup__head">
                    <text>{{ quickFilterTitle }}</text>
                    <u-icon name="close" size="20" color="#94a3b8" @click="quickFilterVisible = false" />
                </view>
                <view class="quick-popup__options">
                    <view v-for="item in quickFilterOptions" :key="item.value" class="quick-option" :class="{ active: quickSelectedValue === item.value }" @click="selectQuickFilter(item.value)">
                        <text>{{ item.label }}</text>
                        <u-icon v-if="quickSelectedValue === item.value" name="checkmark-circle-fill" size="20" color="#2563eb" />
                    </view>
                </view>
            </view>
        </u-popup>
        <ErpWarehousePopup
            v-model:show="warehouseFilterVisible"
            v-model:warehouse-id="filters.warehouse_id"
            v-model:warehouse-name="filters.warehouse_name"
            v-model:location-id="filters.location_id"
            v-model:location-name="filters.location_name"
            :allow-warehouse-only="true"
            :allow-clear="true"
            @change="applyWarehouseFilter"
        />
        <ErpWarehousePopup
            v-model:show="transferVisible"
            v-model:warehouse-id="transferForm.warehouse_id"
            v-model:warehouse-name="transferForm.warehouse_name"
            v-model:location-id="transferForm.location_id"
            v-model:location-name="transferForm.location_name"
            @change="submitTransfer"
        />
        <ErpPartyPopup v-model:show="providerPopupVisible" v-model:partyId="providerPartyId" v-model:partyName="providerPartyName" roleType="supplier" roleContext="refurbish_provider" @select="confirmExternalRefurbish" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'

import { getMobileErpConfig, getMobileStockList, getMobileStockTurnoverSummary, syncMobileStockListing, transferMobileStock } from '@/addon/hsx_erp/api/erp'
import { dictLabel, dictTabs, dictType, ERP_DICT_FALLBACK, loadErpDicts, type ErpDictMap } from '@/addon/hsx_erp/api/dict'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { firstPositiveErpAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import { sendErpAssetRefurbish } from '@/addon/hsx_erp/api/asset'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'



const { pagingStyle } = useListHeader({ tabs: true })



const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const erpDicts = ref<ErpDictMap>(ERP_DICT_FALLBACK)
const refurbishTrackingMode = ref<'simple' | 'external'>('simple')
const providerPopupVisible = ref(false)
const providerPartyId = ref(0)
const providerPartyName = ref('')
const pendingRefurbishRow = ref<any>(null)
const publishingId = ref(0)
const transferringId = ref(0)
const transferVisible = ref(false)
const transferRow = ref<any>(null)
const transferForm = ref({ warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '' })
const turnoverSummary = ref<any>({ thresholds: {} })
const quickFilterVisible = ref(false)
const quickFilterKey = ref<'status' | 'turnover' | 'listing'>('status')
const warehouseFilterVisible = ref(false)

const tabs = computed(() => dictTabs(erpDicts.value, 'asset_status', true))
const activeTab = ref('')
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const turnoverOptions = [
    { label: '全部库龄', value: '' },
    { label: '全部预警', value: 'risk' },
    { label: '周转正常', value: 'healthy' },
    { label: '需要关注', value: 'attention' },
    { label: '周转预警', value: 'warning' },
    { label: '严重滞销', value: 'critical' },
]
const filterFields = computed(() => [
    { key: 'asset_no', label: '资产号', type: 'text', placeholder: '输入资产编号' },
    { key: 'imei', label: 'IMEI / 串号', type: 'text', placeholder: '输入 IMEI 或串号' },
    { key: 'model', label: '型号', type: 'text', placeholder: '输入机型型号' },
    { key: 'party_id', label: '来源/供应商', type: 'party', roleType: 'supplier', labelKey: 'party_name', placeholder: '请选择来源/供应商' },
    { key: 'catalog_product_id', label: '商品型号', type: 'category', labelKey: 'catalog_product_name', placeholder: '请选择型号' },
    { key: 'warehouse_id', label: '仓库', type: 'warehouse', labelKey: 'warehouse_name', locationKey: 'location_id', locationLabelKey: 'location_name', placeholder: '请选择仓库/库位' },
    { key: 'refurbish_status', label: '整备状态', type: 'select', options: dictTabs(erpDicts.value, 'refurbish_status', true) },
    { key: 'sale_target', label: '销售去向', type: 'select', options: dictTabs(erpDicts.value, 'sale_target', true) },
    { key: 'listing_status', label: '上架状态', type: 'select', options: dictTabs(erpDicts.value, 'listing_status', true) },
    { key: 'cost', label: '总成本', type: 'range', minKey: 'min_cost', maxKey: 'max_cost' },
    { key: 'price', label: '标价/预估价', type: 'range', minKey: 'min_price', maxKey: 'max_price' },
    { key: 'age', label: '库龄天数', type: 'range', minKey: 'stock_age_min', maxKey: 'stock_age_max' },
    { key: 'turnover_level', label: '周转等级', type: 'select', options: [
        { label: '全部预警', value: 'risk' }, { label: '周转正常', value: 'healthy' }, { label: '需要关注', value: 'attention' },
        { label: '周转预警', value: 'warning' }, { label: '严重滞销', value: 'critical' }
    ] },
    { key: 'date', label: '入库日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[])
const hasFilterValue = (value: any) => value !== '' && value !== undefined && value !== null && value !== 0
const filterCount = computed(() => filterFields.value.reduce((count, field: any) => {
    if (field.type === 'range') return count + (hasFilterValue(filters.value[field.minKey]) || hasFilterValue(filters.value[field.maxKey]) ? 1 : 0)
    if (field.type === 'dateRange') return count + (hasFilterValue(filters.value[field.startKey]) || hasFilterValue(filters.value[field.endKey]) ? 1 : 0)
    if (field.type === 'warehouse') return count + (hasFilterValue(filters.value[field.key]) || hasFilterValue(filters.value[field.locationKey]) ? 1 : 0)
    return count + (hasFilterValue(filters.value[field.key]) ? 1 : 0)
}, 0))
const inventoryTotal = computed(() => ['healthy_count', 'attention_count', 'warning_count', 'critical_count']
    .reduce((total, key) => total + Number(turnoverSummary.value?.[key] || 0), 0))
const quickFilterTitle = computed(() => ({ status: '库存状态', turnover: '库龄与周转', listing: '上架状态' }[quickFilterKey.value]))
const quickFilterOptions = computed(() => {
    if (quickFilterKey.value === 'status') return tabs.value
    if (quickFilterKey.value === 'listing') return dictTabs(erpDicts.value, 'listing_status', true)
    return turnoverOptions
})
const quickSelectedValue = computed(() => {
    if (quickFilterKey.value === 'status') return activeTab.value
    if (quickFilterKey.value === 'listing') return String(filters.value.listing_status || '')
    return String(filters.value.turnover_level || '')
})
const optionLabel = (options: any[], value: any, fallback: string) => options.find(item => String(item.value) === String(value || ''))?.label || fallback
const statusFilterLabel = computed(() => activeTab.value ? optionLabel(tabs.value, activeTab.value, '状态') : '状态')
const warehouseFilterLabel = computed(() => String(filters.value.location_name || filters.value.warehouse_name || '仓库'))
const turnoverFilterLabel = computed(() => filters.value.turnover_level ? optionLabel(turnoverOptions, filters.value.turnover_level, '库龄') : '库龄')
const listingFilterLabel = computed(() => filters.value.listing_status ? optionLabel(dictTabs(erpDicts.value, 'listing_status', true), filters.value.listing_status, '上架') : '上架')
const reload = () => pagingRef.value?.reload()
function filterTurnover(level: string) { filters.value = { ...filters.value, turnover_level: level }; activeTab.value = 'in_stock'; reload() }
const goSerialTrace = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/serial_trace/list' })
const handleSearch = () => reload()
function openQuickFilter(key: 'status' | 'turnover' | 'listing') {
    quickFilterKey.value = key
    quickFilterVisible.value = true
}
function selectQuickFilter(value: string) {
    if (quickFilterKey.value === 'status') activeTab.value = value
    if (quickFilterKey.value === 'turnover') {
        filters.value = { ...filters.value, turnover_level: value }
        if (value) activeTab.value = 'in_stock'
    }
    if (quickFilterKey.value === 'listing') {
        filters.value = { ...filters.value, listing_status: value }
        if (value) activeTab.value = 'in_stock'
    }
    quickFilterVisible.value = false
    reload()
}
function openWarehouseFilter() { warehouseFilterVisible.value = true }
function applyWarehouseFilter() { reload() }

onShow(async () => {
    erpDicts.value = await loadErpDicts()
    try {
        const config: any = await getMobileErpConfig()
        refurbishTrackingMode.value = config?.data?.refurbish?.tracking_mode === 'external' ? 'external' : 'simple'
    } catch {}
    reload()
})
onLoad((options: any) => {
    if (options?.refurbish_status) filters.value.refurbish_status = String(options.refurbish_status)
    if (options?.turnover_level) filters.value.turnover_level = String(options.turnover_level)
})

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const summaryRequest = pageNo === 1 ? getMobileStockTurnoverSummary() : Promise.resolve(null)
        const [res, turnoverRes]: any[] = await Promise.all([
            getMobileStockList({ keyword: keyword.value, status: activeTab.value, ...filterParams(), page: pageNo, limit: pageSize }), summaryRequest
        ])
        if (turnoverRes) turnoverSummary.value = turnoverRes?.data || { thresholds: {} }
        pagingRef.value?.complete(res?.data?.data || [])
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
const deviceIdentityLine = (row: any) => erpDeviceIdentityLine(row)
const formatDate = (ts: any) => {
    const n = Number(ts || 0)
    if (!n) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}
const stockPosition = (row: any) => row.location_name ? `${row.warehouse_name || '未分仓'} / ${row.location_name}` : (row.warehouse_name || '未分配仓位')
const operationStatusText = (row: any) => [
    row.refurbish_status && row.refurbish_status !== 'none' ? refurbishLabel(row.refurbish_status) : '',
    row.listing_status && row.listing_status !== 'none' ? listingLabel(row.listing_status) : '',
].filter(Boolean).join(' · ')

const goDetail = (row: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/stock/detail?id=${row.id}`
})
async function startRefurbish(row: any) {
    if (refurbishTrackingMode.value === 'external') {
        pendingRefurbishRow.value = row
        providerPartyId.value = 0
        providerPartyName.value = ''
        providerPopupVisible.value = true
        return
    }
    await doStartRefurbish(row, 0, '')
}
async function confirmExternalRefurbish(party: any) {
    providerPopupVisible.value = false
    if (!pendingRefurbishRow.value) return
    await doStartRefurbish(pendingRefurbishRow.value, Number(party?.party_id || 0), String(party?.party_name || ''))
    pendingRefurbishRow.value = null
}
async function doStartRefurbish(row: any, providerPartyId: number, providerName: string) {
    const confirmed = await confirmErpSensitiveAction({ title: '开始整备', content: `确认设备「${row.model || row.imei || '-'}」开始整备？\n设备仍保留原库存位置，但整备完成前不可销售。`, confirmText: '确认开始' })
    if (!confirmed) return
    await sendErpAssetRefurbish({ asset_ids: [Number(row.id)], tracking_mode: refurbishTrackingMode.value, provider_party_id: providerPartyId, remark: providerName ? `送交${providerName}整备` : '移动端开始整备' })
    uni.showToast({ title: providerName ? `已交给${providerName}` : '已进入整备中', icon: 'none' })
    reload()
}
function goCompleteRefurbish(row: any) {
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?id=${row.id}&mode=refurbish_complete` })
}
const isSold = (row: any) => row?.status === 'sold'
const isVoid = (row: any) => row?.status === 'void'
const ageClass = (row: any) => row?.status !== 'in_stock' ? '' : row?.turnover_level === 'critical' ? 'red' : row?.turnover_level === 'warning' ? 'orange' : ''

const turnoverActionType = (row: any) => ['transfer', 'resolve_warehouse', 'complete_refurbish', 'resolve_refurbish'].includes(row?.turnover_action_key) ? 'warning' : 'primary'
const canTransfer = (row: any) => Number(row?.can_transfer ?? row?.warehouse_policy?.can_transfer ?? 0) === 1 || String(row?.turnover_action_key || row?.warehouse_policy?.primary_action || '') === 'resolve_warehouse'
const showTransferShortcut = (row: any) => canTransfer(row) && String(row?.turnover_action_key || '') !== 'transfer'
function handleTurnoverAction(row: any) {
    const action = String(row?.turnover_action_key || 'view')
    if (action === 'start_refurbish') return startRefurbish(row)
    if (['complete_refurbish', 'resolve_refurbish'].includes(action)) return goCompleteRefurbish(row)
    if (action === 'direct_sale') return uni.navigateTo({ url: `/addon/hsx_erp/pages/sale/create?asset_ids=${row.id}` })
    if (action === 'publish_listing') return publishListing(row)
    if (['transfer', 'resolve_warehouse'].includes(action)) return openTransfer(row)
    return goDetail(row)
}
function openTransfer(row: any) {
    if (!canTransfer(row)) {
        uni.showToast({ title: row?.warehouse_policy?.primary_action_reason || '当前设备不可调拨', icon: 'none' })
        return
    }
    transferRow.value = row
    transferForm.value = { warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '' }
    transferVisible.value = true
}
async function submitTransfer(warehouse: any, location: any) {
    const row = transferRow.value
    if (!row?.id || !warehouse?.id || !location?.id || transferringId.value) return
    const from = stockPosition(row)
    const target = `${warehouse.warehouse_name} / ${location.location_name}`
    const confirmed = await confirmErpSensitiveAction({
        title: '确认库存调拨',
        content: `确认将「${row.model || row.imei || '-'}」从「${from}」调拨到「${target}」？调拨后会重新应用目标仓库的销售和商城规则。`,
        confirmText: '确认调拨',
    })
    if (!confirmed) return
    transferringId.value = Number(row.id)
    try {
        await transferMobileStock({ asset_ids: [Number(row.id)], warehouse_id: Number(warehouse.id), location_id: Number(location.id), reason: '移动端库存列表调拨' })
        uni.showToast({ title: '调拨完成', icon: 'success' })
        transferRow.value = null
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '调拨失败，请重试', icon: 'none' })
    } finally {
        transferringId.value = 0
    }
}
async function publishListing(row: any) {
    if (!row?.id || publishingId.value) return
    const confirmed = await confirmErpSensitiveAction({
        title: '上架商城',
        content: `确认将「${row.model || row.imei || '-'}」直接上架商城？系统将使用当前分类、规格、图片和零售价创建一机一品商品。`,
        confirmText: '确认上架',
    })
    if (!confirmed) return
    publishingId.value = Number(row.id)
    try {
        const res: any = await syncMobileStockListing(Number(row.id))
        if (res?.data?.ok === false) throw new Error(res?.data?.message || '上架失败')
        uni.showToast({ title: res?.data?.message || '已上架商城', icon: 'success' })
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '上架失败，请重试', icon: 'none' })
    } finally {
        publishingId.value = 0
    }
}
const primaryTime = (row: any) => Number(row.stock_in_at || 0) || Number(row.create_at || 0)
const priceLabel = (row: any) => row.status === 'sold' ? '实际收入' : '标价'
const displayPrice = (row: any) => {
    if (row.status === 'sold') return `¥${money(row.outbound_net_sale_amount)}`
    const price = firstPositiveErpAmount(row.retail_price, row.estimate_sale_price)
    return Number(price || 0) > 0 ? `¥${money(price)}` : '-'
}
const profitText = (v: any) => Number(v || 0) ? `¥${money(v)}` : '-'
const profitClass = (v: any) => Number(v || 0) < 0 ? 'red' : 'green'

const statusLabel = (s: string) => dictLabel(erpDicts.value, 'asset_status', s)
const statusType = (s: string) => dictType(erpDicts.value, 'asset_status', s)
const refurbishLabel = (s: string) => dictLabel(erpDicts.value, 'refurbish_status', s)
const listingLabel = (s: string) => dictLabel(erpDicts.value, 'listing_status', s)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.quick-filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10rpx; }
.quick-filter { height:52rpx; min-width:0; padding:0 12rpx; border-radius:10rpx; background:#fff; color:#64748b; display:flex; align-items:center; justify-content:center; gap:6rpx; box-sizing:border-box; font-size:23rpx; }
.quick-filter text { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.quick-filter.active { color:#2563eb; background:#eff6ff; font-weight:600; }

.stock-overview { margin:16rpx 22rpx 0; padding:18rpx 8rpx; border-radius:14rpx; background:#fff; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); }
.stock-overview__item { min-width:0; text-align:center; border-right:1rpx solid #eef2f7; }
.stock-overview__item:last-child { border-right:0; }
.stock-overview__item text { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.stock-overview__item .stock-overview__value { color:#0f172a; font-size:27rpx; font-weight:700; line-height:1.2; }
.stock-overview__item .stock-overview__label { margin-top:6rpx; color:#94a3b8; font-size:20rpx; }
.stock-overview__item.warning .stock-overview__value { color:#d97706; }
.stock-overview__item.trace { display:flex; flex-direction:column; align-items:center; justify-content:center; }
.stock-overview__item.trace .stock-overview__label { color:#2563eb; }

.stock-card { padding:22rpx 26rpx; }
.stock-card--sold { background:#f8fbff; border:2rpx solid #bfdbfe; }
.stock-card--void { background:#f8fafc; border:2rpx solid #e2e8f0; }
.stock-card__head { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; }
.stock-title { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.stock-title__model,.stock-title__sub { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.stock-title__model { line-height:1.35; }
.stock-title__sub { color:#64748b; font-size:24rpx; line-height:1.35; }
.stock-statuses { flex-shrink:0; max-width:210rpx; display:flex; flex-direction:column; align-items:flex-end; gap:8rpx; }
.stock-operation-status { max-width:100%; padding:4rpx 8rpx; border-radius:6rpx; background:#eff6ff; color:#2563eb; font-size:19rpx; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; box-sizing:border-box; }
.stock-banner { display:flex; align-items:center; gap:8rpx; margin:14rpx 0 0; padding:12rpx 16rpx; border-radius:10rpx; font-size:22rpx; line-height:1.45; }
.stock-banner text { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.stock-banner.sold { color:#2563eb; background:#eff6ff; }
.stock-banner.void { color:#64748b; background:#f1f5f9; }
.stock-banner.risk { color:#c2410c; background:#fff7ed; }

.stock-meta { display:flex; align-items:center; justify-content:space-between; gap:20rpx; margin-top:16rpx; color:#64748b; font-size:22rpx; }
.stock-meta view { min-width:0; display:flex; align-items:center; gap:7rpx; }
.stock-meta text { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.stock-chips { display:flex; flex-wrap:wrap; gap:8rpx; margin-top:12rpx; }
.stock-chip { max-width:100%; height:38rpx; padding:0 12rpx; border-radius:8rpx; background:#f8fafc; color:#64748b; font-size:21rpx; line-height:38rpx; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; box-sizing:border-box; }
.stock-chip.primary { color:#2563eb; background:#eff6ff; }
.stock-chip.warning { color:#d97706; background:#fffbeb; }
.stock-card__foot { justify-content:space-between; gap:10rpx; margin-top:16rpx; padding-top:16rpx; }
.stock-card__foot .amount-box { flex:1; min-width:0; }
.stock-card__foot .amt-label,.stock-card__foot .amt-value { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.stock-card__foot .amt-value { font-size:27rpx; }
.stock-actions { display:flex; align-items:center; justify-content:space-between; gap:14rpx; margin-top:16rpx; padding-top:16rpx; border-top:1rpx solid #eef2f7; }
.stock-actions__reason { flex:1; min-width:0; color:#94a3b8; font-size:21rpx; line-height:1.45; }
.stock-actions__buttons { flex-shrink:0; display:flex; align-items:center; gap:10rpx; }
.stock-actions__btn { flex-shrink:0; }
.stock-transfer-btn { height:52rpx; padding:0 14rpx; border:1rpx solid #bfdbfe; border-radius:8rpx; display:flex; align-items:center; gap:6rpx; color:#2563eb; font-size:22rpx; box-sizing:border-box; }

.quick-popup { max-height:68vh; background:#fff; display:flex; flex-direction:column; }
.quick-popup__head { height:92rpx; padding:0 32rpx; border-bottom:1rpx solid #eef2f7; display:flex; align-items:center; justify-content:space-between; box-sizing:border-box; color:#0f172a; font-size:30rpx; font-weight:700; }
.quick-popup__options { padding:8rpx 30rpx 28rpx; overflow-y:auto; }
.quick-option { min-height:88rpx; padding:0 10rpx; border-bottom:1rpx solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; color:#334155; font-size:27rpx; }
.quick-option.active { color:#2563eb; font-weight:600; }
</style>
