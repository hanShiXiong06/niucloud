<template>
	<view class="min-h-screen bg-gray-50 w-full" :style="themeColor()" v-if="memberStore.info">
		<view class="p-4 bg-gradient-to-br from-[#454337] to-[#5a5749]">
			<view class="flex justify-between items-center">
				<!-- 左侧用户信息 -->
				<view class="flex items-center space-x-3">
					<up-avatar :src="img(info.headimg)" size="55"
						:default-url="img('static/resource/images/default_headimg.png')"
						class="border-2 border-[#D5C6A9]/30 rounded-full shadow-lg" />
					<view class="space-y-1.5">
						<view class="flex items-center space-x-2">
							<text class="text-[#D5C6A9] font-medium text-lg truncate max-w-[320rpx]">
								{{ info.nickname }}
							</text>
						</view>
						<view
							class="inline-flex items-center space-x-1.5 bg-black/20 backdrop-blur-sm px-3 py-1 rounded-full"
							@click="shareEvent()">
							<up-icon name="integral" size="14" color="#D5C6A9"></up-icon>
							<text class="text-[#D5C6A9] text-xs">{{ info.member_level_name }}</text>
						</view>
					</view>
				</view>

				<!-- 右侧推广码按钮 -->
				<view
					class="flex items-center space-x-2 bg-[#D5C6A9]/10 backdrop-blur-sm px-4 py-2 rounded-full active:scale-95 transition-transform"
					@click="shareEvent()">
					<up-icon :name="img('addon/tk_vip/fenxiao/tgm.png')" size="16" color="#D5C6A9"></up-icon>
					<text class="text-[#D5C6A9] text-sm font-medium">推广码</text>
				</view>
			</view>
		</view>
		<!-- 推广数据卡片 -->
		<view class="p-4 m-4 rounded-2xl bg-white shadow-sm" v-if="fenxiaoinfo">
			<view class="mb-4">
				<view class="flex items-center space-x-2">
					<view class="w-1 h-6 bg-gradient-to-b from-[#E9D88B] to-[#D5C6A9] rounded-full"></view>
					<text class="text-lg font-bold text-gray-800">推广数据</text>
				</view>
			</view>
			<view class="grid grid-cols-2 gap-4">
				<view class="stats-card transform transition-all duration-300 hover:scale-105"
					@click="typeChange('first')">
					<view class="relative overflow-hidden p-4 rounded-xl bg-gradient-to-br from-[#F8F4E5] to-white">
						<view class="relative z-10">
							<text class="block text-2xl font-bold text-gray-800">{{ fenxiaoinfo.first_num }}</text>
							<view class="mt-1 text-sm text-gray-600">一级人数</view>
						</view>
						<view
							class="absolute right-0 bottom-0 w-16 h-16 bg-gradient-to-br from-[#E9D88B] to-[#D5C6A9] opacity-10 rounded-full transform translate-x-1/4 translate-y-1/4">
						</view>
					</view>
				</view>
				<view class="stats-card transform transition-all duration-300 hover:scale-105"
					@click="typeChange('two')">
					<view class="relative overflow-hidden p-4 rounded-xl bg-gradient-to-br from-[#F8F4E5] to-white">
						<view class="relative z-10">
							<text class="block text-2xl font-bold text-gray-800">{{ fenxiaoinfo.second_num }}</text>
							<view class="mt-1 text-sm text-gray-600">二级人数</view>
						</view>
						<view
							class="absolute right-0 bottom-0 w-16 h-16 bg-gradient-to-br from-[#E9D88B] to-[#D5C6A9] opacity-10 rounded-full transform translate-x-1/4 translate-y-1/4">
						</view>
					</view>
				</view>
			</view>
		</view>

		<!-- 分类导航栏 -->
		<view class="sticky-header">
			<scroll-view scroll-x class="scroll-container" :show-scrollbar="false" enhanced :bounces="true">
				<view class="tab-container">
					<view v-for="(item, index) in typeList" :key="index" class="tab-item"
						@click="typeChange(item.type)">
						<view :class="['tab-button', type == item.type ? 'tab-active' : '']">
							<text>{{ item.name }}</text>
							<view v-if="type == item.type" class="active-line"></view>
						</view>
					</view>
				</view>
			</scroll-view>
		</view>
		<!-- 会员列表 -->
		<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getMemberListFn">
			<template v-if="list && list.length > 0">
				<view v-for="(item, index) in list" :key="index"
					class="mx-4 my-2 p-4 bg-white rounded-xl shadow-sm transform transition-all duration-300 hover:shadow-md">
					<view class="flex items-center space-x-3">
						<up-avatar :src="img(item.memberInfo.headimg)" size="60"
							:default-url="img('static/resource/images/default_headimg.png')"
							class="rounded-full border-2 border-gray-100" />
						<view class="flex-1">
							<view class="font-medium text-gray-800">{{ item.memberInfo.nickname }}</view>
							<view class="mt-1 text-xs text-gray-500 flex items-center">
								<up-icon name="shopping-cart" size="12" color="#666666" class="mr-1"></up-icon>
								<text>{{ item.order_num }}单</text>
							</view>
						</view>
					</view>
				</view>
			</template>
			<up-empty v-else mode="list" text="暂无数据" class="py-8"></up-empty>
		</mescroll-body>

		<!-- 返回顶部按钮 -->
		<view class="fixed right-4 bottom-24 z-50 transition-all duration-500"
			:class="[showBackTop ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10 pointer-events-none']"
			@click="backToTop">
			<view
				class="p-3 bg-white/90 backdrop-blur rounded-full shadow-lg active:scale-95 transition-transform hover:bg-white">
				<up-icon name="arrow-upward" color="#454337" size="30"></up-icon>
			</view>
		</view>

		<share-poster ref="sharePosterRef" posterType="tk_vip_poster" :posterId="poster_id" :posterParam="posterParam"
			:copyUrlParam="copyUrlParam" :copyUrl="'/app/pages/index/index'" />
		<tabbar addon="tk_vip" />
		<pay ref="payRef" @close="payLoading = false"></pay>
	</view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { moneyFormat, img, redirect, getToken, isWeixinBrowser, getSiteId, handleOnloadParams } from '@/utils/common';
import { getMemberCommission } from '@/app/api/member';
import useMemberStore from '@/stores/member'
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
import {
	checkFenxiao, getFenxiaoInfo, getFenxiaoMember
} from '@/addon/tk_vip/api/fenxiao'
import { useLogin } from '@/hooks/useLogin'
import { timeChange } from "@/addon/tk_vip/utils/ts/common";
const list = ref([])
const page = ref(1)
const loading = ref(false)
const typeList = ref([
	{
		name: '一级会员',
		type: 'first'
	},
	{
		name: '二级会员',
		type: 'two'
	}
])
const type = ref('first')
const typeChange = (e) => {
	type.value = e
	list.value = []
	getMescroll().resetUpScroll()
}
// 提现
const applyCashOut = () => {
	uni.setStorageSync('cashOutAccountType', 'commission')
	redirect({ url: '/app/pages/member/apply_cash_out' })
}
const userInfo = computed(() => memberStore.info)
/************* 分享海报-start **************/
let tk_vip = ref(null);
let copyUrlParam = ref('');
let posterParam = {};
const poster_id = ref(0)
const fenxiaoinfo = ref()
const getFenxiaoInfoEvent = async () => {
	const res = await getFenxiaoInfo()
	fenxiaoinfo.value = res.data
}
getFenxiaoInfoEvent()
// 分享海报链接
const copyUrlFn = () => {

	if (userInfo.value && userInfo.value.member_id) copyUrlParam.value += '?mid=' + userInfo.value.member_id;
}
const shareEvent = () => {

	// 检测是否登录
	if (!userInfo.value) {

		useLogin().setLoginBack({ url: '/addon/tk_vip/pages/index' })
		return false

	}

	if (userInfo.value && userInfo.value.member_id)
		posterParam.member_id = userInfo.value.member_id;
	copyUrlFn()
	tk_vip.value.openShare()
}
/************* 分享海报-end **************/

const memberStore = useMemberStore();
const info = computed(() => memberStore.info)
// 获取系统状态栏的高度
let menuButtonInfo: any = {};
// 如果是小程序，获取右上角胶囊的尺寸信息，避免导航栏右侧内容与胶囊重叠(支付宝小程序非本API，尚未兼容)
// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
menuButtonInfo = uni.getMenuButtonBoundingClientRect();
// #endif
const headerStyle = computed(() => {
	return {
		backgroundImage: 'url(' + img('static/resource/images/member/commission/commission_bg.png') + ') ',
		backgroundSize: 'cover',
		backgroundRepeat: 'no-repeat',
		backgroundPosition: 'center',
	}
})
//  16为自定头部的padding-bottom
const mescrollTop = computed(() => {
	return Object.keys(menuButtonInfo).length ? (Number(menuButtonInfo.height) * 2 + menuButtonInfo.top * 2 + 530 + 16) + 'rpx' : '530rpx'
})
const getMemberListFn = (mescroll) => {
	loading.value = true;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		type: type.value
	};
	getFenxiaoMember(data)
		.then((res) => {
			let newArr = res.data.data as Array<Object>;
			mescroll.endSuccess(newArr.length);
			//设置列表数据
			if (mescroll.num == 1) {
				list.value = []; //如果是第一页需手动制空列表
			}
			list.value = list.value.concat(newArr);
			loading.value = true;
		})
		.catch(() => {
			loading.value = false;
		});
};

onPageScroll((e) => {
	loading.value = false;
	scrollTop.value = e.scrollTop
	showBackTop.value = scrollTop.value > 200
});
onLoad((option) => {
	// #ifdef MP-WEIXIN
	// 处理小程序场景值参数
	option = handleOnloadParams(option);
	// #endif
	let pid = uni.getStorageSync('pid');
	if (pid && pid > 0) {
		checkFenxiao({ pid: pid })
	}

})
function isBottomReached() {
	const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
	const windowHeight = window.innerHeight;
	const scrollHeight = document.documentElement.scrollHeight;
	return scrollTop + windowHeight >= scrollHeight;
}

// 返回顶部相关
const showBackTop = ref(false)
const scrollTop = ref(0)

// 返回顶部方法
const backToTop = () => {
	uni.pageScrollTo({
		scrollTop: 0,
		duration: 300
	})
}
</script>

<style lang="scss" scoped>
@import '@/addon/tk_vip/utils/styles/common.scss';

/* 添加以下样式到你的style标签中 */
.sticky-header {
	position: sticky;
	top: 0;
	z-index: 50;
	background-color: rgba(255, 255, 255, 0.95);
	box-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.05);
}

.scroll-container {
	white-space: nowrap;
	padding: 24rpx 48rpx;
}

.tab-container {
	display: inline-flex;
	padding: 0 16rpx;
}

.tab-item {
	position: relative;
	display: inline-block;
	margin-right: 32rpx;

	&:last-child {
		margin-right: 0;
	}
}

.tab-button {
	position: relative;
	padding: 10rpx 48rpx;
	border-radius: 999rpx;
	font-size: 28rpx;
	color: #666;
	transition-property: all;
	transition-duration: 300ms;
}

.tab-active {
	background: linear-gradient(90deg, #454337, #5a5749);
	color: #D5C6A9;
	font-weight: bold;
	box-shadow: 0 4rpx 6rpx rgba(0, 0, 0, 0.1);
	/* 小程序中使用scale需要加前缀 */
	transform: scale(1);
}

.active-line {
	position: absolute;
	bottom: -2rpx;
	left: 50%;
	width: 80rpx;
	height: 2rpx;
	background: linear-gradient(90deg, #E9D88B, #D5C6A9);
	border-radius: 999rpx;
	/* 使用translateX替代transform简写形式 */
	transform: translateX(-50%);
}

/* 处理滚动条 */
::-webkit-scrollbar {
	display: none;
	width: 0;
	height: 0;
	color: transparent;
}

.stats-card {
	@apply relative overflow-hidden;

	&:active {
		@apply transform scale-95;
	}
}

:deep(.mescroll-upwarp) {
	@apply min-h-0;
}

:deep(.mescroll-empty) {
	@apply min-h-0;
}

/* #ifdef H5 */
::-webkit-scrollbar {
	display: none;
}

/* #endif */

/* #ifdef APP-PLUS || MP-WEIXIN */
scroll-view {
	::-webkit-scrollbar {
		display: none;
	}
}

/* #endif */
</style>