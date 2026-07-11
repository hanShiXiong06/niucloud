<template>
    <view class="finance-source" :class="{ 'finance-source--compact': compact }">
        <view class="finance-source__head">
            <view class="finance-source__tags">
                <u-tag :text="meta.finance_type_name" :type="tagType" plain plainFill size="mini" />
                <text v-if="showBusinessSource" class="finance-source__business">业务来源：{{ meta.business_source_name }}</text>
            </view>
            <view v-if="meta.source_no || showInternalSourceNo" class="finance-source__numbers">
                <text v-if="meta.source_no" class="finance-source__no">来源单号：{{ meta.source_no }}</text>
                <text v-if="showInternalSourceNo" class="finance-source__no finance-source__no--internal">
                    ERP关联单号：{{ meta.internal_source_no }}
                </text>
            </view>
        </view>
        <view class="finance-source__facts">
            <text>业务场景：{{ bizSceneName }}</text>
            <text>资金方向：{{ directionName }}</text>
            <text v-if="meta.channel_name">渠道：{{ meta.channel_name }}</text>
            <text v-if="!compact && meta.party_role_label">往来角色：{{ meta.party_role_label }}</text>
        </view>
        <view v-if="openingSettleMethod || settleSummary" class="finance-source__settlement">
            <text v-if="openingSettleMethod">结算约定：{{ openingSettleMethod }}</text>
            <text v-if="settleSummary">结算进度：{{ settleSummary }}</text>
        </view>
        <view v-if="meta.source_plugin_name" class="finance-source__plugin">来源插件：{{ meta.source_plugin_name }}</view>
        <view v-if="meta.business_reason" class="finance-source__reason">
            <text class="finance-source__reason-label">业务说明：</text>{{ meta.business_reason }}
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
    erpFinanceSourceMeta,
    erpFinanceSourceTagType,
    type ErpFinanceDirection,
} from '@/addon/hsx_erp/hooks/useErpFinanceSource'

const props = withDefaults(defineProps<{
    row?: any
    direction?: ErpFinanceDirection
    compact?: boolean
}>(), {
    row: () => ({}),
    direction: 'payable',
    compact: false,
})

const meta = computed(() => erpFinanceSourceMeta(props.row, props.direction))
const tagType = computed(() => erpFinanceSourceTagType(meta.value))
const showBusinessSource = computed(() => meta.value.business_source_name !== meta.value.finance_type_name)
const showInternalSourceNo = computed(() => Boolean(
    meta.value.internal_source_no
    && meta.value.internal_source_no !== meta.value.source_no
))
const bizSceneName = computed(() => sceneNames[meta.value.biz_scene] || '插件业务')
const directionName = computed(() => {
    const value = String(meta.value.direction || '').toLowerCase()
    return ['expense', 'out', 'payable', 'decrease'].includes(value) || props.direction === 'payable' ? '支出' : '收入'
})
const openingSettleMethod = computed(() => plainText(
    props.row?.opening_settle_method
    || props.row?.settle_method
    || props.row?.source_order?.settle_method
))
const settleSummary = computed(() => plainText(props.row?.settle_summary))

const sceneNames: Record<string, string> = {
    purchase: '采购入库', purchase_asset: '采购入库', sale: '销售出库',
    purchase_return: '采购退货', sale_return: '销售退货',
    after_sale_compensation: '售后补差', refurbish: '设备整备',
    operating: '经营收支', operating_expense: '经营支出', operating_income: '经营收入',
}

function plainText(value: any) {
    const result = String(value ?? '').trim()
    return result === '0' ? '' : result
}
</script>

<style scoped lang="scss">
.finance-source {
    margin-top: 16rpx;
    padding: 18rpx;
    border: 1rpx solid #dbeafe;
    border-radius: 16rpx;
    background: #f8fbff;
}
.finance-source--compact {
    padding: 14rpx 16rpx;
    border-color: #e2e8f0;
    background: #f8fafc;
}
.finance-source__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16rpx;
}
.finance-source__tags {
    display: flex;
    min-width: 0;
    align-items: center;
    flex-wrap: wrap;
    gap: 10rpx;
}
.finance-source__business {
    color: #334155;
    font-size: 22rpx;
    font-weight: 650;
}
.finance-source__numbers {
    max-width: 48%;
    flex-shrink: 0;
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 4rpx;
}
.finance-source__no {
    max-width: 100%;
    overflow: hidden;
    color: #64748b;
    font-size: 20rpx;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.finance-source__no--internal { color: #94a3b8; }
.finance-source__facts {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx 18rpx;
    margin-top: 12rpx;
    color: #64748b;
    font-size: 21rpx;
}
.finance-source__plugin {
    margin-top: 10rpx;
    color: #94a3b8;
    font-size: 20rpx;
}
.finance-source__settlement {
    display: flex;
    flex-direction: column;
    gap: 4rpx;
    margin-top: 10rpx;
    color: #475569;
    font-size: 21rpx;
    line-height: 1.45;
}
.finance-source--compact .finance-source__settlement {
    overflow: hidden;
    color: #64748b;
}
.finance-source__reason {
    margin-top: 12rpx;
    padding-top: 12rpx;
    border-top: 1rpx dashed #cbd5e1;
    color: #475569;
    font-size: 21rpx;
    line-height: 1.55;
}
.finance-source__reason-label { color: #334155; font-weight: 600; }
</style>
