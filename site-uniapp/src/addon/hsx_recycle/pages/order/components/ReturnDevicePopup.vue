<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="return-popup">
            <view class="popup-header">
                <view>
                    <view class="popup-title">退回设备</view>
                    <view class="popup-subtitle">将创建或追加到该订单的退回单</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="handleClose"></text>
            </view>

            <scroll-view scroll-y class="popup-body">
                <view class="warning-box">
                    <view class="warning-title">确认拒绝回收？</view>
                    <view class="warning-text">
                        PC 端逻辑会按订单聚合处理：同一订单已有退回单时追加设备，没有退回单时自动创建退回单。
                    </view>
                </view>

                <view class="section">
                    <view class="section-header">
                        <view class="section-title">本次退回设备</view>
                        <view class="section-count">{{ selectedDevices.length }} 台</view>
                    </view>
                    <view v-for="device in selectedDevices" :key="device.id" class="device-row">
                        <view class="device-main">
                            <view class="device-model">{{ device.model || device.device_name || '未知型号' }}</view>
                            <view class="device-meta">
                                <text>{{ device.imei || device.user_sn || '-' }}</text>
                                <text v-if="device.status_name"> / {{ device.status_name }}</text>
                            </view>
                        </view>
                        <view v-if="Number(device.final_price || 0) > 0" class="device-price">¥{{ formatMoney(device.final_price) }}</view>
                    </view>
                </view>

                <view class="section">
                    <view class="section-title">退回原因</view>
                    <u-textarea
                        v-model="remark"
                        placeholder="如：用户不同意报价、设备异常、客户不卖了"
                        :maxlength="200"
                        :height="140"
                        count
                    />
                    <view class="form-tip">原因会写入设备流转日志和退回单设备备注。</view>
                </view>
            </scroll-view>

            <view class="popup-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1, marginRight: '20rpx' }">取消</u-button>
                <u-button type="error" @click="handleSubmit" :customStyle="{ flex: 2 }" :loading="submitting">
                    确认退回
                </u-button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { batchReturnDevices } from '@/addon/hsx_recycle/api/order'
import { getReturnOrderList } from '@/addon/hsx_recycle/api/return-order'
import { formatMoney } from '@/addon/hsx_recycle/utils/helper'

const props = withDefaults(defineProps<{
    visible: boolean
    devices?: any[]
    orderId?: number | string
}>(), {
    devices: () => [],
    orderId: ''
})

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
    (event: 'success', value: { returnOrderId: number | string, deviceIds: Array<number | string> }): void
}>()

const show = ref(false)
const remark = ref('')
const submitting = ref(false)

const selectedDevices = computed(() => Array.isArray(props.devices) ? props.devices.filter(item => item?.id) : [])
const selectedDeviceIds = computed(() => selectedDevices.value.map(item => item.id))

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        remark.value = ''
    }
})

const handleClose = () => {
    if (submitting.value) return
    emit('update:visible', false)
}

const handleSubmit = async () => {
    if (!selectedDeviceIds.value.length) {
        uni.showToast({ title: '请选择退回设备', icon: 'none' })
        return
    }

    submitting.value = true
    try {
        await batchReturnDevices({
            ids: selectedDeviceIds.value.join(','),
            remark: remark.value.trim() || (selectedDeviceIds.value.length > 1 ? '商户批量拒绝回收' : '商户拒绝回收')
        })
        const returnOrderId = await findReturnOrderId()
        uni.showToast({ title: '已创建退回处理', icon: 'success' })
        emit('update:visible', false)
        emit('success', {
            returnOrderId,
            deviceIds: selectedDeviceIds.value
        })
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '退回失败', icon: 'none' })
    } finally {
        submitting.value = false
    }
}

const findReturnOrderId = async () => {
    if (!props.orderId) return ''
    try {
        const res: any = await getReturnOrderList({
            page: 1,
            limit: 1,
            order_id: props.orderId,
            order_no: '',
            express_no: '',
            status: '',
            create_at: []
        })
        const rows = res?.data?.data || []
        return rows[0]?.id || ''
    } catch (error) {
        return ''
    }
}
</script>

<style scoped lang="scss">
.return-popup {
    max-height: 82vh;
    display: flex;
    flex-direction: column;
    background: #f6f7fb;
}

.popup-header {
    flex-shrink: 0;
    padding: 28rpx 30rpx 22rpx;
    background: #fff;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 1rpx solid #eef2f7;
}

.popup-title {
    font-size: 32rpx;
    line-height: 40rpx;
    font-weight: 800;
    color: #111827;
}

.popup-subtitle {
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.popup-close {
    flex-shrink: 0;
    font-size: 32rpx;
    color: #64748b;
}

.popup-body {
    flex: 1;
    min-height: 0;
    padding: 20rpx 24rpx;
    box-sizing: border-box;
}

.warning-box,
.section {
    padding: 22rpx;
    border-radius: 16rpx;
    background: #fff;
    margin-bottom: 18rpx;
}

.warning-box {
    border: 1rpx solid #fed7aa;
    background: #fff7ed;
}

.warning-title {
    font-size: 26rpx;
    font-weight: 700;
    color: #c2410c;
}

.warning-text {
    margin-top: 8rpx;
    font-size: 23rpx;
    line-height: 34rpx;
    color: #9a3412;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14rpx;
}

.section-title {
    font-size: 26rpx;
    font-weight: 700;
    color: #1f2937;
}

.section-count {
    font-size: 22rpx;
    color: #64748b;
}

.device-row {
    display: flex;
    align-items: center;
    gap: 16rpx;
    padding: 16rpx 0;
    border-top: 1rpx solid #f1f5f9;
}

.device-row:first-of-type {
    border-top: 0;
}

.device-main {
    flex: 1;
    min-width: 0;
}

.device-model {
    font-size: 26rpx;
    line-height: 34rpx;
    font-weight: 700;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.device-meta {
    margin-top: 4rpx;
    font-size: 22rpx;
    line-height: 30rpx;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.device-price {
    flex-shrink: 0;
    font-size: 25rpx;
    font-weight: 700;
    color: #dc2626;
}

.form-tip {
    margin-top: 10rpx;
    font-size: 22rpx;
    color: #94a3b8;
}

.popup-footer {
    flex-shrink: 0;
    display: flex;
    padding: 18rpx 30rpx calc(18rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #eef2f7;
    background: #fff;
}
</style>
