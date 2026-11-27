<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()">
        <view class="bg-[#fff] px-[24rpx]">
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">店铺名称</view>
                <view class="text-[28rpx]">{{ storeInfo.site_name }}</view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">店铺分类</view>
                <view class="text-[28rpx]">{{ storeInfo.category_name }}</view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">业务类型</view>
                <view class="text-[28rpx]">{{ storeInfo.app_name.join('、') }}</view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">店铺类别</view>
                <view class="text-[28rpx]">{{ storeInfo.is_self ? '自营' : '直营' }}</view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">店铺入驻时间</view>
                <view class="text-[28rpx]">{{ storeInfo.create_time }}</view>
            </view>
            <view class="justify-between item-wrap" v-if="storeInfo.expire_time">
                <view class="text-[28rpx]">店铺到期时间</view>
                <view class="text-[28rpx]">{{ storeInfo.expire_time }}</view>
            </view>
        </view>
        <loading-page :loading="loading"></loading-page>
    </view>
</template>

<script setup lang="ts">
import { ref,computed } from 'vue';
import { getShopInfo } from '@/app/api/site'
// 店铺信息
const storeInfo = ref<any>({
    site_name: '',
    category_name: '',
    app_name: [],
    is_self: 0,
    create_time: '',
    expire_time: ''
})
const loading = ref(true)
const  getShopInfoFn = () => {
    loading.value = true
    getShopInfo().then((res: any) => {
        Object.keys(storeInfo.value).forEach((key: string) => {
            if (res.data[key]!= undefined) storeInfo.value[key] = res.data[key]
        })
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}
getShopInfoFn()
</script>

<style lang="scss" scoped>
.item-wrap{
    display: flex;
    align-items: center;
    min-height: 100rpx;
    box-sizing: border-box;
    border-bottom:  solid 1px #ebebeb;
}
</style>