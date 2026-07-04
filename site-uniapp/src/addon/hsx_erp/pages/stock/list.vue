<template>
    <view class="erp-page">
        <RecyclePageHeader title="库存设备" />
        <view class="page-search">
            <u-search v-model="keyword" placeholder="型号/IMEI/资产号/仓库" :showAction="false" bgColor="#f1f5f9" height="34" @search="reload" @clear="reload" />
            <u-tabs :list="tabs" :current="tabIndex" lineColor="#3b6ef5"
                :activeStyle="{color:'#0f172a',fontWeight:'600'}" :inactiveStyle="{color:'#64748b'}"
                lineWidth="40" @click="onTab" />
        </view>
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无库存设备" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card" @click="goDetail(row)">
                    <!-- 型号 + 库存状态 -->
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.model || '-' }}</text>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <!-- 规格 + IMEI -->
                    <view class="card-meta">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</view>
                    <!-- 资产号 -->
                    <view class="card-meta">资产号：{{ row.asset_no || '-' }}</view>
                    <!-- 仓库 + 来源 -->
                    <view class="card-meta">
                        {{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}
                        <text v-if="row.party_name"> · {{ row.party_name }}</text>
                    </view>
                    <!-- 状态标签行：整备 + 去向 + 上架 -->
                    <view class="status-row">
                        <u-tag v-if="row.refurbish_status && row.refurbish_status !== 'none'"
                            :text="refurbishLabel(row.refurbish_status)"
                            :type="refurbishType(row.refurbish_status)" plain plainFill size="mini" />
                        <u-tag v-if="row.sale_target && row.sale_target !== 'unset'"
                            :text="targetLabel(row.sale_target)" type="primary" plain plainFill size="mini" />
                        <u-tag v-if="row.listing_status && row.listing_status !== 'none'"
                            :text="listingLabel(row.listing_status)"
                            :type="listingType(row.listing_status)" plain plainFill size="mini" />
                    </view>
                    <!-- 金额行 -->
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">总成本</text>
                            <text class="amt-value">¥{{ money(row.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">零售价</text>
                            <text class="amt-value blue">{{ Number(row.retail_price) > 0 ? '¥'+money(row.retail_price) : '-' }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">库龄</text>
                            <text class="amt-value" :class="ageClass(row.stock_in_at, row.status)">
                                {{ row.stock_in_at ? ageDays(row.stock_in_at)+'天' : '-' }}
                            </text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getMobileStockList } from '@/addon/hsx_erp/api/erp'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const { pagingStyle } = useListHeader(96)

const tabs = [
    { name: '在库', value: 'in_stock' },
    { name: '已售', value: 'sold' },
    { name: '已退', value: 'returned' },
    { name: '全部', value: '' },
]
const tabIndex = ref(0)
const curStatus = computed(() => tabs[tabIndex.value].value)
const reload = () => pagingRef.value?.reload()
const onTab = (item: any) => { if (tabIndex.value === item.index) return; tabIndex.value = item.index; reload() }

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileStockList({
            keyword: keyword.value, status: curStatus.value,
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

const money = (v: any) => Number(v || 0).toFixed(2)

const goDetail = (row: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/stock/detail?id=${row.id}`
})
const ageDays = (ts: number) => ts ? Math.floor((Date.now() / 1000 - ts) / 86400) : 0
const ageClass = (ts: number, status: string) => {
    if (status !== 'in_stock') return ''
    const d = ageDays(ts)
    return d >= 90 ? 'red' : d >= 30 ? 'orange' : ''
}

const statusLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const statusType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
const refurbishLabel = (s: string) => ({ none: '无需整备', pending: '待整备', processing: '整备中', done: '整备完成' }[s] || s)
const refurbishType = (s: string) => ({ none: 'info', pending: 'warning', processing: 'primary', done: 'success' }[s] || 'info')
const targetLabel = (s: string) => ({ unset: '去向未定', peer: '卖同行', mall: '上商城' }[s] || s)
const listingLabel = (s: string) => ({ none: '无需上架', need_photo: '待拍照', need_price: '待定价', ready: '可上架', listed: '已上架' }[s] || s)
const listingType = (s: string) => ({ none: 'info', need_photo: 'warning', need_price: 'warning', ready: 'primary', listed: 'success' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
</style>
