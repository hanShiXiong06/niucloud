<template>
    <view :style="themeColor()" class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden">
        <template v-if="!loading && verifyInfo && verifyInfo.value">
            <view class="card-template mt-[var(--top-m)] sidebar-margin">
                <view class="title">核销信息</view>
                <view class="card-template-item justify-between">
                    <text class="text-[28rpx] text-[#333]">核销类型</text>
                    <view class="text-[28rpx] text-[#333]">{{ verifyInfo.type_name }}</view>
                </view>
                <view class="card-template-item justify-between" v-for="(item,index) in verifyInfo.value.content.fixed">
                    <text class="text-[28rpx] text-[#333]">{{ item.title }}</text>
                    <view class="text-[28rpx] text-[#333]">{{ item.value }}</view>
                </view>
            </view>

            <view v-for="(item,index) in verifyInfo.value.content.diy" :key="index"
                  class="card-template mt-[var(--top-m)] sidebar-margin">
                <view class="title">{{ item.title }}</view>
                <view class="card-template-item justify-between" v-for="(subItem,subIndex) in item.list" :key="subIndex">
                    <text class="text-[28rpx] text-[#333]">{{ subItem.title }}</text>
                    <text class="text-[28rpx] text-[#333]">{{ subItem.value }}</text>
                </view>
            </view>
            <view class="card-template mt-[var(--top-m)] sidebar-margin">
                <view class="flex" :style="verifyInfo.value.list.length - 1 != index ? 'margin-bottom: var(--top-m);' : ''" v-for="(item,index) in verifyInfo.value.list" :key="index">
                    <image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)]" mode="aspectFill" v-if="item.cover" :src="img(item.cover)"/>
                    <image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)]" mode="aspectFill" v-else :src="img('addon/tourism/tourism/member/hotel.png')"/>
                    <view class="flex flex-col flex-1 ml-[20rpx] py-[4rpx]">
                        <view class="leading-[1]">
                            <view class="leading-[40rpx] truncate max-w-[490rpx] text-[28rpx]">{{ item.name }}</view>
                            <view class="mt-[14rpx] truncate text-[24rpx] text-[var(--text-color-light9)] leading-[28rpx] max-w-[490rpx]" v-if="item.sub_name">{{ item.sub_name }}</view>
                        </view>
                        <view class="flex items-center mt-[20rpx]">
							<view class="text-[var(--text-color-light6)] text-[28rpx] text-right">x{{item.verify_num }}</view>
							<view class="leading-[1] ml-3 text-[var(--price-text-color)] text-[28rpx]">
								{{item.un_use_msg}}
							</view>
						</view>
                    </view>
                </view>
            </view>

            <view class="common-tab-bar w-[100%]"></view>
            <view class="verify-tab-bar fixed flex-center !text-[26rpx] rounded-[16rpx] h-[80rpx] left-[20rpx] right-[20rpx] text-[#fff] font-500 primary-btn-bg"  @click="verifyFn">确定</view>

        </template>
        <loading-page :loading="loading"></loading-page>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { onLoad, onShow } from '@dcloudio/uni-app'
import { img, redirect, getToken } from '@/utils/common';
import { getVerifyDetailInfo, verify } from '@/app/api/verify'

const loading = ref(true)
const code = ref('');
onLoad((option: any) => {
    if (option.code) code.value = option.code;
    // 小程序扫码进入
    if (option.scene) {
        let sceneParams: any = decodeURIComponent(option.scene).split('&');
        if (sceneParams.length) {
            sceneParams.forEach((item: any) => {
                if (item.indexOf('code') != -1) code.value = item.split('-')[1];
            });
        }
    }
})

onShow(() => {
    if (getToken()) {
        getVerifyDetailInfoFn();
    }
})


const verifyInfo = ref<any>({})
const getVerifyDetailInfoFn = () => {
    loading.value = true;
    getVerifyDetailInfo(code.value).then((res: any) => {
        verifyInfo.value = res.data;
        loading.value = false;
    }).catch(() => {
        setTimeout(() => {
            loading.value = false;
            if (getCurrentPages().length > 1) {
                uni.navigateBack({
                    delta: 1
                });
            } else {
                redirect({ url: '/app/pages/verify/index', param: {}, mode: 'redirectTo' })
            }
        }, 1000);
    })
}
let isLoading = false;
const verifyFn = () => {
    if (isLoading) return false;
    isLoading = true;

    verify(code.value).then((res: any) => {
        setTimeout(() => {
            isLoading = false;
            redirect({ url: '/app/pages/verify/record', param: {}, mode: 'redirectTo' })
        }, 1000);
    }).catch(() => {
        isLoading = false;
    })
}
</script>

<style lang="scss" scoped>
.bg-color{
    background-color: #ccc;
}
.verify-tab-bar{
    bottom: calc(constant(safe-area-inset-bottom) + 30rpx);
    bottom: calc(env(safe-area-inset-bottom) + 30rpx);
}
</style>
