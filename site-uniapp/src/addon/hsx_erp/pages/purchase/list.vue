<template>
    <view class="erp-page">
        <RecyclePageHeader title="采购管理" />
        <view class="page-search">
            <u-search v-model="keyword" placeholder="型号/IMEI/采购单号/供应商" :showAction="false" bgColor="#f1f5f9" height="34" @search="reload" @clear="reload" />
            <u-tabs :list="tabs" :current="tabIndex" lineColor="#3b6ef5"
                :activeStyle="{color:'#0f172a',fontWeight:'600'}" :inactiveStyle="{color:'#64748b'}"
                lineWidth="40" @click="onTab" />
        </view>
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无采购记录" /></template>
            <view class="list-wrap">
                <!-- 新建按钮 -->
                <view class="action-top">
                    <u-button type="primary" size="small" @click="goCreate">+ 采购开单</u-button>
                </view>
                <view v-for="row in list" :key="row.id" class="erp-card" @click="goDetail(row)">
                    <!-- 第一行：型号 + 付款状态 -->
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.model || '-' }}</text>
                        <u-tag :text="financeLabel(row.finance_status)"
                            :type="financeType(row.finance_status)" plain plainFill size="mini" />
                    </view>
                    <!-- 第二行：规格 + IMEI -->
                    <view class="card-meta">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</view>
                    <!-- 第三行：采购单号 + 供应商 -->
                    <view class="card-meta">采购单：{{ row.purchase_no || '-' }} · {{ row.party_name || '-' }}</view>
                    <!-- 第四行：仓库 + 采购员 -->
                    <view class="card-meta">
                        {{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}
                        <text v-if="row.purchaser_name"> · {{ row.purchaser_name }}</text>
                    </view>
                    <!-- 资产状态 -->
                    <view class="status-row">
                        <u-tag :text="assetLabel(row.status)" :type="assetType(row.status)" plain plainFill size="mini" />
                        <u-tag v-if="row.order_status === 'void'" text="采购单已撤" type="info" plain plainFill size="mini" />
                    </view>
                    <!-- 金额行 -->
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">采购成本</text>
                            <text class="amt-value">¥{{ money(row.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已付</text>
                            <text class="amt-value green">¥{{ money(row.paid_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">未付</text>
                            <text class="amt-value orange">¥{{ money(row.payable_amount) }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getMobilePurchaseList } from '@/addon/hsx_erp/api/erp'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const { pagingStyle } = useListHeader(96)

const tabs = [
    { name: '全部', value: '' },
    { name: '待付款', value: 'pending' },
    { name: '部分付款', value: 'partial' },
    { name: '已结清', value: 'settled' },
]
const tabIndex = ref(0)
const curStatus = computed(() => tabs[tabIndex.value].value)
const reload = () => pagingRef.value?.reload()
const onTab = (item: any) => { if (tabIndex.value === item.index) return; tabIndex.value = item.index; reload() }

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobilePurchaseList({
            keyword: keyword.value, finance_status: curStatus.value,
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
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
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
</style>
