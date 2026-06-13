<template>
    <RecycleFormDialog
        :show="show"
        title="转代卖"
        subtitle="生成独立代卖订单，售出与结算在代卖订单中处理"
        confirm-text="确认转代卖"
        :loading="submitting"
        @update:show="handleClose"
        @confirm="handleSubmit"
    >
        <!-- 设备信息 -->
        <view class="device-info">
            <view class="device-info-row">
                <text class="nc-iconfont nc-icon-huishouzhan device-icon"></text>
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
    </RecycleFormDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { transferDeviceToConsignment } from '@/addon/hsx_recycle/api/order'
import RecycleFormDialog from '@/addon/hsx_recycle/components/RecycleFormDialog.vue'
import { useRecycleSubmit } from '@/addon/hsx_recycle/hooks/useRecycleSubmit'

interface Props {
    visible: boolean
    deviceData: any
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
// 提交守卫复用现有 submitting，模板 :loading 绑定无需改动
const submit = useRecycleSubmit(submitting)
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

    // 提交守卫：进行中忽略重复点击，成功后统一提示
    await submit.run(async () => {
        await transferDeviceToConsignment(props.deviceData.id, {
            expected_price: Number(formData.value.expected_price) || 0,
            min_settlement_price: Number(formData.value.min_settlement_price) || 0,
            listing_price: Number(formData.value.listing_price) || 0,
            remark: formData.value.remark
        })
        emit('update:visible', false)
        emit('success')
    }, { success: '已转入代卖订单' })
}
</script>

<style lang="scss" scoped>
/* 外壳（头部 / 底部 / 左右内边距 / 滚动）由 RecycleFormDialog 提供，此处仅写主体内容样式 */
.device-info {
    padding: 24rpx;
    background: var(--hsx-fill-light);
    margin-bottom: 20rpx;
    border-radius: var(--hsx-radius-sm);
}
.device-info-row {
    display: flex;
    align-items: center;
}
.device-icon {
    font-size: 40rpx;
    margin-right: 16rpx;
    color: var(--hsx-primary);
}
.device-main {
    flex: 1;
}
.device-model {
    font-size: 28rpx;
    font-weight: 500;
    color: var(--hsx-text-strong);
}
.device-meta {
    font-size: 24rpx;
    color: var(--hsx-text-placeholder);
    margin-top: 4rpx;
}
.tip-box {
    margin-bottom: 20rpx;
    padding: 20rpx;
    background: #fffbe6;
    border-radius: var(--hsx-radius-sm);
    border: 1rpx solid #ffe58f;
}
.tip-text {
    font-size: 24rpx;
    color: #d48806;
    line-height: 36rpx;
}
.form-content {
    flex: 1;
}
.form-item {
    margin-bottom: 24rpx;
}
.form-label {
    font-size: 26rpx;
    color: var(--hsx-text-strong);
    margin-bottom: 12rpx;
    font-weight: 500;
}
.price-input-wrapper {
    display: flex;
    align-items: center;
    border: 1rpx solid #ddd;
    border-radius: var(--hsx-radius-sm);
    padding: 0 20rpx;
    height: 80rpx;
}
.price-symbol {
    font-size: 32rpx;
    color: var(--hsx-price);
    font-weight: bold;
    margin-right: 12rpx;
}
.price-input {
    flex: 1;
    height: 80rpx;
    font-size: 28rpx;
}
</style>
