<template>
    <view class="min-h-[100vh] bg-[#fff] overflow-hidden" :style="themeColor()">
        <template v-for="(item,key) in menuList">
            <view class="card-template  my-[20rpx]" v-if="item.childs && item.childs.length">
                <view class="title">{{ item.name }}</view>
                <view class="grid grid-cols-5 gap-x-[10rpx] gap-y-[40rpx]" >
                    <view class="flex flex-col items-center" v-for="(subItem,subIndex) in item.childs"  @click="redirect({url: subItem.page})">
                        <image class="w-[48rpx] h-[48rpx] overflow-hidden" :src="img(subItem.icon)" mode="aspectFill"></image>
                        <view class="text-[24rpx] mt-[22rpx]">{{ subItem.name }}</view>
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

<style scoped>

</style>