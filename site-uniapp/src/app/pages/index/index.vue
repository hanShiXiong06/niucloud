<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)]  overflow-hidden" :style="themeColor()" v-if="siteInfo">
        <view class="pb-[530rpx]" :style="{backgroundImage: 'url(' + img('addon/mall/site/index/index_bg.png') + ')',backgroundSize: 'cover',  backgroundRepeat: 'no-repeat'}">
             <!-- #ifdef MP-WEIXIN -->
            <top-tabbar :data="topTabbarData" :scrollBool="topTabarObj.getScrollBool()" />
            <!-- #endif -->
            <view class="box-border p-[30rpx] flex items-center">
                <up-image  width="88rpx" height="88rpx" radius="16rpx" :src="img(siteInfo.front_end_logo)" model="aspectFill">
                    <template #error>
                        <image class="w-[88rpx] h-[88rpx] align-middle rounded-[16rpx]" :src="img('app/site-app/store.png')" mode="aspectFill" />
                    </template>
                </up-image>
                <view class="flex-1 ml-[16rpx] flex items-center justify-between box-border">
                    <text class="text-[30rpx] font-600 text-[#333] truncate max-w-[420rpx]">{{ siteInfo.site_name }}</text>
                    <text class="iconfont iconsaoma text-[40rpx] text-[#333]" @click="openCamera"></text>
                </view>
            </view>
        </view>

        <!-- 内容区：整体上移叠压在头图上 -->
        <view class="-mt-[530rpx]">
            <!-- 数据统计 -->
            <view class="card-template sidebar-margin mb-[20rpx]" v-if="Object.keys(statTotal).length">
                <view class="grid grid-cols-3 gap-x-[10rpx] gap-y-[40rpx]">
                    <view class="flex flex-col items-center" v-for="(item, key) in statTotal" :key="key">
                        <text class="price-font text-[42rpx] fnt-500 mb-[18rpx]">{{ item.num || 0 }}</text>
                        <view class="text-[26rpx] text-[#444]">{{ item.name }}</view>
                    </view>
                </view>
            </view>

            <!-- 待办 -->
            <view class="card-template sidebar-margin mb-[20rpx]" v-if="Object.keys(statTodo).length">
                <view class="grid grid-cols-4">
                    <view class="flex flex-col items-center py-[10rpx]" v-for="(item,index) in statTodo" :key="index" @click="redirect({url: item.page})">
                        <text class="price-font text-[42rpx] fnt-500 mb-[18rpx]">{{ item.num || 0 }}</text>
                        <view class="text-[24rpx] text-[#444]">{{ item.name }}</view>
                    </view>
                </view>
            </view>

            <!-- 应用 -->
            <view class="card-template sidebar-margin mb-[20rpx]">
                <view class="title">应用</view>
                <view class="grid grid-cols-5 gap-x-[10rpx] gap-y-[36rpx] mt-[20rpx]">
                    <view class="flex flex-col items-center" v-for="(item,index) in appList" :key="index" @click="redirect({url: item.page})">
                        <view class="app-tile">
                            <u-icon v-if="item.page == '/app/pages/index/menu'" name="grid-fill" color="var(--primary-color)" size="52rpx"></u-icon>
                            <image v-else class="w-[82rpx] h-[82rpx]" :src="img(item.icon)" mode="aspectFit"></image>
                        </view>
                        <view class="text-[24rpx] mt-[16rpx] text-[#444] truncate max-w-[120rpx]">{{ item.name }}</view>
                    </view>
                </view>
            </view>
        </view>
        <!-- <view class="card-template sidebar-margin" v-if="articleList.length">
            <view class="flex items-center ">
                <view  class="min-w-[64rpx] flex items-center">
                    <image  :src="img('addon/mall/site/index/notice.png')" class="h-[28rpx] w-[auto]  flex-shrink-0" mode="heightFix" />
                </view>
                <view class="w-[2rpx] h-[24rpx] bg-[#eee] mx-[16rpx]"></view>
                <view class="flex-1 flex overflow-hidden horizontal-body items-center">
                    <swiper :vertical="true" :duration="500" :autoplay="true" circular="true" class="flex-1">
                        <swiper-item v-for="(item, index) in articleList" :key="index" @touchmove.prevent.stop>
                            <view @click="toLink()" class="flex itemss-center justify-between leading-[28rpx] text-[#444]">
                                <text class="beyond-hiding truncate text-[26rpx]">{{ item.title }}</text>
                                <text class="ml-[10rpx] text-[26rpx]">{{  item.create_time.split(' ')[0].replace(/-/g, '.') }}</text>
                            </view>
                        </swiper-item>
                    </swiper>
                </view>
            </view>
        </view> -->
        
        <tabbar />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { redirect, img, moneyFormat } from '@/utils/common';
import { onLoad, onShow } from '@dcloudio/uni-app';
import { getTodo, getstat, geTotal, getAppOfIndex } from '@/app/api/index';
import { updateBusinessStatus } from '@/app/api/base_site'
import { getArticleList } from '@/app/api/article';
import useUserStore from '@/stores/user'
import { topTabar } from '@/utils/topTabbar'

/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
let topTabbarData = topTabarObj.setTopTabbarParam({ title: '工作台' })
/********* 自定义头部 - end ***********/

// 获取店铺信息
const userStore = useUserStore()
const siteInfo = computed(() => userStore.siteInfo)


const statTotal = ref<any>({}) 
const statTodo = ref<any>({}) 
const appList = ref<any>([])

onShow(() => {
    // getStatInfoFn()
    // getTodoInfoFn()
    getAppOfIndexFn()
    // getArticleListFn()
})

const changeBusinessStatus = () => {
    const data = {
        business_status: siteInfo.value.business_status ? 0 : 1
    }
    updateBusinessStatus(data).then((res: any) => {
        useUserStore().getSiteInfo()
    })
}

// 统计
const getStatItemList = () => {
    let stat_cache =  uni.getStorageSync('statItemList')
    if(stat_cache){
        return Promise.resolve(stat_cache);
    } else {
        return getstat().then((res: any) => {
            let cacheList = {};
            res.data.forEach((item: any, index: number) => {
                cacheList[item.key] = item;  
            })
            uni.setStorageSync('statItemList', cacheList) 
            return cacheList;
        })
    }      
}
const getStatInfoFn = async () => {
    
    let statListItem = await getStatItemList(); 
    statTotal.value = statListItem
    let statList: any = {};

    for(let key in statListItem){
        let itemOfUrl = statListItem[key];
        let temp_item = statList[itemOfUrl.url];
        if (!temp_item) {
            statList[itemOfUrl.url] = {};
        }
        statList[itemOfUrl.url][itemOfUrl.key] = itemOfUrl;
    }

    for( let key in statList){ 
        geTotal({ url: key }).then((res: any) => {
            for( let res_i in res.data){
                if(statTotal.value[res_i]){
                    statTotal.value[res_i].num = res.data[res_i];
                }
            }
        })

    }   
}

// 代办
const getTodoItemList = () => {
    let todo_cache =  uni.getStorageSync('todoItemList')
    if(todo_cache){
        return Promise.resolve(todo_cache);
    } else {
        return getTodo().then((res: any) => {
            let cacheList = {};
            res.data.forEach((item: any, index: number) => {
                cacheList[item.key] = item;  
            })
            uni.setStorageSync('todoItemList', cacheList) 
            return cacheList;
        })
    }
}
const getTodoInfoFn = async () => {

    let todoListItem = await getTodoItemList();
    statTodo.value = todoListItem;
    let todoList: any = {};
    // 代办事项
    for(let key in todoListItem){
        let itemOfUrl = todoListItem[key];
        let temp_item = todoList[itemOfUrl.url];
        if (!temp_item) {
            todoList[itemOfUrl.url] = {};
        }
        todoList[itemOfUrl.url][itemOfUrl.key] = itemOfUrl;
    }
    for( let key in todoList){
        geTotal({ url: key }).then((res: any) => {
            for( let res_i in res.data){
                if(statTodo.value[res_i]){
                    statTodo.value[res_i].num = res.data[res_i];
                }
            }
        })
    }
}


// 获取资讯列表
const articleList = ref<any>([])
const getArticleListFn = () => {
    getArticleList({ limit: 5 }).then((res: any) => {
        articleList.value = res.data
    })
}
// 菜单
const getAppOfIndexFn = () => {
    getAppOfIndex().then((res: any) => {
        appList.value = res.data
        appList.value.push({
            name: '全部',
            icon: '/app/site-app/more.png',
            page: '/app/pages/index/menu'
        })
    })
}


// 跳转
const toLink = () =>{
    redirect({ url: '/app/pages/article/list'})
}

const openCamera = () => {
    redirect({ url:'/app/pages/verify/index' })
}
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
swiper {
    height: 28rpx;
}

.beyond-hiding {
    display: inline-block;
    width: 100%;
    white-space: nowrap;
    line-height: 28rpx
}
</style>

