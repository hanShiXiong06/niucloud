<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="popup-container">
            <view class="popup-header">
                <view class="popup-title">登记售出</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1" @click="handleClose"></text>
            </view>

            <view class="popup-body">
                <view class="form-item">
                    <view class="form-label">成交价 <text class="required">*</text></view>
                    <view class="input-wrapper">
                        <text class="input-prefix">¥</text>
                        <input
                            v-model="formData.sold_price"
                            class="form-input"
                            type="digit"
                            placeholder="请输入成交价"
                        />
                    </view>
                </view>

                <view class="form-item">
                    <view class="form-label">客户结算金额 <text class="required">*</text></view>
                    <view class="input-wrapper">
                        <text class="input-prefix">¥</text>
                        <input
                            v-model="formData.settlement_amount"
                            class="form-input"
                            type="digit"
                            placeholder="请输入客户结算金额"
                        />
                    </view>
                    <view class="form-tip">服务收益 = 成交价 - 客户结算金额</view>
                </view>

                <view class="form-item">
                    <view class="form-label">备注</view>
                    <u-textarea
                        v-model="formData.remark"
                        placeholder="备注说明（选填）"
                        :maxlength="200"
                        count
                    ></u-textarea>
                </view>
            </view>

            <view class="popup-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">取消</u-button>
                <u-button type="primary" @click="handleSubmit" :customStyle="{ flex: 1, marginLeft: '16rpx' }" :loading="submitting">
                    确定
                </u-button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
    visible: boolean
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'submit'])

const show = ref(false)
const submitting = ref(false)
const formData = ref({
    sold_price: '',
    settlement_amount: '',
    remark: ''
})

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        formData.value = {
            sold_price: '',
            settlement_amount: '',
            remark: ''
        }
        submitting.value = false
    }
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const handleClose = () => {
    show.value = false
}

const handleSubmit = () => {
    if (!formData.value.sold_price || Number(formData.value.sold_price) <= 0) {
        uni.showToast({ title: '请输入有效的成交价', icon: 'none' })
        return
    }
    if (!formData.value.settlement_amount || Number(formData.value.settlement_amount) <= 0) {
        uni.showToast({ title: '请输入有效的客户结算金额', icon: 'none' })
        return
    }
    if (Number(formData.value.settlement_amount) > Number(formData.value.sold_price)) {
        uni.showToast({ title: '客户结算金额不能大于成交价', icon: 'none' })
        return
    }
    emit('submit', { ...formData.value })
}

defineExpose({ setSubmitting: (val: boolean) => { submitting.value = val } })
</script>

<style scoped lang="scss">
.popup-container {
    background: #fff;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 30rpx;
    border-bottom: 1rpx solid #f2f3f5;
}

.popup-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #1f2937;
}

.popup-body {
    flex: 1;
    padding: 30rpx;
    overflow-y: auto;
}

.form-item {
    margin-bottom: 30rpx;
}

.form-label {
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 500;
    color: #334155;
}

.required {
    color: #ef4444;
    margin-left: 4rpx;
}

.input-wrapper {
    display: flex;
    align-items: center;
    padding: 0 20rpx;
    height: 80rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    border: 1rpx solid #e2e8f0;
}

.input-prefix {
    font-size: 32rpx;
    font-weight: 600;
    color: #ea580c;
    margin-right: 8rpx;
}

.form-input {
    flex: 1;
    font-size: 32rpx;
    font-weight: 600;
    color: #1f2937;
}

.form-tip {
    margin-top: 12rpx;
    font-size: 24rpx;
    color: #64748b;
}

.popup-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
}
</style>
