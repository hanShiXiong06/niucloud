<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)]  overflow-hidden" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 product-warp bg-[#fff] py-[14rpx] px-[20rpx]">
            <view class="search-input">
                <input class="input" maxlength="50" type="text" v-model="keyword"  placeholder="请搜索会员编号/昵称/手机号" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTypeFn">
                <text v-if="keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="keyword=''"></text>
                <text @click.stop="searchTypeFn" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !mr-0"></text>
            </view>
        </view>
        <mescroll-body ref="mescrollRef" @init="mescrollInit" top="88rpx" @down="downCallback" @up="getMemberListFn">
            <view class="sidebar-margin pt-[var(--top-m)]" v-if="memberList.length">
                <view class="mb-[var(--top-m)] card-template" v-for="(item, index) in memberList" :key="index" @click="toLink(item)">
                    <view class="flex mb-[30rpx]">
                        <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(item.headimg)" :size="'80rpx'" leftIcon="none" />
                        <view class="ml-[16rpx] flex-1 flex flex-col justify-between">
                            <view class="text-[30rpx] font-500">{{ item.nickname }}</view>
                            <view class="flex flex-wrap">
                                <text class="h-[32rpx] px-[12rpx] flex-center bg-[#f8f8f8] border-solid border-[1rpx] border-[#ddd] rounded-[8rpx] text-[20rpx] mr-[20rpx]">{{ item.is_follow_name }}</text>
                            </view>
                        </view>
                    </view>
                    <view class="grid grid-cols-3 gap-x-[10rpx]">
                        <view class="flex flex-col items-center">
                            <text class="price-font text-[40rpx] fnt-500 mb-[12rpx]">{{ item.comsum_num || 0 }}</text>
                            <view class="text-[26rpx]">消费次数</view>
                        </view>
                        <view class="flex flex-col items-center">
                            <text class="price-font text-[40rpx] fnt-500 mb-[12rpx]">{{ parseFloat(item.comsum_money).toFixed(2) }}</text>
                            <view class="text-[26rpx]">累计消费金额</view>
                        </view>
                        <view class="flex flex-col items-center">
                            <text class="price-font text-[40rpx] fnt-500 mb-[12rpx]">{{ Number(item.comsum_num) ? (Number(item.comsum_money)/Number(item.comsum_num)).toFixed(2) : 0 }}</text>
                            <view class="text-[26rpx]">客单价</view>
                        </view>
                    </view>
                    <view class="h-[1rpx] bg-[#f6f6f6] my-[20rpx]"></view>
                    <view class="flex items-center">
                        <view class="text-[24rpx]" v-if="item.first_consum_time">
                            <text class="text-[#666]">首次消费时间：</text>
                            <text class="text-[#999]">{{ item.first_consum_time.split(' ')[0] }}</text>
                        </view>
                        <view class="w-[1rpx] h-[18rpx] bg-[#ddd] mx-[20rpx]" v-if="item.last_consum_time"></view>
                        <view class="text-[24rpx]" v-if="item.last_consum_time">
                            <text class="text-[#666]">最近消费时间：</text>
                            <text class="text-[#999]">{{ item.last_consum_time.split(' ')[0] }}</text>
                        </view>
                    </view>
                </view>
            </view>
            <mescroll-empty v-if="!memberList.length && loading"></mescroll-empty>
        </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { redirect, img } from '@/utils/common';
import { getShopMember } from '@/app/api/member'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const keyword = ref('')
const memberList = ref<any>([]);
const loading = ref(false);

const getMemberListFn = (mescroll : any) => {
	loading.value = false;
	let data : object = {
		page: mescroll.num,
		limit: mescroll.size,
		keyword: keyword.value
	};

	getShopMember(data).then((res : any) => {
		let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (mescroll.num == 1) {
			memberList.value = []; //如果是第一页需手动制空列表
		}
		memberList.value = memberList.value.concat(newArr);
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const searchTypeFn = () => {
	getMescroll().resetUpScroll();
}

const  toLink = (data: any) => {
	redirect({url: '/app/pages/member/detail', param: { member_id: data.member_id }});
}
</script>

<style scoped>

</style>