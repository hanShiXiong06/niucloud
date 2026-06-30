<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden" :style="themeColor()">
         <mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: false }" @up="getListFn" top="0">
            <view class="px-[24rpx] pt-[24rpx]">
                <view
                    class="site-card flex items-center"
                    :class="{ 'site-card--active': siteInfo.site_id == item.site_id }"
                    v-for="(item,index) in list" :key="index"
                    @click="selectSite(item)">
                    <view class="relative rounded-[16rpx] overflow-hidden flex-shrink-0">
                        <up-image class="overflow-hidden" width="96rpx" height="96rpx" radius="16rpx" :src="img(item.front_end_logo)" model="aspectFill">
                            <template #error>
                                <image :src="img('addon/mall/site/default_store.png')" class="w-[96rpx] h-[96rpx] rounded-[16rpx] bg-[#fff] align-middle"></image>
                            </template>
                        </up-image>
                    </view>

                    <view class="flex-1 flex flex-col justify-center ml-[24rpx] overflow-hidden">
                        <text class="block text-[30rpx] font-600 text-[#333] truncate">{{ item.site_name }}</text>
                    </view>

                    <view class="flex items-center flex-shrink-0 ml-[16rpx]" v-if="siteInfo.site_id == item.site_id">
                        <text class="nc-iconfont nc-icon-duihaoV6xx text-primary text-[32rpx]"></text>
                        <text class="text-[24rpx] text-primary ml-[6rpx]">当前</text>
                    </view>
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
.site-card {
    background: #fff;
    border-radius: 24rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    border: 2rpx solid transparent;
    box-sizing: border-box;
}
.site-card--active {
    border-color: var(--primary-color);
}
</style>
