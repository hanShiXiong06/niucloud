<template>
    <view class="bg-[#fff] min-h-[100vh] overflow-hidden"  :style="themeColor()">
        <view class="px-[20rpx] py-[14rpx] bg-[#fff]">
            <view class="h-[60rpx] bg-[#f6f6f6] rounded-[30rpx] flex items-center">
                <view class="search-input !px-[20rpx]">
                    <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn"></text>
                    <input class="input" maxlength="50" type="text" v-model="code" placeholder="请输入核销码" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="verifyFn()">
                    <text class="nc-iconfont nc-icon-saoyisaoV6xx" @click="toLink"></text>
                </view>
            </view>
        </view>
        <view class="common-tab-bar w-[100%]"></view>
        <view class="verify-tab-bar fixed flex-center !text-[26rpx] rounded-[16rpx] h-[80rpx] left-[20rpx] right-[20rpx] text-[#fff] font-500 primary-btn-bg"  @click="verifyFn">确定</view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { redirect } from '@/utils/common';
const code = ref<string>('')

const  verifyFn = () => {
    if(!code.value) {
        uni.showToast({ title: '请输入核销码', icon: 'none' })
        return false;
    }
    redirect({ url: '/app/pages/verify/verify', param: { code: code.value } })
}

const toLink = () => {
    redirect({ url: '/app/pages/verify/index'})
}
</script>

<style lang="scss" scoped>
.verify-tab-bar{
    bottom: calc(constant(safe-area-inset-bottom) + 30rpx);
    bottom: calc(env(safe-area-inset-bottom) + 30rpx);
}
</style>