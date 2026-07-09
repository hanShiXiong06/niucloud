<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="型号/IMEI/销售单号/客户"
            :tabs="tabs"
            :show-filter="true"
            :filter-count="filterCount"
            @search="handleSearch"
            @tab-change="onTab"
            @filter="filterVisible = true"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无销售记录" /></template>
            <view class="list-wrap">

                <view v-for="row in list" :key="row.id" class="erp-card sale-card" :class="{ 'sale-card--void': isVoidSale(row) }" @click="goDetail(row)">
                    <view class="sale-card__head">
                        <view class="sale-title">
                            <text class="card-title sale-title__model">{{ row.model || '-' }}</text>
                            <text class="sale-title__sub">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</text>
                        </view>
                        <view class="tag-stack">
                            <u-tag v-if="isVoidSale(row)" text="已作废" type="info" plain plainFill size="mini" />
                            <u-tag v-else :text="financeLabel(row.finance_status)"
                                :type="financeType(row.finance_status)" plain plainFill size="mini" />
                            <u-tag v-if="!isVoidSale(row)" :text="assetLabel(row.status)" :type="assetType(row.status)" plain plainFill size="mini" />
                        </view>
                    </view>

                    <view v-if="isVoidSale(row)" class="void-banner">
                        <u-icon name="info-circle" color="#64748b" size="14" />
                        <text>{{ voidSummary(row) }}</text>
                    </view>

                    <view class="sale-line">
                        <text class="sale-line__label">销售单</text>
                        <text class="sale-line__value">{{ row.sale_no || '-' }}</text>
                    </view>
                    <view class="sale-line">
                        <text class="sale-line__label">客户</text>
                        <text class="sale-line__value">{{ row.party_name || '-' }}</text>
                    </view>

                    <view class="sale-chips">
                        <view class="sale-chip">{{ row.warehouse_name || '-' }}</view>
                        <view v-if="row.salesman_name" class="sale-chip">开单人 {{ row.salesman_name }}</view>
                        <view v-if="row.operator_name && row.operator_name !== row.salesman_name" class="sale-chip muted">操作人 {{ row.operator_name }}</view>
                        <view class="sale-chip status" :class="{ void: isVoidSale(row) }">{{ assetLabel(row.status) }}</view>
                        <view v-if="row.sale_channel" class="sale-chip primary">{{ row.sale_channel }}</view>
                    </view>

                    <view class="sale-time">{{ erpTimeLine(row, ['sale_at', 'sold_at', 'received_at']) }}</view>

                    <view class="erp-card__foot sale-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">售价</text>
                            <text class="amt-value blue">¥{{ money(row.sale_price) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">成本</text>
                            <text class="amt-value">¥{{ money(row.cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">毛利</text>
                            <text class="amt-value" :class="Number(row.profit)>=0?'green':'red'">¥{{ money(row.profit) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ isVoidSale(row) ? '作废应收' : '未收' }}</text>
                            <text class="amt-value" :class="isVoidSale(row) ? 'muted' : 'orange'">¥{{ money(row.receivable_amount) }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
            <view class="fab" @click="goCreate" ><u-icon name="plus" color="#fff" size="26"></u-icon></view>

        <ErpFilterPopup
            v-model:show="filterVisible"
            v-model="filters"
            title="筛选销售记录"
            :fields="filterFields"
            @confirm="applyFilter"
            @reset="resetFilter"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileSaleList } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'

const { pagingStyle } = useListHeader(126)


const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)

const tabs = [
    { label: '全部', value: '' },
    { label: '待收款', value: 'pending' },
    { label: '部分收款', value: 'partial' },
    { label: '已结清', value: 'settled' },
]
const activeTab = ref('')
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const filterFields = [
    { key: 'asset_no', label: '资产号', type: 'text', placeholder: '输入资产编号' },
    { key: 'imei', label: 'IMEI / 串号', type: 'text', placeholder: '输入 IMEI 或串号' },
    { key: 'model', label: '型号', type: 'text', placeholder: '输入机型型号' },
    { key: 'party_id', label: '客户', type: 'party', roleType: 'customer', labelKey: 'party_name', placeholder: '请选择客户' },
    { key: 'category_id', label: '设备分类', type: 'category', labelKey: 'category_name', placeholder: '请选择分类' },
    { key: 'warehouse_id', label: '仓库', type: 'warehouse', labelKey: 'warehouse_name', locationKey: 'location_id', locationLabelKey: 'location_name', placeholder: '请选择仓库/库位' },
    { key: 'sale_no', label: '销售单号', type: 'text', placeholder: '输入销售单号' },
    { key: 'sale_channel', label: '销售渠道', type: 'text', placeholder: '如同行/商城/门店' },
    { key: 'salesman_uid', label: '开单人', type: 'staff', labelKey: 'salesman_name', placeholder: '请选择开单人' },
    { key: 'operator_uid', label: '操作人', type: 'staff', labelKey: 'operator_name', placeholder: '请选择操作人' },
    { key: 'price', label: '销售金额', type: 'range', minKey: 'min_amount', maxKey: 'max_amount' },
    { key: 'profit', label: '毛利', type: 'range', minKey: 'min_profit', maxKey: 'max_profit' },
    { key: 'date', label: '销售日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[]
const filterCount = computed(() => Object.entries(filters.value).filter(([key, v]) => !key.endsWith('_name') && v !== '' && v !== undefined && v !== null).length)
const reload = () => pagingRef.value?.reload()
const handleSearch = () => reload()
const onTab = (val: string) => { activeTab.value = val; reload() }

onShow(() => reload())


const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileSaleList({
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
    url: `/addon/hsx_erp/pages/sale/detail?sale_order_id=${row.sale_order_id}&sale_no=${encodeURIComponent(row.sale_no || '')}`
})
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/sale/create' })
const financeLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s || '-')
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const assetLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const assetType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
const isVoidSale = (row: any) => row?.finance_status === 'void' || row?.order_status === 'void' || row?.status === 'void'
function voidSummary(row: any) {
    const text = row?.received_amount && Number(row.received_amount) > 0 ? '该销售记录已作废，请核对收款流水' : '该销售记录已作废，设备已退回库存'
    return `${text}${row?.sale_no ? ' · ' + row.sale_no : ''}`
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.sale-card {
    padding: 22rpx 26rpx;
    transition: background .2s ease;
}

.sale-card--void {
    background: #f8fafc;
    border: 2rpx solid #e2e8f0;
}

.sale-card--void .sale-title__model,
.sale-card--void .sale-line__value,
.sale-card--void .amt-value {
    color: #64748b;
}

.sale-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18rpx;
}

.tag-stack {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8rpx;
}

.void-banner {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin: 10rpx 0 8rpx;
    padding: 12rpx 16rpx;
    border-radius: 12rpx;
    background: #f1f5f9;
    color: #64748b;
    font-size: 22rpx;
    line-height: 1.45;
}

.void-banner text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sale-title {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8rpx;
}

.sale-title__model,
.sale-title__sub,
.sale-line__value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sale-title__model {
    display: block;
    line-height: 1.35;
}

.sale-title__sub {
    display: block;
    font-size: 24rpx;
    line-height: 1.35;
    color: #64748b;
}

.sale-line {
    display: flex;
    align-items: center;
    gap: 12rpx;
    min-width: 0;
    margin-top: 10rpx;
    font-size: 24rpx;
    line-height: 1.35;
}

.sale-line__label {
    flex-shrink: 0;
    color: #94a3b8;
}

.sale-line__value {
    flex: 1;
    min-width: 0;
    color: #475569;
}

.sale-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx;
    margin-top: 12rpx;
}

.sale-chip {
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

.sale-chip.primary {
    color: #2563eb;
    background: #eff6ff;
}

.sale-chip.status {
    color: #16a34a;
    background: #f0fdf4;
}

.sale-chip.status.void {
    color: #64748b;
    background: #f1f5f9;
}

.sale-chip.muted {
    color: #94a3b8;
}

.sale-time {
    margin-top: 10rpx;
    font-size: 22rpx;
    line-height: 1.35;
    color: #94a3b8;
}

.sale-card__foot {
    justify-content: space-between;
    gap: 10rpx;
    margin-top: 14rpx;
    padding-top: 14rpx;
}

.sale-card__foot .amount-box {
    flex: 1;
    min-width: 0;
}

.sale-card__foot .amt-label,
.sale-card__foot .amt-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sale-card__foot .amt-value {
    font-size: 27rpx;
}

.sale-card__foot .amt-value.muted {
    color: #64748b;
}
</style>
