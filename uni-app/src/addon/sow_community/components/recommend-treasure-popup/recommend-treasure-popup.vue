<template>
    <view @touchmove.prevent.stop>
        <u-popup  :show="show" @close="show = false"  mode="bottom" :round="10">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title text-[#333]">TA提到的宝贝</view>
                <view class="mx-[var(--popup-sidebar-m)]">
                    <scroll-view scroll-y="true" class="h-[50vh]" >
                        <view class="flex p-[var(--pad-sidebar-m)] border-solid border-[2rpx] border-[#eee] rounded-[var(--rounded-big)] mb-[30rpx]"   v-for="(item, index) in treasureList" :key="index" @click="redirect({url: item.treasure_url})">
                            <view class="w-[140rpx] h-[140rpx] flex items-center justify-center rounded-[var(--goods-rounded-small)] overflow-hidden">
                                <image v-if="item.treasure_image" class="w-[140rpx] h-[140rpx] rounded-[var(--goods-rounded-small)] overflow-hidden" :src="img(item.treasure_image)" mode="aspectFill"></image>
                                <image  v-else class="w-[140rpx] h-[140rpx] rounded-[var(--goods-rounded-small)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                            </view>
                            <view class="flex flex-1 flex-col justify-between  ml-[20rpx]">
                                <view class="w-[100%] flex flex-col items-baseline">
                                    <view class="text-[#333] text-[28rpx] max-h-[80rpx] leading-[40rpx] multi-hidden">{{ item.treasure_name }}</view>
                                    <view class="box-border mt-[10rpx]  leading-[36rpx] using-hidden text-[var(--text-color-light9)] text-[26rpx]">{{ item.treasure_sub_name }}</view>
                                </view>
                                <view class="flex justify-between items-end self-end mt-[10rpx] w-[100%]">
                                    <view class="text-[var(--price-text-color)] price-font">
                                        <text class="text-[22rpx] font-500">￥</text>
                                        <text class="text-[36rpx] font-500">{{ parseFloat(item.treasure_price).toFixed(2).split('.')[0] }}</text>
                                        <text class="text-[22rpx] font-500">.{{ parseFloat(item.treasure_price).toFixed(2).split('.')[1] }}</text>
                                    </view>
                                    <view class="w-[92rpx] h-[40rpx] rounded-[20rpx] bg-[var(--primary-color)] flex-center text-[#fff] text-[22rpx]">购买</view>
                                </view>
                            </view>
                        </view>
                    </scroll-view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import {  reactive, ref } from 'vue'
import { img, redirect } from '@/utils/common';

const show = ref(false)

let treasureList =reactive<any>([])

const open = (data: any) => {
    treasureList = []
    treasureList = data
    show.value = true
}

defineExpose({
    open
})
</script>

<style lang="scss" scoped>

</style>
