<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="popup-container">
            <view class="popup-header">
                <view>
                    <view class="popup-title">完成退回</view>
                    <view class="popup-subtitle">确认设备已退回给客户后，再完成本次退回流程</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="handleClose"></text>
            </view>

            <view class="popup-body">
                <u-alert
                    type="primary"
                    title="完成后将结束退回流程"
                    description="建议填写本次退回说明，例如签收情况、异常备注或仓库交接说明，便于后续追踪。"
                    show-icon
                    :customStyle="{ marginBottom: '18rpx' }"
                ></u-alert>

                <view class="form-item">
                    <view class="form-label">退回说明</view>
                    <u-textarea
                        v-model="remark"
                        placeholder="例如：客户已签收，包裹完整无异常"
                        :maxlength="200"
                        :height="150"
                        count
                    ></u-textarea>
                </view>
            </view>

            <view class="popup-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">取消</u-button>
                <u-button type="primary" @click="handleSubmit" :customStyle="{ flex: 1, marginLeft: '16rpx' }" :loading="submitting">
                    确认完成
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
    if (!(await confirmDanger('确认完成本次退回？完成后退回流程结束、不可撤销。', { title: '确认完成退回', confirmText: '确认完成' }))) return
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
    border: 1rpx solid #bfdbfe;
    background: var(--hsx-primary-50);
}

.warning-title {
    display: block;
    font-size: 26rpx;
    font-weight: 700;
    color: var(--hsx-primary-dark);
}

.warning-text {
    display: block;
    margin-top: 8rpx;
    font-size: 23rpx;
    line-height: 34rpx;
    color: var(--hsx-primary-dark);
}

.form-label {
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #334155;
}

.popup-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #eef2f7;
    background: #fff;
}
</style>
