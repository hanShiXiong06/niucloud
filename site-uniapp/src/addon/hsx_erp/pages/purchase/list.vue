<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="型号/IMEI/采购单号/供应商"
            :tabs="tabs"
            :show-filter="true"
            :filter-count="filterCount"
            @search="handleSearch"
            @tab-change="onTab"
            @filter="filterVisible = true"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无采购记录" /></template>
            <view class="list-wrap">

                <view v-for="row in list" :key="row.id" class="erp-card purchase-card" :class="{ 'purchase-card--returned': row.is_returned || row.status === 'returned' }" @click="goDetail(row)">
                    <view class="erp-card__head">
                        <view class="title-main">
                            <text class="card-title">{{ row.model || '-' }}</text>
                            <text class="sub-no">{{ row.purchase_no || '-' }} · {{ row.party_name || '-' }}</text>
                        </view>
                        <view class="tag-stack">
                            <u-tag v-if="row.is_returned || row.status === 'returned'" text="已退货" type="warning" plain plainFill size="mini" />
                            <u-tag v-else :text="assetLabel(row.status)" :type="assetType(row.status)" plain plainFill size="mini" />
                            <u-tag :text="financeLabel(row.finance_status)" :type="financeType(row.finance_status)" plain plainFill size="mini" />
                        </view>
                    </view>
                    <view v-if="row.is_returned || row.status === 'returned'" class="return-banner">
                        <u-icon name="info-circle" color="#ea580c" size="14" />
                        <text>{{ returnSummary(row) }}</text>
                    </view>
                    <view class="compact-meta">
                        <text>{{ row.spec || '无规格' }}</text>
                    </view>
                    <view class="compact-meta">
                        <text>IMEI {{ row.imei || '-' }}</text>
                    </view>
                    <view class="compact-meta muted">
                        <text>{{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}</text>
                        <text>{{ erpTimeLine(row, ['return_at', 'stock_in_at', 'purchase_at']) }}</text>
                    </view>
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">成本</text>
                            <text class="amt-value">¥{{ money(row.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已付</text>
                            <text class="amt-value green">¥{{ money(row.paid_amount) }}</text>
                        </view>
                        <view v-if="!(row.is_returned || row.status === 'returned')" class="amount-box">
                            <text class="amt-label">未付</text>
                            <text class="amt-value orange">¥{{ money(row.payable_amount) }}</text>
                        </view>
                        <view v-else class="amount-box">
                            <text class="amt-label">退货</text>
                            <text class="amt-value orange">¥{{ money(row.return_cost || row.total_cost) }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <view class="fab" @click="goCreate" ><u-icon name="plus" color="#fff" size="26"></u-icon></view>

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
import { onShow } from '@dcloudio/uni-app'

import { getMobilePurchaseList } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
// useListHeader hook
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)

const tabs = [
    { label: '全部', value: '' },
    { label: '待付款', value: 'pending' },
    { label: '部分付款', value: 'partial' },
    { label: '已结清', value: 'settled' },
]
const activeTab = ref('')
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const filterFields = [
    { key: 'asset_no', label: '资产号', type: 'text', placeholder: '输入资产编号' },
    { key: 'imei', label: 'IMEI / 串号', type: 'text', placeholder: '输入 IMEI 或串号' },
    { key: 'model', label: '型号', type: 'text', placeholder: '输入机型型号' },
    { key: 'party_id', label: '供应商', type: 'party', roleType: 'supplier', labelKey: 'party_name', placeholder: '请选择供应商' },
    { key: 'category_id', label: '设备分类', type: 'category', labelKey: 'category_name', placeholder: '请选择分类' },
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
const { pagingStyle } = useListHeader(126)
onShow(() => reload())



const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobilePurchaseList({
            keyword: keyword.value, finance_status: activeTab.value,
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

const goDetail = (row: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/purchase/detail?purchase_order_id=${row.purchase_order_id}&purchase_no=${encodeURIComponent(row.purchase_no || '')}`
})
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/purchase/create' })
const financeLabel = (s: string) => ({ pending: '待付款', partial: '部分付款', settled: '已结清', void: '已作废' }[s] || s || '-')
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const assetLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const assetType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
const refundModeLabel = (s: string) => ({ cash: '现金退回', offset: '应收冲减', balance: '余额处理' }[s] || s || '冲减应付')
function returnSummary(row: any) {
    const paid = Number(row.return_paid_amount ?? row.paid_amount ?? 0)
    if (paid <= 0) return `未付款退货，已自动冲减应付${row.return_no ? ' · ' + row.return_no : ''}`
    const settled = Number(row.return_settled_amount || 0)
    const mode = refundModeLabel(row.refund_mode)
    const status = settled > 0 ? '已退款' : '待退款'
    return `${mode} · ${status} ¥${money(paid)}${row.return_no ? ' · ' + row.return_no : ''}`
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.purchase-card { transition: background .2s ease; }
.purchase-card--returned { background: #fffaf5; border: 2rpx solid #fed7aa; }
.title-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
.sub-no { font-size: 22rpx; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tag-stack { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 8rpx; }
.return-banner { display: flex; align-items: center; gap: 8rpx; margin: 10rpx 0 8rpx; padding: 12rpx 16rpx; border-radius: 12rpx; background: #fff7ed; color: #ea580c; font-size: 18rpx; line-height: 1.45; }
.compact-meta { display: flex; align-items: center; justify-content: space-between; gap: 18rpx; margin-top: 8rpx; font-size: 24rpx; color: #64748b; line-height: 1.45; }
.compact-meta text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.compact-meta.muted { color: #94a3b8; font-size: 22rpx; }
</style>
