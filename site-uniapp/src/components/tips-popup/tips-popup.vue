<template>
    <view @touchmove.prevent.stop>
        <u-popup  :show="popupShow" mode="center" round="8" :safeAreaInsetBottom="false">
            <view class="bg-[#fff] flex flex-col justify-between w-[600rpx] rounded-[var(--rounded-big)] box-border p-[35rpx] relative">
                <view class="text-[18px] text-center py-[10rpx]">提示</view>
                <view class="text-[28rpx] text-center py-[40rpx] text-[#666]">{{ title }}</view>
                <view class="flex items-center">
                    <view class="flex-1 mr-[30rpx] flex justify-center bg-[var(--primary-color-light)]  h-[70rpx] leading-[70rpx] text-[var(--primary-color)] text-[24rpx] font-500 rounded-[16rpx]" @click="cancel">取消</view>
                    <view class="flex-1 flex justify-center bg-[var(--primary-color)] h-[70rpx] leading-[70rpx] text-[#fff] text-[26rpx]  font-500 rounded-[16rpx]" @click="handleConfirm">确认</view>
                </view>
            </view>
        </u-popup>
    </view>
    
</template>

<script setup lang="ts">
import { ref } from 'vue';

const popupShow = ref(false);
const title = ref('');
const confirmCallback = ref<Function | null>(null);

const open = (data: any, callback?: Function) => {
    title.value = data;
    popupShow.value = true;
    if (callback && typeof callback === 'function') {
        confirmCallback.value = callback;
    }
}

const handleConfirm = () => {
    if (confirmCallback.value && typeof confirmCallback.value === 'function') {
        confirmCallback.value();
    }
    // 关闭弹窗
    title.value = '';
    popupShow.value = false;
    confirmCallback.value = null;
}

const cancel = () => {
    title.value = '';
    popupShow.value = false;
    confirmCallback.value = null;
}

defineExpose({
    open
})
</script>