<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="popup-container">
            <view class="popup-header">
                <view class="popup-title">取消代卖</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1" @click="handleClose"></text>
            </view>

            <view class="popup-body">
                <view class="warning-box">
                    <text class="nc-iconfont nc-icon-jinggaoV6xx warning-icon"></text>
                    <text class="warning-text">取消后将无法恢复，请谨慎操作</text>
                </view>

                <view class="form-item">
                    <view class="form-label">取消原因 <text class="required">*</text></view>
                    <u-textarea
                        v-model="formData.remark"
                        placeholder="请输入取消原因或备注"
                        :maxlength="200"
                        count
                    ></u-textarea>
                </view>
            </view>

            <view class="popup-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">取消</u-button>
                <u-button type="error" @click="handleSubmit" :customStyle="{ flex: 1, marginLeft: '16rpx' }" :loading="submitting">
                    确认取消
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
    remark: ''
})

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        formData.value = { remark: '' }
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
    if (!formData.value.remark.trim()) {
        uni.showToast({ title: '请输入取消原因', icon: 'none' })
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

.warning-box {
    display: flex;
    align-items: center;
    padding: 20rpx;
    margin-bottom: 30rpx;
    border-radius: 12rpx;
    background: #fef2f2;
    border: 1rpx solid #fecaca;
}

.warning-icon {
    font-size: 32rpx;
    color: #dc2626;
    margin-right: 12rpx;
}

.warning-text {
    flex: 1;
    font-size: 26rpx;
    color: #991b1b;
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

.popup-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
}
</style>
