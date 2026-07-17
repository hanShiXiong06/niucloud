<template>
    <view class="erp-page">
        <ErpPageHeader :title="pageTitle" />
        <view v-if="loading" class="loading-wrap">
            <u-loading-icon size="36" />
        </view>
        <view v-else-if="loadError" class="error-wrap">
            <u-empty mode="data" :text="loadError" />
            <u-button type="primary" size="small" :customStyle="{ marginTop:'24rpx' }" @click="loadDetail">重新加载</u-button>
        </view>
        <scroll-view v-else scroll-y style="height:100%">
            <view class="detail-wrap">
                <!-- 采购单头部信息 -->
                <view class="form-card" v-if="order">
                    <view class="form-card-title">采购信息</view>
                    <view class="field">
                        <text class="label">采购单号</text>
                        <text class="value">{{ order.purchase_no }}</text>
                    </view>
                    <view class="field">
                        <text class="label">供货方</text>
                        <text class="value">{{ erpPartyDisplayName(order) }}</text>
                    </view>
                    <view v-if="order.m_no" class="field">
                        <text class="label">M号</text>
                        <text class="value">{{ order.m_no }}</text>
                    </view>
                    <view class="field">
                        <text class="label">业务来源</text>
                        <text class="value">{{ order.origin_name || 'ERP采购' }}{{ order.origin_plugin_name ? ' · ' + order.origin_plugin_name : '' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">原业务单号</text>
                        <text class="value">{{ order.origin_no || order.purchase_no || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">采购员</text>
                        <text class="value">{{ order.purchaser_name || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">订单状态</text>
                        <text class="value">{{ order.business_status_label || orderStatusLabel(order.status) }}</text>
                    </view>
                    <view class="field">
                        <text class="label">采购款状态</text>
                        <u-tag :text="financeLabel(order.finance_status)" :type="financeType(order.finance_status)" plain plainFill size="mini" />
                    </view>
                    <view class="field">
                        <text class="label">创建时间</text>
                        <text class="value">{{ formatErpTime(order.create_at) }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">更新时间</text>
                        <text class="value">{{ formatErpTime(order.update_at) }}</text>
                    </view>
                    <view class="erp-card__foot finance-foot">
                        <view class="amount-box">
                            <text class="amt-label">原采购</text>
                            <text class="amt-value">¥{{ money(order.original_total_cost ?? order.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">退货冲减</text>
                            <text class="amt-value orange">¥{{ money(Math.max(0, Number(order.original_total_cost || order.total_cost || 0) - Number(order.effective_purchase_amount || 0))) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">有效本金</text>
                            <text class="amt-value blue">¥{{ money(order.effective_purchase_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已付</text>
                            <text class="amt-value green">¥{{ money(order.paid_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">未付</text>
                            <text class="amt-value orange">¥{{ money(order.payable_amount) }}</text>
                        </view>
                    </view>
                </view>

                <!-- 设备列表 -->
                <view class="section-title">
                    设备明细（有效 {{ order?.effective_asset_count ?? items.length }} 台<text v-if="Number(order?.returned_count || 0)"> · 已退 {{ order.returned_count }} 台</text>）
                </view>
                <view v-if="items.some(item => item.status === 'in_stock')" class="return-guide">
                    <view class="return-guide__title">采购退货怎么处理</view>
                    <view class="return-guide__steps">
                        <text>1. 选择设备</text><text>→</text><text>2. 确认退货</text><text>→</text><text>3. 已付款到应收款确认退款</text>
                    </view>
                </view>
                <view v-if="!items.length" class="empty-card">暂无设备明细</view>
                <view v-for="item in items" :key="item.id" class="erp-card device-card">
                    <view class="erp-card__head">
                        <text class="card-title">{{ item.model || '-' }}</text>
                        <u-tag :text="assetLabel(item.status)" :type="assetType(item.status)" plain plainFill size="mini" />
                    </view>
                    <view class="return-tip" v-if="item.status === 'returned'">已完成采购退货，设备已移出可售库存。</view>
                    <view v-else-if="item.status === 'in_stock'" class="return-flow-tip" :class="{ 'return-flow-tip--refund': item.return_flow?.requires_refund }">
                        <text class="return-flow-title">{{ returnActionLabel(item) }}</text>
                        <text>{{ item.return_flow?.description || '-' }}</text>
                    </view>
                    <view class="card-meta">{{ erpSpecLine(item.spec) }}</view>
                        <view class="card-meta">IMEI {{ item.imei || '-' }}</view>
                    <view class="card-meta" v-if="item.category_name">分类：{{ item.category_name }}</view>
                    <view class="card-meta" v-if="item.warehouse_name">
                        仓库：{{ item.warehouse_name }}{{ item.location_name ? ' / '+item.location_name : '' }}
                    </view>
                    <view class="card-time">{{ erpTimeLine(item, ['stock_in_at']) }}</view>
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">采购成本</text>
                            <text class="amt-value">¥{{ money(item.purchase_cost) }}</text>
                        </view>
                        <view class="amount-box" v-if="Number(item.adjust_cost)">
                            <text class="amt-label">成本调整</text>
                            <text class="amt-value orange">{{ Number(item.adjust_cost)>0?'+':'' }}¥{{ money(item.adjust_cost) }}</text>
                        </view>
                        <view class="amount-box" v-if="Number(item.refurbish_cost)">
                            <text class="amt-label">整备费用（独立应付）</text>
                            <text class="amt-value orange">+¥{{ money(item.refurbish_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">当前总成本</text>
                            <text class="amt-value blue">¥{{ money(item.total_cost) }}</text>
                        </view>
                        <view class="amount-box" v-if="item.status !== 'returned' && item.status !== 'void'">
                            <text class="amt-label">已付款</text>
                            <text class="amt-value green">¥{{ money(item.paid_amount) }}</text>
                        </view>
                        <view class="amount-box" v-if="item.status !== 'returned' && item.status !== 'void'">
                            <text class="amt-label">未付款</text>
                            <text class="amt-value orange">¥{{ money(item.unpaid_amount) }}</text>
                        </view>
                    </view>
                    <!-- 行内操作 -->
                    <view class="device-actions" v-if="item.status === 'in_stock'">
                        <u-button
                            size="small"
                            plain
                            text="调成本"
                            :customStyle="actionButtonStyle"
                            @click.stop="goAdjustCost(item)"
                        />
                        <u-button
                            size="small"
                            type="warning"
                            :text="returnActionLabel(item)"
                            :disabled="item.return_flow?.returnable === false"
                            :customStyle="returnButtonStyle"
                            @click.stop="goReturn(item)"
                        />
                    </view>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getMobilePurchaseInfo } from '@/addon/hsx_erp/api/erp'
import { erpTimeLine, formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import { erpSpecLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import { erpPartyDisplayName } from '@/addon/hsx_erp/hooks/useErpPartyText'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import { ERP_DICT_FALLBACK, dictLabel, dictType, loadErpDicts, type ErpDictMap } from '@/addon/hsx_erp/api/dict'

const order = ref<any>(null)
const items = ref<any[]>([])
const loading = ref(true)
const loadError = ref('')
const purchaseOrderId = ref(0)
const purchaseNo = ref('')
const detailLoaded = ref(false)
const dicts = ref<ErpDictMap>(ERP_DICT_FALLBACK)
const actionButtonStyle = { width: '132rpx', height: '56rpx', margin: '0' }
const returnButtonStyle = { minWidth: '190rpx', height: '56rpx', margin: '0' }
let loadSeq = 0

const pageTitle = computed(() => '采购单详情')

onLoad((query: any) => {
    purchaseOrderId.value = Number(query?.purchase_order_id || query?.id || 0)
    purchaseNo.value = safeDecode(query?.purchase_no || '')
    loadErpDicts().then(rows => { dicts.value = rows })
    loadDetail()
})

onShow(() => {
    if (detailLoaded.value) reload()
})

async function loadDetail(options: { silent?: boolean } = {}) {
    const seq = ++loadSeq
    if (!purchaseOrderId.value) {
        loading.value = false
        loadError.value = '缺少采购单ID'
        return
    }
    const silent = !!options.silent || !!order.value
    if (!silent) loading.value = true
    loadError.value = ''
    try {
        const res: any = await withTimeout(getMobilePurchaseInfo(purchaseOrderId.value), 12000)
        if (seq !== loadSeq) return
        const data = res?.data || {}
        if (!data?.id) {
            throw new Error('采购单不存在或无权查看')
        }
        order.value = data
        items.value = Array.isArray(data.items) ? data.items : []
        purchaseNo.value = data.purchase_no || purchaseNo.value
    } catch (e: any) {
        if (seq !== loadSeq) return
        const message = e?.message || e?.msg || '采购单加载失败'
        if (silent && order.value) {
            uni.showToast({ title: message, icon: 'none' })
        } else {
            loadError.value = message
            order.value = null
            items.value = []
            uni.showToast({ title: loadError.value, icon: 'none' })
        }
    } finally {
        if (seq === loadSeq) {
            loading.value = false
            detailLoaded.value = true
        }
    }
}

function reload() {
    return loadDetail({ silent: true })
}

function safeDecode(value: any) {
    try { return decodeURIComponent(String(value || '')) } catch { return String(value || '') }
}

function withTimeout<T>(promise: Promise<T>, timeoutMs: number): Promise<T> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('采购单加载超时，请重试')), timeoutMs)
        promise.then(resolve).catch(reject).finally(() => clearTimeout(timer))
    })
}

const goAdjustCost = (item: any) => {
    const q = `id=${item.asset_id || item.id}&model=${encodeURIComponent(item.model || '')}&asset_no=${encodeURIComponent(item.asset_no || '')}&imei=${encodeURIComponent(item.imei || '')}&status=${encodeURIComponent(item.status || '')}&cost=${item.total_cost || item.purchase_cost || 0}&wh=${encodeURIComponent(item.warehouse_name || '')}&loc=${encodeURIComponent(item.location_name || '')}`
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?${q}` })
}
const goReturn = (item: any) => {
    if (item.return_flow?.returnable === false) return
    uni.navigateTo({
        url: `/addon/hsx_erp/pages/purchase_return/create?purchase_order_id=${purchaseOrderId.value}&purchase_no=${encodeURIComponent(purchaseNo.value)}&party_name=${encodeURIComponent(order.value?.party_name || '')}&asset_id=${item.asset_id || item.id || ''}`
    })
}
const returnActionLabel = (_item: any) => '采购退货'

const money = (v: any) => Number(v || 0).toFixed(2)
const financeLabel = (s: string) => dictLabel(dicts.value, 'purchase_finance_status', s)
const financeType = (s: string) => dictType(dicts.value, 'purchase_finance_status', s)
const assetLabel = (s: string) => dictLabel(dicts.value, 'asset_status', s)
const assetType = (s: string) => dictType(dicts.value, 'asset_status', s)
const orderStatusLabel = (s: string) => dictLabel(dicts.value, 'purchase_order_status', s)
const orderStatusType = (s: string) => dictType(dicts.value, 'purchase_order_status', s)

defineExpose({ reload })
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.detail-wrap { padding: 16rpx 0 120rpx; }
.form-card .value { word-break: break-all; }
.finance-foot { margin: 8rpx 0 0; padding: 18rpx 0 22rpx; }
.device-card { margin-bottom: 20rpx; }
.return-guide { margin:0 24rpx 20rpx; padding:18rpx 20rpx; border:1rpx solid #fed7aa; border-radius:18rpx; background:#fffaf5; }
.return-guide__title { color:#9a3412; font-size:24rpx; font-weight:650; }
.return-guide__steps { display:flex; flex-wrap:wrap; gap:8rpx; margin-top:8rpx; color:#64748b; font-size:20rpx; line-height:1.5; }
.empty-card { background:#fff; border-radius:28rpx; padding:40rpx 20rpx; margin:0 24rpx 20rpx; text-align:center; color:#94a3b8; font-size:26rpx; }
.return-tip { margin-top:4rpx; padding:12rpx 16rpx; border-radius:12rpx; background:#fff7ed; color:#ea580c; font-size:24rpx; line-height:1.45; }
.return-flow-tip { display:flex; flex-direction:column; gap:6rpx; margin-top:10rpx; padding:14rpx 16rpx; border-radius:12rpx; background:#f8fafc; color:#64748b; font-size:22rpx; line-height:1.45; }
.return-flow-tip--refund { background:#fff7ed; color:#c2410c; }
.return-flow-title { color:#0f172a; font-weight:650; }
.device-actions { display:flex; justify-content:flex-end; gap:12rpx; margin-top:16rpx; padding-top:12rpx; border-top:1rpx solid #f1f5f9; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
.error-wrap { min-height:520rpx; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32rpx; }
</style>
