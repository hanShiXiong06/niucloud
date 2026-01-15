<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden" :style="themeColor()">
         <mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: false }" @up="getListFn" top="0">
            <view class="bg-[#fff] px-[20rpx]" v-if="list.length">
                <view class="box-border item-wrap flex items-center justify-between"  v-for="(item,index) in list" :key="index" @click="selectSite(item)">
                    <view class="relative rounded-[6rpx] overflow-hidden">
                        <up-image class="overflow-hidden" width="60rpx" height="60rpx" radius="6rpx" :src="img(item.front_end_logo)" model="aspectFill">
                            <template #error>
                                <image :src="img('addon/mall/site/default_store.png')" class="w-[60rpx] h-[60rpx] rounded-[6rpx] bg-[#fff] align-middle"></image>
                            </template>
                        </up-image>
                        <view v-if="!item.business_status" class="absolute top-0 left-0 right-0 bottom-0 bg-[rgba(0,0,0,0.7)] text-[#fff] text-[16rpx] flex-center">已打烊</view>
                    </view>
                    
                    <view class="flex-1 flex items-center ml-[16rpx] box-border">
                        <view class="text-[28rpx] max-w-[400rpx] truncate font-500 text-[#333]">{{ item.site_name }}</view>
                    </view>
                    <view class="iconfont iconxuanze1 text-primary" v-if="siteInfo.site_id == item.site_id"></view>
                </view>
            </view>
            <mescroll-empty v-if="!list.length && !loading"></mescroll-empty>
         </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { img, redirect } from '@/utils/common'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { getHomeSite } from '@/app/api/site';
import { onPageScroll, onReachBottom, onLoad, onShow } from '@dcloudio/uni-app';
import useUserStore from '@/stores/user'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const userStore = useUserStore()

const siteInfo = computed(() => {
    return userStore.siteInfo
})

const list = ref<Array<any>>([])
const loading = ref<boolean>(true)

const getListFn = (mescroll: any) => {
    mescroll.size = 30;
    let data: Object = {
        page: mescroll.num,
        limit: mescroll.size
    };

    getHomeSite(data).then((res: any) => {
        let newArr = res.data.data.filter((item: any) => {
            return item.site_id != siteInfo.value.site_id
        });
        
        //设置列表数据
        if (mescroll.num == 1) {
            list.value = []; //如果是第一页需手动制空列表
            list.value.unshift(siteInfo.value)
        }
        list.value = list.value.concat(newArr);
        mescroll.endSuccess(newArr.length);
        loading.value = false;
    }).catch(() => {
        loading.value = false;
        mescroll.endErr(); // 请求失败, 结束加载
    })
}
const selectSite = (data: any) => { 
    uni.removeStorageSync('statItemList');
    uni.setStorageSync('siteId', data.site_id);
    uni.setStorageSync('siteInfo', data);
    useUserStore().$patch((site) => {
        site.siteInfo = data
    })
    useUserStore().getSiteInfo()
    redirect({url:'/app/pages/index/index', mode: 'reLaunch' })
}
</script>
<style lang="scss" scoped>
.item-wrap{
    min-height: 100rpx;
    box-sizing: border-box;
    border-bottom:  solid 1rpx #ebebeb;
}
</style>