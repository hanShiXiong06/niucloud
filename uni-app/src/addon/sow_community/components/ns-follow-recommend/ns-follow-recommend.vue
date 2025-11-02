<template>
    <view class="follow-recommend">
        <view class="mt-[60rpx] sidebar-margin pb-[30rpx]" v-if="recommendList.length">
            <view class="flex-center mb-[20rpx]">
                <image class="w-[38rpx] h-[22rpx]" :src="img('addon/sow_community/follow/title_left.png')" mode="aspectFill"></image>
                <text class="text-[32rpx] mx-[18rpx] font-500 text-[#333]">为你推荐</text>
                <image class="w-[38rpx] h-[22rpx]" :src="img('addon/sow_community/follow/title_right.png')" mode="aspectFill"></image>
            </view>
            <view>
                <view class="card-template mb-[var(--top-m)]" v-for="(item,index) in recommendList" :key="index">
                    <view class="flex-between-center">
                        <view class="flex" @click="redirect({url: '/addon/sow_community/pages/member',param: {member_id: item.member_id}})">
                            <u-avatar :src="img(item.headimg)" size="44" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')" />
                            <view class="flex-1 flex flex-col justify-between ml-[16rpx]">
                                <view class="text-[30rpx] font-500 leading-[42rpx] max-w-[300rpx] using-hidden">{{ item.nickname }}</view>
                                <view class="flex items-center">
                                    <text class="text-[24rpx] text-[#666] mr-[8rpx]">内容</text>
                                    <text class="text-[26rpx]">{{ item.content_num }}</text>
                                    <text class="w-[1rpx] h-[20rpx] bg-[#D9D9D9] mx-[20rpx]"></text>
                                    <text class="text-[24rpx] text-[#666] mr-[8rpx]">粉丝</text>
                                    <text class="text-[26rpx]">{{ item.fans_num }}</text>
                                </view>
                            </view>
                        </view>
                        <view class="flex-shrink-0 w-[130rpx] h-[54rpx] flex-center primary-btn-bg rounded-full" v-if="item.is_follow == 0">
                            <text class="nc-iconfont nc-icon-jiahaoV6xx text-[#fff] text-[28rpx] mt-[2rpx] mr-[4rpx]"></text>
                            <text class="text-[24rpx] text-[#fff]" @click="followFn(item)">关注</text>
                        </view>
                        <view class="w-[140rpx] h-[54rpx] bg-[#f6f6f6] border-solid border-[#eee] border-[2rpx] box-border text-center leading-[52rpx] text-[24rpx] rounded-full" v-else @click="cancelFollow(item)">已关注</view>
                    </view>
                    <scroll-view :scroll-x="true" class="align-middle whitespace-nowrap w-full box-border mt-[30rpx] h-[230rpx]" v-if="item.content_list.length">
                        <view class="inline-block mr-[20rpx]" v-for="(subItem, subIndex) in item.content_list" :key="subIndex" @click="toDetail(subItem)">
                            <view class="w-[180rpx] h-[230rpx] rounded-[var(--rounded-small)] relative">
                                <image class="w-full h-full rounded-[var(--rounded-small)]" :src="img(subItem.content_cover)" mode="aspectFill"/>
                                <image class="w-[44rpx] h-[44rpx] absolute right-0 top-0 left-0 bottom-0 m-auto" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" v-if="subItem.content_type == 2"/>
                            </view>
                        </view>
                    </scroll-view>
                </view>
            </view>
        </view>
    </view>
    <tips-popup ref="followRef" title="确定取消关注" @confirm="handleCancelFollow"/>
</template>

<script setup lang="ts">
import { img, redirect } from '@/utils/common';
import { ref } from 'vue'
import {  follow, getRecommendList } from '@/addon/sow_community/api/follow';
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'

const optionLoading = ref(false)
const recommendList = ref([])

const getRecommendListFn = () =>{
    getRecommendList({page: 1, limit: 20}).then(res =>{
        recommendList.value = res.data.data.map(item =>{
            item.is_follow = 0
            return item
        })
    })
}

getRecommendListFn()
// 关注
const followFn = (data: any) =>{
    if (optionLoading.value) return
	optionLoading.value = true
    data.is_follow = 1
    follow({follow_member_id: data.member_id, is_follow: 1}).then(res =>{
        optionLoading.value = false
        uni.showToast({
            title: '关注成功',
            icon: 'none'
        })
    })
}

// 取消关注
const followRef = ref()
const curData = ref<any>({})
const cancelFollow = (data: any) =>{
    curData.value = data
    followRef.value.open()
}

const handleCancelFollow = () =>{
    curData.value.is_follow = 0
    follow({follow_member_id: curData.value.member_id, is_follow: 0}).then(res =>{})
}

// 去详情
const toDetail = (data: any) =>{
    if(data.content_type == 1){
        redirect({ url: '/addon/sow_community/pages/image/detail', param: { content_id: data.content_id } })
    }else{
        redirect({ url: '/addon/sow_community/pages/video/detail', param: { content_id: data.content_id } })
    }
}
</script>

<style scoped>

</style>
