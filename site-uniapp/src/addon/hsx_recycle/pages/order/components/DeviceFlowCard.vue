<template>
    <view class="device-flow-card" :class="[selected ? 'device-flow-card--selected' : '']">
        <view class="device-flow-card__header">
            <view class="device-flow-card__title-wrap">
                <view
                    v-if="selectable"
                    class="device-selector"
                    :class="[selected ? 'device-selector--active' : '']"
                    @click.stop="emit('toggle-select')"
                ></view>
                <view class="device-flow-card__title-main">
                    <view class="device-flow-card__title">{{ device.model || device.device_name || device.model_name || '未知型号' }}</view>
                </view>
            </view>

            <view class="device-flow-card__price-wrap">
                <view class="device-flow-card__price">{{ priceLabel }}</view>
                <view v-if="priceSubLabel" class="device-flow-card__price-sub">{{ priceSubLabel }}</view>
            </view>
        </view>

        <view class="device-flow-card__meta">
            <view class="device-flow-card__meta-row">
                <text class="meta-label">IMEI</text>
                <view class="meta-copy" @click.stop="copyIMEI(device.imei)">
                    <text class="meta-value">{{ device.imei || '-' }}</text>
                    <text v-if="device.imei" class="nc-iconfont nc-icon-fuzhiV6xx1 meta-copy__icon"></text>
                </view>
            </view>
            <view v-if="device.user_sn" class="device-flow-card__meta-row">
                <text class="meta-label">用户串号</text>
                <view class="meta-copy" @click.stop="copyIMEI(device.user_sn)">
                    <text class="meta-value">{{ device.user_sn }}</text>
                    <text class="nc-iconfont nc-icon-fuzhiV6xx1 meta-copy__icon"></text>
                </view>
            </view>
        </view>

        <view class="device-flow-card__tags">
            <!-- 只显示当前阶段对应的那一个状态：代卖 > 打款 > 确认 > 质检 -->
            <u-tag v-if="stageTag" :text="stageTag.text" :type="stageTag.type" plain plainFill size="mini"></u-tag>
            <DeviceStatusBadge v-else :status="device.status" :status-name="device.status_name" />
        </view>

        <view v-if="summaryItems.length" class="device-flow-card__summary">
            <u-tag
                v-for="item in summaryItems"
                :key="item.label"
                :text="`${ item.label }：${ item.value }`"
                type="info"
                plain
                size="mini"
            ></u-tag>
        </view>

        <RecycleCheckSummary v-if="deviceCheckMeta" :meta="deviceCheckMeta" class="device-flow-card__check" />

        <view v-if="!isReturned && device.pay_disabled_reason && !device.can_pay" class="device-flow-card__hint device-flow-card__hint--danger">
            {{ device.pay_disabled_reason }}
        </view>
        <view v-if="!isReturned && device.confirm_disabled_reason && !device.can_confirm && showConfirmTag" class="device-flow-card__hint">
            {{ device.confirm_disabled_reason }}
        </view>

        <view class="device-flow-card__flow"  v-if="flowHighlights.length">
            <view class="device-flow-card__flow-head" @click="expanded = !expanded">
                <view class="device-flow-card__flow-title">设备流转</view>
                <!-- <view class="device-flow-card__flow-toggle">
                    <text>{{ expanded ? '收起' : '展开查看' }}</text>
                    <text class="nc-iconfont" :class="[expanded ? 'nc-icon-shangV6xx1' : 'nc-icon-xiaV6xx1']"></text>
                </view> -->
            </view>

            <view class="device-flow-card__flow-highlight">
                <view v-for="item in flowHighlights" :key="`${ item.label }-${ item.value }`" class="flow-node">
                    <text class="flow-node__label">{{ item.label }}</text>
                    <text class="flow-node__value">{{ item.value }}</text>
                </view>
            </view>

            <view v-if="expanded" class="device-flow-card__flow-body">
                <view v-if="device.check_at || inspectorName" class="device-flow-card__info-list">
                    <view v-if="device.check_at" class="device-flow-card__info-row">
                        <text class="info-label">质检时间</text>
                        <text class="info-value">{{ formatTime(device.check_at) }}</text>
                    </view>
                    <view v-if="inspectorName" class="device-flow-card__info-row">
                        <text class="info-label">质检员</text>
                        <text class="info-value">{{ inspectorName }}</text>
                    </view>
                    <view v-if="device.price_remark" class="device-flow-card__info-row device-flow-card__info-row--top">
                        <text class="info-label">报价备注</text>
                        <text class="info-value">{{ device.price_remark }}</text>
                    </view>
                </view>

                <view v-if="isConsigned && consignmentRows.length" class="device-flow-card__info-list">
                    <view v-for="item in consignmentRows" :key="item.label" class="device-flow-card__info-row">
                        <text class="info-label">{{ item.label }}</text>
                        <text class="info-value">{{ item.value }}</text>
                    </view>
                </view>

                <view v-if="imageGroups.length" class="device-flow-card__image-groups">
                    <view v-for="group in imageGroups" :key="group.label" class="image-group">
                        <view class="image-group__title">{{ group.label }}</view>
                        <view class="image-group__list">
                            <view
                                v-for="(item, index) in group.items"
                                :key="`${ group.label }-${ index }`"
                                class="image-group__item"
                                @click="previewGroup(group.items, index)"
                            >
                                <image class="image-group__img" :src="item.thumb || item.url" mode="aspectFill" />
                            </view>
                        </view>
                    </view>
                </view>

                <!-- <view class="timeline">
                    <view class="timeline__title">完整流转记录</view>
                    
                    <view v-if="logs.length" class="timeline__list">
                        <view v-for="(log, index) in logs" :key="log.id || `${ log.source_type }-${ index }`" class="timeline__item">
                            <view class="timeline__line-wrap">
                                <view class="timeline__dot"></view>
                                <view v-if="index < logs.length - 1" class="timeline__line"></view>
                            </view>
                            <view class="timeline__content">
                                <view class="timeline__top">
                                    <text class="timeline__status">{{ log.status_name || log.operation_type || '状态更新' }}</text>
                                    <text class="timeline__time">{{ formatTime(log.create_at) }}</text>
                                </view>
                                <view class="timeline__operator">{{ log.operator_name || '系统' }}</view>
                                <view v-if="log.remark" class="timeline__remark">{{ log.remark }}</view>
                            </view>
                        </view>
                    </view>
                    <view v-else class="timeline__empty">暂无完整流转记录</view>
                </view> -->
            </view>
        </view>

        <view v-if="actions.length" class="device-flow-card__actions">
            <view
                v-for="action in actions"
                :key="action.type"
                class="action-btn"
                :class="[action.primary ? 'action-btn--primary' : '', action.danger ? 'action-btn--danger' : '']"
                @click.stop="emit('action', action.type)"
            >
                {{ action.label }}
            </view>
        </view>
    </view>

</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { img } from '@/utils/common'
import DeviceStatusBadge from '@/addon/hsx_recycle/components/DeviceStatusBadge.vue'
import RecycleCheckSummary from '@/addon/hsx_recycle/components/RecycleCheckSummary.vue'
import { previewImages as openPreview } from '@/addon/hsx_recycle/utils/preview'
import { formatMoney, formatTime } from '@/addon/hsx_recycle/utils/helper'
import { copyIMEI } from '@/addon/hsx_recycle/utils/clipboard'
import {
    buildDeviceFlowHighlights,
    getDevicePriceLabel,
    getDevicePriceSubLabel,
    isConsignedDevice,
    isReturnedDevice,
    shouldShowConfirmStatus
} from '@/addon/hsx_recycle/utils/device'

interface SummaryItem {
    label: string
    value: string
}

interface ActionItem {
    label: string
    type: string
    primary?: boolean
    danger?: boolean
}

interface ImageItem {
    url: string
    thumb: string
}

const props = withDefaults(defineProps<{
    device: Record<string, any>
    summaryItems?: SummaryItem[]
    actions?: ActionItem[]
    selectable?: boolean
    selected?: boolean
}>(), {
    summaryItems: () => [],
    actions: () => [],
    selectable: false,
    selected: false
})

const emit = defineEmits(['action', 'toggle-select'])

const expanded = ref(false)

const device = computed(() => props.device || {})
const isConsigned = computed(() => isConsignedDevice(device.value))
const isReturned = computed(() => isReturnedDevice(device.value))
const priceLabel = computed(() => getDevicePriceLabel(device.value))
const priceSubLabel = computed(() => getDevicePriceSubLabel(device.value))
const showConfirmTag = computed(() => !isConsigned.value && shouldShowConfirmStatus(device.value) && Boolean(device.value.confirm_status_name))
// 打款状态只在「客户已确认」之后才显示（确认前不显示是否打款）
const showPayTag = computed(() => !isConsigned.value && Number(device.value.confirm_status) === 1 && Boolean(device.value.pay_status_name))

// 质检结果（结构化 check_meta），统一用 RecycleCheckSummary 展示
const deviceCheckMeta = computed(() => {
    const parse = (v: any): Record<string, any> => {
        if (!v) return {}
        if (typeof v === 'string') { try { const p = JSON.parse(v); return p && typeof p === 'object' && !Array.isArray(p) ? p : {} } catch { return {} } }
        return typeof v === 'object' && !Array.isArray(v) ? v : {}
    }
    const meta = parse(parse(device.value.info).check_meta)
    return Array.isArray(meta.result_items) && meta.result_items.length ? meta : null
})

// 当前阶段状态（只显示一个）：已退回 > 代卖 > 打款 > 确认 > 质检基础状态
const stageTag = computed<{ text: string, type: string } | null>(() => {
    if (isReturned.value) {
        return { text: device.value.status_name || device.value.dispose_status_name || '已退回', type: 'error' }
    }
    if (isConsigned.value) {
        return { text: device.value.consignmentOrder?.status_name || device.value.dispose_status_name || '已转代卖', type: 'warning' }
    }
    if (showPayTag.value) return { text: String(device.value.pay_status_name), type: 'success' }
    if (showConfirmTag.value) return { text: String(device.value.confirm_status_name), type: 'warning' }
    return null
})
const logs = computed(() => Array.isArray(device.value.logs) ? device.value.logs : [])
const flowHighlights = computed(() => buildDeviceFlowHighlights(device.value))
const inspectorName = computed(() => device.value.checkUser?.real_name || device.value.checkUser?.username || '')

const consignmentRows = computed(() => {
    const order = device.value.consignmentOrder || {}
    if (!isConsigned.value) return []

    const rows: Array<{ label: string, value: string }> = []
    if (order.consignment_no) rows.push({ label: '代卖单号', value: order.consignment_no })
    if (order.status_name) rows.push({ label: '代卖状态', value: order.status_name })
    if (Number(order.listing_price || 0) > 0) rows.push({ label: '挂牌价', value: `¥${formatMoney(order.listing_price || 0)}` })
    if (Number(order.sold_price || 0) > 0) rows.push({ label: '成交价', value: `¥${formatMoney(order.sold_price || 0)}` })
    if (Number(order.settlement_amount || 0) > 0) rows.push({ label: '结算金额', value: `¥${formatMoney(order.settlement_amount || 0)}` })
    if (order.pay_status_name) rows.push({ label: '结算状态', value: order.pay_status_name })
    return rows
})

const imageGroups = computed(() => {
    const groups: Array<{ label: string, items: ImageItem[] }> = []
    const sellerImages = buildImageItems(device.value.check_images_seller || device.value.check_images, device.value.check_images_seller_thumb_small || device.value.check_images_thumb_small)
    const buyerImages = buildImageItems(device.value.check_images_buyer, device.value.check_images_buyer_thumb_small)
    const summaryImages = buildImageItems(device.value.check_images, device.value.check_images_thumb_small)

    if (sellerImages.length) groups.push({ label: '卖家质检图片', items: sellerImages })
    if (buyerImages.length) groups.push({ label: '买家质检图片', items: buyerImages })
    if (!groups.length && summaryImages.length) groups.push({ label: '质检图片', items: summaryImages })

    return groups
})

const buildImageItems = (rawValue: any, thumbs: any) => {
    const urls = String(rawValue || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .map((item) => img(item))

    const thumbList = Array.isArray(thumbs)
        ? thumbs.map((item) => img(item))
        : []

    return urls.map((url, index) => ({
        url,
        thumb: thumbList[index] || url
    }))
}

const previewGroup = (items: ImageItem[], index: number) => {
    openPreview(items.map((item) => item.url), index)
}
</script>

<style scoped lang="scss">
.device-flow-card {
    padding: 24rpx 0;
}

.device-flow-card + .device-flow-card {
    border-top: 1rpx solid #f1f5f9;
}

.device-flow-card--selected {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.03), rgba(59, 130, 246, 0.02));
}

.device-flow-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24rpx;
}

.device-flow-card__title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 14rpx;
    flex: 1;
    min-width: 0;
}

.device-flow-card__title-main {
    flex: 1;
    min-width: 0;
}

.device-selector {
    width: 32rpx;
    height: 32rpx;
    margin-top: 4rpx;
    border-radius: 50%;
    border: 2rpx solid #cbd5e1;
    background: #fff;
    flex-shrink: 0;
}

.device-selector--active {
    border-color: var(--hsx-primary);
    background: var(--hsx-primary);
    box-shadow: inset 0 0 0 6rpx #fff;
}

.device-flow-card__title {
    font-size: 28rpx;
    font-weight: 600;
    color: #1f2937;
}

.device-flow-card__category {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.device-flow-card__price-wrap {
    text-align: right;
    flex-shrink: 0;
}

.device-flow-card__price {
    font-size: 30rpx;
    font-weight: 700;
    color: #ea580c;
}

.device-flow-card__price-sub {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #94a3b8;
}

.device-flow-card__meta {
    margin-top: 16rpx;
}

.device-flow-card__meta-row,
.device-flow-card__info-row {
    display: flex;
    align-items: center;
    gap: 18rpx;
    margin-top: 8rpx;
    font-size: 22rpx;
}

.device-flow-card__info-row--top {
    align-items: flex-start;
}

.meta-copy {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8rpx;
    min-width: 0;
}

.meta-copy__icon {
    flex-shrink: 0;
    color: var(--hsx-primary);
    font-size: 24rpx;
}

.meta-label,
.info-label {
    width: 100rpx;
    color: #94a3b8;
    flex-shrink: 0;
}

.meta-value,
.info-value {
    flex: 1;
    color: #475569;
    word-break: break-all;
}

.device-flow-card__tags,
.device-flow-card__summary,
.device-flow-card__actions,
.device-flow-card__flow-highlight,
.image-group__list {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
}

.device-flow-card__tags,
.device-flow-card__summary,
.device-flow-card__actions {
    margin-top: 14rpx;
}

.tag,
.summary-chip {
    padding: 6rpx 14rpx;
    border-radius: 999rpx;
    font-size: 20rpx;
}

.tag--status {
    background: var(--hsx-primary-100);
    color: var(--hsx-primary-dark);
}

.tag--warning {
    background: #fef3c7;
    color: #b45309;
}

.tag--success {
    background: #dcfce7;
    color: #15803d;
}

.tag--purple {
    background: #ede9fe;
    color: #6d28d9;
}

.summary-chip {
    background: #f8fafc;
    color: #475569;
}

.device-flow-card__check-result,
.device-flow-card__flow {
    margin-top: 16rpx;
    padding: 18rpx;
    border-radius: 14rpx;
    background: #f8fafc;
}

.device-flow-card__check-result {
    color: #475569;
    font-size: 22rpx;
    line-height: 1.7;
}

.device-flow-card__check-line + .device-flow-card__check-line {
    margin-top: 8rpx;
}

.device-flow-card__hint {
    margin-top: 14rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.6;
}

.device-flow-card__hint--danger {
    color: #dc2626;
}

.device-flow-card__flow-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24rpx;
}

.device-flow-card__flow-title,
.timeline__title {
    font-size: 24rpx;
    font-weight: 600;
    color: #1f2937;
}

.device-flow-card__flow-toggle {
    display: flex;
    align-items: center;
    gap: 8rpx;
    font-size: 22rpx;
    color: var(--hsx-primary);
}

.device-flow-card__flow-highlight {
    margin-top: 16rpx;
}

.flow-node {
    min-width: 148rpx;
    padding: 16rpx 18rpx;
    border-radius: 12rpx;
    background: #fff;
}

.flow-node__label {
    display: block;
    font-size: 20rpx;
    color: #94a3b8;
}

.flow-node__value {
    display: block;
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #334155;
    line-height: 1.5;
}

.device-flow-card__flow-body {
    margin-top: 18rpx;
}

.device-flow-card__info-list + .device-flow-card__info-list,
.timeline,
.image-group + .image-group {
    margin-top: 18rpx;
}

.image-group__title {
    font-size: 22rpx;
    color: #334155;
    margin-bottom: 12rpx;
}

.image-group__item {
    width: 132rpx;
    height: 132rpx;
    border-radius: 12rpx;
    overflow: hidden;
    background: #e5e7eb;
}

.image-group__img {
    width: 100%;
    height: 100%;
}

.timeline__list {
    margin-top: 14rpx;
}

.timeline__item {
    display: flex;
    gap: 18rpx;
}

.timeline__item + .timeline__item {
    margin-top: 18rpx;
}

.timeline__line-wrap {
    position: relative;
    width: 24rpx;
    flex-shrink: 0;
    display: flex;
    justify-content: center;
}

.timeline__dot {
    width: 16rpx;
    height: 16rpx;
    margin-top: 6rpx;
    border-radius: 50%;
    background: var(--hsx-primary);
}

.timeline__line {
    position: absolute;
    top: 28rpx;
    bottom: -18rpx;
    width: 2rpx;
    background: var(--hsx-primary-100);
}

.timeline__content {
    flex: 1;
    min-width: 0;
}

.timeline__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.timeline__status {
    font-size: 22rpx;
    font-weight: 600;
    color: #1f2937;
}

.timeline__time {
    font-size: 20rpx;
    color: #94a3b8;
    flex-shrink: 0;
}

.timeline__operator {
    margin-top: 6rpx;
    font-size: 20rpx;
    color: #64748b;
}

.timeline__remark,
.timeline__empty {
    margin-top: 8rpx;
    font-size: 21rpx;
    color: #475569;
    line-height: 1.6;
}

.action-btn {
    padding: 12rpx 22rpx;
    border-radius: 999rpx;
    font-size: 22rpx;
    color: #475569;
    background: #fff;
    border: 1rpx solid #e2e8f0;
}

.action-btn--primary {
    color: #fff;
    background: var(--hsx-primary);
    border-color: var(--hsx-primary);
}

.action-btn--danger {
    color: #dc2626;
    background: #fff1f2;
    border-color: #fecdd3;
}
</style>
