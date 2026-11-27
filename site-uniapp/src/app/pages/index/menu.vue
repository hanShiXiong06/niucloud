<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()">
        <template v-for="(item,key) in menuList" :key="key">
            <view class="card-template m-[20rpx]" v-if="item.childs && item.childs.length">
                <view class="title">{{ item.name }}</view>
                <view class="grid grid-cols-5 gap-x-[10rpx] gap-y-[36rpx] mt-[20rpx]">
                    <view class="flex flex-col items-center" v-for="(subItem,subIndex) in item.childs" :key="subIndex" @click="redirect({url: subItem.page})">
                        <view class="app-tile">
                            <image class="w-[82rpx] h-[82rpx]" :src="img(subItem.icon)" mode="aspectFit"></image>
                        </view>
                        <view class="text-[24rpx] mt-[16rpx] text-[#444] truncate max-w-[120rpx]">{{ subItem.name }}</view>
                    </view>
                </view>
            </view>
        </template>
        <tabbar />
    </view>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { redirect, img } from '@/utils/common';
import { getApp } from '@/app/api/index'

const menuList = reactive({})
const  getAppFn = () => {
    getApp().then((res: any) => {
        Object.assign(menuList, res.data)
    })
}
getAppFn()
</script>

<style lang="scss" scoped>
.app-tile {
    width: 96rpx;
    height: 96rpx;
    border-radius: 24rpx;
    background: #F5F7FA;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
