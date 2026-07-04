<template>
    <view class="erp-page">
        <RecyclePageHeader :title="pageTitle" />
        <view v-if="loading" class="loading-wrap">
            <u-loading-icon size="36" />
        </view>
        <scroll-view v-else scroll-y style="height:100%">
            <view class="detail-wrap">
                <!-- 采购单头部信息 -->
                <view class="info-card" v-if="order">
                    <view class="info-row">
                        <text class="info-label">采购单号</text>
                        <text class="info-value">{{ order.purchase_no }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">供应商</text>
                        <text class="info-value">{{ order.party_name }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">采购员</text>
                        <text class="info-value">{{ order.purchaser_name || '-' }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">付款状态</text>
                        <u-tag :text="financeLabel(order.finance_status)"
                            :type="financeType(order.finance_status)" plain plainFill size="mini" />
                    </view>
                    <view class="info-row">
                        <text class="info-label">总成本</text>
                        <text class="info-value font-bold">¥{{ money(order.total_cost) }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">已付</text>
                        <text class="info-value green">¥{{ money(order.paid_amount) }}</text>
                    </view>
                    <view class="info-row">
                        <text class="info-label">未付</text>
                        <text class="info-value orange">¥{{ money(order.payable_amount) }}</text>
                    </view>
                </view>

                <!-- 设备列表 -->
                <view class="section-title">设备明细（{{ items.length }} 台）</view>
                <view v-for="item in items" :key="item.id" class="device-card">
                    <view class="device-card__head">
                        <text class="device-name">{{ item.model || '-' }}</text>
                        <u-tag :text="assetLabel(item.status)" :type="assetType(item.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">{{ item.spec || '-' }} · IMEI {{ item.imei || '-' }}</view>
                    <view class="card-meta" v-if="item.warehouse_name">
                        仓库：{{ item.warehouse_name }}{{ item.location_name ? ' / '+item.location_name : '' }}
                    </view>
                    <view class="device-costs">
                        <view class="cost-item">
                            <text class="cost-label">采购成本</text>
                            <text class="cost-value">¥{{ money(item.purchase_cost) }}</text>
                        </view>
                        <view class="cost-item" v-if="Number(item.adjust_cost)">
                            <text class="cost-label">成本调整</text>
                            <text class="cost-value orange">{{ Number(item.adjust_cost)>0?'+':'' }}¥{{ money(item.adjust_cost) }}</text>
                        </view>
                        <view class="cost-item">
                            <text class="cost-label">当前总成本</text>
                            <text class="cost-value font-bold">¥{{ money(item.total_cost) }}</text>
                        </view>
                    </view>
                    <!-- 行内操作 -->
                    <view class="device-actions" v-if="item.status === 'in_stock'">
                        <u-button size="mini" plain @click.stop="goAdjustCost(item)">调成本</u-button>
                        <u-button size="mini" plain type="warning" @click.stop="goReturn(item)">退货</u-button>
                    </view>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getMobilePurchaseInfo } from '@/addon/hsx_erp/api/erp'

const order = ref<any>(null)
const items = ref<any[]>([])
const loading = ref(true)
const purchaseOrderId = ref(0)
const purchaseNo = ref('')

const pageTitle = computed(() => purchaseNo.value ? `采购单 ${purchaseNo.value}` : '采购单详情')

onLoad((query: any) => {
    purchaseOrderId.value = Number(query?.purchase_order_id || 0)
    purchaseNo.value = decodeURIComponent(query?.purchase_no || '')
    loadDetail()
})

async function loadDetail() {
    if (!purchaseOrderId.value) return
    loading.value = true
    try {
        const res: any = await getMobilePurchaseInfo(purchaseOrderId.value)
        const data = res?.data || {}
        order.value = data
        items.value = data.items || []
    } finally { loading.value = false }
}

const goAdjustCost = (item: any) => {
    const q = `id=${item.asset_id}&model=${encodeURIComponent(item.model||'')}&asset_no=${encodeURIComponent(item.asset_no||'')}&current_cost=${item.total_cost}`
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?${q}` })
}
const goReturn = (item: any) => {
    uni.navigateTo({
        url: `/addon/hsx_erp/pages/purchase_return/create?purchase_order_id=${purchaseOrderId.value}&purchase_no=${encodeURIComponent(purchaseNo.value)}&party_name=${encodeURIComponent(asset.value?.party_name || '')}`
    })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const financeLabel = (s: string) => ({ pending: '待付款', partial: '部分付款', settled: '已结清', void: '已作废' }[s] || s)
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const assetLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s)
const assetType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.detail-wrap { padding: 24rpx; }
.info-card { background:#fff; border-radius:16rpx; padding:24rpx; margin-bottom:24rpx; box-shadow:0 2rpx 12rpx rgba(0,0,0,.05); }
.info-row { display:flex; align-items:center; justify-content:space-between; padding:12rpx 0; border-bottom:1rpx solid #f1f5f9; }
.info-row:last-child { border-bottom:none; }
.info-label { font-size:26rpx; color:#64748b; }
.info-value { font-size:26rpx; color:#0f172a; }
.info-value.font-bold { font-weight:600; }
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
.cost-value.orange { color:#ea580c; }
.cost-value.font-bold { font-weight:600; }
.device-actions { display:flex; gap:12rpx; margin-top:16rpx; padding-top:12rpx; border-top:1rpx solid #f1f5f9; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
</style>
