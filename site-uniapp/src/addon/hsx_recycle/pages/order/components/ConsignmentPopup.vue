<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="consignment-popup">
            <!-- 头部 -->
            <view class="popup-header">
                <view class="popup-title">转代卖</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="handleClose"></text>
            </view>

            <!-- 设备信息 -->
            <view class="device-info">
                <view class="device-info-row">
                    <text class="device-icon">📱</text>
                    <view class="device-main">
                        <view class="device-model">{{ device.model || '未知型号' }}</view>
                        <view class="device-meta">
                            <text v-if="device.imei">IMEI: {{ device.imei }}</text>
                        </view>
                    </view>
                </view>
            </view>

            <!-- 提示 -->
            <view class="tip-box">
                <text class="tip-text">确认后，该设备会在原回收订单中变为"已转代卖"，同时生成独立代卖订单。后续售出和结算在代卖订单中处理。</text>
            </view>

            <!-- 表单 -->
            <view class="form-content">
                <view class="form-item">
                    <view class="form-label">期望售价</view>
                    <view class="price-input-wrapper">
                        <text class="price-symbol">¥</text>
                        <input v-model="formData.expected_price" type="number" placeholder="请输入期望售价" class="price-input" />
                    </view>
                </view>

                <view class="form-item">
                    <view class="form-label">最低结算价</view>
                    <view class="price-input-wrapper">
                        <text class="price-symbol">¥</text>
                        <input v-model="formData.min_settlement_price" type="number" placeholder="低于此价格需协商" class="price-input" />
                    </view>
                </view>

                <view class="form-item">
                    <view class="form-label">挂牌价</view>
                    <view class="price-input-wrapper">
                        <text class="price-symbol">¥</text>
                        <input v-model="formData.listing_price" type="number" placeholder="对外展示价格" class="price-input" />
                    </view>
                </view>

                <view class="form-item">
                    <view class="form-label">备注</view>
                    <u-textarea
                        v-model="formData.remark"
                        placeholder="例如：客户不接受回收报价，要求代卖"
                        :maxlength="200"
                        :height="120"
                        count
                    ></u-textarea>
                </view>
            </view>

            <!-- 底部按钮 -->
            <view class="popup-footer">
                <u-button @click="handleClose" :customStyle="{flex: 1, marginRight: '20rpx'}">
                    取消
                </u-button>
                <u-button type="primary" @click="handleSubmit" :customStyle="{flex: 2}" :loading="submitting">
                    确认转代卖
                </u-button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { transferDeviceToConsignment } from '@/addon/hsx_recycle/api/order'

interface Props {
    visible: boolean
    deviceData: any
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
const device = computed(() => props.deviceData || {})

const formData = ref({
    expected_price: '',
    min_settlement_price: '',
    listing_price: '',
    remark: ''
})

watch(() => props.visible, (val) => {
    show.value = val
    if (val) {
        formData.value = {
            expected_price: '',
            min_settlement_price: '',
            listing_price: '',
            remark: ''
        }
    }
})

const handleClose = () => {
    emit('update:visible', false)
}

const handleSubmit = async () => {
    if (!props.deviceData?.id) {
        uni.showToast({ title: '请选择设备', icon: 'none' })
        return
    }

    submitting.value = true
    try {
        await transferDeviceToConsignment(props.deviceData.id, {
            expected_price: Number(formData.value.expected_price) || 0,
            min_settlement_price: Number(formData.value.min_settlement_price) || 0,
            listing_price: Number(formData.value.listing_price) || 0,
            remark: formData.value.remark
        })
        uni.showToast({ title: '已转入代卖订单' })
        emit('update:visible', false)
        emit('success')
    } catch (error: any) {
        uni.showToast({ title: error.message || '转代卖失败', icon: 'none' })
    } finally {
        submitting.value = false
    }
}
</script>

<style lang="scss" scoped>
.consignment-popup {
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}
.popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30rpx;
    border-bottom: 1rpx solid #f0f0f0;
}
.popup-title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
}
.device-info {
    padding: 24rpx 30rpx;
    background: #f8f9fa;
    margin: 20rpx 30rpx 0;
    border-radius: 12rpx;
}
.device-info-row {
    display: flex;
    align-items: center;
}
.device-icon {
    font-size: 40rpx;
    margin-right: 16rpx;
}
.device-main {
    flex: 1;
}
.device-model {
    font-size: 28rpx;
    font-weight: 500;
    color: #333;
}
.device-meta {
    font-size: 24rpx;
    color: #999;
    margin-top: 4rpx;
}
.tip-box {
    margin: 20rpx 30rpx;
    padding: 20rpx;
    background: #fffbe6;
    border-radius: 12rpx;
    border: 1rpx solid #ffe58f;
}
.tip-text {
    font-size: 24rpx;
    color: #d48806;
    line-height: 36rpx;
}
.form-content {
    padding: 0 30rpx;
    flex: 1;
    overflow-y: auto;
}
.form-item {
    margin-bottom: 24rpx;
}
.form-label {
    font-size: 26rpx;
    color: #333;
    margin-bottom: 12rpx;
    font-weight: 500;
}
.price-input-wrapper {
    display: flex;
    align-items: center;
    border: 1rpx solid #ddd;
    border-radius: 12rpx;
    padding: 0 20rpx;
    height: 80rpx;
}
.price-symbol {
    font-size: 32rpx;
    color: #e6a23c;
    font-weight: bold;
    margin-right: 12rpx;
}
.price-input {
    flex: 1;
    height: 80rpx;
    font-size: 28rpx;
}
.popup-footer {
    display: flex;
    padding: 20rpx 30rpx;
    border-top: 1rpx solid #f0f0f0;
}
</style>
