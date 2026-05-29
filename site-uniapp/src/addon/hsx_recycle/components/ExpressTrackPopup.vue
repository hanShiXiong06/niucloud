<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="track-popup">
            <view class="track-popup__header">
                <view>
                    <view class="track-popup__title">查看物流</view>
                    <view class="track-popup__subtitle">实时查询快递轨迹与最新状态</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 track-popup__close" @click="handleClose"></text>
            </view>

            <view class="track-popup__summary">
                <view class="track-popup__summary-row">
                    <text class="track-popup__summary-label">快递公司</text>
                    <text class="track-popup__summary-value">{{ companyName || '-' }}</text>
                </view>
                <view class="track-popup__summary-row">
                    <text class="track-popup__summary-label">快递单号</text>
                    <view class="track-popup__summary-inline">
                        <text class="track-popup__summary-value">{{ expressNo || '-' }}</text>
                        <text
                            v-if="expressNo"
                            class="nc-iconfont nc-icon-fuzhiV6xx1 track-popup__summary-icon"
                            @click.stop="copyExpressNo"
                        ></text>
                    </view>
                </view>
            </view>

            <scroll-view scroll-y class="track-popup__body">
                <view v-if="loading" class="track-popup__state">物流加载中...</view>
                <view v-else-if="errorMessage" class="track-popup__state track-popup__state--error">{{ errorMessage }}</view>
                <view v-else-if="!timeline.length" class="track-popup__state">暂无物流轨迹</view>

                <view v-else class="track-timeline">
                    <view
                        v-for="(item, index) in timeline"
                        :key="`${ item.time || 'time' }-${ index }`"
                        class="track-timeline__item"
                    >
                        <view class="track-timeline__axis">
                            <view class="track-timeline__dot" :class="{ 'track-timeline__dot--active': index === 0 }"></view>
                            <view v-if="index !== timeline.length - 1" class="track-timeline__line"></view>
                        </view>
                        <view class="track-timeline__content">
                            <view class="track-timeline__time">{{ item.time || '--' }}</view>
                            <view class="track-timeline__text">
                                <template
                                    v-for="(segment, segIndex) in parseContextSegments(item.context)"
                                    :key="`${ index }-${ segIndex }`"
                                >
                                    <text
                                        v-if="segment.isPhone"
                                        class="track-timeline__phone"
                                        @click.stop="makePhoneCall(segment.phone)"
                                    >
                                        {{ segment.text }}
                                    </text>
                                    <text v-else>{{ segment.text }}</text>
                                </template>
                            </view>
                        </view>
                    </view>
                </view>
            </scroll-view>

            <view class="track-popup__footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">关闭</u-button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { copy } from '@/utils/common'
import { makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import { queryExpressTrack } from '@/addon/hsx_recycle/api/express'

type TimelineItem = {
    time: string
    context: string
}

type ContextSegment = {
    text: string
    isPhone: boolean
    phone: string
}

const props = withDefaults(defineProps<{
    visible: boolean
    expressNo?: string
    mobile?: string
    companyName?: string
}>(), {
    expressNo: '',
    mobile: '',
    companyName: ''
})

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
}>()

const show = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const timeline = ref<TimelineItem[]>([])
const mobilePhoneReg = /1[3-9]\d{9}/g

watch(() => props.visible, async (value) => {
    show.value = value
    if (value) {
        await loadTrack()
    } else {
        resetState()
    }
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const resetState = () => {
    loading.value = false
    errorMessage.value = ''
    timeline.value = []
}

const handleClose = () => {
    if (loading.value) return
    show.value = false
}

const copyExpressNo = () => {
    if (!props.expressNo) return
    copy(props.expressNo)
}

const formatTimestamp = (value: number | string) => {
    const numeric = Number(value)
    if (!Number.isFinite(numeric) || numeric <= 0) return ''
    const timestamp = numeric > 9999999999 ? numeric : numeric * 1000
    const date = new Date(timestamp)
    if (Number.isNaN(date.getTime())) return ''
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hour = String(date.getHours()).padStart(2, '0')
    const minute = String(date.getMinutes()).padStart(2, '0')
    const second = String(date.getSeconds()).padStart(2, '0')
    return `${year}-${month}-${day} ${hour}:${minute}:${second}`
}

const normalizeTrack = (payload: any): TimelineItem[] => {
    const rawList = Array.isArray(payload)
        ? payload
        : Array.isArray(payload?.logisticsTraceDetailList)
            ? payload.logisticsTraceDetailList
            : Array.isArray(payload?.list)
                ? payload.list
                : []

    return rawList.map((item: any) => {
        const time = typeof item?.time === 'string'
            ? item.time
            : typeof item?.timeDesc === 'string'
                ? item.timeDesc
                : formatTimestamp(item?.time || item?.datetime || 0)

        const context = typeof item?.context === 'string'
            ? item.context
            : typeof item?.desc === 'string'
                ? item.desc
                : typeof item?.remark === 'string'
                    ? item.remark
                    : ''

        return {
            time: time || '--',
            context: context || '--'
        }
    }).filter((item: TimelineItem) => item.time !== '--' || item.context !== '--')
}

const parseContextSegments = (context: string): ContextSegment[] => {
    const content = String(context || '').trim()
    if (!content) return [{ text: '--', isPhone: false, phone: '' }]

    const segments: ContextSegment[] = []
    let lastIndex = 0
    mobilePhoneReg.lastIndex = 0

    let match: RegExpExecArray | null = null
    while ((match = mobilePhoneReg.exec(content)) !== null) {
        const phone = match[0]
        const start = match.index

        if (start > lastIndex) {
            segments.push({
                text: content.slice(lastIndex, start),
                isPhone: false,
                phone: ''
            })
        }

        segments.push({
            text: phone,
            isPhone: true,
            phone
        })

        lastIndex = start + phone.length
    }

    if (lastIndex < content.length) {
        segments.push({
            text: content.slice(lastIndex),
            isPhone: false,
            phone: ''
        })
    }

    return segments.length ? segments : [{ text: content, isPhone: false, phone: '' }]
}

const loadTrack = async () => {
    if (!props.expressNo) {
        errorMessage.value = '暂无快递单号'
        timeline.value = []
        return
    }

    loading.value = true
    errorMessage.value = ''
    timeline.value = []

    try {
        const res: any = await queryExpressTrack({
            express_code: props.expressNo,
            mobile: props.mobile || ''
        })
        const list = normalizeTrack(res?.data)
        timeline.value = list
        if (!list.length) {
            errorMessage.value = ''
        }
    } catch (error: any) {
        errorMessage.value = error?.msg || error?.message || '查询物流失败'
    } finally {
        loading.value = false
    }
}
</script>

<style scoped lang="scss">
.track-popup {
    height: 78vh;
    max-height: 78vh;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.track-popup__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    padding: 30rpx;
    border-bottom: 1rpx solid #eef2f7;
}

.track-popup__title {
    font-size: 32rpx;
    font-weight: 700;
    color: #111827;
}

.track-popup__subtitle {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.track-popup__close {
    flex-shrink: 0;
    font-size: 32rpx;
    color: #64748b;
}

.track-popup__summary {
    margin: 24rpx 24rpx 0;
    padding: 22rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}

.track-popup__summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.track-popup__summary-row + .track-popup__summary-row {
    margin-top: 14rpx;
}

.track-popup__summary-label {
    flex-shrink: 0;
    font-size: 23rpx;
    color: #64748b;
}

.track-popup__summary-value {
    font-size: 24rpx;
    font-weight: 600;
    color: #1f2937;
    word-break: break-all;
    text-align: right;
}

.track-popup__summary-inline {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10rpx;
}

.track-popup__summary-icon {
    color: #2563eb;
    font-size: 24rpx;
}

.track-popup__body {
    flex: 1;
    min-height: 0;
    margin-top: 18rpx;
    padding: 0 24rpx;
    box-sizing: border-box;
}

.track-popup__state {
    padding: 140rpx 0;
    text-align: center;
    font-size: 24rpx;
    color: #94a3b8;
}

.track-popup__state--error {
    color: #ef4444;
}

.track-timeline {
    padding: 10rpx 0 24rpx;
}

.track-timeline__item {
    display: flex;
    gap: 18rpx;
}

.track-timeline__axis {
    width: 24rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.track-timeline__dot {
    width: 16rpx;
    height: 16rpx;
    margin-top: 8rpx;
    border-radius: 50%;
    background: #cbd5e1;
}

.track-timeline__dot--active {
    background: #2563eb;
    box-shadow: 0 0 0 8rpx rgba(37, 99, 235, 0.12);
}

.track-timeline__line {
    flex: 1;
    width: 2rpx;
    margin-top: 10rpx;
    background: #e2e8f0;
}

.track-timeline__content {
    flex: 1;
    min-width: 0;
    padding-bottom: 28rpx;
}

.track-timeline__time {
    font-size: 22rpx;
    color: #64748b;
}

.track-timeline__text {
    margin-top: 8rpx;
    font-size: 25rpx;
    line-height: 38rpx;
    color: #1f2937;
    word-break: break-word;
}

.track-timeline__phone {
    color: #2563eb;
}

.track-popup__footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #eef2f7;
    background: #fff;
}
</style>
