<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="型号/IMEI/资产号/仓库"
            :tabs="tabs"
            @search="handleSearch"
            @tab-change="onTab"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
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
                    <view class="card-meta">
                        {{ timeLabel(row) }}：{{ formatTime(primaryTime(row)) }}
                        <text v-if="Number(row.update_at || 0)"> · 更新 {{ formatTime(row.update_at) }}</text>
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
                            <text class="amt-label">{{ priceLabel(row) }}</text>
                            <text class="amt-value blue">{{ displayPrice(row) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ row.status === 'sold' ? '毛利' : '库龄' }}</text>
                            <text class="amt-value" :class="row.status === 'sold' ? profitClass(row.profit) : ageClass(row.stock_in_at || row.create_at, row.status)">
                                {{ row.status === 'sold' ? profitText(row.profit) : ageText(row) }}
                            </text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileStockList } from '@/addon/hsx_erp/api/erp'
import { dictLabel, dictTabs, dictType, ERP_DICT_FALLBACK, loadErpDicts, type ErpDictMap } from '@/addon/hsx_erp/api/dict'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'



const { pagingStyle } = useListHeader(126)



const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const erpDicts = ref<ErpDictMap>(ERP_DICT_FALLBACK)

const tabs = computed(() => dictTabs(erpDicts.value, 'asset_status', true))
const activeTab = ref('')
const reload = () => pagingRef.value?.reload()
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
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

const money = (v: any) => Number(v || 0).toFixed(2)
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
const priceLabel = (row: any) => row.status === 'sold' ? '售价' : '标价'
const displayPrice = (row: any) => {
    const price = row.status === 'sold' ? row.sale_price : (row.retail_price || row.estimate_sale_price)
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
</style>
