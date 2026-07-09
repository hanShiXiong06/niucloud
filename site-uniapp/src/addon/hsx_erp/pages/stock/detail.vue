<template>
    <view class="erp-page">
        <ErpPageHeader title="设备档案" />
        <view v-if="loading" class="loading-wrap"><u-loading-icon size="36" /></view>
        <view v-else-if="loadError" class="error-wrap">
            <u-empty mode="data" :text="loadError" />
            <view class="retry-btn">
                <u-button type="primary" size="small" text="重新加载" @click="loadDetail" />
            </view>
        </view>
        <scroll-view v-else-if="asset" scroll-y style="height:100%">
            <view class="detail-wrap">
                <view class="form-card asset-summary-card">
                    <view class="asset-head">
                        <view class="asset-title">
                            <text class="asset-title__model">{{ asset.model || '-' }}</text>
                            <text class="asset-title__sub">{{ asset.spec || '无规格' }} · IMEI {{ asset.imei || '-' }}</text>
                        </view>
                        <u-tag :text="statusLabel(asset.status)" :type="statusType(asset.status)" plain plainFill size="mini" />
                    </view>

                    <view v-if="asset.status === 'sold'" class="asset-banner sold">
                        <u-icon name="checkmark-circle" color="#2563eb" size="14" />
                        <text>已售出{{ asset.sale_order?.sale_no ? ' · ' + asset.sale_order.sale_no : '' }}</text>
                    </view>
                    <view v-else-if="asset.status === 'void'" class="asset-banner void">
                        <u-icon name="info-circle" color="#64748b" size="14" />
                        <text>该设备已作废</text>
                    </view>

                    <view class="erp-card__foot asset-finance-foot">
                        <view class="amount-box">
                            <text class="amt-label">总成本</text>
                            <text class="amt-value">¥{{ money(asset.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ asset.status === 'sold' ? '售价' : '预估价' }}</text>
                            <text class="amt-value blue">{{ asset.status === 'sold' ? ('¥' + money(asset.sale_price)) : displayEstimate(asset) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ asset.status === 'sold' ? '毛利' : '库龄' }}</text>
                            <text class="amt-value" :class="asset.status === 'sold' ? (Number(asset.profit)>=0?'green':'red') : ''">
                                {{ asset.status === 'sold' ? ('¥' + money(asset.profit)) : ageText(asset) }}
                            </text>
                        </view>
                    </view>

                    <view class="field">
                        <text class="label">资产号</text>
                        <text class="value">{{ asset.asset_no || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">仓库</text>
                        <text class="value">{{ asset.warehouse_name || '-' }}{{ asset.location_name ? ' / '+asset.location_name : '' }}</text>
                    </view>
                    <view v-if="asset.category_name" class="field">
                        <text class="label">分类</text>
                        <text class="value">{{ asset.category_name }}</text>
                    </view>
                    <view v-if="asset.party_name" class="field field--last">
                        <text class="label">来源</text>
                        <text class="value">{{ asset.party_name }}</text>
                    </view>
                </view>

                <view class="form-card">
                    <view class="form-card-title">成本拆解</view>
                    <view class="field"><text class="label">采购成本</text><text class="value">¥{{ money(asset.purchase_cost) }}</text></view>
                    <view class="field" v-if="Number(asset.adjust_cost)">
                        <text class="label">成本调整</text>
                        <text class="value orange">{{ Number(asset.adjust_cost)>0?'+':'' }}¥{{ money(asset.adjust_cost) }}</text>
                    </view>
                    <view class="field" v-if="Number(asset.refurbish_cost)">
                        <text class="label">整备成本</text>
                        <text class="value">¥{{ money(asset.refurbish_cost) }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">当前总成本</text>
                        <text class="value strong">¥{{ money(asset.total_cost) }}</text>
                    </view>
                </view>

                <view class="form-card" v-if="asset.purchase_order">
                    <view class="form-card-title">采购信息</view>
                    <view class="field"><text class="label">供应商</text><text class="value">{{ asset.party_name }}</text></view>
                    <view class="field"><text class="label">采购单号</text><text class="value">{{ asset.purchase_order.purchase_no }}</text></view>
                    <view class="field field--last"><text class="label">付款状态</text>
                        <u-tag :text="financeLabel(asset.purchase_order.finance_status)" :type="financeType(asset.purchase_order.finance_status)" plain plainFill size="mini" />
                    </view>
                </view>

                <view class="form-card" v-if="asset.sale_order">
                    <view class="form-card-title">销售信息</view>
                    <view class="field"><text class="label">客户</text><text class="value">{{ asset.sale_order.party_name }}</text></view>
                    <view class="field"><text class="label">销售单号</text><text class="value">{{ asset.sale_order.sale_no }}</text></view>
                    <view class="field"><text class="label">售价</text><text class="value blue">¥{{ money(asset.sale_price) }}</text></view>
                    <view class="field field--last"><text class="label">毛利</text>
                        <text class="value" :class="Number(asset.profit)>=0?'green':'red'">¥{{ money(asset.profit) }}</text>
                    </view>
                </view>

                <view class="section-title">设备履历</view>
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
                <view class="empty-tip" v-if="!ledger.length">暂无设备履历</view>

                <!-- 底部操作 -->
                <view class="bottom-actions">
                    <view v-if="asset.status === 'in_stock'" class="bottom-action-btn">
                        <u-button type="primary" text="调整成本" @click="goAdjust" />
                    </view>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getMobileStockInfo } from '@/addon/hsx_erp/api/erp'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'

const asset = ref<any>(null)
const ledger = ref<any[]>([])
const loading = ref(true)
const loadError = ref('')
const assetId = ref(0)
const detailLoaded = ref(false)
let loadSeq = 0

onLoad((query: any) => {
    assetId.value = Number(query?.id || 0)
    loadDetail()
})

onShow(() => {
    if (detailLoaded.value) reload()
})

async function loadDetail(options: { silent?: boolean } = {}) {
    const seq = ++loadSeq
    if (!assetId.value) {
        loading.value = false
        loadError.value = '缺少设备ID'
        return
    }
    const silent = !!options.silent || !!asset.value
    if (!silent) loading.value = true
    loadError.value = ''
    try {
        const res: any = await withTimeout(getMobileStockInfo(assetId.value), 12000)
        if (seq !== loadSeq) return
        const data = res?.data || {}
        if (!data?.id) {
            throw new Error('设备不存在或无权查看')
        }
        asset.value = data
        ledger.value = data.asset_ledgers || data.ledger || data.asset_ledger || []
    } catch (e: any) {
        if (seq !== loadSeq) return
        const message = e?.message || e?.msg || '设备档案加载失败'
        if (silent && asset.value) {
            uni.showToast({ title: message, icon: 'none' })
        } else {
            loadError.value = message
            asset.value = null
            ledger.value = []
            uni.showToast({ title: message, icon: 'none' })
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

function withTimeout<T>(promise: Promise<T>, timeoutMs: number): Promise<T> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('设备档案加载超时，请重试')), timeoutMs)
        promise.then(resolve).catch(reject).finally(() => clearTimeout(timer))
    })
}

const goAdjust = () => {
    if (!asset.value) return
    const a = asset.value
    const q = `id=${a.id}&model=${encodeURIComponent(a.model || '')}&asset_no=${encodeURIComponent(a.asset_no || '')}&imei=${encodeURIComponent(a.imei || '')}&status=${encodeURIComponent(a.status || '')}&cost=${a.total_cost || 0}&wh=${encodeURIComponent(a.warehouse_name || '')}&loc=${encodeURIComponent(a.location_name || '')}`
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?${q}` })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const formatDate = (ts: number) => ts ? new Date(ts * 1000).toLocaleDateString('zh-CN') : '-'
const ageDays = (ts: number) => ts ? Math.floor((Date.now() / 1000 - ts) / 86400) : 0
const ageText = (row: any) => {
    const ts = Number(row?.stock_in_at || row?.create_at || 0)
    return ts ? `${ageDays(ts)}天` : '-'
}
const displayEstimate = (row: any) => {
    const price = row?.retail_price || row?.estimate_sale_price
    return Number(price || 0) > 0 ? `¥${money(price)}` : '-'
}
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

.detail-wrap { padding: 16rpx 0 120rpx; }
.asset-summary-card { padding-top:24rpx; }
.asset-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; }
.asset-title { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.asset-title__model { font-size:32rpx; font-weight:700; color:#0f172a; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.asset-title__sub { font-size:24rpx; color:#64748b; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.asset-banner { display:flex; align-items:center; gap:8rpx; margin:16rpx 0 0; padding:12rpx 16rpx; border-radius:12rpx; font-size:22rpx; line-height:1.45; }
.asset-banner.sold { background:#eff6ff; color:#2563eb; }
.asset-banner.void { background:#f1f5f9; color:#64748b; }
.asset-banner text { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.asset-finance-foot { justify-content:space-between; gap:10rpx; margin:16rpx 0 10rpx; padding:18rpx 0; border-top:0; border-bottom:2rpx solid #f3f4f6; }
.asset-finance-foot .amount-box { flex:1; min-width:0; }
.asset-finance-foot .amt-value { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.form-card .value { word-break:break-all; }
.form-card .value.blue { color:#2563eb; }
.form-card .value.green { color:#16a34a; }
.form-card .value.red { color:#dc2626; }
.form-card .value.orange { color:#ea580c; }
.form-card .value.strong { font-weight:700; }
.section-title { font-size:28rpx; font-weight:600; color:#374151; padding:8rpx 28rpx 16rpx; }
.flow-card { background:#fff; border-radius:20rpx; padding:18rpx 22rpx; margin:0 24rpx 14rpx; box-shadow:0 2rpx 12rpx rgba(0,0,0,.04); }
.flow-head { display:flex; justify-content:space-between; margin-bottom:6rpx; }
.flow-action { font-size:26rpx; font-weight:600; color:#0f172a; }
.flow-time { font-size:22rpx; color:#94a3b8; }
.empty-tip { text-align:center; color:#94a3b8; font-size:26rpx; padding:40rpx 0; }
.bottom-actions { margin:32rpx; }
.bottom-action-btn { width:100%; }
.error-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:520rpx; padding:40rpx; box-sizing:border-box; }
.retry-btn { margin-top:24rpx; width:180rpx; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
</style>
