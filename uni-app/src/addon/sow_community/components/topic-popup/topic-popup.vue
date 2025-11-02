<template>
    <view @touchmove.prevent.stop>
        <u-popup  :show="show" @close="show = false"  mode="bottom" :round="10">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">参与话题</view>
                <view class="py-[14rpx] flex items-center justify-between px-[20rpx]">
                    <view class="flex-1 search-input mr-[20rpx]">
                        <text @click.stop="searchTopicFn()" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn"></text>
                        <input class="input" maxlength="50" type="text" v-model="keyword" placeholder="请输入关键字搜索" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTopicFn()">
                        <text v-if="keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="keyword=''"></text>
                    </view>
                    <view class="text-[28rpx]" @click.stop="searchTopicFn()">搜索</view>
                </view>
                <view class="mx-[var(--popup-sidebar-m)]">
                    <scroll-view scroll-y="true" class="h-[50vh]">
                        <view class="text-[30rpx] pt-[48rpx]">推荐话题</view>
                        <view class="flex flex-wrap mt-[32rpx]" v-if="topicData.recommend_list && topicData.recommend_list.length">
                            <text v-for="(item, index) in topicData.recommend_list" :key="index" class="h-[58rpx] px-[24rpx] border-solid border-[2rpx] border-[#ddd] mb-[28rpx] mr-[28rpx] text-[24rpx] rounded-full flex-center" :class="{'label-item': topicId.includes(item.topic_id)}" @click="handleSelect(item)">{{ item.topic_name }}</text>
                        </view>
                        <view  class="empty-page-popup" v-else>
                            <image class="img" :src="img('static/resource/images/system/empty.png')" model="aspectFit" />
                            <view class="desc">暂无话题</view>
                        </view>
                        <view class="text-[30rpx] mt-[48rpx]">全部话题</view>
                        <view class="flex flex-wrap mt-[32rpx]" v-if="topicData.list && topicData.list.length">
                            <text  v-for="(item, index) in topicData.list" :key="index" class="h-[58rpx] px-[24rpx] border-solid border-[2rpx] border-[#ddd] mb-[28rpx] mr-[28rpx] text-[24rpx] rounded-full flex-center" :class="{'label-item': topicId.includes(item.topic_id)}" @click="handleSelect(item)">{{ item.topic_name }}</text>
                        </view>
                        <view  class="empty-page-popup" v-else>
                            <image class="img" :src="img('static/resource/images/system/empty.png')" model="aspectFit" />
                            <view class="desc">暂无话题</view>
                        </view>
                    </scroll-view>
                </view>

                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="addTopic">确定({{ num }}/5)</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { img } from '@/utils/common';
import { getTopicList } from '@/addon/sow_community/api/topic';

const show = ref(false)
const keyword = ref('')
const topicId = ref<any>([])
const curTopic = ref<any>([])
const topicData = ref<any>({})

const emits = defineEmits(['confirm'])
const searchTopicFn = () => {
    getTopicListFn()
}

const num = computed(() => {
    return curTopic.value.length
})

const handleSelect = (data: any) =>{
    const index  = curTopic.value.findIndex((item: any) => item.topic_id === data.topic_id)
    if(index !== -1){
        curTopic.value.splice(index, 1)
        topicId.value.splice(index, 1)
    }else{
        if(curTopic.value.length < 5){
            curTopic.value.push(data)
            topicId.value.push(data.topic_id)
        }
    }
}

const addTopic = () => {
    emits('confirm', curTopic.value)
    show.value = false
}

const  getTopicListFn = () => {
    getTopicList({topic_name: keyword.value}).then((res: any) =>{
        topicData.value = res.data
    })
}
getTopicListFn()
const open = (data: any) => {
    curTopic.value = []
    topicId.value = []
    if(data && data.length){
        topicId.value = data
        data.forEach((item: any) => {
            topicData.value.list.forEach((val: any) =>{
                if(item === val.topic_id){
                    curTopic.value.push(val)
                }
            })
        })
    }

    show.value = true
}

defineExpose({
    open
})
</script>

<style lang="scss" scoped>
.label-item{
    color: var(--primary-color);
    background-color: var(--primary-color-light);
    border: 1rpx solid transparent;
}
</style>
