<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)]  overflow-hidden" :style="themeColor()" v-if="Object.keys(memberInfo).length">
        <view class="sidebar-margin card-template flex my-[var(--top-m)]">
            <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(memberInfo.headimg)" :size="'80rpx'" leftIcon="none" />
            <view class="ml-[16rpx] flex-1 flex flex-col justify-between">
                <view class="text-[30rpx] font-500">{{ memberInfo.nickname }}</view>
                <view class="flex flex-wrap">
                    <text  class="h-[32rpx] px-[12rpx] flex-center bg-[#f8f8f8] border-solid border-[1rpx] border-[#ddd] rounded-[8rpx] text-[20rpx] mr-[20rpx]">{{ memberInfo.is_follow ? '已关注' : '未关注' }}</text>
                </view>
            </view>
        </view>
        <view class="sidebar-margin mb-[var(--top-m)] card-template">
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">店铺消费</view>
                <view class="text-[28rpx]">{{ memberInfo.comsum_money }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">店铺客单价</view>
                <view class="text-[28rpx]">{{ (Number(memberInfo.comsum_money) / Number(memberInfo.comsum_num)).toFixed(2) }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">首次消费时间</view>
                <view class="text-[28rpx]">{{ memberInfo.first_consum_time }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">最近消费时间</view>
                <view class="text-[28rpx]">{{ memberInfo.last_consum_time }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">在我店铺的优惠券</view>
                <view class="flex items-center" @click="redirect({url: '/app/pages/member/coupon', param: { member_id: memberInfo.member_id }})">
                    <text  class="text-[26rpx] text-[var(--text-color-light9)]">查看</text>
                    <text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[var(--text-color-light9)] pt-[2rpx]"></text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref,  reactive } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { redirect, img } from '@/utils/common';
import { getShopMemberInfo } from '@/app/api/member'

const  memberId = ref<any>('')
const memberInfo = reactive<any>({})

onLoad((option: any) => {
    memberId.value = option.member_id || ''
    getShopMemberInfoFn()
})

const  getShopMemberInfoFn = () => {
    getShopMemberInfo(memberId.value).then((res : any) => {
        Object.assign(memberInfo, res.data)
    })
}
</script>

<style scoped>

</style>