<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="popup-container">
            <view class="popup-header">
                <view>
                    <view class="popup-title">取消退回单</view>
                    <view class="popup-subtitle">取消后该退回单不再继续流转</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="handleClose"></text>
            </view>

            <view class="popup-body">
                <u-alert
                    type="warning"
                    title="请确认取消原因"
                    description="取消原因会写入退回单和后续日志，便于客服与仓库追踪。取消后不再继续流转且无法回到之前状态，请谨慎操作（如需恢复请重新入库创建订单）。"
                    show-icon
                    :customStyle="{ marginBottom: '18rpx' }"
                ></u-alert>

                <view class="form-item">
                    <view class="form-label">取消原因 <text class="required">*</text></view>
                    <u-textarea
                        v-model="remark"
                        placeholder="例如：客户改主意、地址无效、误操作创建"
                        :maxlength="200"
                        :height="150"
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
import { confirmDanger } from '@/addon/hsx_recycle/utils/confirm'

const props = defineProps<{
    visible: boolean
}>()

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
    (event: 'submit', value: { remark: string }): void
}>()

const show = ref(false)
const remark = ref('')
const submitting = ref(false)

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        remark.value = ''
        submitting.value = false
    }
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const handleClose = () => {
    if (submitting.value) return
    show.value = false
}

const handleSubmit = async () => {
    if (!remark.value.trim()) {
        uni.showToast({ title: '请输入取消原因', icon: 'none' })
        return
    }
    if (!(await confirmDanger('确认取消该退回单？取消后无法恢复。', { title: '确认取消退回', confirmText: '确认取消' }))) return
    emit('submit', { remark: remark.value.trim() })
}

defineExpose({
    setSubmitting: (value: boolean) => {
        submitting.value = value
    }
})
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
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    padding: 30rpx;
    border-bottom: 1rpx solid #eef2f7;
}

.popup-title {
    font-size: 32rpx;
    font-weight: 700;
    color: #111827;
}

.popup-subtitle {
    margin-top: 8rpx;
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
    padding: 24rpx;
    overflow-y: auto;
    background: #f6f7fb;
}

.warning-box,
.form-item {
    padding: 22rpx;
    border-radius: 16rpx;
    background: #fff;
}

.warning-box {
    margin-bottom: 18rpx;
    border: 1rpx solid #fed7aa;
    background: #fff7ed;
}

.warning-title {
    display: block;
    font-size: 26rpx;
    font-weight: 700;
    color: #c2410c;
}

.warning-text {
    display: block;
    margin-top: 8rpx;
    font-size: 23rpx;
    line-height: 34rpx;
    color: #9a3412;
}

.form-label {
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #334155;
}

.required {
    margin-left: 4rpx;
    color: #ef4444;
}

.popup-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #eef2f7;
    background: #fff;
}
</style>
