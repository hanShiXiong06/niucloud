<template>
    <view class="erp-page">
        <ErpPageHeader title="应收详情" />

        <view class="detail-wrap">
            <view v-if="loading" class="loading-wrap">
                <u-loading-icon size="24" />
                <text>加载中...</text>
            </view>

            <view v-else-if="loadError" class="error-wrap">
                <text>{{ loadError }}</text>
                <u-button type="primary" size="small" plain @click="loadDetail">重试</u-button>
            </view>

            <template v-else-if="detail">
                <view class="form-card">
                    <view class="form-card-title">应收信息</view>
                    <view class="field"><text class="label">类型</text><text class="value">{{ detail.source_label || sourceLabel(detail.source_type) }}</text></view>
                    <view class="field"><text class="label">往来单位</text><text class="value">{{ detail.party_name || '-' }}</text></view>
                    <view class="field"><text class="label">应收单号</text><text class="value">{{ detail.receivable_no || '-' }}</text></view>
                    <view class="field"><text class="label">来源单据</text><text class="value">{{ sourceNo }}</text></view>
                    <view v-if="detail.purchase_no" class="field"><text class="label">原采购单</text><text class="value">{{ detail.purchase_no }}</text></view>
                    <view v-if="sourceOrder?.sale_channel" class="field"><text class="label">销售渠道</text><text class="value">{{ sourceOrder.sale_channel }}</text></view>
                    <view v-if="sourceOrder?.salesman_name" class="field"><text class="label">销售员</text><text class="value">{{ sourceOrder.salesman_name }}</text></view>
                    <view v-if="sourceOrder?.refund_mode" class="field"><text class="label">退款方式</text><text class="value">{{ refundLabel(sourceOrder.refund_mode) }}</text></view>
                    <view class="field"><text class="label">状态</text><text class="value">{{ statusLabel(detail.status) }}</text></view>
                    <view class="field field--last"><text class="label">发生时间</text><text class="value">{{ erpTimeLine(detail, ['occurred_at']) }}</text></view>
                    <view v-if="detail.remark || detail.return_remark" class="remark-box">{{ detail.return_remark || detail.remark }}</view>
                </view>

                <view class="erp-card">
                    <view class="erp-card__head">
                        <text class="card-title">金额汇总</text>
                        <u-tag :text="statusLabel(detail.status)" :type="statusType(detail.status)" plain plainFill size="mini" />
                    </view>
                    <view class="erp-card__foot finance-foot">
                        <view class="amount-box">
                            <text class="amt-label">应收合计</text>
                            <text class="amt-value blue">¥{{ money(detail.amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已收</text>
                            <text class="amt-value green">¥{{ money(detail.settled_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">剩余应收</text>
                            <text class="amt-value orange">¥{{ money(detail.remain_amount) }}</text>
                        </view>
                    </view>
                    <view class="card-meta">结算摘要：{{ detail.settle_summary || '-' }}</view>
                </view>

                <view class="section-title">关联设备</view>
                <view v-if="!items.length" class="empty-card">暂无设备明细</view>
                <view v-for="item in items" :key="item.asset_id || item.id" class="erp-card device-card">
                    <view class="erp-card__head">
                        <view>
                            <text class="card-title">{{ item.model || '-' }}</text>
                            <view class="card-meta">{{ item.spec || '-' }} · IMEI {{ item.imei || '-' }}</view>
                        </view>
                        <u-tag v-if="item.status" :text="assetLabel(item.status)" :type="assetType(item.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta" v-if="item.asset_no">资产编号：{{ item.asset_no }}</view>
                    <view class="card-meta" v-if="item.warehouse_name || item.location_name">
                        仓库：{{ item.warehouse_name || '-' }}{{ item.location_name ? ' / ' + item.location_name : '' }}
                    </view>
                    <view class="card-meta" v-if="item.reason">原因：{{ item.reason }}</view>
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">{{ sourceType === 'purchase_return' ? '应退' : '售价' }}</text>
                            <text class="amt-value blue">¥{{ money(item.sale_price || item.refund_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已收</text>
                            <text class="amt-value green">¥{{ money(item.allocated_settled) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">剩余</text>
                            <text class="amt-value orange">¥{{ money(item.allocated_remain) }}</text>
                        </view>
                    </view>
                </view>

                <view class="section-title">结算记录</view>
                <view v-if="!settlements.length" class="empty-card">暂无结算记录</view>
                <view v-for="row in settlements" :key="row.settlement_id" class="erp-card settlement-card">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.settlement_type_text || settlementLabel(row.settlement_type) }}</text>
                        <text class="settlement-amount">¥{{ money(row.applied_amount || row.amount) }}</text>
                    </view>
                    <view class="card-meta">结算单号：{{ row.settlement_no || '-' }}</view>
                    <view class="card-meta">方式：{{ row.pay_method_text || row.capital_account_name || '-' }}</view>
                    <view class="card-meta" v-if="row.operator_name">操作人：{{ row.operator_name }}</view>
                    <view class="card-time">{{ formatErpTime(row.confirmed_at) }}</view>
                    <view v-if="row.remark" class="remark-box">{{ row.remark }}</view>
                </view>
            </template>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'

import { getMobileReceivableInfo } from '@/addon/hsx_erp/api/erp'
import { erpTimeLine, formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'

const id = ref(0)
const detail = ref<any>(null)
const loading = ref(false)
const loadError = ref('')

const sourceOrder = computed(() => detail.value?.source_order || null)
const items = computed(() => detail.value?.items || [])
const settlements = computed(() => detail.value?.settlements || [])
const sourceType = computed(() => String(detail.value?.source_type || ''))
const sourceNo = computed(() => detail.value?.batch_no || detail.value?.source_no || sourceOrder.value?.sale_no || sourceOrder.value?.return_no || '-')

onLoad((query: any) => {
    id.value = Number(query?.id || 0)
})

onShow(() => {
    if (id.value > 0) loadDetail()
})

async function loadDetail() {
    if (id.value <= 0) {
        loadError.value = '缺少应收记录 ID'
        return
    }
    loading.value = true
    loadError.value = ''
    try {
        const res: any = await getMobileReceivableInfo(id.value)
        detail.value = res?.data || null
        if (!detail.value) loadError.value = '未找到应收记录'
    } catch (e: any) {
        loadError.value = e?.message || '应收详情加载失败'
    } finally {
        loading.value = false
    }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const sourceLabel = (s: string) => ({ sale: '销售收款', purchase_return: '采购退货退款' }[s] || s || '应收款')
const statusLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const assetLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废', completed: '已完成' }[s] || s || '-')
const assetType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info', completed: 'success' }[s] || 'info')
const refundLabel = (s: string) => ({ receivable: '形成应收', offset: '折账', cash: '现金退款' }[s] || s || '-')
const settlementLabel = (s: string) => ({ receipt: '收款', offset: '折账', payment: '付款' }[s] || s || '-')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.detail-wrap { padding: 16rpx 0 120rpx; }
.form-card .value { word-break: break-all; }
.finance-foot { margin: 8rpx 0 0; padding: 18rpx 0 22rpx; }
.section-title { margin: 28rpx 24rpx 16rpx; font-size: 28rpx; font-weight: 700; color: #0f172a; }
.device-card, .settlement-card { margin-bottom: 20rpx; }
.settlement-amount { font-size: 30rpx; font-weight: 700; color: #16a34a; }
.remark-box { margin: 16rpx 0 ; padding: 18rpx; border-radius: 12rpx; background: #f8fafc; color: #64748b; font-size: 24rpx; line-height: 1.55; }
.empty-card { background: #fff; border-radius: 28rpx; padding: 40rpx 20rpx; margin: 0 24rpx 20rpx; text-align: center; color: #94a3b8; font-size: 26rpx; }
.loading-wrap, .error-wrap { min-height: 420rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20rpx; color: #94a3b8; font-size: 26rpx; }
</style>
