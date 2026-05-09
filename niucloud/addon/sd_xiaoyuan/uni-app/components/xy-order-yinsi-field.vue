<template>
    <view class="yinsi-card form-card column">
        <view class="card-header">
            <u-icon name="lock-fill" size="22" color="#666"></u-icon>
            <text class="label">隐私信息</text>
            <text class="optional">选填</text>
        </view>
        <text class="yinsi-tip">门禁码、宿舍号、备用电话等可写在这里，仅接单员接单后可见。</text>
        <textarea
            class="yinsi-input"
            :value="modelValue"
            @input="onInput"
            placeholder="如：宿舍楼号、房间号、取件密码等"
            :maxlength="500"
        />
    </view>
</template>

<script setup lang="ts">
defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void
}>()

const onInput = (e: any) => {
    emit('update:modelValue', e?.detail?.value ?? '')
}
</script>

<style lang="scss" scoped>
.yinsi-card {
    &.form-card {
        background: #fff;
        border-radius: 16rpx;
        padding: 24rpx;
        margin-bottom: 20rpx;
    }
    .card-header {
        display: flex;
        align-items: center;
        margin-bottom: 12rpx;
        .label {
            font-size: 28rpx;
            color: #333;
            font-weight: 500;
            margin-left: 12rpx;
            flex: 1;
        }
        .optional {
            font-size: 24rpx;
            color: #999;
        }
    }
    .yinsi-tip {
        font-size: 24rpx;
        color: #888;
        line-height: 1.5;
        margin-bottom: 16rpx;
        display: block;
    }
    .yinsi-input {
        width: 100%;
        min-height: 160rpx;
        background: #f8f8f8;
        border-radius: 12rpx;
        padding: 20rpx;
        font-size: 28rpx;
        box-sizing: border-box;
    }
}
</style>
