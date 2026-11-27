<template>
    <u-popup :show="show" mode="bottom" :round="round" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="rc-form-dialog">
            <!-- 头部：标题 + 可选副标题 + 关闭 -->
            <view class="rc-form-dialog__header">
                <slot name="header">
                    <view class="rc-form-dialog__heading">
                        <text class="rc-form-dialog__title">{{ title }}</text>
                        <text v-if="subtitle" class="rc-form-dialog__subtitle">{{ subtitle }}</text>
                    </view>
                </slot>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 rc-form-dialog__close" @click="handleClose"></text>
            </view>

            <!-- 主体：可滚动内容区 -->
            <scroll-view scroll-y class="rc-form-dialog__body" :style="bodyStyle">
                <slot></slot>
            </scroll-view>

            <!-- 底部：默认「取消 + 主操作（自带防重 loading）」，可用 #footer 覆盖 -->
            <view v-if="showFooter" class="rc-form-dialog__footer">
                <slot name="footer">
                    <u-button
                        class="rc-form-dialog__btn"
                        :customStyle="{ flex: 1, marginRight: '20rpx' }"
                        @click="handleClose"
                    >{{ cancelText }}</u-button>
                    <u-button
                        type="primary"
                        class="rc-form-dialog__btn"
                        :customStyle="{ flex: 2 }"
                        :loading="loading"
                        :disabled="confirmDisabled"
                        @click="handleConfirm"
                    >{{ confirmText }}</u-button>
                </slot>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 统一业务弹窗外壳——对齐 admin/src/addon/hsx_recycle/components/FormDialog.vue。
 * 把现有 u-popup「头部 + 滚动主体 + 底部按钮」三段式收敛为共享外壳：
 * 统一头尾结构、防重提交（loading 期间忽略重复点击）、可自定义底部。
 *
 * @example
 * <RecycleFormDialog
 *   v-model:show="visible"
 *   title="设备定价"
 *   subtitle="根据质检结果设定收购价格"
 *   :loading="submit.loading"
 *   :confirm-disabled="!form.final_price"
 *   confirm-text="确认定价"
 *   @confirm="onConfirm"
 * >
 *   <!-- 表单内容 -->
 * </RecycleFormDialog>
 */
const props = withDefaults(defineProps<{
    show: boolean
    title?: string
    subtitle?: string
    confirmText?: string
    cancelText?: string
    /** 主操作进行中：按钮 loading + 防重入。 */
    loading?: boolean
    /** 主操作是否禁用（如必填项未完成）。 */
    confirmDisabled?: boolean
    /** 是否显示底部操作区，默认 true。 */
    showFooter?: boolean
    /** 主体最大高度（vh），默认 60。 */
    maxBodyVh?: number
    round?: string | number
}>(), {
    title: '',
    subtitle: '',
    confirmText: '确定',
    cancelText: '取消',
    loading: false,
    confirmDisabled: false,
    showFooter: true,
    maxBodyVh: 60,
    round: '20'
})

const emit = defineEmits<{
    (e: 'update:show', value: boolean): void
    (e: 'confirm'): void
    (e: 'cancel'): void
    (e: 'close'): void
}>()

const bodyStyle = computed(() => `max-height:${ props.maxBodyVh }vh;`)

const handleClose = () => {
    emit('update:show', false)
    emit('cancel')
    emit('close')
}

const handleConfirm = () => {
    // 防重入：提交进行中或禁用时忽略点击
    if (props.loading || props.confirmDisabled) return
    emit('confirm')
}
</script>

<style scoped lang="scss">
.rc-form-dialog {
    display: flex;
    flex-direction: column;
    background: #fff;
    border-top-left-radius: var(--hsx-radius-lg);
    border-top-right-radius: var(--hsx-radius-lg);
    overflow: hidden;
}

.rc-form-dialog__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 32rpx var(--popup-sidebar-m, 30rpx) 24rpx;
    border-bottom: 2rpx solid var(--hsx-border);
}

.rc-form-dialog__heading {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.rc-form-dialog__title {
    font-size: 32rpx;
    font-weight: 600;
    line-height: 44rpx;
    color: var(--hsx-text-strong);
}

.rc-form-dialog__subtitle {
    margin-top: 6rpx;
    font-size: 22rpx;
    line-height: 32rpx;
    color: var(--hsx-text-secondary);
}

.rc-form-dialog__close {
    flex-shrink: 0;
    margin-left: 20rpx;
    font-size: 32rpx;
    color: var(--hsx-text-placeholder);
    padding: 4rpx;
}

.rc-form-dialog__body {
    box-sizing: border-box;
    padding: 28rpx var(--popup-sidebar-m, 30rpx);
}

.rc-form-dialog__footer {
    display: flex;
    align-items: center;
    padding: 20rpx var(--popup-sidebar-m, 30rpx);
    padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    border-top: 2rpx solid var(--hsx-border);
}
</style>
