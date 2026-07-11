<template>
    <view class="erp-page">
        <ErpPageHeader :title="pageTitle" />
        <view v-if="loading" class="loading-wrap"><u-loading-icon size="36" /></view>
        <scroll-view v-else scroll-y style="height:100%">
            <view class="detail-wrap">
                <view class="form-card sale-info-card" v-if="order">
                    <view class="sale-summary">
                        <view class="sale-summary__main">
                            <text class="sale-summary__label">销售单</text>
                            <text class="sale-summary__no">{{ order.sale_no || '-' }}</text>
                        </view>
                        <u-tag :text="financeLabel(order.finance_status)"
                            :type="financeType(order.finance_status)" plain plainFill size="mini" />
                    </view>

                    <view class="erp-card__foot sale-finance-foot">
                        <view class="amount-box">
                            <text class="amt-label">实际销售收入</text>
                            <text class="amt-value blue">¥{{ money(netSaleAmount(order)) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">总毛利</text>
                            <text class="amt-value" :class="Number(order.profit)>=0?'green':'red'">¥{{ money(order.profit) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已收</text>
                            <text class="amt-value green">¥{{ money(order.received_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">未收</text>
                            <text class="amt-value orange">¥{{ money(order.receivable_amount) }}</text>
                        </view>
                    </view>
                    <view v-if="compensationAmount(order) > 0" class="sale-adjust-note">
                        原成交 ¥{{ money(order.gross_total_amount ?? order.total_amount) }} · 售后补差 -¥{{ money(compensationAmount(order)) }} · 实际收入 ¥{{ money(netSaleAmount(order)) }}
                    </view>

                    <view class="field">
                        <text class="label">客户</text>
                        <text class="value">{{ order.party_name || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">销售渠道</text>
                        <text class="value">{{ order.sale_channel || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">业务来源</text>
                        <text class="value">{{ order.origin_name || 'ERP销售' }}{{ order.origin_plugin_name ? ' · ' + order.origin_plugin_name : '' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">原业务单号</text>
                        <text class="value">{{ order.origin_no || order.sale_no || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">开单人</text>
                        <text class="value">{{ order.salesman_name || '-' }}</text>
                    </view>
                    <view v-if="order.operator_name && order.operator_name !== order.salesman_name" class="field">
                        <text class="label">操作人</text>
                        <text class="value">{{ order.operator_name }}</text>
                    </view>
                    <view class="field">
                        <text class="label">创建时间</text>
                        <text class="value">{{ formatErpTime(order.create_at) }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">更新时间</text>
                        <text class="value">{{ formatErpTime(order.update_at) }}</text>
                    </view>
                    <view class="order-actions" v-if="canCancelSale">
                        <view class="cancel-warning">
                            {{ cancelSaleNotice }}
                        </view>
                        <view class="action-button">
                            <u-button
                                type="warning"
                                plain
                                size="small"
                                :loading="cancelling"
                                :text="cancelSaleText"
                                @click="cancelSale"
                            />
                        </view>
                    </view>
                </view>

                <view class="section-title">销售设备（{{ items.length }} 台）</view>
                <view v-if="!items.length" class="empty-card">暂无销售设备</view>
                <view v-for="item in items" :key="item.id" class="erp-card sale-device-card">
                    <view class="device-card__head">
                        <view class="device-title">
                            <text class="device-name">{{ item.model || '-' }}</text>
                            <text class="device-spec">{{ deviceIdentityLine(item) }}</text>
                        </view>
                        <u-tag :text="assetLabel(item.status)" :type="assetType(item.status)" plain plainFill size="mini" />
                    </view>
                    <view class="sale-chips">
                        <view v-if="item.warehouse_name" class="sale-chip">{{ item.warehouse_name }}{{ item.location_name ? ' / ' + item.location_name : '' }}</view>
                        <view v-if="item.category_name" class="sale-chip muted">{{ item.category_name }}</view>
                    </view>
                    <view class="card-time">{{ erpTimeLine(item, ['sale_at', 'sold_at']) }}</view>
                    <view v-if="compensationAmount(item) > 0" class="sale-adjust-note sale-adjust-note--device">
                        原成交 ¥{{ money(item.sale_price) }} · 售后补差 -¥{{ money(compensationAmount(item)) }} · 实际收入 ¥{{ money(netSaleAmount(item)) }}
                    </view>
                    <view class="erp-card__foot sale-device-foot">
                        <view class="amount-box">
                            <text class="amt-label">实际收入</text>
                            <text class="amt-value blue">¥{{ money(netSaleAmount(item)) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">成本</text>
                            <text class="amt-value">¥{{ money(item.cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">毛利</text>
                            <text class="amt-value" :class="Number(item.profit)>=0?'green':'red'">¥{{ money(item.profit) }}</text>
                        </view>
                    </view>
                    <view class="device-actions" v-if="item.status === 'sold'">
                        <view v-if="canCancelSale" class="action-button mini">
                            <u-button size="mini" plain type="warning" :text="saleItemCount > 1 ? '撤回此台' : '撤销此台'" @click.stop="cancelSaleItem(item)" />
                        </view>
                        <view v-else-if="!canCancelSale" class="action-button mini">
                            <u-button size="mini" plain type="warning" text="销售退货" @click.stop="goReturn(item)" />
                        </view>
                    </view>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { cancelMobileSale, cancelMobileSaleItem, getMobileSaleInfo } from '@/addon/hsx_erp/api/erp'
import { erpTimeLine, formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import { erpNetSaleAmount, erpSaleCompensationAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'

const order = ref<any>(null)
const items = ref<any[]>([])
const loading = ref(true)
const saleOrderId = ref(0)
const saleNo = ref('')
const detailLoaded = ref(false)
const cancelling = ref(false)

const pageTitle = computed(() => saleNo.value ? `销售单 ${saleNo.value}` : '销售单详情')
const saleItemCount = computed(() => items.value.length || 0)
const canCancelSale = computed(() =>
    order.value &&
    order.value.status === 'completed' &&
    order.value.finance_status === 'pending' &&
    Number(order.value.received_amount || 0) <= 0
)
const cancelSaleText = computed(() => saleItemCount.value > 1 ? `整单撤销（${saleItemCount.value} 台）` : '撤销销售，退回库存')
const cancelSaleNotice = computed(() => {
    if (saleItemCount.value > 1) {
        return `整单操作：确认后会同时撤销本单 ${saleItemCount.value} 台设备，应收作废，设备全部退回库存。`
    }
    return '确认后该设备应收作废，并退回原仓库库存。'
})

onLoad((query: any) => {
    saleOrderId.value = Number(query?.sale_order_id || 0)
    saleNo.value = decodeURIComponent(query?.sale_no || '')
    loadDetail()
})

onShow(() => {
    if (detailLoaded.value) loadDetail()
})

async function loadDetail() {
    if (!saleOrderId.value) return
    loading.value = true
    try {
        const res: any = await getMobileSaleInfo(saleOrderId.value)
        const data = res?.data || {}
        order.value = data
        items.value = data.items || []
    } finally {
        loading.value = false
        detailLoaded.value = true
    }
}

const goReturn = (item?: any) => uni.navigateTo({
    url: `/addon/hsx_erp/pages/sale_return/create?sale_order_id=${saleOrderId.value}&sale_no=${encodeURIComponent(saleNo.value)}&party_name=${encodeURIComponent(order.value?.party_name || '')}&asset_id=${Number(item?.asset_id || 0)}`
})

const cancelSale = () => {
    if (!canCancelSale.value || cancelling.value) return
    uni.showModal({
        title: saleItemCount.value > 1 ? '整单撤销销售' : '撤销销售',
        content: cancelSaleNotice.value,
        confirmText: '确认撤销',
        cancelText: '取消',
        success: async (res) => {
            if (!res.confirm) return
            cancelling.value = true
            try {
                await cancelMobileSale(saleOrderId.value, { remark: '手机端撤销未收款销售单' })
                uni.showToast({ title: '已退回库存', icon: 'success' })
                await loadDetail()
            } catch (e: any) {
                uni.showToast({ title: e?.message || '撤销失败', icon: 'none' })
            } finally {
                cancelling.value = false
            }
        }
    })
}

const cancelSaleItem = (item: any) => {
    if (!canCancelSale.value || cancelling.value) return
    if (saleItemCount.value <= 1) {
        cancelSale()
        return
    }
    const model = item?.model || '该设备'
    uni.showModal({
        title: '撤回单台设备',
        content: `确认只撤回「${model}」吗？该设备会退回库存，本销售单金额和应收会同步扣减，其他设备保持已售。`,
        confirmText: '确认撤回',
        cancelText: '取消',
        success: async (res) => {
            if (!res.confirm) return
            cancelling.value = true
            try {
                await cancelMobileSaleItem(Number(item.id || 0), { remark: '手机端单台撤销销售' })
                uni.showToast({ title: '已撤回该设备', icon: 'success' })
                await loadDetail()
            } catch (e: any) {
                uni.showToast({ title: e?.message || '撤回失败', icon: 'none' })
            } finally {
                cancelling.value = false
            }
        }
    })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const netSaleAmount = (row: any) => erpNetSaleAmount(row)
const compensationAmount = (row: any) => erpSaleCompensationAmount(row)
const deviceIdentityLine = (row: any) => erpDeviceIdentityLine(row)
const financeLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s)
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const assetLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const assetType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.detail-wrap { padding: 16rpx 0 120rpx; }
.sale-info-card { padding-top: 24rpx; }
.sale-summary { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; margin-bottom: 18rpx; }
.sale-summary__main { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.sale-summary__label { font-size:22rpx; color:#94a3b8; }
.sale-summary__no { font-size:30rpx; font-weight:700; color:#0f172a; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sale-finance-foot { justify-content:space-between; gap:10rpx; margin:0 0 10rpx; padding:18rpx 0; border-top:0; border-bottom:2rpx solid #f3f4f6; }
.sale-finance-foot .amount-box,
.sale-device-foot .amount-box { flex:1; min-width:0; }
.sale-finance-foot .amt-value,
.sale-device-foot .amt-value { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sale-adjust-note { margin:0 0 14rpx; padding:12rpx 15rpx; border-radius:12rpx; background:#fff7ed; color:#c2410c; font-size:21rpx; line-height:1.45; }
.sale-adjust-note--device { margin:12rpx 0 0; }
.form-card .value { word-break: break-all; }
.order-actions { margin-top:18rpx; padding:18rpx 0 22rpx; border-top:2rpx solid #f3f4f6; }
.cancel-warning { margin-bottom:14rpx; padding:14rpx 16rpx; border-radius:14rpx; background:#fff7ed; color:#c2410c; font-size:24rpx; line-height:1.45; }
.action-button { width:100%; }
.action-button.mini { width:132rpx; }
.section-title { font-size:28rpx; font-weight:600; color:#374151; padding:8rpx 28rpx 16rpx; }
.empty-card { background:#fff; border-radius:28rpx; padding:40rpx 20rpx; margin:0 24rpx 20rpx; text-align:center; color:#94a3b8; font-size:26rpx; }
.sale-device-card { margin-bottom:20rpx; }
.device-card__head { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; margin-bottom:10rpx; }
.device-title { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.device-name { font-size:30rpx; font-weight:700; color:#0f172a; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.device-spec { font-size:24rpx; color:#64748b; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sale-chips { display:flex; flex-wrap:wrap; gap:8rpx; margin-top:10rpx; }
.sale-chip { max-width:100%; height:38rpx; padding:0 12rpx; border-radius:19rpx; background:#f8fafc; color:#64748b; font-size:21rpx; line-height:38rpx; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; box-sizing:border-box; }
.sale-chip.muted { color:#94a3b8; }
.sale-device-foot { justify-content:space-between; gap:10rpx; margin-top:14rpx; padding-top:14rpx; }
.device-actions { display:flex; justify-content:flex-end; gap:12rpx; margin-top:16rpx; padding-top:14rpx; border-top:2rpx solid #f3f4f6; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
</style>
