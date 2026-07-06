<template>
    <view class="erp-page">
        <RecyclePageHeader title="设备档案" />
        <view v-if="loading" class="loading-wrap"><u-loading-icon size="36" /></view>
        <scroll-view v-else-if="asset" scroll-y style="height:100%">
            <view class="detail-wrap">
                <!-- 基本信息 -->
                <view class="info-card">
                    <view class="device-head">
                        <text class="device-main">{{ asset.model || '-' }}</text>
                        <u-tag :text="statusLabel(asset.status)" :type="statusType(asset.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta" style="margin-top:8rpx">{{ asset.spec || '-' }} · IMEI {{ asset.imei || '-' }}</view>
                    <view class="card-meta">资产号：{{ asset.asset_no || '-' }}</view>
                    <view class="card-meta">仓库：{{ asset.warehouse_name || '-' }}{{ asset.location_name ? ' / '+asset.location_name : '' }}</view>
                </view>

                <!-- 成本拆解 -->
                <view class="cost-section">
                    <view class="cost-row">
                        <text class="cost-key">采购成本</text>
                        <text class="cost-val">¥{{ money(asset.purchase_cost) }}</text>
                    </view>
                    <view class="cost-row" v-if="Number(asset.adjust_cost)">
                        <text class="cost-key">成本调整</text>
                        <text class="cost-val orange">{{ Number(asset.adjust_cost)>0?'+':'' }}¥{{ money(asset.adjust_cost) }}</text>
                    </view>
                    <view class="cost-row" v-if="Number(asset.refurbish_cost)">
                        <text class="cost-key">整备成本</text>
                        <text class="cost-val">¥{{ money(asset.refurbish_cost) }}</text>
                    </view>
                    <view class="cost-row total">
                        <text class="cost-key">当前总成本</text>
                        <text class="cost-val bold">¥{{ money(asset.total_cost) }}</text>
                    </view>
                </view>

                <!-- 采购信息 -->
                <view class="info-card" v-if="asset.purchase_order">
                    <view class="section-label">采购信息</view>
                    <view class="info-row"><text class="info-label">供应商</text><text class="info-value">{{ asset.party_name }}</text></view>
                    <view class="info-row"><text class="info-label">采购单号</text><text class="info-value">{{ asset.purchase_order.purchase_no }}</text></view>
                    <view class="info-row"><text class="info-label">付款状态</text>
                        <u-tag :text="financeLabel(asset.purchase_order.finance_status)" :type="financeType(asset.purchase_order.finance_status)" plain plainFill size="mini" />
                    </view>
                </view>

                <!-- 销售信息 -->
                <view class="info-card" v-if="asset.sale_order">
                    <view class="section-label">销售信息</view>
                    <view class="info-row"><text class="info-label">客户</text><text class="info-value">{{ asset.sale_order.party_name }}</text></view>
                    <view class="info-row"><text class="info-label">销售单号</text><text class="info-value">{{ asset.sale_order.sale_no }}</text></view>
                    <view class="info-row"><text class="info-label">售价</text><text class="info-value blue">¥{{ money(asset.sale_price) }}</text></view>
                    <view class="info-row"><text class="info-label">毛利</text>
                        <text class="info-value" :class="Number(asset.profit)>=0?'green':'red'">¥{{ money(asset.profit) }}</text>
                    </view>
                </view>

                <!-- 库存流水 -->
                <view class="section-title">库存流水</view>
                <view class="flow-card" v-for="(flow, idx) in ledger" :key="idx">
                    <view class="flow-head">
                        <text class="flow-action">{{ actionLabel(flow.action) }}</text>
                        <text class="flow-time">{{ formatDate(flow.occurred_at || flow.create_at) }}</text>
                    </view>
                    <view class="card-meta" v-if="flow.source_no">单据：{{ flow.source_no }}</view>
                    <view class="card-meta" v-if="flow.before_status && flow.after_status">
                        {{ statusLabel(flow.before_status) }} → {{ statusLabel(flow.after_status) }}
                    </view>
                    <view class="card-meta" v-if="flow.cost_delta && Number(flow.cost_delta)">
                        成本变化：{{ Number(flow.cost_delta)>0?'+':'' }}¥{{ money(flow.cost_delta) }}
                    </view>
                    <view class="card-meta" v-if="flow.remark">{{ flow.remark }}</view>
                </view>
                <view class="empty-tip" v-if="!ledger.length">暂无流水记录</view>

                <!-- 底部操作 -->
                <view class="bottom-actions">
                    <u-button v-if="asset.status === 'in_stock'" type="primary" @click="goAdjust">调整成本</u-button>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getMobileStockInfo } from '@/addon/hsx_erp/api/erp'

const asset = ref<any>(null)
const ledger = ref<any[]>([])
const loading = ref(true)
const assetId = ref(0)
const detailLoaded = ref(false)

onLoad((query: any) => {
    assetId.value = Number(query?.id || 0)
    loadDetail()
})

onShow(() => {
    if (detailLoaded.value) loadDetail()
})

async function loadDetail() {
    if (!assetId.value) return
    loading.value = true
    try {
        const res: any = await getMobileStockInfo(assetId.value)
        const data = res?.data || {}
        asset.value = data
        ledger.value = data.ledger || data.asset_ledger || []
    } finally {
        loading.value = false
        detailLoaded.value = true
    }
}

const goAdjust = () => {
    if (!asset.value) return
    const a = asset.value
    const q = `id=${a.id}&model=${encodeURIComponent(a.model || '')}&asset_no=${encodeURIComponent(a.asset_no || '')}&imei=${encodeURIComponent(a.imei || '')}&status=${encodeURIComponent(a.status || '')}&cost=${a.total_cost || 0}&wh=${encodeURIComponent(a.warehouse_name || '')}&loc=${encodeURIComponent(a.location_name || '')}`
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?${q}` })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const formatDate = (ts: number) => ts ? new Date(ts * 1000).toLocaleDateString('zh-CN') : '-'
const statusLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const statusType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
const financeLabel = (s: string) => ({ pending: '待付款', partial: '部分付款', settled: '已结清', void: '已作废' }[s] || s || '-')
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const actionLabel = (a: string) => ({
    inbound: '采购入库', sold: '销售出库', purchase_return: '采购退货',
    sale_return: '销售退货', cost_adjust: '成本调整', purchase_cancel: '采购撤销',
    sale_cancel: '销售撤销', refurbish: '整备', flow_set: '流转设置'
}[a] || a || '-')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.detail-wrap { padding: 24rpx 24rpx 120rpx; }
.info-card { background:#fff; border-radius:16rpx; padding:24rpx; margin-bottom:20rpx; box-shadow:0 2rpx 12rpx rgba(0,0,0,.05); }
.device-head { display:flex; align-items:center; justify-content:space-between; }
.device-main { font-size:32rpx; font-weight:700; color:#0f172a; }
.cost-section { background:#fff; border-radius:16rpx; padding:20rpx 24rpx; margin-bottom:20rpx; }
.cost-row { display:flex; justify-content:space-between; padding:10rpx 0; border-bottom:1rpx solid #f8fafc; }
.cost-row.total { border-bottom:none; border-top:2rpx solid #e2e8f0; margin-top:4rpx; padding-top:14rpx; }
.cost-key { font-size:26rpx; color:#64748b; }
.cost-val { font-size:26rpx; color:#0f172a; }
.cost-val.orange { color:#ea580c; }
.cost-val.bold { font-weight:700; font-size:28rpx; }
.section-label { font-size:24rpx; color:#94a3b8; margin-bottom:12rpx; }
.info-row { display:flex; align-items:center; justify-content:space-between; padding:10rpx 0; border-bottom:1rpx solid #f1f5f9; }
.info-row:last-child { border-bottom:none; }
.info-label { font-size:26rpx; color:#64748b; }
.info-value { font-size:26rpx; color:#0f172a; }
.info-value.blue { color:#2563eb; }
.info-value.green { color:#16a34a; }
.info-value.red { color:#dc2626; }
.section-title { font-size:28rpx; font-weight:600; color:#374151; margin:16rpx 0 12rpx; }
.flow-card { background:#fff; border-radius:12rpx; padding:16rpx 20rpx; margin-bottom:12rpx; }
.flow-head { display:flex; justify-content:space-between; margin-bottom:6rpx; }
.flow-action { font-size:26rpx; font-weight:600; color:#0f172a; }
.flow-time { font-size:22rpx; color:#94a3b8; }
.empty-tip { text-align:center; color:#94a3b8; font-size:26rpx; padding:40rpx 0; }
.bottom-actions { margin-top:32rpx; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
</style>
