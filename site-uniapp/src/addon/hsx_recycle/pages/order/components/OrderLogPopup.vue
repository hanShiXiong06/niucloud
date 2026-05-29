<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="log-popup">
            <view class="log-popup__header">
                <view>
                    <view class="log-popup__title">{{ title }}</view>
                    <view class="log-popup__subtitle">{{ type === 'payment' ? '查看本订单的打款记录' : '查看本订单的通知记录' }}</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="handleClose"></text>
            </view>

            <scroll-view scroll-y class="log-popup__content">
                <view v-if="loading" class="log-popup__state">加载中...</view>
                <view v-else-if="!records.length" class="log-popup__state">暂无记录</view>

                <view v-else class="log-popup__list">
                    <view v-for="(item, index) in records" :key="item.id || index" class="log-card">
                        <template v-if="type === 'payment'">
                            <view class="log-card__top">
                                <text class="log-card__title">¥{{ formatMoney(item.amount || 0) }}</text>
                                <text class="log-card__time">{{ formatTime(item.pay_time || item.create_at) }}</text>
                            </view>
                            <view class="log-card__meta">
                                <text>批次：{{ item.pay_no || '-' }}</text>
                                <text>方式：{{ item.pay_type || '-' }}</text>
                            </view>
                            <view class="log-card__meta">
                                <text>设备：{{ item.device_model || '-' }}</text>
                                <text>IMEI：{{ item.device_imei || '-' }}</text>
                            </view>
                            <view class="log-card__meta">
                                <text>账户：{{ item.pay_account || '-' }}</text>
                                <text>操作人：{{ item.operator?.real_name || item.operator?.username || '-' }}</text>
                            </view>
                            <view v-if="item.pay_remark" class="log-card__remark">{{ item.pay_remark }}</view>
                            <view v-if="getPaymentImages(item).length" class="log-card__images">
                                <view
                                    v-for="(imageUrl, imageIndex) in getPaymentImages(item)"
                                    :key="`${ item.id || index }-${ imageIndex }`"
                                    class="log-card__image-item"
                                    @click="previewImages(getPaymentImages(item), imageIndex)"
                                >
                                    <image class="log-card__image" :src="imageUrl" mode="aspectFill" />
                                </view>
                            </view>
                        </template>

                        <template v-else>
                            <view class="log-card__top">
                                <text class="log-card__title">{{ item.scene_name || '订单通知' }}</text>
                                <text class="log-card__status" :class="getNoticeStatusClass(item.status)">{{ item.status_name || '-' }}</text>
                            </view>
                            <view class="log-card__meta">
                                <text>触发时间：{{ item.create_time_text || formatTime(item.create_at) }}</text>
                            </view>
                            <view class="log-card__meta">
                                <text>发送时间：{{ item.send_time_text || '-' }}</text>
                                <text>设备：{{ getNoticeDeviceText(item) }}</text>
                            </view>
                            <view v-if="item.notice_key" class="log-card__meta">
                                <text>通知标识：{{ item.notice_key }}</text>
                            </view>
                            <view v-if="item.fail_reason" class="log-card__remark log-card__remark--danger">{{ item.fail_reason }}</view>
                        </template>
                    </view>
                </view>
            </scroll-view>

            <view class="log-popup__footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">关闭</u-button>
            </view>
        </view>
    </u-popup>

    <ImagePreviewOverlay
        v-model:visible="previewVisible"
        :urls="previewUrls"
        :current="previewCurrent"
        @change="previewCurrent = $event"
    />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { formatMoney, formatTime } from '@/addon/hsx_recycle/utils/helper'
import ImagePreviewOverlay from '@/addon/hsx_recycle/components/ImagePreviewOverlay.vue'

const props = withDefaults(defineProps<{
    visible: boolean
    title: string
    records?: any[]
    loading?: boolean
    type?: 'payment' | 'notice'
}>(), {
    records: () => [],
    loading: false,
    type: 'payment'
})

const emit = defineEmits(['update:visible'])

const show = ref(false)
const previewVisible = ref(false)
const previewUrls = ref<string[]>([])
const previewCurrent = ref(0)

const records = computed(() => Array.isArray(props.records) ? props.records : [])
const type = computed(() => props.type || 'payment')

watch(() => props.visible, (value) => {
    show.value = value
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const handleClose = () => {
    show.value = false
    emit('update:visible', false)
}

const getPaymentImages = (item: any) => {
    return String(item?.payment_images || '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean)
        .map((value) => img(value))
}

const previewImages = (images: string[], index: number) => {
    if (!images.length) return
    previewUrls.value = images
    previewCurrent.value = index
    previewVisible.value = true
}

const getNoticeStatusClass = (status: number | string) => {
    const value = Number(status || 0)
    if (value === 1) return 'log-card__status--success'
    if (value === 2) return 'log-card__status--danger'
    if (value === 3) return 'log-card__status--muted'
    return 'log-card__status--warning'
}

const getNoticeDeviceText = (item: any) => {
    const deviceIds = Array.isArray(item?.device_ids) ? item.device_ids : []
    return deviceIds.length ? `${deviceIds.length}台` : '整单'
}
</script>

<style scoped lang="scss">
.log-popup {
    height: 78vh;
    max-height: 78vh;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.log-popup__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    padding: 30rpx;
    border-bottom: 1rpx solid #f2f3f5;
    flex-shrink: 0;
}

.log-popup__title {
    font-size: 32rpx;
    font-weight: 600;
    color: #1f2937;
}

.log-popup__subtitle {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.log-popup__content {
    flex: 1;
    height: 0;
    min-height: 0;
    padding: 0 30rpx;
    box-sizing: border-box;
    overflow: hidden;
}

.log-popup__state {
    padding: 120rpx 0;
    text-align: center;
    font-size: 24rpx;
    color: #94a3b8;
}

.log-popup__list {
    padding: 24rpx 0 12rpx;
}

.log-card {
    padding: 22rpx 24rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}

.log-card + .log-card {
    margin-top: 18rpx;
}

.log-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.log-card__title {
    font-size: 26rpx;
    font-weight: 600;
    color: #1f2937;
}

.log-card__time {
    font-size: 20rpx;
    color: #94a3b8;
    flex-shrink: 0;
}

.log-card__status {
    padding: 6rpx 14rpx;
    border-radius: 999rpx;
    font-size: 20rpx;
}

.log-card__status--success {
    color: #15803d;
    background: #dcfce7;
}

.log-card__status--danger {
    color: #dc2626;
    background: #fee2e2;
}

.log-card__status--warning {
    color: #b45309;
    background: #fef3c7;
}

.log-card__status--muted {
    color: #64748b;
    background: #e2e8f0;
}

.log-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10rpx 24rpx;
    margin-top: 14rpx;
    font-size: 22rpx;
    color: #475569;
    line-height: 1.6;
}

.log-card__remark {
    margin-top: 14rpx;
    font-size: 22rpx;
    color: #475569;
    line-height: 1.7;
}

.log-card__remark--danger {
    color: #dc2626;
}

.log-card__images {
    display: flex;
    flex-wrap: wrap;
    gap: 14rpx;
    margin-top: 16rpx;
}

.log-card__image-item {
    width: 132rpx;
    height: 132rpx;
    border-radius: 12rpx;
    overflow: hidden;
    background: #e2e8f0;
}

.log-card__image {
    width: 100%;
    height: 100%;
}

.log-popup__footer {
    display: flex;
    gap: 16rpx;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
    background: #fff;
    flex-shrink: 0;
}
</style>
