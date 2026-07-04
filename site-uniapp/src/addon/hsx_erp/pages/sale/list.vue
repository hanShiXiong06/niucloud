<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="型号/IMEI/销售单号/客户"
            :tabs="tabs"
            @search="handleSearch"
            @tab-change="onTab"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15">
            <template #empty><u-empty mode="list" text="暂无销售记录" /></template>
            <view class="list-wrap">

                <view v-for="row in list" :key="row.id" class="erp-card" @click="goDetail(row)">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.model || '-' }}</text>
                        <u-tag :text="financeLabel(row.finance_status)"
                            :type="financeType(row.finance_status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</view>
                    <view class="card-meta">销售单：{{ row.sale_no || '-' }} · {{ row.party_name || '-' }}</view>
                    <view class="card-meta">
                        {{ row.warehouse_name || '-' }}
                        <text v-if="row.salesman_name"> · {{ row.salesman_name }}</text>
                    </view>
                    <view class="status-row">
                        <u-tag :text="assetLabel(row.status)" :type="assetType(row.status)" plain plainFill size="mini" />
                        <u-tag v-if="row.sale_channel" :text="row.sale_channel" type="primary" plain plainFill size="mini" />
                    </view>
                    <view class="erp-card__foot">
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
                            <text class="amt-label">未收</text>
                            <text class="amt-value orange">¥{{ money(row.receivable_amount) }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
            <view class="fab" @click="goCreate" ><u-icon name="plus" color="#fff" size="26"></u-icon></view>

    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileSaleList } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'

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
const reload = () => pagingRef.value?.reload()
const handleSearch = () => reload()
const onTab = (val: string) => { activeTab.value = val; reload() }

onShow(() => reload())


const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileSaleList({
            keyword: keyword.value, finance_status: activeTab.value,
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
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
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
</style>
