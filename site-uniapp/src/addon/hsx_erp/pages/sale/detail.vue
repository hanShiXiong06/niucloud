<template>
    <view class="erp-page">
        <RecyclePageHeader :title="pageTitle" />
        <view v-if="loading" class="loading-wrap"><u-loading-icon size="36" /></view>
        <scroll-view v-else scroll-y style="height:100%">
            <view class="detail-wrap">
                <view class="info-card" v-if="order">
                    <view class="info-row">
                        <text class="info-label">销售单号</text>
                        <text class="info-value">{{ order.sale_no }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">客户</text>
                        <text class="info-value">{{ order.party_name }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">销售员</text>
                        <text class="info-value">{{ order.salesman_name || '-' }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">收款状态</text>
                        <u-tag :text="financeLabel(order.finance_status)"
                            :type="financeType(order.finance_status)" plain plainFill size="mini" />
                    </view>
                    <view class="info-row">
                        <text class="info-label">销售总额</text>
                        <text class="info-value blue">¥{{ money(order.total_amount) }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">总毛利</text>
                        <text class="info-value green">¥{{ money(order.profit) }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">已收</text>
                        <text class="info-value green">¥{{ money(order.received_amount) }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">未收</text>
                        <text class="info-value orange">¥{{ money(order.receivable_amount) }}</text>
                    </view>
                </view>

                <view class="section-title">销售设备（{{ items.length }} 台）</view>
                <view v-for="item in items" :key="item.id" class="device-card">
                    <view class="device-card__head">
                        <text class="device-name">{{ item.model || '-' }}</text>
                        <u-tag :text="item.status === 'sold' ? '已售' : item.status" type="primary" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">{{ item.spec || '-' }} · IMEI {{ item.imei || '-' }}</view>
                    <view class="device-costs">
                        <view class="cost-item">
                            <text class="cost-label">售价</text>
                            <text class="cost-value blue">¥{{ money(item.sale_price) }}</text>
                        </view>
                        <view class="cost-item">
                            <text class="cost-label">成本</text>
                            <text class="cost-value">¥{{ money(item.cost) }}</text>
                        </view>
                        <view class="cost-item">
                            <text class="cost-label">毛利</text>
                            <text class="cost-value" :class="Number(item.profit)>=0?'green':'red'">¥{{ money(item.profit) }}</text>
                        </view>
                    </view>
                    <view class="device-actions" v-if="item.status === 'sold'">
                        <u-button size="mini" plain type="warning" @click.stop="goReturn()">退货</u-button>
                    </view>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getMobileSaleInfo } from '@/addon/hsx_erp/api/erp'

const order = ref<any>(null)
const items = ref<any[]>([])
const loading = ref(true)
const saleOrderId = ref(0)
const saleNo = ref('')

const pageTitle = computed(() => saleNo.value ? `销售单 ${saleNo.value}` : '销售单详情')

onLoad((query: any) => {
    saleOrderId.value = Number(query?.sale_order_id || 0)
    saleNo.value = decodeURIComponent(query?.sale_no || '')
    loadDetail()
})

async function loadDetail() {
    if (!saleOrderId.value) return
    loading.value = true
    try {
        const res: any = await getMobileSaleInfo(saleOrderId.value)
        const data = res?.data || {}
        order.value = data
        items.value = data.items || []
    } finally { loading.value = false }
}

const goReturn = () => uni.navigateTo({ url: `/addon/hsx_erp/pages/sale_return/list?sale_order_id=${saleOrderId.value}` })
const money = (v: any) => Number(v || 0).toFixed(2)
const financeLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s)
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.detail-wrap { padding: 24rpx; }
.info-card { background:#fff; border-radius:16rpx; padding:24rpx; margin-bottom:24rpx; box-shadow:0 2rpx 12rpx rgba(0,0,0,.05); }
.info-row { display:flex; align-items:center; justify-content:space-between; padding:12rpx 0; border-bottom:1rpx solid #f1f5f9; }
.info-row:last-child { border-bottom:none; }
.info-label { font-size:26rpx; color:#64748b; }
.info-value { font-size:26rpx; color:#0f172a; }
.info-value.blue { color:#2563eb; }
.info-value.green { color:#16a34a; }
.info-value.orange { color:#ea580c; }
.section-title { font-size:28rpx; font-weight:600; color:#374151; margin:8rpx 0 16rpx; }
.device-card { background:#fff; border-radius:12rpx; padding:20rpx; margin-bottom:16rpx; }
.device-card__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8rpx; }
.device-name { font-size:28rpx; font-weight:600; color:#0f172a; }
.device-costs { display:flex; gap:20rpx; margin-top:12rpx; flex-wrap:wrap; }
.cost-item { display:flex; flex-direction:column; gap:4rpx; }
.cost-label { font-size:22rpx; color:#94a3b8; }
.cost-value { font-size:26rpx; color:#0f172a; }
.cost-value.blue { color:#2563eb; }
.cost-value.green { color:#16a34a; }
.cost-value.red { color:#dc2626; }
.device-actions { display:flex; gap:12rpx; margin-top:16rpx; padding-top:12rpx; border-top:1rpx solid #f1f5f9; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
</style>
