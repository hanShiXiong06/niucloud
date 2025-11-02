<template>
     <view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 z-99 px-[30rpx]  bg-[#fff]">
            <view class="flex-center h-[88rpx]">
                <view class="text-[32rpx] text-[#666] leading-[88rpx]" :class="{'tab-active': curTab == 'follow'}" @click="handleTab('follow')">{{ t('follow') }}</view>
                <view class="text-[32rpx] text-[#666] leading-[88rpx] ml-[50rpx]" :class="{'tab-active': curTab == 'fans'}" @click="handleTab('fans')">{{ t('fans') }}</view>
            </view>
            <view class="py-[14rpx]">
                <view class="search-input !h-[72rpx]">
                    <input class="input" maxlength="50" type="text" v-model="keywords" :placeholder="t('searchTips')" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTypeFn()">
                    <text @click.stop="searchTypeFn()" class="nc-iconfont nc-icon-sousuo-duanV6xx1 text-[32rpx]"></text>
                </view>
            </view>
        </view>
        <mescroll-body ref="mescrollRef" top="188rpx"  @init="mescrollInit" :down="{ use: false }" @up="getFollowListFn">
            <view class="sidebar-margin">
                <view class="px-[24rpx] py-[20rpx] rounded-[var(--rounded-big)] bg-[#fff] mt-[var(--top-m)] flex-between-center"  v-for="(item, index) in followList" :key="index">
                    <view class="flex items-center" @click="toMember(item)">
                        <u-avatar :src="img(item.headimg)" size="50" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"/>
                        <view class="flex-1 ml-[20rpx]">
                            <view class="text-[30rpx] font-500 mb-[10rpx] max-w-[300rpx] leading-[42rpx] using-hidden">{{item.nickname}}</view>
                            <view class="text-[22rpx] text-[#666]">{{item.content_create_time}}</view>
                        </view>
                    </view>
                    <view  v-if="item.is_follow == 1" class="w-[140rpx] h-[54rpx] bg-[#f6f6f6] border-solid border-[#eee] border-[2rpx] box-border text-center leading-[52rpx] text-[24rpx] rounded-full flex-shrink-0" @click="cancelFollow(item)">{{ t('unFollow') }}</view>
                    <view v-else class="w-[140rpx] h-[54rpx] bg-primary text-[#fff]  box-border text-center leading-[52rpx] text-[24rpx] rounded-full flex-shrink-0 flex-center" @click="followFn(item)">
                        <text class="nc-iconfont nc-icon-jiahaoV6xx text-[30rpx]"></text>
                        <text>{{ t('follow') }}</text>
                    </view>
                </view>
            </view>
            <mescroll-empty v-if="!followList.length && loading" :option="{tip : curTab== 'follow' ? t('noFollow'): t('noFans') ,icon: img('/addon/sow_community/default_follow.jpg')}"></mescroll-empty>
        </mescroll-body>
        <tips-popup ref="followRef" :title="t('unfollowTips')" @confirm="handleCancelFollow"/>
     </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/locale'
import { img,redirect } from '@/utils/common';
import { getFollowList,getFollowFans, follow } from '@/addon/sow_community/api/follow';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const loading = ref<boolean>(false)
const optionLoading = ref(false)
const keywords = ref('')
const curTab = ref('follow')
const memberId = ref(0)
const followList = ref([])

onLoad((options: any) =>{
    curTab.value = options.status || 'follow'
    memberId.value = options.member_id || ''
})

const searchTypeFn = () => {
    followList.value = []
    getMescroll().resetUpScroll();
}

const handleTab = (tab: string) =>{
    curTab.value = tab
    followList.value = []
    getMescroll().resetUpScroll();
}

interface mescrollStructure {
	num: number,
	size: number,
	endSuccess: Function,
	[propName: string]: any
}

const getFollowListFn = (mescroll: mescrollStructure) => {
	loading.value = false;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		keyword: keywords.value,
        member_id: memberId.value

	};
    let api = curTab.value === 'follow' ? getFollowList : getFollowFans;
	api(data).then((res: any) => {
		let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			followList.value = []; //如果是第一页需手动制空列表
		}
		followList.value = followList.value.concat(newArr);
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

// 取消关注
const followRef = ref()
const curData = ref<any>({})
const cancelFollow = (data: any) =>{
    curData.value = data
    followRef.value.open()
}

const handleCancelFollow = () => {
    const val = curTab.value == 'follow' ? curData.value.follow_member_id : curData.value.member_id
    follow({ follow_member_id: val, is_follow: 0 }).then(res => {
        getMescroll().resetUpScroll();
    }).catch(() => {
    });
}

// 关注
const followFn = (data: any) =>{
    if (optionLoading.value) return
	optionLoading.value = true
    const val = curTab.value == 'follow' ? data.follow_member_id : data.member_id
    follow({follow_member_id: val, is_follow: 1}).then(res =>{
        uni.showToast({
            title: '关注成功',
            icon: 'none'
        })
        optionLoading.value = false
        getMescroll().resetUpScroll();
    }).catch(() => {
        optionLoading.value = false;
    });
}

// 去个人主页
const toMember = (data: any) =>{
    const memberId = curTab.value == 'follow' ? data.follow_member_id : data.member_id
    redirect({url: '/addon/sow_community/pages/member',param: {member_id: memberId }})
}
</script>
<style lang="scss" scoped>
.tab-active{
    font-weight: 500;
    color: #111;
}
</style>
