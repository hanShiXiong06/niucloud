<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden pb-[40rpx]" :style="themeColor()" v-if="Object.keys(memberInfo).length">
        <!-- 头部 -->
        <view class="sidebar-margin card-template mt-[var(--top-m)]">
            <view class="flex items-center">
                <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(memberInfo.headimg)" size="96rpx" leftIcon="none"></u-avatar>
                <view class="ml-[20rpx] flex-1 min-w-0">
                    <view class="flex items-center flex-wrap">
                        <text class="text-[32rpx] font-500 truncate max-w-[300rpx]">{{ memberInfo.nickname || '未命名' }}</text>
                        <u-tag v-if="memberInfo.member_level_name" :text="memberInfo.member_level_name" type="warning" size="mini" plain plainFill class="ml-[12rpx]"></u-tag>
                        <u-tag :text="Number(memberInfo.status) === 1 ? '正常' : '锁定'" :type="Number(memberInfo.status) === 1 ? 'success' : 'error'" size="mini" class="ml-[8rpx]"></u-tag>
                    </view>
                    <view class="text-[24rpx] text-[#999] mt-[10rpx]">
                        <text>{{ memberInfo.member_no }}</text>
                        <text v-if="memberInfo.mobile" class="ml-[16rpx]">{{ memberInfo.mobile }}</text>
                    </view>
                </view>
            </view>
            <view class="flex flex-wrap mt-[16rpx]" v-if="memberInfo.member_label_array && memberInfo.member_label_array.length">
                <u-tag v-for="(lb, k) in memberInfo.member_label_array" :key="k" :text="lb.label_name" type="info" size="mini" plain class="mr-[12rpx] mb-[8rpx]"></u-tag>
            </view>
        </view>

        <!-- 资产 -->
        <view class="sidebar-margin card-template mt-[var(--top-m)]">
            <view class="grid grid-cols-3">
                <view class="flex flex-col items-center">
                    <text class="price-font text-[38rpx] fnt-500">{{ memberInfo.point || 0 }}</text>
                    <view class="text-[24rpx] text-[#999] mt-[8rpx]">积分</view>
                </view>
                <view class="flex flex-col items-center">
                    <text class="price-font text-[38rpx] fnt-500">{{ parseFloat(memberInfo.balance || 0).toFixed(2) }}</text>
                    <view class="text-[24rpx] text-[#999] mt-[8rpx]">余额</view>
                </view>
                <view class="flex flex-col items-center">
                    <text class="price-font text-[38rpx] fnt-500">{{ memberInfo.growth || 0 }}</text>
                    <view class="text-[24rpx] text-[#999] mt-[8rpx]">成长值</view>
                </view>
            </view>
        </view>

        <!-- 消费 -->
        <view class="sidebar-margin card-template mt-[var(--top-m)]">
            <u-cell title="店铺消费" :value="String(memberInfo.comsum_money || 0)" :border="true"></u-cell>
            <u-cell title="消费次数" :value="String(memberInfo.comsum_num || 0)" :border="true"></u-cell>
            <u-cell title="客单价" :value="Number(memberInfo.comsum_num) ? (Number(memberInfo.comsum_money) / Number(memberInfo.comsum_num)).toFixed(2) : '0.00'" :border="true"></u-cell>
            <u-cell title="首次消费时间" :value="memberInfo.first_consum_time || '-'" :border="true"></u-cell>
            <u-cell title="最近消费时间" :value="memberInfo.last_consum_time || '-'" :border="false"></u-cell>
        </view>

        <!-- 基础信息 -->
        <view class="sidebar-margin card-template mt-[var(--top-m)]">
            <u-cell title="性别" :value="sexText" :border="true"></u-cell>
            <u-cell v-if="memberInfo.birthday" title="生日" :value="memberInfo.birthday" :border="true"></u-cell>
            <u-cell v-if="memberInfo.register_channel_name" title="注册渠道" :value="memberInfo.register_channel_name" :border="true"></u-cell>
            <u-cell title="注册时间" :value="memberInfo.create_time || '-'" :border="true"></u-cell>
            <u-cell title="我店铺的优惠券" isLink @click="redirect({ url: '/app/pages/member/coupon', param: { member_id: memberInfo.member_id } })" :border="false"></u-cell>
        </view>
    </view>
</template>

<script setup lang="ts">
import { reactive, computed, ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { redirect, img } from '@/utils/common';
import { getShopMemberInfo } from '@/app/api/member'

const memberId = ref<any>('')
const memberInfo = reactive<any>({})

const sexText = computed(() => {
    const s = Number(memberInfo.sex);
    return s === 1 ? '男' : s === 2 ? '女' : '保密';
});

onLoad((option: any) => {
    memberId.value = option.member_id || ''
    getShopMemberInfoFn()
})

const getShopMemberInfoFn = () => {
    getShopMemberInfo(memberId.value).then((res: any) => {
        Object.assign(memberInfo, res.data)
    })
}
</script>

<style scoped>

</style>
