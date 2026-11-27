<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)]  overflow-hidden" :style="themeColor()">
        <view class="sidebar-margin pt-[var(--top-m)]" v-if="couponList.length">
            <view v-for="(item,index) in couponList" :key="index" class="bg-[#fff5f3] py-[20rpx] flex items-center relative  rounded-[var(--rounded-mid)] overflow-hidden mb-[20rpx] coupon-item">
                <view class="w-[200rpx] flex  flex-col items-center justify-center ">
                    <view class="price-font flex items-baseline text-[var(--price-text-color)] price-font mb-[6rpx]">
                        <text class="text-[26rpx] leading-[34rpx] mr-[2rpx] text-center">￥</text>
                        <text class="text-[46rpx]  leading-[54rpx]  truncate">{{ item.price }}</text>
                    </view>
                    <text class="truncate max-w-[180rpx]  text-[24rpx] h-[32rpx] leading-[32rpx] text-[var(--price-text-color)]" v-if="item.min_condition_money === '0.00'">无门槛使用</text>
                    <text class="truncate max-w-[180rpx]  text-[24rpx] h-[32rpx] leading-[32rpx] text-[var(--price-text-color)]"  v-else>无门槛使用</text>
                </view>
                <view class="ml-[10rpx] flex-1 box-border">
                    <text class="truncate max-w-[300rpx] text-[24rpx] leading-[34rpx]">{{ item.title }}</text>
                     <view class="w-[100%] mt-[6rpx] text-[20rpx] leading-[34rpx] text-[var(--text-color-light6)]">
                        <text>有效期至<text>{{ item.expire_time ? item.expire_time : '' }}</text></text>
                    </view>
                </view>
            </view>
        </view>
        <view class="empty-page" v-else>
            <image class="img" :src="img('static/resource/images/system/empty.png')" mode="aspectFill" />
            <view class="desc">暂无优惠券</view>
        </view>
        <loading-page :loading="loading"></loading-page>
    </view>
</template>

<script setup lang="ts">
import { ref,  reactive } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { redirect, img } from '@/utils/common';
import { getShopMemberCoupon } from '@/app/api/member'

const  memberId = ref<any>('')
const couponList = ref<any>([])
const loading = ref(true)

onLoad((option: any) => {
    memberId.value = option.member_id || ''
    getShopMemberCouponFn()
})


const  getShopMemberCouponFn = () => {
    loading.value = true
    getShopMemberCoupon(memberId.value).then((res: any) => {
        couponList.value = res.data.data
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}
</script>

<style lang="scss" scoped>
.coupon-item{
	:before{
		content: '';
		display: block;
		width: 28rpx;
		height: 28rpx;
		background-color: var(--page-bg-color);
		position: absolute;
		top: 50%;
		left: -14rpx;
		border-radius:14rpx;
		transform: translateY(-50%);
	}
	:after{
		content: '';
		display: block;
		width: 28rpx;
		height: 28rpx;
		background-color: var(--page-bg-color);
		position: absolute;
		top: 50%;
		right: -14rpx;
		border-radius:14rpx;
		transform: translateY(-50%);
	}
}
</style>