<template>
    <view  class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()" v-if="siteInfo">
        <view class="px-[20rpx] bg-[#fff] mb-[20rpx]">
            <view class="py-[20rpx] flex" @click="redirect({ url: '/app/pages/site/store'})">
                <up-image  width="88rpx" height="88rpx" radius="16rpx" :src="img(siteInfo.front_end_logo)" model="aspectFill">
                    <template #error>
                        <image class="w-[88rpx] h-[88rpx] align-middle rounded-[16rpx]" :src="img('addon/wuxinggou/default_shop.png')" mode="aspectFill" />
                    </template>
                </up-image>
                <view class="flex-1 ml-[16rpx] flex flex-col justify-between box-border box-border">
                    <view class="text-[28rpx] text-[#333]">{{ siteInfo.site_name }}</view>
                    <view class="flex">
                        <text class="flex-center text-[20rpx] bg-primary text-[#fff] px-[8rpx] h-[34rpx] rounded-[6rpx]" v-if="siteInfo.business_status">营业中</text>
                        <text class="flex-center text-[20rpx] bg-[#9098A3] text-[#fff] px-[8rpx] h-[34rpx] rounded-[6rpx]" v-else>已打烊</text>
                    </view>
                </view>
                <view>
                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[#c4c4c4] font-500"></text>
                </view>
            </view> 
            <view class="h-[100rpx] box-border border-0 border-t-[1rpx] border-solid border-[#ebebeb] flex items-center justify-between" @click="redirect({url: '/app/pages/site/account'})">
                <text>登录账号</text>
                <text class="flex-1 text-right" v-if="userInfo">{{ userInfo.username }}</text>
                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[#c4c4c4] font-500 relative top-[2rpx]"></text>
            </view>   
        </view>  
        <view class="px-[20rpx] bg-[#fff] mb-[20rpx]">
            <view class="h-[100rpx] box-border flex items-center justify-between border-0" :class="{'border-t-[1rpx] border-solid border-[#ebebeb]': index}" v-for="(item,index) in centerList" :key="index" @click="redirect({url: item.page})">
                <text>{{ item.name }}</text>
                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[#c4c4c4] font-500"></text>
            </view>
        </view>
        <view class="px-[20rpx] bg-[#fff] mb-[20rpx]">
            <view class="h-[100rpx] box-border flex items-center justify-between" @click="redirect({url: '/app/pages/site/about'})">
                <text>关于</text>
                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[#c4c4c4] font-500"></text>
            </view>
        </view>
       <view class="mt-[30rpx] h-[100rpx] bg-[#fff] flex-center text-[26rpx] font-500 text-[#666]" @click="popupShow = true">退出登录</view>
        <tabbar />
        <!-- 退出登录弹窗 -->
        <u-popup  :show="popupShow" mode="center" round="8" :safeAreaInsetBottom="false">
            <view class="bg-[#fff] flex flex-col justify-between w-[600rpx] min-h-[240rpx] rounded-[var(--rounded-big)] box-border p-[35rpx] relative">
                <view class="text-[28rpx] text-center">是否退出登录?</view>
                <view class="flex items-center">
                    <view class="flex-1 mr-[30rpx] flex justify-center bg-[var(--primary-color-light)]  h-[70rpx] leading-[70rpx] text-[var(--primary-color)] text-[24rpx] font-500 rounded-[16rpx]" @click="popupShow = false">取消</view>
                    <view class="flex-1 flex justify-center bg-[var(--primary-color)] h-[70rpx] leading-[70rpx] text-[#fff] text-[26rpx]  font-500 rounded-[16rpx]" @click="logout">确认</view>
                    
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref,computed } from 'vue';
import { redirect, img } from '@/utils/common';
import { getSiteCenter } from '@/app/api/site'
import useUserStore from '@/stores/user'

const userStore = useUserStore()
const siteInfo = computed(() => userStore.siteInfo)
const userInfo = computed(() => userStore.userInfo)

const centerList = ref([])
const getSiteCenterFn = () => {
    getSiteCenter().then((res:any) => {
        centerList.value = res.data
    })

}
// getSiteCenterFn()

const popupShow = ref(false)
const logout = ()=>{
    userStore.logout()
}


</script>
<style lang="scss" scoped>
.icon-style{
    background: linear-gradient( 90deg, #D7FBEC 0%, #A8F8D6 100%);
}
.bg-style{
	background: linear-gradient( 90deg, #E6FFF5 0%, #CEFFEA 100%);
}
.rate-wrap{
    width: 480rpx;
    white-space: nowrap;
    box-sizing: border-box;
}
</style>
