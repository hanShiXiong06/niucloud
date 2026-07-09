<template>
    <view class="cost-detail-page">
        <!-- 设备信息 -->
        <view class="card asset-card">
            <view class="asset-card__head">
                <text class="asset-title">{{ asset.model || '设备成本调整' }}</text>
                <u-tag :text="statusLabel(asset.inventory_status)" :type="statusType(asset.inventory_status)" plain plainFill size="mini"></u-tag>
            </view>
            <view class="info-grid">
                <view class="info-item">
                    <text class="info-label">资产号</text>
                    <text class="info-value">{{ asset.asset_no || '-' }}</text>
                </view>
                <view class="info-item">
                    <text class="info-label">IMEI</text>
                    <text class="info-value">{{ asset.imei || '-' }}</text>
                </view>
                <view class="info-item" v-if="asset.warehouse_name">
                    <text class="info-label">仓库库位</text>
                    <text class="info-value">{{ asset.warehouse_name }}{{ asset.location_name ? ' / ' + asset.location_name : '' }}</text>
                </view>
                <view class="info-item" v-if="supplierName">
                    <text class="info-label">供应商</text>
                    <text class="info-value">{{ supplierName }}</text>
                </view>
                <view class="info-item" v-if="stockInText">
                    <text class="info-label">入库时间</text>
                    <text class="info-value">{{ stockInText }}</text>
                </view>
            </view>
        </view>

        <!-- 不可调整状态：只读提示 -->
        <view v-if="blocked" class="card blocked-card">
            <u-icon name="lock-fill" color="#94a3b8" size="22"></u-icon>
            <text class="blocked-text">当前状态「{{ statusLabel(asset.inventory_status) }}」不可调整成本</text>
        </view>

        <template v-else>
            <!-- 成本调整 -->
            <view class="card">
                <view class="current-cost">
                    <text class="cc-label">当前成本</text>
                    <text class="cc-value">¥{{ formatMoney(asset.current_cost) }}</text>
                </view>
                <view class="divider"></view>
                <view class="field-label">调整后成本</view>
                <view class="cost-input-row">
                    <text class="cost-prefix">¥</text>
                    <u-input
                        v-model="newCost"
                        type="digit"
                        border="none"
                        placeholder="输入新的成本"
                        :customStyle="{ padding: '0', fontSize: '40rpx', fontWeight: '700' }"
                    ></u-input>
                </view>
                <view v-if="deltaValid && Math.abs(delta) >= 0.001" class="delta-preview" :class="delta >= 0 ? 'up' : 'down'">
                    {{ delta >= 0 ? '↑ 上调' : '↓ 下调' }} ¥{{ formatMoney(Math.abs(delta)) }}
                </view>
            </view>

            <!-- 调整原因 -->
            <view class="card">
                <view class="field-label">调整原因</view>
                <view class="reason-chips">
                    <view
                        v-for="r in reasonPresets"
                        :key="r"
                        class="chip"
                        :class="{ active: reason === r }"
                        @click="reason = r"
                    >{{ r }}</view>
                </view>
                <u-textarea
                    v-model="reason"
                    placeholder="补充说明（选填）"
                    :maxlength="200"
                    count
                    height="120"
                    :customStyle="{ background: '#f8fafc', marginTop: '16rpx' }"
                ></u-textarea>
            </view>

            <!-- 同步应付 -->
            <view class="card" v-if="!isOutbound">
                <view class="switch-row">
                    <view class="switch-text">
                        <text class="switch-title">差额计入应付</text>
                        <text class="switch-sub">勾选后成本差额同步到对供应商的应付（仅入库应付设备可用）</text>
                    </view>
                    <u-switch v-model="syncPayable" size="22" activeColor="#3b6ef5"></u-switch>
                </view>
            </view>
            <view class="card outbound-note" v-else>
                <u-icon name="info-circle" color="#94a3b8" size="16"></u-icon>
                <text>已出库设备：仅订正成本与毛利口径，不联动应付/应收。</text>
            </view>

            <!-- 历史调整 -->
            <view class="card" v-if="adjustHistory.length">
                <view class="field-label">历史成本调整</view>
                <view v-for="(h, i) in adjustHistory" :key="i" class="history-item">
                    <view class="history-line">
                        <text class="history-cost">¥{{ formatMoney(h.before_cost) }} → ¥{{ formatMoney(h.after_cost) }}</text>
                        <text class="history-time">{{ fmtTime(h.occurred_at) }}</text>
                    </view>
                    <text class="history-remark">{{ h.remark || h.cost_type_text }}<text v-if="h.operator_name"> · {{ h.operator_name }}</text></text>
                </view>
            </view>

            <!-- 回填说明 -->
            <view class="backfill-note">
                <u-icon name="reload" color="#3b6ef5" size="16"></u-icon>
                <text class="bf-text">在 ERP 调整后，系统会自动把新成本回填到回收 / 财务，无需到各业务端重复操作。</text>
            </view>
        </template>

        <!-- 底部操作条 -->
        <view v-if="!blocked" class="footer-bar">
            <u-button
                type="primary"
                shape="circle"
                :loading="submitting"
                :disabled="!canSubmit"
                text="确认调整"
                color="#3b6ef5"
                @click="submit"
            ></u-button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getErpAssetInfo, adjustErpAssetCost } from '@/addon/hsx_erp/api/asset'
import { INVENTORY_STATUS_MAP, INVENTORY_OUTBOUND, isCostAdjustAllowed } from '@/addon/hsx_erp/api/dict'

const assetId = ref<string>('')
const asset = ref<any>({})
const supplierName = ref<string>('')
const adjustHistory = ref<any[]>([])
const newCost = ref<string>('')
const reason = ref<string>('')
const syncPayable = ref<boolean>(false)
const submitting = ref<boolean>(false)

const reasonPresets = ['录入有误', '退补差价', '复检改判', '议价调整']

const statusLabel = (s: string) => INVENTORY_STATUS_MAP[s] || s || '-'
const statusType = (s: string) => {
    if (s === 'in_stock' || s === 'available_for_sale') return 'success'
    if (s === 'sold' || s === 'outbound' || s === 'locked') return 'primary'
    if (s === 'returned') return 'warning'
    if (s === 'void') return 'info'
    if (s === 'lost' || s === 'inbound_rejected') return 'error'
    return 'warning'
}
const formatMoney = (v: any) => Number(v || 0).toFixed(2)
const fmtTime = (ts: any) => {
    const n = Number(ts)
    if (!n) return ''
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${ d.getFullYear() }-${ p(d.getMonth() + 1) }-${ p(d.getDate()) } ${ p(d.getHours()) }:${ p(d.getMinutes()) }`
}

const blocked = computed(() => !isCostAdjustAllowed(asset.value.inventory_status))
const isOutbound = computed(() => String(asset.value.inventory_status) === INVENTORY_OUTBOUND)
const stockInText = computed(() => fmtTime(asset.value.stock_in_at))

const delta = computed(() => {
    const n = Number(newCost.value)
    if (isNaN(n)) return 0
    return Math.round((n - Number(asset.value.current_cost || 0)) * 100) / 100
})
const deltaValid = computed(() => newCost.value !== '' && !isNaN(Number(newCost.value)))
const canSubmit = computed(() => {
    if (!deltaValid.value) return false
    if (Number(newCost.value) < 0) return false
    if (Math.abs(delta.value) < 0.001) return false
    return true
})

const dec = (v: any) => {
    try { return decodeURIComponent(String(v ?? '')) } catch (e) { return String(v ?? '') }
}

// 后台补全：供应商、入库时间、历史调整（详情接口）
const enrich = async () => {
    try {
        const res: any = await getErpAssetInfo(assetId.value)
        const d = res?.data || {}
        if (d.asset) asset.value = { ...asset.value, ...d.asset }
        supplierName.value = String(d.counterparty?.unit_name || '')
        adjustHistory.value = (d.cost_ledger || []).filter((x: any) => String(x.cost_type) === 'manual_adjust').slice(0, 5)
    } catch (e) {
        // 详情接口失败不影响主流程，页面已用列表数据渲染
    }
}

const submit = async () => {
    if (!canSubmit.value || submitting.value) return
    if (blocked.value) {
        uni.showToast({ title: `当前状态「${statusLabel(asset.value.inventory_status)}」不可调整成本`, icon: 'none' })
        return
    }
    if (Number(newCost.value) < 0) {
        uni.showToast({ title: '成本不能为负', icon: 'none' })
        return
    }
    submitting.value = true
    try {
        await adjustErpAssetCost(
            assetId.value,
            Number(newCost.value),
            reason.value,
            isOutbound.value ? false : syncPayable.value
        )
        uni.showToast({ title: '成本已调整，已回填回收/财务', icon: 'none' })
        setTimeout(() => uni.navigateBack(), 800)
    } catch (e) {
        // 拦截器已提示具体错误
    } finally {
        submitting.value = false
    }
}

onLoad((options: any) => {
    assetId.value = String(options?.id || '')
    if (!assetId.value) {
        uni.showToast({ title: '缺少资产ID', icon: 'none' })
        return
    }
    // 优先用列表带过来的数据：秒开
    if (options?.cost !== undefined && options?.cost !== '') {
        asset.value = {
            id: Number(options.id),
            model: dec(options.model),
            asset_no: dec(options.asset_no),
            imei: dec(options.imei),
            inventory_status: dec(options.status),
            current_cost: Number(options.cost || 0),
            warehouse_name: dec(options.wh),
            location_name: dec(options.loc)
        }
    }
    // 再后台补全供应商/入库时间/历史
    enrich()
})
</script>

<style lang="scss" scoped>
.cost-detail-page {
    min-height: 100vh;
    background: #f6f7fb;
    padding: 24rpx 24rpx 180rpx;
}

.card {
    background: #fff;
    border-radius: 20rpx;
    padding: 28rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.asset-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}
.asset-title {
    font-size: 34rpx;
    font-weight: 600;
    color: #0f172a;
    flex: 1;
}
.info-grid {
    margin-top: 20rpx;
    display: flex;
    flex-direction: column;
    gap: 14rpx;
}
.info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .info-label { font-size: 24rpx; color: #94a3b8; }
    .info-value { font-size: 26rpx; color: #334155; max-width: 70%; text-align: right; }
}

.blocked-card {
    display: flex;
    align-items: center;
    gap: 14rpx;

    .blocked-text { font-size: 28rpx; color: #64748b; }
}

.current-cost {
    display: flex;
    align-items: baseline;
    justify-content: space-between;

    .cc-label { font-size: 26rpx; color: #94a3b8; }
    .cc-value { font-size: 40rpx; font-weight: 700; color: #0f172a; }
}
.divider {
    height: 1rpx;
    background: #f1f5f9;
    margin: 24rpx 0;
}
.field-label {
    font-size: 26rpx;
    color: #64748b;
    margin-bottom: 18rpx;
}
.cost-input-row {
    display: flex;
    align-items: center;
    border-bottom: 2rpx solid #e2e8f0;
    padding-bottom: 12rpx;

    .cost-prefix { font-size: 36rpx; color: #0f172a; margin-right: 12rpx; }
}
.delta-preview {
    margin-top: 18rpx;
    font-size: 26rpx;
    font-weight: 600;

    &.up { color: #dc2626; }
    &.down { color: #16a34a; }
}

.reason-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}
.chip {
    padding: 12rpx 28rpx;
    border-radius: 999rpx;
    background: #f1f5f9;
    color: #475569;
    font-size: 26rpx;

    &.active {
        background: #eef2ff;
        color: #3b6ef5;
        font-weight: 500;
    }
}

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24rpx;

    .switch-text { flex: 1; }
    .switch-title { font-size: 28rpx; color: #0f172a; }
    .switch-sub {
        display: block;
        margin-top: 8rpx;
        font-size: 22rpx;
        color: #94a3b8;
        line-height: 1.5;
    }
}
.outbound-note {
    display: flex;
    align-items: center;
    gap: 12rpx;
    font-size: 24rpx;
    color: #94a3b8;
}

.history-item {
    padding: 18rpx 0;
    border-bottom: 1rpx solid #f1f5f9;

    &:last-child { border-bottom: none; padding-bottom: 0; }
}
.history-line {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .history-cost { font-size: 26rpx; color: #0f172a; font-weight: 500; }
    .history-time { font-size: 22rpx; color: #94a3b8; }
}
.history-remark {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #94a3b8;
}

.backfill-note {
    display: flex;
    align-items: flex-start;
    gap: 12rpx;
    padding: 24rpx 28rpx;
    background: #eef2ff;
    border-radius: 16rpx;

    .bf-text { flex: 1; font-size: 24rpx; color: #5566aa; line-height: 1.6; }
}

.footer-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 20rpx 24rpx calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -8rpx 28rpx rgba(15, 23, 42, 0.08);
}
</style>
