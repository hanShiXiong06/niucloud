<template>
    <view class="erp-page">
        <RecyclePageHeader title="销售出库" />
        <view class="page-search">
            <u-search v-model="keyword" placeholder="型号/IMEI/销售单号/客户" :showAction="false" bgColor="#f1f5f9" height="34" @search="reload" @clear="reload" />
            <u-tabs :list="tabs" :current="tabIndex" lineColor="#3b6ef5"
                :activeStyle="{color:'#0f172a',fontWeight:'600'}" :inactiveStyle="{color:'#64748b'}"
                lineWidth="40" @click="onTab" />
        </view>
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无销售记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card">
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
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getMobileSaleList } from '@/addon/hsx_erp/api/erp'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const { pagingStyle } = useListHeader(96)

const tabs = [
    { name: '全部', value: '' },
    { name: '待收款', value: 'pending' },
    { name: '部分收款', value: 'partial' },
    { name: '已结清', value: 'settled' },
]
const tabIndex = ref(0)
const curStatus = computed(() => tabs[tabIndex.value].value)
const reload = () => pagingRef.value?.reload()
const onTab = (item: any) => { if (tabIndex.value === item.index) return; tabIndex.value = item.index; reload() }

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileSaleList({
            keyword: keyword.value, finance_status: curStatus.value,
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const financeLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s || '-')
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const assetLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const assetType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
</style>
