<template>
    <view  class="bg-[#fff] min-h-screen overflow-hidden"  :style="themeColor()" v-if="Object.values(memberInfo).length">
        <view class=" fixed left-0 right-0 top-0 z-100">
            <view :style="{background: 'url(' + img('/addon/sow_community/member/member_bg.jpg') + ') left bottom / cover no-repeat'}">
                <view class="overflow-hidden pb-[70rpx]">
                    <!-- #ifdef MP-WEIXIN || APP-PLUS -->
                    <top-tabbar :data="topTabbarData"  :customBack="()=>{ redirect({ url: '/addon/sow_community/pages/index'}) }" />
                    <!-- #endif -->
                    <view class="flex-between-center mt-[30rpx] px-[20rpx] mb-[50rpx]">
                        <view class="flex items-center" @click="redirect({url: '/app/pages/member/personal'})">
                            <u-avatar :src="img(memberInfo.headimg)" size="50" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                            <view class="flex-1 ml-[20rpx]">
                            <view class="flex items-center mb-[10rpx]">
                                    <text class="text-[30rpx] font-500 text-[#fff] leading-[42rpx] using-hidden mr-[10rpx]">{{ memberInfo.nickname }}</text>
                            </view>
                            <view class="text-[24rpx] text-[#fff]">ID：{{ memberInfo.member_no }}</view>
                            </view>
                        </view>
                        <template v-if="userInfo">
                            <view class="flex items-center justify-center w-[150rpx] h-[56rpx] bg-[rgba(255,255,255,0.2)] rounded-[28rpx] box-border" v-if="userInfo.member_id == memberInfo.member_id" @click="redirect({url: '/addon/sow_community/pages/create'})">
                                <text class="nc-iconfont nc-icon-xiugaiV6xx text-[#fff] text-[24rpx] mr-[8rpx]"></text>
                                <text class="text-[24rpx] text-[#fff]">{{ t('toCreate') }}</text>
                            </view>
                            <template v-else>
                                <view class="flex items-center justify-center w-[150rpx] h-[56rpx] border-solid border-[2rpx] border-[#fff] rounded-[28rpx] box-border flex-shrink-0 "  v-if="memberInfo.is_follow" @click="cancelFollow()">
                                    <text class="text-[24rpx] text-[#fff]">{{ t('unFollow') }}</text>
                                </view>
                                <view class="flex items-center justify-center w-[150rpx] h-[56rpx] border-solid border-[2rpx] border-[#fff] rounded-[28rpx] box-border flex-shrink-0" v-else @click="followFn()">
                                    <text class="nc-iconfont nc-icon-jiahaoV6xx mr-[6rpx] text-[#fff]"></text>
                                    <text class="text-[24rpx] text-[#fff]">{{ t('follow') }}</text>
                                </view>
                            </template>
                        </template>
                    </view>
                    <view class="flex-between-center">
                        <view class="flex-1 text-[#fff]  text-center" @click="toFollow()">
                            <view class="text-[24rpx] mb-[20rpx] opacity-80">{{ t('follow') }}</view>
                            <view class="text-[36rpx] price-font">{{ memberInfo.follow_num }}</view>
                        </view>
                        <view class="flex-1 text-[#fff]  text-center" @click="toFans()">
                            <view class="text-[24rpx] mb-[20rpx] opacity-80">{{ t('fans') }}</view>
                            <view class="text-[36rpx] price-font">{{ memberInfo.fans_num }}</view>
                        </view>
                        <view class="flex-1 text-[#fff]  text-center">
                            <view class="text-[24rpx] mb-[20rpx] opacity-80">{{ t('like') }}</view>
                            <view class="text-[36rpx] price-font">{{ memberInfo.like_num }}</view>
                        </view>
                    </view>
                </view>
            </view>
            <!-- 作品列表 -->
            <view class="bg-[#fff] mt-[-40rpx] rounded-tl-[30rpx] rounded-tr-[30rpx] pt-[30rpx] pb-[42rpx] px-[20rpx]" >
                <view class="flex-center">
                    <text class="text-[28rpx] text-[#666] leading-[40rpx]" :class="{'active-tab': curTab == 'content' }" @click="handleTab('content')">{{ t('content') }}</text>
                    <text class="text-[28rpx] text-[#666] leading-[40rpx] ml-[50rpx]" :class="{'active-tab': curTab == 'like' }" @click="handleTab('like')">{{ t('liked') }}</text>
                </view>
            </view>
        </view>
        <mescroll-body ref="mescrollRef" :top="mescrollTop"   @init="mescrollInit" :down="{ use: false }" @up="getMemberListFn">
            <view class="bg-[#fff] px-[20rpx] flex flex-wrap" v-if="contentList.length">
                <view class="w-[226rpx] h-[280rpx] rounded-[var(--rounded-small)] relative mb-[20rpx]" :class="{'mr-[16rpx]': (index + 1) % 3 }" v-for="(item, index) in contentList" :key="index" @click="toDetail(item)">
                    <image  class="w-full h-full rounded-[var(--rounded-small)] align-middle" :src="img(item.content_cover)" :mode="'aspectFill'" />
                    <view class="absolute bottom-0 left-0 right-0 bg-color h-[44rpx] px-[10rpx] flex-between-center rounded-bl-[var(--rounded-small)] rounded-br-[var(--rounded-small)]">
                        <view class="flex items-center" v-if="curTab == 'content'">
                            <text class="nc-iconfont nc-icon-a-xiangyouV6xx2  mr-[4rpx]  text-[#fff] text-[20rpx]"></text>
                            <text class="text-[#fff] text-[22rpx]">{{ item.view_num }}</text>
                        </view>
                        <view class="flex items-center">
                            <text class="nc-iconfont nc-icon-guanzhuV6xx mr-[4rpx] text-[#fff] text-[20rpx]"></text>
                            <text class="text-[#fff] text-[22rpx]">{{ item.like_num }}</text>
                        </view>
                    </view>
                    <image class="w-[74rpx] h-[74rpx] absolute right-0 top-0 left-0 bottom-0 m-auto" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" v-if="item.content_type == 2" />
                    <view class="absolute left-0 top-0 right-0 bottom-0 bg-[rgba(51,51,51,0.5)] flex flex-col items-center justify-center rounded-[var(--rounded-small)]" v-if="item.status == 1">
                        <view class="text-[24rpx] text-[#fff] mb-[8rpx]">{{ t('inAudit') }}</view>
                        <view class="text-[20rpx] text-color">{{ t('auditTips') }}</view>
                    </view>
                    <view class="absolute left-0 top-0 right-0 bottom-0 bg-[rgba(51,51,51,0.5)] flex flex-col items-center justify-center rounded-[var(--rounded-small)]" v-if="item.status == -1">
                        <view class="text-[24rpx] text-[#fff] mb-[8rpx]">{{ t('auditRefuse') }}</view>
                        <view class="text-[20rpx] text-color">{{ item.refuse_reason }}</view>
                    </view>
                    <view class="absolute left-0 top-0 right-0 bottom-0 bg-[rgba(51,51,51,0.5)] flex flex-col items-center justify-center rounded-[var(--rounded-small)]" v-if="item.status == -2">
                        <view class="text-[24rpx] text-[#fff] mb-[8rpx]">{{ t('offContent') }}</view>
                        <view class="text-[20rpx] text-color">{{ t('offContentTips') }}</view>
                    </view>
                </view>
            </view>
            <mescroll-empty v-if="!contentList.length && loading" :option="{tip : t('noContent')}"></mescroll-empty>
        </mescroll-body>
        <tips-popup ref="followRef" :title="t('unfollowTips')" @confirm="handleCancelFollow"/>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { t } from '@/locale'
import { img, redirect, pxToRpx, goback } from '@/utils/common';
import { topTabar } from '@/utils/topTabbar';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import useMemberStore from '@/stores/member'
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'
import { getMember, getContentMyList, getContentLike, follow } from '@/addon/sow_community/api/follow';
import useSystemStore from "@/stores/system";

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
let topTabbarData = topTabarObj.setTopTabbarParam({ title: '个人主页' })
/********* 自定义头部 - end ***********/

const systemStore = useSystemStore()
// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

const loading = ref<boolean>(false)
const optionLoading = ref(false)
const memberId = ref(0)
const memberInfo = ref({})
const curTab = ref('content')
const contentList = ref([])

onLoad((option: any) => {
    memberId.value = option.member_id || ''

})
onShow(() =>{
    getMemberFn()
})

const handleTab = (tab: string) => {
    curTab.value = tab
    contentList.value = []
    getMescroll().resetUpScroll();
}

const getMemberFn = () =>{
    getMember({member_id:memberId.value}).then((res:any) =>{
        memberInfo.value = res.data
    }).catch((err)=>{
        let parameter = {
            url: '/addon/sow_community/pages/index',
            title:err.msg,
            mode: 'reLaunch'
        };
        goback(parameter)
    })
}

const getMemberListFn = (mescroll: any) =>{
    loading.value = false;
    let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
        member_id:memberId.value
	};
    let api =  curTab.value  == 'content' ? getContentMyList : getContentLike
    api(data).then((res: any) => {
        let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			contentList.value = []; //如果是第一页需手动制空列表
		}
		contentList.value = contentList.value.concat(newArr);
		mescroll.endSuccess(newArr.length);
		loading.value = true;
    }).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

// 取消关注
const followRef = ref()
const cancelFollow = () =>{
    followRef.value.open()
}

const handleCancelFollow = () =>{
    memberInfo.value.is_follow = !memberInfo.value.is_follow
    follow({follow_member_id: memberInfo.value.member_id, is_follow: 0}).then((res: any) =>{
        let obj = {}
        if (uni.getStorageSync('featuredVideosItem')) {
            obj = uni.getStorageSync('featuredVideosItem')
        }
        obj.is_follow = false;
        uni.setStorageSync('featuredVideosItem', obj);
    }).catch(() => {

    });
}

// 关注
const followFn = () =>{
    if (optionLoading.value) return
	optionLoading.value = true
    memberInfo.value.is_follow = !memberInfo.value.is_follow
    follow({follow_member_id: memberInfo.value.member_id, is_follow: 1}).then((res: any) =>{
        optionLoading.value = false
        uni.showToast({
            title: '关注成功',
            icon: 'none'
        })

        let obj = {}
        if (uni.getStorageSync('featuredVideosItem')) {
            obj = uni.getStorageSync('featuredVideosItem')
        }
        obj.is_follow = true;
        uni.setStorageSync('featuredVideosItem', obj);
    }).catch(() => {
        optionLoading.value = false;
    });
}

// 去详情
const toDetail = (data: any) =>{
    if(data.content_type == 1){
        redirect({ url: '/addon/sow_community/pages/image/detail', param: { content_id: data.content_id } })
    }else{
        redirect({ url: '/addon/sow_community/pages/video/detail', param: { content_id: data.content_id } })
    }
}

// 去关注
const toFollow = () =>{
    if(!memberInfo.value.is_self){
        return false
    }
    redirect({url: '/addon/sow_community/pages/follow', param: { status: 'follow', member_id: memberInfo.value.member_id}})
}

// 去粉丝
const toFans = () =>{
    if(!memberInfo.value.is_self){
        return false
    }
    redirect({url: '/addon/sow_community/pages/follow', param: { status: 'fans', member_id: memberInfo.value.member_id}})
}

const mescrollTop = computed(() => {
    // #ifndef APP-PLUS
    let style = Object.keys(systemStore.menuButtonInfo).length ? (pxToRpx(Number(systemStore.menuButtonInfo.height)) + pxToRpx(systemStore.menuButtonInfo.top) + pxToRpx(8) +  402) + 'rpx' : '402rpx'
    // #endif
	// #ifdef APP-PLUS
    let style = (pxToRpx(systemStore.topTabbarInfo.fullHeight) + pxToRpx(8) +  402) + 'rpx'
    // #endif
    return style
})
</script>

<style lang="scss" scoped>
.active-tab{
    position: relative;
    color: #333;
    &::after{
        content: "";
        position: absolute;
        width: 32rpx;
        height: 6rpx;
        border-radius: 4rpx;
        background-color: var(--primary-color);
        left: 50%;
        bottom: -10rpx;
       transform: translateX(-50%);

    }
}
.bg-color{
    background-color: rgba(51,51,51,0.5);
}
.text-color{
    color: rgba(255,255,255,0.8);
}
</style>
