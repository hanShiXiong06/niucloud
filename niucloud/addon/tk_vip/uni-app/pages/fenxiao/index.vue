<template>
	<view class="min-h-[100vh] w-full bg-[#f5f5f5]" :style="themeColor()" v-if="memberStore.info">
		<!-- 顶部用户信息卡片 -->
		<view class="bgcontent p-4">
			<view class="flex justify-between items-center mb-4">
				<view class="flex items-center">
					<view class="rounded-full border-2 border-[#E9D88B] overflow-hidden">
						<up-avatar :src="img(info.headimg)" size="55"
							:default-url="img('static/resource/images/default_headimg.png')" />
					</view>
					<view class="ml-[24rpx] flex-1">
						<view class="text-[#EAEDDC] text-[32rpx] font-medium truncate max-w-[360rpx] mb-2">
							{{ info.nickname }}
						</view>
						<view
							class="inline-block bg-gradient-to-r from-[#E9D88B] via-[#F7EED1] to-[#D5C6A9] px-[24rpx]  rounded-full shadow-sm">
							<text class="text-[22rpx] leading-[24rpx] text-[#2F302B] font-medium whitespace-nowrap">{{
								info.member_level_name }}</text>
						</view>
					</view>
				</view>
				<view
					class="flex items-center bg-[#454337]/90 hover:bg-[#454337] transition-all duration-300 px-4 py-2 rounded-full shadow-sm cursor-pointer"
					@click="shareEvent()">
					<up-icon :name="img('addon/tk_vip/fenxiao/tgm.png')" size="20"></up-icon>
					<text class="text-[#D5C6A9] ml-2 text-sm">推广码</text>
				</view>
			</view>

			<!-- 收益卡片 -->
			<view class="bg-gradient-to-br from-[#2F302B] to-[#3a3b35] p-5 rounded-[16rpx] shadow-lg"
				:style="{ ...headerStyle, backgroundImage: 'url(' + img('addon/tk_vip/fenxiao/bjtt.png') + ')' }">
				<view class="flex items-center justify-between mb-6" @click="applyCashOut()">
					<view>
						<text class="text-[#B3B4A2] text-sm">可提现金额(元)</text>
						<view class="flex items-center mt-2">
							<text class="text-[#F7EED1] text-2xl font-bold">
								{{ memberStore.info ? moneyFormat(memberStore.info.commission) : '0.00' }}
							</text>
							<up-icon color="#B9BAB6" name="arrow-right" class="ml-2"></up-icon>
						</view>
					</view>
					<view
						class="bg-gradient-to-r from-[#E9D88B] to-[#D5C6A9] px-6 py-2 rounded-full text-[#2F302B] font-medium shadow-md hover:shadow-lg transition-all duration-300">
						去提现
					</view>
				</view>

				<view class="grid grid-cols-3 gap-4 mt-4" v-if="fenxiaoinfo">
					<view class="text-center"
						@click="redirect({ url: '/app/pages/member/detailed_account?type=commission' })">
						<text class="text-[#989795] text-sm">累计佣金(元)</text>
						<view class="text-[#F0F0E3] text-lg font-bold mt-2">
							{{ moneyFormat(memberStore.info?.commission_get) || '0.00' }}
						</view>
					</view>
					<view class="text-center border-l border-r border-[#454337]">
						<text class="text-[#989795] text-sm">提现中(元)</text>
						<view class="text-[#F0F0E3] text-lg font-bold mt-2">
							{{ moneyFormat(memberStore.info?.commission_cash_outing) || '0.00' }}
						</view>
					</view>
					<view class="text-center">
						<text class="text-[#989795] text-sm">累计订单(个)</text>
						<view class="text-[#F0F0E3] text-lg font-bold mt-2">
							{{ fenxiaoinfo.first_order_num +
								fenxiaoinfo.second_order_num + fenxiaoinfo.self_order_num +
								fenxiaoinfo.self_direct_order_num }}
						</view>
					</view>
				</view>
			</view>

		</view>

		<!-- 推广数据卡片 -->
		<view class="mx-4 mt-4" v-if="fenxiaoinfo">
			<view class="bg-white rounded-[16rpx] p-4 shadow-sm mb-4 hover:shadow-md transition-all duration-300">
				<view class="flex items-center justify-between mb-6">
					<view class="flex items-center">
						<view class="w-1 h-6 bg-gradient-to-b from-[#E9D88B] to-[#D5C6A9] rounded-full"></view>
						<text class="font-bold ml-3 text-[28rpx]">推广人数</text>
					</view>
					<view class="flex items-center cursor-pointer"
						@click="redirect({ url: '/addon/tk_vip/pages/fenxiao/member' })">
						<text class="text-[#666] text-sm">查看成员</text>
						<up-icon name="arrow-right" color="#666" size="14" class="ml-1"></up-icon>
					</view>
				</view>
				<view class="flex justify-around">
					<view class="text-center cursor-pointer hover:scale-105 transition-all duration-300">
						<view class="text-xl font-bold text-[#333]">{{ fenxiaoinfo.first_num }}</view>
						<view class="text-[#666] text-sm mt-1">一级人数</view>
					</view>
					<view class="text-center cursor-pointer hover:scale-105 transition-all duration-300">
						<view class="text-xl font-bold text-[#333]">{{ fenxiaoinfo.second_num }}</view>
						<view class="text-[#666] text-sm mt-1">二级人数</view>
					</view>
				</view>
			</view>

			<!-- 订单数据卡片 -->
			<view class="bg-white rounded-[16rpx] p-4 mb-4 shadow-sm hover:shadow-md transition-all duration-300">
				<view class="flex items-center justify-between mb-6">
					<view class="flex items-center">
						<view class="w-1 h-6 bg-gradient-to-b from-[#E9D88B] to-[#D5C6A9] rounded-full"></view>
						<text class="font-bold ml-3 text-[28rpx]">完成订单</text>
					</view>
					<view class="flex items-center cursor-pointer"
						@click="redirect({ url: '/addon/tk_vip/pages/fenxiao/order' })">
						<text class="text-[#666] text-sm">查看订单</text>
						<up-icon name="arrow-right" color="#666" size="14" class="ml-1"></up-icon>
					</view>
				</view>
				<view class="flex justify-around">
					<view class="text-center cursor-pointer hover:scale-105 transition-all duration-300">
						<view class="text-xl font-bold text-[#333]">{{ fenxiaoinfo.first_order_num }}</view>
						<view class="text-[#666] text-sm mt-1">一级订单</view>
					</view>
					<view class="text-center cursor-pointer hover:scale-105 transition-all duration-300">
						<view class="text-xl font-bold text-[#333]">{{ fenxiaoinfo.second_order_num }}</view>
						<view class="text-[#666] text-sm mt-1">二级订单</view>
					</view>
				</view>
			</view>
			<!-- <view class="bg-white rounded-[16rpx] p-4 shadow-sm hover:shadow-md transition-all duration-300">
				<view class="flex items-center justify-between mb-6">
					<view class="flex items-center">
						<view class="w-1 h-6 bg-gradient-to-b from-[#E9D88B] to-[#D5C6A9] rounded-full"></view>
						<text class="font-bold ml-3 text-[28rpx]">自购推广</text>
					</view>
					<view class="flex items-center cursor-pointer"
						@click="redirect({ url: '/addon/tk_vip/pages/fenxiao/orderself' })">
						<text class="text-[#666] text-sm">查看订单</text>
						<up-icon name="arrow-right" color="#666" size="14" class="ml-1"></up-icon>
					</view>
				</view>
				<view class="flex justify-around">
					<view class="text-center cursor-pointer hover:scale-105 transition-all duration-300">
						<view class="text-xl font-bold text-[#333]">{{ fenxiaoinfo.self_order_num }}</view>
						<view class="text-[#666] text-sm mt-1">自购订单</view>
					</view>
					<view class="text-center cursor-pointer hover:scale-105 transition-all duration-300">
						<view class="text-xl font-bold text-[#333]">{{ fenxiaoinfo.self_direct_order_num }}</view>
						<view class="text-[#666] text-sm mt-1">下级自购</view>
					</view>
				</view>
			</view> -->
		</view>
	</view>
	<share-poster ref="sharePosterRef" posterType="tk_vip_poster" :posterId="poster_id" :posterParam="posterParam"
		:copyUrlParam="copyUrlParam" :copyUrl="'/app/pages/index/index'" />
	<tabbar addon="tk_vip" />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { moneyFormat, img, redirect, getToken, isWeixinBrowser, getSiteId, handleOnloadParams } from '@/utils/common';
import { getMemberCommission } from '@/app/api/member';
import useMemberStore from '@/stores/member'
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import {
	checkFenxiao, getFenxiaoInfo
} from '@/addon/tk_vip/api/fenxiao'
import { useLogin } from '@/hooks/useLogin'

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

		useLogin().setLoginBack({ url: '/addon/tk_vip/pages/index' })
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
</script>
<style lang="scss" scoped>
@import '@/addon/tk_vip/utils/styles/common.scss';

.bgcontent {
	background: linear-gradient(0deg, rgba(246, 246, 246, 0.2) 0%, rgba(27, 27, 27, 1) 40%);
}

.gotx {
	background: linear-gradient(90deg, rgba(252, 242, 189, 1) 20%, rgba(255, 236, 171, 1.0) 40%);
}

.class-select {
	position: relative;
	font-weight: bold;

	&::after {
		content: "";
		position: absolute;
		bottom: 0;
		height: 6rpx;
		background-color: var(--primary-color);
		width: 90%;
		left: 50%;
		transform: translateX(-50%);
	}
}

page {
	background-color: #F5F5F5;
}

.fx {
	font-size: 15px;
	display: flex;
	width: 18%;
	text-align: center;
	background: #000000;
	padding: 10px;
	border-radius: 0px 10px 10px 0px;
}

.fx .icon1 {
	width: 15px;
	height: 15px;
	margin-right: 5px;
}

.fxwz {
	font-size: 12px !important;
	color: #cecdcd;
}

.reseller {

	min-height: 100vh;
	font-size: 14px;

	.top {
		color: #FFFFFF;
		//display: flex;
		justify-content: space-between;
		position: relative;
		width: 100%;
		height: 250px;

		background-size: cover;
		background-position: center;
		background-repeat: no-repeat;

		//background-color: #2196F3;
		//background: linear-gradient(top, #2196F3, #03A9F4);
		.top1 {
			display: flex;
		}

		.title {
			background-color: #FFFFFF
		}

		.num {
			font-size: 15px;
			line-height: 25px;
			text-align: center;
			width: 33%;
			padding-top: 8%;
		}

		.num1 {
			text-align: center;
			width: 100%;
			padding-top: 10%;
		}

	}

	.jilu {
		font-size: 15px;
		position: absolute;
		left: 78%;
		top: 20%;
	}

	.jl {
		font-size: 12px !important;
		background: #ffffff05;
		padding: 10px;
		border-radius: 10px 0px 0px 10px;
	}

	.fx {
		font-size: 15px;
		position: absolute;
		display: flex;
		left: 0;
		top: 20%;
		width: 18%;
		text-align: center;
		background: #ffffff21;
		padding: 10px;
		border-radius: 0px 10px 10px 0px;
	}

	.fx .icon1 {
		width: 15px;
		height: 15px;
		margin-right: 5px;
	}

	.fxwz {
		font-size: 12px !important;
		color: #cecdcd;
	}

	.tixian {
		display: flex;
		justify-content: center;
		background-color: #F5F5F5;
		width: 55%;
		height: 60px;
		border-radius: 60px;
		position: absolute;
		bottom: -30px;
		left: 23%;

		.btn {
			margin-top: 7%;
			text-align: center;
			width: 85%;
			background-color: #e5349b;
			height: 40px;
			border: none;
			border-radius: 50px;
			line-height: 40px;
			color: #FFFFFF;
		}


	}

	.icon {
		display: flex;
		justify-content: space-between;
		flex-wrap: wrap;

		margin-left: 3%;
		margin-right: 3%;
		margin-top: 10%;

		.ico {
			border-radius: 10px;
			border: 1px solid #C0C0C0;
			width: 49%;
			height: auto;
			margin-top: 8px;
			padding-bottom: 10px;
			border: none;
			font-size: 15px;
			flex-direction: column;
			background-color: #FFFFFF;

			.img {
				width: 40px;
				height: 40px;
			}

			.tubiao {
				margin-top: 20px;
				margin-left: 10px;
				text-align: center;
				width: 35%;
				float: left;
			}

			.tt {
				float: left;
				margin-top: 17px;
			}

			.text {
				color: #8F8F94;
				text-align: center;
				font-size: 14px;

			}

			.text_r {
				font-size: 18px;
				font-weight: bold;
			}
		}
	}

	.kong {
		height: 100px;
	}
}
</style>