<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="popup-container">
            <view class="popup-header">
                <view class="popup-title">设置挂牌价</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1" @click="handleClose"></text>
            </view>

            <view class="popup-body">
                <view class="form-item">
                    <view class="form-label">挂牌价 <text class="required">*</text></view>
                    <view class="input-wrapper">
                        <text class="input-prefix">¥</text>
                        <input
                            v-model="formData.listing_price"
                            class="form-input"
                            type="digit"
                            placeholder="请输入挂牌价"
                        />
                    </view>
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
    defaultPrice?: string | number
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'submit'])

const show = ref(false)
const submitting = ref(false)
const formData = ref({
    listing_price: '',
    remark: ''
})

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        formData.value = {
            listing_price: props.defaultPrice ? String(props.defaultPrice) : '',
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
    if (!formData.value.listing_price || Number(formData.value.listing_price) <= 0) {
        uni.showToast({ title: '请输入有效的挂牌价', icon: 'none' })
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

.popup-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
}
</style>
