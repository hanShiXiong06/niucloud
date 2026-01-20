<template>
	<view class="min-h-[100vh] w-full bg-[#f5f5f5] overflow-x-hidden" :style="themeColor()" v-if="memberStore.info">
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
					<up-icon :name="img('addon/tk_jhkd/fenxiao/tgm.png')" size="16" color="#D5C6A9"></up-icon>
					<text class="text-[#D5C6A9] text-sm font-medium">推广码</text>
				</view>
			</view>
		</view>
		<view class="tk-card shadow-sm rounded-lg mb-4" v-if="fenxiaoinfo">
			<!-- 推广订单统计 -->
			<view class="mt-4">
				<view class="flex items-center mb-4">
					<view class="w-1 h-5 bg-[#E9D88B] rounded-full"></view>
					<text class="font-bold ml-3 text-[30rpx] text-gray-800">自购推广订单</text>
				</view>
				<view class="grid grid-cols-2 gap-4" v-if="orderData">
					<view @click="typeChange('self')"
						class="bg-gradient-to-br from-white to-[#fbfdf8] rounded-xl p-4 shadow-sm">
						<text class="text-2xl font-bold text-gray-800 block text-center">{{ orderData.self_order
						}}</text>
						<text class="text-gray-500 text-sm mt-2 block text-center">自购订单</text>
					</view>
					<view @click="typeChange('self_direct')"
						class="bg-gradient-to-br from-white to-[#fbfdf8] rounded-xl p-4 shadow-sm">
						<text class="text-2xl font-bold text-gray-800 block text-center">{{ orderData.self_direct_order
						}}</text>
						<text class="text-gray-500 text-sm mt-2 block text-center">下级自购</text>
					</view>
				</view>
			</view>
		</view>

		<!-- 订单类型选择器 -->
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
		<!-- 订单列表 -->
		<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">
			<template v-if="list">
				<view v-for="(item, index) in list" :key="index" class="tk-card !mt-3 !p-4 rounded-lg shadow-sm">
					<!-- 订单头部信息 -->
					<view class="flex justify-between items-center pb-3 border-b border-gray-100">
						<view class="flex items-center space-x-2" @click="copy(item.order_id)">
							<text class="text-gray-500">订单号:</text>
							<text class="text-gray-700">{{ item.order_id }}</text>
						</view>
						<view :class="[
							'px-3 py-1 rounded-full text-sm',
							item.status == 1 ? 'bg-green-50 text-green-600' : 'bg-gray-50 text-gray-600'
						]">
							{{ item.status == 1 ? '已结算' : (item.status == 0 ? '未结算' : '已取消') }}
						</view>
					</view>

					<!-- 配送信息 -->
					<view class="flex items-center justify-center mt-4 space-x-6"
						v-if="item.start_address && Object.keys(item.start_address).length">
						<view class="flex items-center space-x-3">
							<text class="bg-[#CCC6A9] text-white px-2 py-1 rounded-lg text-sm">寄</text>
							<text class="text-gray-800 font-medium">{{
								item.start_address?.address ? item.start_address.address.split('-')[0] : ''
							}}</text>
						</view>
						<view class="flex items-center space-x-2">
							<up-icon name="more-dot-fill" color="#63625f" size="20"></up-icon>
							<up-icon name="more-dot-fill" color="#63625f" size="20"></up-icon>
							<up-icon name="arrow-right" color="#63625f" size="20"></up-icon>
						</view>
						<view class="flex items-center space-x-3">
							<text class="bg-[#454337] text-white px-2 py-1 rounded-lg text-sm">收</text>
							<text class="text-gray-800 font-medium">{{
								item.end_address?.address ? item.end_address.address.split('-')[0] : ''
							}}</text>
						</view>
					</view>

					<!-- 订单底部信息 -->
					<view class="mt-4 pt-3 border-t border-gray-100">
						<view class="flex justify-between items-center mb-2">
							<text class="text-gray-500">订单状态：{{ item.status_name }}</text>
							<text v-if="type == 'self' && item.first_commission > 0" class="text-[#454337] font-bold">
								佣金: {{ item.first_commission }}
							</text>
							<text v-if="type == 'self_direct' && item.two_commission > 0"
								class="text-[#454337] font-bold">
								佣金: {{ item.two_commission }}
							</text>
						</view>
						<view class="flex justify-between items-center text-sm">
							<text class="text-gray-600">下单人：{{ item.memberInfo.nickname }}</text>
							<text class="text-gray-500">{{ item.create_time }}</text>
						</view>
					</view>
				</view>
			</template>
			<view class="mt-120px">
				<up-empty v-if="list.length == 0" mode="list" text="暂无数据"></up-empty>
			</view>
		</mescroll-body>

		<!-- 返回顶部按钮 -->
		<view v-show="showBackTop" @click="backToTop"
			class="fixed right-4 bottom-24 bg-white/80 backdrop-blur-sm p-3 rounded-full shadow-lg z-50 transition-all duration-300">
			<up-icon name="arrow-upward" color="#454337" size="24"></up-icon>
		</view>
	</view>

	<share-poster ref="sharePosterRef" posterType="tk_jhkd_poster" :posterId="poster_id" :posterParam="posterParam"
		:copyUrlParam="copyUrlParam" :copyUrl="'/addon/tk_jhkd/pages/index'" />
	<tabbar addon="tk_jhkd" />
	<pay ref="payRef" @close="payLoading = false"></pay>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { moneyFormat, img, redirect, handleOnloadParams, copy } from '@/utils/common';
import { getMemberCommission } from '@/app/api/member';
import useMemberStore from '@/stores/member'
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import {
	checkFenxiao, getFenxiaoInfo, getSelfFenxiaoOrder, getOrderData
} from '@/addon/tk_jhkd/api/fenxiao'
import { useLogin } from '@/hooks/useLogin'
import { timeChange } from "@/addon/tk_jhkd/utils/ts/common";
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const list = ref([])
const page = ref(1)
const loading = ref(false)
const orderData = ref()
getOrderData().then((res) => {
	orderData.value = res.data
})
const typeList = ref([
	{
		name: '自购订单',
		type: 'self'
	},
	{
		name: '下级自购',
		type: 'self_direct'
	}
])
const type = ref('self')
const typeChange = (e) => {
	type.value = e
	page.value = 1
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
let sharePosterRef = ref(null);
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

		useLogin().setLoginBack({ url: '/addon/tk_jhkd/pages/index' })
		return false

	}

	if (userInfo.value && userInfo.value.member_id)
		posterParam.member_id = userInfo.value.member_id;
	copyUrlFn()
	sharePosterRef.value.openShare()
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

const getOrderListFn = (mescroll) => {
	loading.value = true;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		type: type.value
	};
	getSelfFenxiaoOrder(data)
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

onLoad((option) => {
	// #ifdef MP-WEIXIN
	// 处理小程序场景值参数
	option = handleOnloadParams(option);
	// #endif
	let pid = uni.getStorageSync('pid');
	if (pid && pid > 0) {
		checkFenxiao({ pid: pid })
	}
	getOrderListFn()

})
// 返回顶部功能
const showBackTop = ref(false)
const scrollTop = ref(0)

// 监听页面滚动
onPageScroll((e) => {
	scrollTop.value = e.scrollTop
	showBackTop.value = scrollTop.value > 200
})

// 返回顶部方法
const backToTop = () => {
	uni.pageScrollTo({
		scrollTop: 0,
		duration: 300
	})
}
</script>

<style lang="scss" scoped>
@import '@/addon/tk_jhkd/utils/styles/common.scss';

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

	&:hover {
		.stats-icon {
			@apply opacity-100 scale-110;
		}
	}
}

.stats-icon {
	@apply absolute right-4 top-1/2 transform -translate-y-1/2 opacity-50 transition-all duration-300;
}
</style>