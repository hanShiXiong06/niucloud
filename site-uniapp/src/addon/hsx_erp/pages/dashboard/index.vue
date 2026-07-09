<template>
    <view class="erp-page dashboard-page">
        <view class="dashboard-head">
            <view class="head-title">
                <text class="dash-title">ERP 经营看板</text>
                <text class="dash-sub">录入、库存、应收应付和折账统一入口</text>
            </view>
            <view>
            <u-button size="mini" type="primary" plain :loading="loading" @click="loadData">
                刷新
            </u-button>
            </view>
        </view>

        <view class="period-card">
            <u-tabs
                :list="periodTabs"
                :current="periodIndex"
                lineWidth="24"
                lineHeight="4"
                :activeStyle="tabActiveStyle"
                :inactiveStyle="tabInactiveStyle"
                @change="onPeriodChange"
            />
        </view>

        <view class="quick-card">
            <u-grid :border="false" col="4">
                <u-grid-item v-for="item in quickActions" :key="item.path" @click="go(item.path)">
                    <view class="quick-item">
                        <view class="quick-icon" :class="item.tone">
                            <text>{{ item.icon }}</text>
                        </view>
                        <text class="quick-label">{{ item.label }}</text>
                    </view>
                </u-grid-item>
            </u-grid>
        </view>

        <view class="overview-card">
            <view class="overview-main">
                <view>
                    <text class="metric-label">销售额</text>
                    <text class="overview-value">¥{{ money(summary.sale_amount) }}</text>
                    <text class="metric-sub">毛利 ¥{{ money(summary.profit_amount) }}</text>
                </view>
                <u-tag text="本期" type="primary" plain plainFill size="mini" />
            </view>
            <view class="overview-foot">
                <view class="amount-box">
                    <text class="amt-label">采购额</text>
                    <text class="amt-value">¥{{ money(summary.purchase_amount) }}</text>
                    <text class="metric-sub">{{ summary.purchase_count || 0 }} 笔</text>
                </view>
                <view class="amount-box">
                    <text class="amt-label">库存成本</text>
                    <text class="amt-value blue">¥{{ money(summary.stock_cost) }}</text>
                    <text class="metric-sub">{{ summary.stock_count || 0 }} 台</text>
                </view>
                <view class="amount-box">
                    <text class="amt-label">折账金额</text>
                    <text class="amt-value orange">¥{{ money(summary.offset_amount) }}</text>
                    <text class="metric-sub">{{ todo.offset_party_count || 0 }} 个主体</text>
                </view>
            </view>
        </view>

        <view class="finance-card">
            <u-cell-group :border="false">
                <u-cell title="待付款" :label="`${todo.payable_count || 0} 笔`" isLink @click="go('/addon/hsx_erp/pages/payable/list')">
                    <template #value>
                        <text class="cell-amount red">¥{{ money(summary.payable_remain) }}</text>
                    </template>
                </u-cell>
                <u-cell title="待收款" :label="`${todo.receivable_count || 0} 笔`" isLink @click="go('/addon/hsx_erp/pages/receivable/list')">
                    <template #value>
                        <text class="cell-amount blue">¥{{ money(summary.receivable_remain) }}</text>
                    </template>
                </u-cell>
                <u-cell title="已收款" label="本期">
                    <template #value>
                        <text class="cell-amount green">¥{{ money(summary.receipt_amount) }}</text>
                    </template>
                </u-cell>
                <u-cell title="已付款" label="本期" :border="false">
                    <template #value>
                        <text class="cell-amount orange">¥{{ money(summary.payment_amount) }}</text>
                    </template>
                </u-cell>
            </u-cell-group>
        </view>

        <view class="section-title">最近结算</view>
        <view class="erp-card settlement-card" v-for="row in recent.settlements" :key="'s'+row.id">
            <view class="erp-card__head">
                <text class="card-title">{{ row.party_name || '-' }}</text>
                <u-tag :text="settlementLabel(row.settlement_type)" :type="settlementType(row.settlement_type)" plain plainFill size="mini" />
            </view>
            <view class="card-meta">{{ row.settlement_no }} · {{ row.capital_account_name || '无资金账户' }}</view>
            <view class="compact-foot">
                <text class="compact-amount">¥{{ money(row.amount) }}</text>
                <text class="card-time">{{ time(row.confirmed_at) }}</text>
            </view>
        </view>
        <view v-if="loading" class="loading-row">
            <u-loading-icon mode="circle" text="加载中" />
        </view>
        <u-empty v-else-if="!(recent.settlements || []).length" mode="list" text="暂无结算记录" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMobileErpDashboard } from '@/addon/hsx_erp/api/erp'

const loading = ref(false)
const period = ref('month')
const data = ref<any>({})
const periods = [
    { label: '今日', value: 'today' },
    { label: '本月', value: 'month' },
    { label: '全部', value: 'all' },
]
const periodTabs = periods.map(item => ({ name: item.label, value: item.value }))
const periodIndex = computed(() => Math.max(periods.findIndex(item => item.value === period.value), 0))
const tabActiveStyle = { color: '#3b6ef5', fontWeight: '700', fontSize: '28rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '28rpx' }
const quickActions = [
    { label: '采购录入', icon: '采', tone: 'blue', path: '/addon/hsx_erp/pages/purchase/create' },
    { label: '销售出库', icon: '销', tone: 'green', path: '/addon/hsx_erp/pages/sale/create' },
    { label: '库存设备', icon: '库', tone: 'purple', path: '/addon/hsx_erp/pages/stock/list' },
    { label: '成本调整', icon: '调', tone: 'orange', path: '/addon/hsx_erp/pages/cost_adjust/list' },
    { label: '应付款', icon: '付', tone: 'red', path: '/addon/hsx_erp/pages/payable/list' },
    { label: '应收款', icon: '收', tone: 'cyan', path: '/addon/hsx_erp/pages/receivable/list' },
    { label: '采购退货', icon: '退', tone: 'gray', path: '/addon/hsx_erp/pages/purchase_return/list' },
    { label: '销售退货', icon: '返', tone: 'gray', path: '/addon/hsx_erp/pages/sale_return/list' },
]

const summary = computed(() => data.value?.summary || {})
const todo = computed(() => data.value?.todo || {})
const recent = computed(() => data.value?.recent || {})

onShow(loadData)

function switchPeriod(value: string) {
    period.value = value
    loadData()
}

function onPeriodChange(item: any) {
    const index = typeof item === 'number' ? item : Number(item?.index ?? 0)
    const value = item?.value || periods[index]?.value
    if (value && value !== period.value) switchPeriod(value)
}

async function loadData() {
    loading.value = true
    try {
        const res: any = await getMobileErpDashboard({ period: period.value })
        data.value = res?.data || {}
    } catch (e: any) {
        uni.showToast({ title: e?.message || '经营数据加载失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

function go(path: string) {
    uni.navigateTo({ url: path })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const settlementLabel = (s: string) => ({ receipt: '收款', payment: '付款', offset: '折账' }[s] || s || '-')
const settlementType = (s: string) => ({ receipt: 'success', payment: 'warning', offset: 'error' }[s] || 'info')
const time = (v: any) => {
    const ts = Number(v || 0)
    if (!ts) return '-'
    const d = new Date(ts * 1000)
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const h = String(d.getHours()).padStart(2, '0')
    const min = String(d.getMinutes()).padStart(2, '0')
    return `${m}-${day} ${h}:${min}`
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.dashboard-page {
    padding-bottom: 40rpx;
}
.dashboard-head {
    background: #fff;
    padding: 34rpx 28rpx 18rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}
.head-title {
    flex: 1;
    min-width: 0;
}
.dash-title {
    display: block;
    font-size: 38rpx;
    font-weight: 800;
    color: #0f172a;
}
.dash-sub {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    color: #64748b;
}
.period-card,
.quick-card,
.overview-card,
.finance-card {
    background: #fff;
    border-radius: 28rpx;
    margin: 0 24rpx 20rpx;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);
    overflow: hidden;
}
.period-card {
    margin-top: 18rpx;
    padding: 4rpx 16rpx;
}
.quick-card {
    padding: 18rpx 0 14rpx;
}
.quick-item {
    min-height: 126rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10rpx;
}
.quick-icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 22rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 28rpx;
    font-weight: 800;
}
.quick-icon.blue { background: #2563eb; }
.quick-icon.green { background: #16a34a; }
.quick-icon.purple { background: #7c3aed; }
.quick-icon.orange { background: #ea580c; }
.quick-icon.red { background: #dc2626; }
.quick-icon.cyan { background: #0891b2; }
.quick-icon.gray { background: #64748b; }
.quick-label {
    font-size: 23rpx;
    color: #334155;
}
.overview-card {
    padding: 26rpx 28rpx 22rpx;
}
.overview-main {
    display: flex;
    justify-content: space-between;
    gap: 20rpx;
}
.metric-label {
    display: block;
    font-size: 23rpx;
    color: #64748b;
}
.overview-value {
    display: block;
    margin-top: 8rpx;
    font-size: 42rpx;
    font-weight: 800;
    color: #0f172a;
}
.metric-sub {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #94a3b8;
}
.overview-foot {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24rpx;
    margin-top: 22rpx;
    padding-top: 20rpx;
    border-top: 2rpx solid #f3f4f6;
}
.finance-card {
    padding: 4rpx 0;
}
.cell-amount {
    font-size: 28rpx;
    font-weight: 700;
    color: #0f172a;
}
.cell-amount.red { color: #dc2626; }
.cell-amount.blue { color: #2563eb; }
.cell-amount.green { color: #16a34a; }
.cell-amount.orange { color: #ea580c; }
.settlement-card {
    padding: 22rpx 26rpx;
}
.compact-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12rpx;
}
.compact-amount {
    font-size: 30rpx;
    color: #0f172a;
    font-weight: 800;
}
.loading-row {
    padding: 42rpx 0;
    display: flex;
    justify-content: center;
}
</style>
