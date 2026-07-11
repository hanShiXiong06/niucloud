<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="型号/IMEI/资产号/仓库"
            :tabs="tabs"
            :show-filter="true"
            :filter-count="filterCount"
            @search="handleSearch"
            @tab-change="onTab"
            @filter="filterVisible = true"
        />
        <view class="trace-entry" @click="goSerialTrace">
            <view><text class="trace-entry__title">串号追踪</text><text class="trace-entry__sub">查询同一 IMEI / SN 的多次入库与完整流转</text></view>
            <u-icon name="arrow-right" color="#64748b" size="15" />
        </view>
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无库存设备" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card stock-card" :class="{ 'stock-card--sold': isSold(row), 'stock-card--void': isVoid(row) }" @click="goDetail(row)">
                    <view class="stock-card__head">
                        <view class="stock-title">
                            <text class="card-title stock-title__model">{{ row.model || '-' }}</text>
                            <text class="stock-title__sub">{{ deviceIdentityLine(row) }}</text>
                        </view>
                        <view class="tag-stack">
                            <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                            <u-tag v-if="isSold(row) && row.sale_channel" :text="row.sale_channel" type="primary" plain plainFill size="mini" />
                        </view>
                    </view>

                    <view v-if="isSold(row)" class="stock-banner sold">
                        <u-icon name="checkmark-circle" color="#2563eb" size="14" />
                        <text>已售出{{ row.sale_no ? ' · ' + row.sale_no : '' }}{{ row.sale_party_name ? ' · ' + row.sale_party_name : '' }}</text>
                    </view>
                    <view v-else-if="isVoid(row)" class="stock-banner void">
                        <u-icon name="info-circle" color="#64748b" size="14" />
                        <text>该设备已作废</text>
                    </view>

                    <view class="stock-line">
                        <text class="stock-line__label">{{ isSold(row) ? '销售时间' : timeLabel(row) }}</text>
                        <text class="stock-line__value">{{ formatTime(isSold(row) ? (row.sale_at || row.update_at) : primaryTime(row)) }}</text>
                    </view>

                    <template v-if="!isSold(row)">
                        <view class="stock-chips">
                            <view class="stock-chip">{{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}</view>
                            <view v-if="row.category_name" class="stock-chip muted">{{ row.category_name }}</view>
                            <view v-if="row.refurbish_status && row.refurbish_status !== 'none'" class="stock-chip">{{ refurbishLabel(row.refurbish_status) }}</view>
                            <view v-if="row.sale_target && row.sale_target !== 'unset'" class="stock-chip primary">{{ targetLabel(row.sale_target) }}</view>
                            <view v-if="row.listing_status && row.listing_status !== 'none'" class="stock-chip">{{ listingLabel(row.listing_status) }}</view>
                        </view>
                        <view v-if="isExpanded(row)" class="stock-extra">
                            <view class="stock-line">
                                <text class="stock-line__label">资产号</text>
                                <text class="stock-line__value">{{ row.asset_no || '-' }}</text>
                            </view>
                            <view class="stock-line">
                                <text class="stock-line__label">入库来源</text>
                                <text class="stock-line__value">{{ row.inbound_origin_name || 'ERP采购' }}{{ row.inbound_origin_plugin_name ? ' · ' + row.inbound_origin_plugin_name : '' }}</text>
                            </view>
                            <view v-if="row.m_no" class="stock-line">
                                <text class="stock-line__label">M号</text>
                                <text class="stock-line__value">{{ row.m_no }}</text>
                            </view>
                            <view class="stock-line">
                                <text class="stock-line__label">更新时间</text>
                                <text class="stock-line__value">{{ formatTime(row.update_at) }}</text>
                            </view>
                        </view>
                        <view class="stock-expand" @click.stop="toggleExpand(row)">
                            <text>{{ isExpanded(row) ? '收起' : '更多信息' }}</text>
                            <u-icon :name="isExpanded(row) ? 'arrow-up' : 'arrow-down'" color="#64748b" size="13" />
                        </view>
                    </template>

                    <view v-else class="stock-chips">
                        <view v-if="row.sale_party_name" class="stock-chip">{{ row.sale_party_name }}</view>
                        <view class="stock-chip primary">{{ row.outbound_origin_name || 'ERP销售' }} / {{ row.outbound_channel || row.sale_channel || '-' }}</view>
                        <view v-if="row.warehouse_name" class="stock-chip muted">原仓 {{ row.warehouse_name }}</view>
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
                            <text class="amt-value" :class="isSold(row) ? profitClass(row.profit) : ageClass(row.stock_in_at || row.create_at, row.status)">
                                {{ isSold(row) ? profitText(row.profit) : ageText(row) }}
                            </text>
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
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileStockList } from '@/addon/hsx_erp/api/erp'
import { dictLabel, dictTabs, dictType, ERP_DICT_FALLBACK, loadErpDicts, type ErpDictMap } from '@/addon/hsx_erp/api/dict'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { firstPositiveErpAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'



const { pagingStyle } = useListHeader(126)



const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const erpDicts = ref<ErpDictMap>(ERP_DICT_FALLBACK)
const expandedMap = ref<Record<string, boolean>>({})

const tabs = computed(() => dictTabs(erpDicts.value, 'asset_status', true))
const activeTab = ref('')
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const filterFields = computed(() => [
    { key: 'asset_no', label: '资产号', type: 'text', placeholder: '输入资产编号' },
    { key: 'imei', label: 'IMEI / 串号', type: 'text', placeholder: '输入 IMEI 或串号' },
    { key: 'model', label: '型号', type: 'text', placeholder: '输入机型型号' },
    { key: 'party_id', label: '来源/供应商', type: 'party', roleType: 'supplier', labelKey: 'party_name', placeholder: '请选择来源/供应商' },
    { key: 'category_id', label: '设备分类', type: 'category', labelKey: 'category_name', placeholder: '请选择分类' },
    { key: 'warehouse_id', label: '仓库', type: 'warehouse', labelKey: 'warehouse_name', locationKey: 'location_id', locationLabelKey: 'location_name', placeholder: '请选择仓库/库位' },
    { key: 'refurbish_status', label: '整备状态', type: 'select', options: dictTabs(erpDicts.value, 'refurbish_status', true) },
    { key: 'sale_target', label: '销售去向', type: 'select', options: dictTabs(erpDicts.value, 'sale_target', true) },
    { key: 'listing_status', label: '上架状态', type: 'select', options: dictTabs(erpDicts.value, 'listing_status', true) },
    { key: 'cost', label: '总成本', type: 'range', minKey: 'min_cost', maxKey: 'max_cost' },
    { key: 'price', label: '标价/预估价', type: 'range', minKey: 'min_price', maxKey: 'max_price' },
    { key: 'age', label: '库龄天数', type: 'range', minKey: 'stock_age_min', maxKey: 'stock_age_max' },
    { key: 'date', label: '入库日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[])
const filterCount = computed(() => Object.entries(filters.value).filter(([key, v]) => !key.endsWith('_name') && v !== '' && v !== undefined && v !== null).length)
const reload = () => pagingRef.value?.reload()
const goSerialTrace = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/serial_trace/list' })
const handleSearch = () => reload()
const onTab = (val: string) => { activeTab.value = val; reload() }

onShow(async () => {
    erpDicts.value = await loadErpDicts()
    reload()
})

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileStockList({
            keyword: keyword.value, status: activeTab.value,
            ...filterParams(),
            page: pageNo, limit: pageSize
        })
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
const formatTime = (ts: any) => {
    const n = Number(ts || 0)
    if (!n) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}

const goDetail = (row: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/stock/detail?id=${row.id}`
})
const isSold = (row: any) => row?.status === 'sold'
const isVoid = (row: any) => row?.status === 'void'
const isExpanded = (row: any) => !!expandedMap.value[String(row?.id || '')]
function toggleExpand(row: any) {
    const key = String(row?.id || '')
    if (!key) return
    expandedMap.value = { ...expandedMap.value, [key]: !expandedMap.value[key] }
}
const ageDays = (ts: number) => ts ? Math.floor((Date.now() / 1000 - ts) / 86400) : 0
const ageClass = (ts: number, status: string) => {
    if (status !== 'in_stock') return ''
    const d = ageDays(ts)
    return d >= 90 ? 'red' : d >= 30 ? 'orange' : ''
}
const primaryTime = (row: any) => Number(row.stock_in_at || 0) || Number(row.create_at || 0)
const timeLabel = (row: any) => Number(row.stock_in_at || 0) ? '入库时间' : '创建时间'
const ageText = (row: any) => {
    const ts = Number(row.stock_in_at || row.create_at || 0)
    return ts ? `${ageDays(ts)}天` : '-'
}
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
const refurbishType = (s: string) => dictType(erpDicts.value, 'refurbish_status', s)
const targetLabel = (s: string) => dictLabel(erpDicts.value, 'sale_target', s)
const listingLabel = (s: string) => dictLabel(erpDicts.value, 'listing_status', s)
const listingType = (s: string) => dictType(erpDicts.value, 'listing_status', s)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.stock-card {
    padding: 22rpx 26rpx;
}

.stock-card--sold {
    background: #f8fbff;
    border: 2rpx solid #bfdbfe;
}

.stock-card--void {
    background: #f8fafc;
    border: 2rpx solid #e2e8f0;
}

.stock-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18rpx;
}

.stock-title {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8rpx;
}

.stock-title__model,
.stock-title__sub,
.stock-line__value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stock-title__model {
    display: block;
    line-height: 1.35;
}

.stock-title__sub {
    display: block;
    font-size: 24rpx;
    line-height: 1.35;
    color: #64748b;
}

.tag-stack {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8rpx;
}

.stock-banner {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin: 12rpx 0 10rpx;
    padding: 12rpx 16rpx;
    border-radius: 12rpx;
    font-size: 22rpx;
    line-height: 1.45;
}

.stock-banner text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stock-banner.sold {
    background: #eff6ff;
    color: #2563eb;
}

.stock-banner.void {
    background: #f1f5f9;
    color: #64748b;
}

.stock-line {
    display: flex;
    align-items: center;
    gap: 12rpx;
    min-width: 0;
    margin-top: 10rpx;
    font-size: 24rpx;
    line-height: 1.35;
}

.stock-line__label {
    flex-shrink: 0;
    color: #94a3b8;
}

.stock-line__value {
    flex: 1;
    min-width: 0;
    color: #475569;
}

.stock-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx;
    margin-top: 12rpx;
}

.stock-chip {
    max-width: 100%;
    height: 38rpx;
    padding: 0 12rpx;
    border-radius: 19rpx;
    background: #f8fafc;
    color: #64748b;
    font-size: 21rpx;
    line-height: 38rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    box-sizing: border-box;
}

.stock-chip.primary {
    color: #2563eb;
    background: #eff6ff;
}

.stock-chip.muted {
    color: #94a3b8;
}

.stock-chip.warning {
    color: #d97706;
    background: #fffbeb;
}

.stock-extra {
    margin-top: 10rpx;
    padding: 2rpx 0 4rpx;
}

.stock-expand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6rpx;
    margin-top: 12rpx;
    min-height: 44rpx;
    border-radius: 22rpx;
    background: #f8fafc;
    color: #64748b;
    font-size: 22rpx;
}

.stock-card__foot {
    justify-content: space-between;
    gap: 10rpx;
    margin-top: 14rpx;
    padding-top: 14rpx;
}

.stock-card__foot .amount-box {
    flex: 1;
    min-width: 0;
}

.stock-card__foot .amt-label,
.stock-card__foot .amt-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stock-card__foot .amt-value {
    font-size: 27rpx;
}
.trace-entry{display:flex;align-items:center;justify-content:space-between;gap:16rpx;margin:14rpx 22rpx 0;padding:17rpx 20rpx;border-radius:14rpx;background:#f8fafc;color:#334155}.trace-entry__title,.trace-entry__sub{display:block}.trace-entry__title{font-size:25rpx;font-weight:700}.trace-entry__sub{margin-top:4rpx;color:#94a3b8;font-size:20rpx}
</style>
